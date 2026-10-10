<?php

namespace Tests\Feature;

use App\Models\GameCardDeck;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\PropertyOwnership;
use App\Models\User;
use App\Services\DiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDice;
use Tests\TestCase;

class Stage3Test extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private FakeDice $dice;
    private GameRoom $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = User::factory()->create(['username' => 'host1']);
        $this->dice = new FakeDice();
        $this->app->instance(DiceService::class, $this->dice);

        $this->actingAs($this->host)->post(route('game.store'), ['players' => ['Ana', 'Budi']])->assertRedirect();
        $this->room = GameRoom::firstOrFail();
    }

    private function p(string $name): GamePlayer
    {
        return GamePlayer::where('game_room_id', $this->room->id)->where('name', $name)->firstOrFail();
    }

    private function act(string $action, array $params = [])
    {
        return $this->actingAs($this->host)->postJson(route('game.action', [$this->room->code, $action]), $params);
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

    private function setDeck(string $deck, array $ids): void
    {
        $row = GameCardDeck::where('game_room_id', $this->room->id)->where('deck', $deck)->firstOrFail();
        $row->order = $ids;
        $row->pointer = 0;
        $row->held = [];
        $row->save();
    }

    private function makeBudiCurrent(): void
    {
        $this->room->forceFill(['current_player_id' => $this->p('Budi')->id])->save();
    }

    // ---------------------------------------------------------------- rumah & hotel

    public function test_cannot_build_without_full_group(): void
    {
        $this->own('Ana', 1);

        $this->act('build', ['tile_id' => 1])->assertStatus(422);

        $this->assertSame(0, (int) $this->prop(1)->houses);
        $this->assertSame(1500, $this->p('Ana')->money);
    }

    public function test_build_houses_evenly(): void
    {
        $this->own('Ana', 1);
        $this->own('Ana', 3);

        $this->act('build', ['tile_id' => 1])->assertOk();
        $this->act('build', ['tile_id' => 1])->assertStatus(422); // belum merata
        $this->act('build', ['tile_id' => 3])->assertOk();

        $this->assertSame(1, (int) $this->prop(1)->houses);
        $this->assertSame(1, (int) $this->prop(3)->houses);
        $this->assertSame(1400, $this->p('Ana')->money); // 2 x 50
    }

    public function test_fifth_build_becomes_hotel_and_returns_houses(): void
    {
        $this->own('Ana', 1, ['houses' => 4]);
        $this->own('Ana', 3, ['houses' => 4]);

        $this->act('build', ['tile_id' => 1])->assertOk();

        $this->assertTrue((bool) $this->prop(1)->has_hotel);
        $this->assertSame(0, (int) $this->prop(1)->houses);
        $this->assertSame(1450, $this->p('Ana')->money);
        $this->act('build', ['tile_id' => 1])->assertStatus(422); // sudah hotel
    }

    public function test_house_stock_limit(): void
    {
        $this->room->forceFill(['rules' => array_merge($this->room->rules, ['house_stock' => 1])])->save();
        $this->own('Ana', 1);
        $this->own('Ana', 3);

        $this->act('build', ['tile_id' => 1])->assertOk();
        $this->act('build', ['tile_id' => 3])->assertStatus(422); // stok bank habis
    }

    public function test_sell_building_refunds_half_and_is_even(): void
    {
        $this->own('Ana', 1, ['houses' => 2]);
        $this->own('Ana', 3, ['houses' => 2]);

        $this->act('sell_building', ['tile_id' => 3])->assertOk();
        $this->assertSame(1525, $this->p('Ana')->money);
        $this->assertSame(1, (int) $this->prop(3)->houses);

        $this->act('sell_building', ['tile_id' => 3])->assertStatus(422); // harus dari yang terbanyak
        $this->act('sell_building', ['tile_id' => 1])->assertOk();
    }

    // ---------------------------------------------------------------- hipotek

    public function test_mortgage_and_unmortgage(): void
    {
        $this->own('Ana', 6);

        $this->act('mortgage', ['tile_id' => 6])->assertOk();
        $this->assertSame(1550, $this->p('Ana')->money);
        $this->assertTrue((bool) $this->prop(6)->is_mortgaged);

        $this->act('unmortgage', ['tile_id' => 6])->assertOk(); // 50 + 10%
        $this->assertSame(1495, $this->p('Ana')->money);
        $this->assertFalse((bool) $this->prop(6)->is_mortgaged);
    }

    public function test_cannot_mortgage_when_group_has_buildings(): void
    {
        $this->own('Ana', 1, ['houses' => 1]);
        $this->own('Ana', 3);

        $this->act('mortgage', ['tile_id' => 3])->assertStatus(422);
        $this->assertFalse((bool) $this->prop(3)->is_mortgaged);
    }

    public function test_mortgaged_property_collects_no_rent(): void
    {
        $this->own('Ana', 3, ['is_mortgaged' => true]);
        $this->makeBudiCurrent();
        $this->dice->queue = [[1, 2]];

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertSame(1500, $this->p('Budi')->money);
        $this->assertSame(1500, $this->p('Ana')->money);
    }

    // ---------------------------------------------------------------- penjara

    public function test_go_to_jail_tile(): void
    {
        $this->p('Ana')->update(['position' => 27]);
        $this->dice->queue = [[1, 2]];

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $ana = $this->p('Ana');
        $this->assertTrue($ana->in_jail);
        $this->assertSame(10, $ana->position);
        $this->assertSame(1500, $ana->money); // tanpa gaji
    }

    public function test_third_double_sends_to_jail(): void
    {
        $this->room->forceFill(['doubles_count' => 2])->save();
        $this->dice->queue = [[2, 2]];

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $ana = $this->p('Ana');
        $this->assertTrue($ana->in_jail);
        $this->assertSame(10, $ana->position);
    }

    public function test_failed_jail_roll_keeps_player_in_jail(): void
    {
        $this->p('Ana')->update(['in_jail' => true, 'position' => 10]);
        $this->dice->queue = [[1, 2]];

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $ana = $this->p('Ana');
        $this->assertTrue($ana->in_jail);
        $this->assertSame(1, (int) $ana->jail_turns);
        $this->assertSame(10, $ana->position);
    }

    public function test_double_frees_player_without_extra_turn(): void
    {
        $this->p('Ana')->update(['in_jail' => true, 'position' => 10]);
        $this->dice->queue = [[2, 2]];

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_buy');
        $this->assertFalse($this->p('Ana')->in_jail);
        $this->assertSame(14, $this->p('Ana')->position);

        $this->act('decline')->assertOk()->assertJsonPath('phase', 'await_end');
    }

    public function test_pay_jail_fine_and_use_card(): void
    {
        $this->p('Ana')->update(['in_jail' => true, 'position' => 10]);
        $this->act('pay_jail')->assertOk()->assertJsonPath('phase', 'await_roll');
        $this->assertFalse($this->p('Ana')->in_jail);
        $this->assertSame(1450, $this->p('Ana')->money);

        $this->p('Ana')->update(['in_jail' => true, 'jail_cards' => 1]);
        $this->act('use_jail_card')->assertOk();
        $this->assertFalse($this->p('Ana')->in_jail);
        $this->assertSame(0, (int) $this->p('Ana')->jail_cards);
        $this->assertSame(1450, $this->p('Ana')->money);
    }

    public function test_third_failed_attempt_forces_fine_and_move(): void
    {
        $this->p('Ana')->update(['in_jail' => true, 'position' => 10, 'jail_turns' => 2]);
        $this->dice->queue = [[1, 2]];

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_buy');

        $ana = $this->p('Ana');
        $this->assertFalse($ana->in_jail);
        $this->assertSame(13, $ana->position);
        $this->assertSame(1450, $ana->money);
    }

    // ---------------------------------------------------------------- kartu

    private function landOnChance(array $cards): void
    {
        $this->setDeck('chance', $cards);
        $this->p('Ana')->update(['position' => 4]);
        $this->dice->queue = [[1, 2]]; // petak 7 = Kesempatan
    }

    public function test_card_gives_money(): void
    {
        $this->landOnChance(['ch_dividen']);

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertSame(1550, $this->p('Ana')->money);
    }

    public function test_card_moves_token_to_tile(): void
    {
        $this->landOnChance(['ch_jakarta']);

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_buy')->assertJsonPath('pending.tile_id', 37);

        $this->assertSame(37, $this->p('Ana')->position);
    }

    public function test_card_sends_to_jail(): void
    {
        $this->landOnChance(['ch_jail']);

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertTrue($this->p('Ana')->in_jail);
        $this->assertSame(10, $this->p('Ana')->position);
    }

    public function test_jail_card_is_kept(): void
    {
        $this->landOnChance(['ch_jailcard']);

        $this->act('roll')->assertOk();

        $this->assertSame(1, (int) $this->p('Ana')->jail_cards);
    }

    public function test_card_pays_other_players(): void
    {
        $this->landOnChance(['ch_chairman']);

        $this->act('roll')->assertOk();

        $this->assertSame(1450, $this->p('Ana')->money);
        $this->assertSame(1550, $this->p('Budi')->money);
    }

    public function test_card_nearest_station_pays_double_rent(): void
    {
        $this->own('Budi', 15);
        $this->landOnChance(['ch_station']);

        $this->act('roll')->assertOk();

        $this->assertSame(15, $this->p('Ana')->position);
        $this->assertSame(1450, $this->p('Ana')->money); // 25 x 2
        $this->assertSame(1550, $this->p('Budi')->money);
    }

    public function test_repair_card_charges_per_building(): void
    {
        $this->own('Ana', 1, ['houses' => 2]);
        $this->own('Ana', 3, ['has_hotel' => true]);
        $this->landOnChance(['ch_repairs']);

        $this->act('roll')->assertOk();

        $this->assertSame(1350, $this->p('Ana')->money); // 2 x 25 + 100
    }

    // ---------------------------------------------------------------- utang & kebangkrutan

    private function landOnTaxWith(int $money): void
    {
        $this->p('Ana')->update(['position' => 1, 'money' => $money]);
        $this->dice->queue = [[1, 2]]; // petak 4 = Pajak Penghasilan 200
    }

    public function test_debt_phase_lets_player_raise_money_and_pay(): void
    {
        $this->own('Ana', 6);
        $this->own('Ana', 8);
        $this->landOnTaxWith(100);

        $this->act('roll')->assertOk()->assertJsonPath('phase', 'await_debt');
        $this->act('pay_debt')->assertStatus(422); // belum cukup

        $this->act('mortgage', ['tile_id' => 6])->assertOk();
        $this->act('mortgage', ['tile_id' => 8])->assertOk();
        $this->act('pay_debt')->assertOk()->assertJsonPath('phase', 'await_end');

        $this->assertSame(0, $this->p('Ana')->money);
    }

    public function test_immediate_bankruptcy_when_assets_cannot_cover(): void
    {
        $this->landOnTaxWith(10);

        $this->act('roll')->assertOk();

        $this->room->refresh();
        $this->assertSame('finished', $this->room->status);
        $this->assertTrue($this->p('Ana')->is_bankrupt);
        $this->assertSame($this->p('Budi')->id, $this->room->winner_player_id);
    }

    public function test_voluntary_bankruptcy_returns_assets_to_bank(): void
    {
        $this->own('Ana', 6);
        $this->own('Ana', 8);
        $this->landOnTaxWith(100);
        $this->act('roll')->assertJsonPath('phase', 'await_debt');

        $this->act('declare_bankruptcy')->assertOk();

        $this->assertTrue($this->p('Ana')->is_bankrupt);
        $this->assertNull($this->prop(6)->owner_player_id);
        $this->assertFalse((bool) $this->prop(6)->is_mortgaged);
        $this->room->refresh();
        $this->assertSame('finished', $this->room->status);
    }

    public function test_bankrupt_player_gives_money_and_properties_to_creditor(): void
    {
        $this->own('Ana', 39, ['has_hotel' => true]);
        $this->own('Budi', 1);
        $this->p('Budi')->update(['position' => 36, 'money' => 100]);
        $this->makeBudiCurrent();
        $this->dice->queue = [[1, 2]]; // petak 39, sewa hotel 2000

        $this->act('roll')->assertOk();

        $this->assertTrue($this->p('Budi')->is_bankrupt);
        $this->assertSame(1600, $this->p('Ana')->money);
        $this->assertSame($this->p('Ana')->id, (int) $this->prop(1)->owner_player_id);
        $this->room->refresh();
        $this->assertSame('finished', $this->room->status);
        $this->assertSame($this->p('Ana')->id, $this->room->winner_player_id);
    }
}