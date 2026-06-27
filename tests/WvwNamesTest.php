<?php
use PHPUnit\Framework\TestCase;

final class WvwNamesTest extends TestCase {
    public function test_friendly_wins() {
        $this->assertSame('Our Guild', WVW_Names::resolve(2001, [2001 => 'Our Guild'], [2001 => 'Raw Name']));
    }
    public function test_falls_back_to_raw() {
        $this->assertSame('Raw Name', WVW_Names::resolve(2001, [], [2001 => 'Raw Name']));
    }
    public function test_falls_back_to_generic_when_unknown() {
        $this->assertSame('Team 2001', WVW_Names::resolve(2001, [], []));
    }
    public function test_empty_friendly_value_is_ignored() {
        $this->assertSame('Raw Name', WVW_Names::resolve(2001, [2001 => ''], [2001 => 'Raw Name']));
    }
    public function test_friendly_string_key() {
        $this->assertSame('Str Name', WVW_Names::resolve(2001, ['2001' => 'Str Name'], []));
    }
    public function test_raw_string_key() {
        $this->assertSame('Raw Str', WVW_Names::resolve(2001, [], ['2001' => 'Raw Str']));
    }
    public function test_built_in_wr_default_used() {
        // World Restructuring team id with no admin/raw entry -> built-in name.
        $this->assertSame("Rall's Rest", WVW_Names::resolve(11002, [], []));   // NA
        $this->assertSame('Yohlon Haven', WVW_Names::resolve(11004, [], []));  // NA
        $this->assertSame('Skrittsburgh', WVW_Names::resolve(12001, [], []));  // EU
        $this->assertSame("Fortune's Vale", WVW_Names::resolve(12002, [], [])); // EU
    }
    public function test_friendly_overrides_wr_default() {
        $this->assertSame('My Team', WVW_Names::resolve(11002, [11002 => 'My Team'], []));
    }
    public function test_wr_default_beats_raw() {
        // Built-in WR name takes precedence over a stale /v2/worlds raw name.
        $this->assertSame("Rall's Rest", WVW_Names::resolve(11002, [], [11002 => 'Old World']));
    }
}
