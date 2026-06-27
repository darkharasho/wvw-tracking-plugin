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
}
