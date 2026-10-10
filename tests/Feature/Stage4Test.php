<?php

namespace Tests\Feature;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;
use App\Services\DiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Support\FakeDice;
use Tests\TestCase;

class Stage4Test extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private FakeDice $dice;
    private GameRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = User::factory()->create(['username' => 'host1', 'name' => 'Pemain Satu']);
        $this->dice = new FakeDice();
        $this->app->instance(DiceService::class, $this->dice);
    }

    private function newAiGame(array $names = ['Robo'], string $difficulty = 'easy'): GameRoom
    {
        $this->actingAs($this->host)
            ->post(route('game.ai.store'), ['difficulty' => $difficulty, 'ai_names' => $names])
            ->assertRedirect();
        $this->room = GameRoom::firstOrFail();

        return $this->room;
    }

    private function p(string $name): GamePlayer
    {
        return GamePlayer::where('game_room_id', $this->room->id)->where('name', $name)->firstOrFail();
    }

    private function step()
    {
        return $this->actingAs($this->host)->postJson(route('game.ai.step', $this->room->code));
    }

    private function act(string $action, array $params = [])
    {
        return $this->actingAs($this->host)->postJson(route('game.action', [$this->room->code, $action]), $params);
    }

    private function makeCurrent(string $name): void
    {
        $this->room->forceFill(['current_player_id' => $this->p($name)->id])->save();
    }

    private function own(string $player, int $tile, array $extra = []): void
    {
        PropertyOwnership::where('game_room_id', $this->room->id)->where('tile_id', $tile)
            ->update(['owner_player_id' => $this->p($player)->id] + $extra);
    }

    private function prop(int $tile): PropertyOwnership
    {
        return PropertyOwnership::where('game_room_id', $this->room->id)->where('tile_id', $tile)->firstOrFail();
    }

    // ---------------------------------------------------------------- membuat game

    public function test_creates_ai_game_with_human_first(): void
    {
        $room = $this->newAiGame(['Robo', 'Beta'], 'hard');

        $this->assertSame('ai', $room->mode);
        $this->assertSame('hard', $room->ai_difficulty);
        $this->assertSame(3, GamePlayer::where('game_room_id', $room->id)->count());

        $human = $this->p('Pemain Satu');
        $this->assertFalse((bool) $human->is_ai);
        $this->assertSame($human->id, $room->current_player_id);
        $this->assertTrue((bool) $this->p('Robo')->is_ai);
        $this->assertSame('hard', $this->p('Beta')->ai_difficulty);
    }

    public function test_ai_game_validation(): void
    {
        $post = fn (array $data) => $this->actingAs($this->host)->post(route('game.ai.store'), $data);

        $post(['difficulty' => 'easy', 'ai_names' => []])->assertSessionHasErrors('ai_names');
        $post(['difficulty' => 'ninja', 'ai_names' => ['Robo']])->assertSessionHasErrors('difficulty');
        $post(['difficulty' => 'easy', 'ai_names' => ['Robo', 'robo']])->assertSessionHasErrors();
        $post(['difficulty' => 'easy', 'ai_names' => ['A', 'B', 'C', 'D']])->assertSessionHasErrors('ai_names');
        $post(['difficulty' => 'easy', 'ai_names' => ['pemain satu']])->assertSessionHasErrors('ai_names');

        $this->assertSame(0, GameRoom::count());
    }

    public function test_game_page_and_state_expose_ai_turn(): void
    {
        $room = $this->newAiGame();

        $this->actingAs($this->host)->get(route('game.show', $room->code))->assertOk();
        $this->actingAs($this->host)->getJson(route('game.state', $room->code))
            ->assertOk()->assertJsonPath('ai_turn', false);

        $this->makeCurrent('Robo');
        $this->actingAs($this->host)->getJson(route('game.state', $room->code))
            ->assertOk()->assertJsonPath('ai_turn', true)->assertJsonPath('can_act', false);
    }

    // ---------------------------------------------------------------- akses

    public function test_human_cannot_act_on_ai_turn_and_step_needs_ai_turn(): void
    {
        $this->newAiGame();

        $this->step()->assertStatus(422); // giliran manusia

        $this->makeCurrent('Robo');
        $this->act('roll')->assertStatus(422); // bukan giliran manusia
    }

    public function test_outsider_cannot_step_ai(): void
    {
        $this->newAiGame();
        $this->makeCurrent('Robo');
        $other = User::factory()->create(['username' => 'orang2']);

        $this->actingAs($other)->postJson(route('game.ai.step', $this->room->code))->assertForbidden();
    }

    // ---------------------------------------------------------------- keputusan Easy

    public function test_ai_plays_a_full_turn_step_by_step(): void
    {
        $this->newAiGame();
        $this->makeCurrent('Robo');
        $this->dice->queue = [[1, 2]];

        $this->step()->assertOk()->assertJsonPath('phase', 'await_buy');
        $this->assertSame(3, $this->p('Robo')->position);

        $this->step()->assertOk()->assertJsonPath('phase', 'await_end'); // Easy: sisa uang 1440 -> beli
        $this->assertSame($this->p('Robo')->id, (int) $this->prop(3)->owner_player_id);
        $this->assertSame(1440, $this->p('Robo')->money);

        $this->step()->assertOk();
        $this->room->refresh();
        $this->assertSame($this->p('Pemain Satu')->id, $this->room->current_player_id);
    }

    public function test_easy_declines_when_cash_would_drop_too_low(): void
    {
        $this->newAiGame();
        $this->makeCurrent('Robo');
        $this->p('Robo')->update(['money' => 450]);
        $this->dice->queue = [[1, 2]];

        $this->step()->assertOk()->assertJsonPath('phase', 'await_buy');
        $this->step()->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertNull($this->prop(3)->owner_player_id);
        $this->assertSame(450, $this->p('Robo')->money);
    }

    public function test_easy_never_builds(): void
    {
        $this->newAiGame();
        $this->makeCurrent('Robo');
        $this->own('Robo', 1);
        $this->own('Robo', 3);
        $this->dice->queue = [[1, 2]];

        $this->step()->assertOk()->assertJsonPath('phase', 'await_end'); // langsung lempar dadu

        $this->assertSame(0, (int) PropertyOwnership::where('game_room_id', $this->room->id)->sum('houses'));
    }

    // ---------------------------------------------------------------- keputusan Hard

    public function test_hard_builds_before_rolling(): void
    {
        $this->newAiGame(['Robo'], 'hard');
        $this->makeCurrent('Robo');
        $this->own('Robo', 1);
        $this->own('Robo', 3);

        $this->step()->assertOk()->assertJsonPath('phase', 'await_roll');

        $this->assertSame(1, (int) PropertyOwnership::where('game_room_id', $this->room->id)->sum('houses'));
        $this->assertSame(1450, $this->p('Robo')->money);
        $this->assertNull($this->room->fresh()->last_dice); // belum melempar dadu
    }

    public function test_hard_buys_to_complete_a_color_group(): void
    {
        $this->newAiGame(['Robo'], 'hard');
        $this->makeCurrent('Robo');
        $this->own('Robo', 1);
        $this->p('Robo')->update(['money' => 100]);
        $this->dice->queue = [[1, 2]];

        $this->step()->assertOk()->assertJsonPath('phase', 'await_buy');
        $this->step()->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertSame($this->p('Robo')->id, (int) $this->prop(3)->owner_player_id);
        $this->assertSame(40, $this->p('Robo')->money);
    }

    // ---------------------------------------------------------------- utang

    public function test_ai_mortgages_to_pay_debt(): void
    {
        $this->newAiGame();
        $this->makeCurrent('Robo');
        $this->own('Robo', 6);
        $this->own('Robo', 8);
        $this->p('Robo')->update(['position' => 1, 'money' => 100]);
        $this->dice->queue = [[1, 2]]; // petak 4 = Pajak 200

        $this->step()->assertOk()->assertJsonPath('phase', 'await_debt');
        $this->step()->assertOk()->assertJsonPath('phase', 'await_debt');   // gadai pertama
        $this->step()->assertOk()->assertJsonPath('phase', 'await_debt');   // gadai kedua
        $this->step()->assertOk()->assertJsonPath('phase', 'await_end');    // bayar utang

        $this->assertSame(0, $this->p('Robo')->money);
        $this->assertTrue((bool) $this->prop(6)->is_mortgaged);
        $this->assertTrue((bool) $this->prop(8)->is_mortgaged);
    }

    // ---------------------------------------------------------------- simulasi penuh

    public function test_full_game_with_ai_runs_without_rule_errors(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->newAiGame(['Robo', 'Beta'], 'hard');

        for ($i = 0; $i < 400; $i++) {
            $room = GameRoom::findOrFail($this->room->id);
            if ($room->status !== 'playing') {
                break;
            }
            $current = GamePlayer::findOrFail($room->current_player_id);

            if ($current->is_ai) {
                $this->step()->assertOk();
                continue;
            }

            switch ($room->turn_phase) {
                case 'await_roll':
                    $this->act('roll')->assertOk();
                    break;
                case 'await_buy':
                    $affordable = $current->money >= (int) ($room->pending['price'] ?? PHP_INT_MAX);
                    $this->act($affordable ? 'buy' : 'decline')->assertOk();
                    break;
                case 'await_debt':
                    $affordable = $current->money >= (int) ($room->pending['amount'] ?? PHP_INT_MAX);
                    $this->act($affordable ? 'pay_debt' : 'declare_bankruptcy')->assertOk();
                    break;
                default:
                    $this->act('end_turn')->assertOk();
            }
        }

        $room = GameRoom::findOrFail($this->room->id);
        $this->assertContains($room->status, ['playing', 'finished']);
        $this->assertGreaterThan(20, (int) $room->turn_number);
    }
}