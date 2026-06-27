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
}
