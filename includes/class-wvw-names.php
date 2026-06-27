<?php
class WVW_Names {

    /**
     * Built-in World Restructuring team names (NA, ids 11001-11012). ArenaNet's
     * API exposes no name for these team ids, so they are bundled here as a
     * default. The admin team-name map overrides/extends this (e.g. for EU).
     */
    public static function wr_defaults() {
        return [
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
