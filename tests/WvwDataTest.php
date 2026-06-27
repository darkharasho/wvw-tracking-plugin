<?php
use PHPUnit\Framework\TestCase;

final class WvwDataTest extends TestCase {
    private function match($id) {
        $all = json_decode(file_get_contents(__DIR__ . '/fixtures/matches-sample.json'), true);
        foreach ($all as $m) { if ($m['id'] === $id) return $m; }
        $this->fail("fixture match $id not found");
    }

    public function test_scores() {
        $this->assertSame(
            ['red' => 1000, 'green' => 3000, 'blue' => 2000],
            WVW_Data::scores($this->match('2-1'))
        );
    }

    public function test_kills() {
        $this->assertSame(
            ['red' => 1000, 'green' => 3000, 'blue' => 2000],
            WVW_Data::kills($this->match('2-1'))
        );
    }

    public function test_deaths() {
        $this->assertSame(
            ['red' => 2000, 'green' => 1000, 'blue' => 4000],
            WVW_Data::deaths($this->match('2-1'))
        );
    }

    public function test_victory_points() {
        $this->assertSame(
            ['red' => 30, 'green' => 50, 'blue' => 20],
            WVW_Data::victory_points($this->match('2-1'))
        );
    }

    public function test_skirmish_returns_latest() {
        $this->assertSame(
            ['red' => 11, 'green' => 33, 'blue' => 22],
            WVW_Data::skirmish($this->match('2-1'))
        );
    }

    public function test_ppt_sums_points_tick_by_owner() {
        // Red: Camp2 + Castle12 + Tower4 + Camp2 = 20; Blue: Tower4; Green: Keep8
        $this->assertSame(
            ['red' => 20, 'green' => 8, 'blue' => 4],
            WVW_Data::ppt($this->match('2-1'))
        );
    }

    public function test_objective_counts() {
        $counts = WVW_Data::objective_counts($this->match('2-1'));
        $this->assertSame(['Camp' => 2, 'Tower' => 1, 'Keep' => 0, 'Castle' => 1], $counts['red']);
        $this->assertSame(['Camp' => 0, 'Tower' => 1, 'Keep' => 0, 'Castle' => 0], $counts['blue']);
        $this->assertSame(['Camp' => 0, 'Tower' => 0, 'Keep' => 1, 'Castle' => 0], $counts['green']);
    }

    private function all() {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/matches-sample.json'), true);
    }

    public function test_find_match_by_id() {
        $m = WVW_Data::find_match($this->all(), ['match' => '1-1']);
        $this->assertSame('1-1', $m['id']);
    }

    public function test_find_match_by_team_follows_all_worlds() {
        // 2101 is a linked world inside match 2-1's red all_worlds.
        $m = WVW_Data::find_match($this->all(), ['team' => 2101]);
        $this->assertSame('2-1', $m['id']);
    }

    public function test_find_match_id_wins_over_team() {
        $m = WVW_Data::find_match($this->all(), ['match' => '1-1', 'team' => 2101]);
        $this->assertSame('1-1', $m['id']);
    }

    public function test_find_match_returns_null_when_absent() {
        $this->assertNull(WVW_Data::find_match($this->all(), ['team' => 999999]));
    }

    public function test_tier_number() {
        $this->assertSame(1, WVW_Data::tier_number($this->match('2-1')));
    }

    public function test_rank_orders_by_victory_points_desc() {
        // VP: green 50, red 30, blue 20
        $this->assertSame(['green', 'red', 'blue'], WVW_Data::rank($this->match('2-1')));
    }

    public function test_movement_top_tier() {
        // top tier (not bottom): 1st green stays, 2nd red stays, 3rd blue down
        $this->assertSame(
            ['red' => 'stays', 'green' => 'stays', 'blue' => 'down'],
            WVW_Data::movement($this->match('2-1'), true, false)
        );
    }

    public function test_movement_middle_tier() {
        // not top, not bottom: 1st green up, 2nd red stays, 3rd blue down
        $this->assertSame(
            ['red' => 'stays', 'green' => 'up', 'blue' => 'down'],
            WVW_Data::movement($this->match('2-1'), false, false)
        );
    }

    public function test_kdr() {
        $this->assertSame(0.5, WVW_Data::kdr(1000, 2000));
        $this->assertSame(3.0, WVW_Data::kdr(3000, 1000));
        $this->assertSame(5.0, WVW_Data::kdr(5, 0)); // deaths 0 guard
    }

    public function test_ppk() {
        $this->assertSame(33.33, WVW_Data::ppk(1000, 2000));
        $this->assertSame(75.0, WVW_Data::ppk(3000, 1000));
        $this->assertSame(0.0, WVW_Data::ppk(0, 0)); // both zero guard
    }
}
