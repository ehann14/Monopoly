<?php

namespace Tests\Feature;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;
use App\Services\DiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDice;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private FakeDice $dice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = User::factory()->create(['username' => 'host1']);
        $this->dice = new FakeDice();
        $this->app->instance(DiceService::class, $this->dice);
    }

    private function newGame(array $names = ['Ana', 'Budi']): GameRoom
    {
        $this->actingAs($this->host)->post(route('game.store'), ['players' => $names])->assertRedirect();
        return GameRoom::firstOrFail();
    }

    private function act(GameRoom $room, string $action, array $headers = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->host)->withHeaders($headers)
            ->postJson(route('game.action', [$room->code, $action]));
    }

    private function player(GameRoom $room, string $name): GamePlayer
    {
        return GamePlayer::where('game_room_id', $room->id)->where('name', $name)->firstOrFail();
    }

    public function test_new_game_setup(): void
    {
        $room = $this->newGame(['Ana', 'Budi', 'Cici']);

        $this->assertSame('playing', $room->status);
        $this->assertSame('local', $room->mode);
        $this->assertSame(3, GamePlayer::where('game_room_id', $room->id)->count());
        $this->assertSame(28, PropertyOwnership::where('game_room_id', $room->id)->count());
        $this->assertSame(1500, $this->player($room, 'Cici')->money);
        $this->assertSame($this->player($room, 'Ana')->id, $room->current_player_id);
    }

    public function test_needs_at_least_two_distinct_players(): void
    {
        $this->actingAs($this->host)->post(route('game.store'), ['players' => ['Ana']])->assertSessionHasErrors('players');
        $this->actingAs($this->host)->post(route('game.store'), ['players' => ['Ana', 'ana']])->assertSessionHasErrors();
        $this->assertSame(0, GameRoom::count());
    }

    public function test_roll_moves_token_and_offers_purchase(): void
    {
        $room = $this->newGame();
        $this->dice->queue = [[1, 2]];

        $this->act($room, 'roll')->assertOk()->assertJsonPath('phase', 'await_buy');

        $this->assertSame(3, $this->player($room, 'Ana')->position);
        $this->assertSame(1500, $this->player($room, 'Ana')->money);
    }

    public function test_buy_property_deducts_money_and_sets_owner(): void
    {
        $room = $this->newGame();
        $this->dice->queue = [[1, 2]];
        $this->act($room, 'roll');
        $this->act($room, 'buy')->assertOk()->assertJsonPath('phase', 'await_end');

        $ana = $this->player($room, 'Ana');
        $this->assertSame(1440, $ana->money);
        $this->assertSame($ana->id, PropertyOwnership::where('game_room_id', $room->id)->where('tile_id', 3)->value('owner_player_id'));
    }

    public function test_cannot_buy_without_enough_money(): void
    {
        $room = $this->newGame();
        $ana = $this->player($room, 'Ana');
        $ana->update(['money' => 10]);
        $this->dice->queue = [[1, 2]];
        $this->act($room, 'roll')->assertJsonPath('can_buy', false);

        $this->act($room, 'buy')->assertStatus(422);

        $this->assertSame(10, $ana->fresh()->money);
        $this->assertNull(PropertyOwnership::where('game_room_id', $room->id)->where('tile_id', 3)->value('owner_player_id'));
    }

    public function test_decline_ends_offer_without_charging(): void
    {
        $room = $this->newGame();
        $this->dice->queue = [[1, 2]];
        $this->act($room, 'roll');
        $this->act($room, 'decline')->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertSame(1500, $this->player($room, 'Ana')->money);
    }

    public function test_passing_start_pays_salary(): void
    {
        $room = $this->newGame();
        $this->player($room, 'Ana')->update(['position' => 38]);
        $this->dice->queue = [[1, 2]];

        $this->act($room, 'roll')->assertOk();

        $ana = $this->player($room, 'Ana');
        $this->assertSame(1, $ana->position);
        $this->assertSame(1700, $ana->money);
    }

    public function test_rent_is_paid_to_owner(): void
    {
        $room = $this->newGame();
        $this->dice->queue = [[1, 2], [1, 2]];

        $this->act($room, 'roll');
        $this->act($room, 'buy');
        $this->act($room, 'end_turn')->assertOk();
        $this->act($room, 'roll')->assertOk();

        $this->assertSame(1496, $this->player($room, 'Budi')->money);
        $this->assertSame(1444, $this->player($room, 'Ana')->money);
    }

    public function test_doubles_grant_extra_roll(): void
    {
        $room = $this->newGame();
        $this->dice->queue = [[3, 3]];
        $this->act($room, 'roll')->assertJsonPath('phase', 'await_buy');
        $this->act($room, 'decline')->assertJsonPath('phase', 'await_roll');

        $room->refresh();
        $this->assertSame($this->player($room, 'Ana')->id, $room->current_player_id);
        $this->assertSame(1, $room->doubles_count);
    }

    public function test_third_double_sends_to_jail(): void
    {
        $room = $this->newGame();
        $room->forceFill(['doubles_count' => 2])->save();
        $this->dice->queue = [[2, 2]];

        $this->act($room, 'roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $ana = $this->player($room, 'Ana');
        $this->assertTrue($ana->in_jail);
        $this->assertSame(10, $ana->position);
        $this->assertSame(1500, $ana->money); // masuk penjara tidak menerima gaji
    }

    public function test_actions_out_of_phase_are_rejected(): void
    {
        $room = $this->newGame();
        $this->act($room, 'buy')->assertStatus(422);
        $this->act($room, 'end_turn')->assertStatus(422);
    }

    public function test_user_outside_game_is_forbidden(): void
    {
        $room = $this->newGame();
        $other = User::factory()->create(['username' => 'orang2']);

        $this->actingAs($other)->getJson(route('game.state', $room->code))->assertForbidden();
        $this->act($room, 'roll', [], $other)->assertForbidden();
        $this->actingAs($other)->get(route('game.show', $room->code))->assertForbidden();
    }

    public function test_duplicate_request_with_same_key_is_processed_once(): void
    {
        $room = $this->newGame();
        $this->dice->queue = [[1, 2], [3, 4]];
        $headers = ['Idempotency-Key' => 'kunci-1'];

        $this->act($room, 'roll', $headers)->assertOk();
        $this->act($room, 'roll', $headers)->assertOk();

        $this->assertSame(3, $this->player($room, 'Ana')->position);
        $this->assertSame(1, \App\Models\GameLog::where('game_room_id', $room->id)->where('type', 'dice')->count());
    }

    public function test_stale_version_is_rejected(): void
    {
        $room = $this->newGame();
        $this->actingAs($this->host)
            ->postJson(route('game.action', [$room->code, 'roll']), ['expected_version' => 999])
            ->assertStatus(422);
    }

    public function test_bankruptcy_ends_two_player_game(): void
    {
        $room = $this->newGame();
        $ana = $this->player($room, 'Ana');
        $budi = $this->player($room, 'Budi');
        PropertyOwnership::where('game_room_id', $room->id)->where('tile_id', 3)->update(['owner_player_id' => $ana->id]);
        $budi->update(['money' => 1]);
        $room->forceFill(['current_player_id' => $budi->id])->save();
        $this->dice->queue = [[1, 2]];

        $this->act($room, 'roll')->assertOk();

        $room->refresh();
        $this->assertSame('finished', $room->status);
        $this->assertSame($ana->id, $room->winner_player_id);
        $this->assertTrue($budi->fresh()->is_bankrupt);
        $this->assertSame(1501, $ana->fresh()->money);
        $this->act($room, 'roll')->assertStatus(422);
    }

    public function test_state_contains_no_game_master_secrets(): void
    {
        $room = $this->newGame();
        $json = $this->actingAs($this->host)->getJson(route('game.state', $room->code))->assertOk()->json();

        $this->assertArrayNotHasKey('dice_override', $json);
        $this->assertArrayNotHasKey('audit', $json);
        $this->assertFalse($json['room']['gm_enabled']);
    }
}