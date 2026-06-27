<?php
class WVW_Names {

    /**
     * Built-in World Restructuring team names (NA 11xxx, EU 12xxx, CN 18xxx).
     * ArenaNet's API exposes no name for these team ids, so they are bundled
     * here. Source: github.com/Drevarr/GW2-WVW-Teams (gw2_data.py). The admin
     * team-name map overrides/extends this when teams are renamed at a relink.
     */
    public static function wr_defaults() {
        return [
            // NA
            11001 => 'Moogooloo',
            11002 => "Rall's Rest",
            11003 => 'Domain of Torment',
            11004 => 'Yohlon Haven',
            11005 => 'Tomb of Drascir',
            11006 => 'Hall of Judgment',
            11007 => 'Throne of Balthazar',
            11008 => "Dwayna's Temple",
            11009 => "Abaddon's Prison",
            11010 => 'Cathedral of Blood',
            11011 => 'Lutgardis Conservatory',
            11012 => 'Mosswood',
            // EU
            12001 => 'Skrittsburgh',
            12002 => "Fortune's Vale",
            12003 => 'Silent Woods',
            12004 => "Ettin's Back",
            12005 => 'Domain of Anguish',
            12006 => 'Palawadan',
            12007 => 'Bloodstone Gulch',
            12008 => 'Frost Citadel',
            12009 => 'Dragrimmar',
            12010 => "Grenth's Door",
            12011 => 'Mirror of Lyssa',
            12012 => "Melandru's Dome",
            12013 => "Kormir's Library",
            12014 => 'Great House Aviary',
            12015 => 'Bava Nisos',
            // CN
            18001 => 'Moogooloo',
            18002 => "Titan's Staircase",
            18003 => 'Skrittsburgh',
            18004 => 'Seven Pines',
            18005 => 'Phoenix Dawn',
            18006 => 'Thornwatch',
            18007 => 'Griffonfall',
            18008 => 'Stonefall',
            18009 => 'First Haven',
            18010 => "Dragon's Claw",
            18011 => "Giant's Rise",
            18012 => "Reaper's Corridor",
            18013 => "Fortune's Vale",
            18014 => 'Silent Woods',
            18015 => "Grenth's Door",
        ];
    }

    /**
     * Resolve a team id to a display name. Precedence:
     * admin friendly map -> built-in WR defaults -> raw /v2/worlds name ->
     * generic "Team {id}". Never returns an empty string.
     */
    public static function resolve($id, array $friendly, array $raw) {
        $key = (string) $id;
        if (isset($friendly[$key]) && $friendly[$key] !== '') {
            return $friendly[$key];
        }
        // tolerate int-keyed arrays
        if (isset($friendly[(int) $id]) && $friendly[(int) $id] !== '') {
            return $friendly[(int) $id];
        }
        $defaults = self::wr_defaults();
        if (isset($defaults[(int) $id])) {
            return $defaults[(int) $id];
        }
        if (isset($raw[$key]) && $raw[$key] !== '') {
            return $raw[$key];
        }
        if (isset($raw[(int) $id]) && $raw[(int) $id] !== '') {
            return $raw[(int) $id];
        }
        return 'Team ' . $id;
    }
}
