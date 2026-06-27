<?php
if (!defined('ABSPATH') && !defined('WVW_TEST')) {
    // Allow standalone test loading; in WP, ABSPATH is defined.
}

class WVW_Data {

    /** Empty three-team accumulator in display order. */
    private static function empty_triple() {
        return ['red' => 0, 'green' => 0, 'blue' => 0];
    }

    /** Pull a {red,green,blue} sub-array into an int triple in display order. */
    private static function triple($arr) {
        $a = is_array($arr) ? $arr : [];
        return [
            'red'   => (int) (isset($a['red']) ? $a['red'] : 0),
            'green' => (int) (isset($a['green']) ? $a['green'] : 0),
            'blue'  => (int) (isset($a['blue']) ? $a['blue'] : 0),
        ];
    }

    public static function scores(array $match) {
        return self::triple(isset($match['scores']) ? $match['scores'] : []);
    }

    public static function kills(array $match) {
        return self::triple(isset($match['kills']) ? $match['kills'] : []);
    }

    public static function deaths(array $match) {
        return self::triple(isset($match['deaths']) ? $match['deaths'] : []);
    }

    public static function victory_points(array $match) {
        return self::triple(isset($match['victory_points']) ? $match['victory_points'] : []);
    }

    public static function skirmish(array $match) {
        $list = isset($match['skirmishes']) ? $match['skirmishes'] : [];
        if (empty($list)) {
            return self::empty_triple();
        }
        $last = end($list);
        return self::triple(isset($last['scores']) ? $last['scores'] : []);
    }

    public static function ppt(array $match) {
        $ppt = self::empty_triple();
        foreach (self::iterate_objectives($match) as $obj) {
            $color = strtolower(isset($obj['owner']) ? $obj['owner'] : '');
            if (!isset($ppt[$color])) {
                continue; // Neutral or unknown
            }
            $ppt[$color] += (int) (isset($obj['points_tick']) ? $obj['points_tick'] : 0);
        }
        return $ppt;
    }

    public static function objective_counts(array $match) {
        $types = ['Camp' => 0, 'Tower' => 0, 'Keep' => 0, 'Castle' => 0];
        $counts = [
            'red'   => $types,
            'green' => $types,
            'blue'  => $types,
        ];
        foreach (self::iterate_objectives($match) as $obj) {
            $color = strtolower(isset($obj['owner']) ? $obj['owner'] : '');
            $type  = isset($obj['type']) ? $obj['type'] : '';
            if (isset($counts[$color]) && isset($counts[$color][$type])) {
                $counts[$color][$type]++;
            }
        }
        return $counts;
    }

    /** Yield every objective across all maps of a match. */
    private static function iterate_objectives(array $match) {
        $out = [];
        $maps = isset($match['maps']) ? $match['maps'] : [];
        foreach ($maps as $map) {
            $objs = isset($map['objectives']) ? $map['objectives'] : [];
            foreach ($objs as $obj) {
                $out[] = $obj;
            }
        }
        return $out;
    }

    public static function find_match(array $matches, array $args) {
        if (!empty($args['match'])) {
            foreach ($matches as $m) {
                if (isset($m['id']) && (string) $m['id'] === (string) $args['match']) {
                    return $m;
                }
            }
            return null;
        }
        if (!empty($args['team'])) {
            $team = (int) $args['team'];
            foreach ($matches as $m) {
                $aw = isset($m['all_worlds']) ? $m['all_worlds'] : [];
                foreach (['red', 'green', 'blue'] as $color) {
                    $ids = isset($aw[$color]) ? array_map('intval', $aw[$color]) : [];
                    if (in_array($team, $ids, true)) {
                        return $m;
                    }
                }
            }
        }
        return null;
    }

    public static function tier_number(array $match) {
        $id = isset($match['id']) ? (string) $match['id'] : '';
        $pos = strpos($id, '-');
        return $pos === false ? 0 : (int) substr($id, $pos + 1);
    }

    /** Restructured-team IDs (World Restructuring) start at this id. */
    const WR_TEAM_ID_MIN = 10000;

    /**
     * The team identifier to name a side by. Prefers the World Restructuring
     * team id (>= WR_TEAM_ID_MIN) found in all_worlds[$color]; falls back to the
     * legacy main world id in worlds[$color].
     */
    public static function team_id(array $match, $color) {
        $aw = isset($match['all_worlds'][$color]) ? $match['all_worlds'][$color] : [];
        foreach ($aw as $id) {
            if ((int) $id >= self::WR_TEAM_ID_MIN) {
                return (int) $id;
            }
        }
        return (int) (isset($match['worlds'][$color]) ? $match['worlds'][$color] : 0);
    }

    /** Colors ordered by victory points desc; tie-break war score desc, then fixed order. */
    public static function rank(array $match) {
        $vp = self::victory_points($match);
        $sc = self::scores($match);
        $order = ['red' => 0, 'green' => 1, 'blue' => 2];
        $colors = ['red', 'green', 'blue'];
        usort($colors, function ($a, $b) use ($vp, $sc, $order) {
            if ($vp[$a] !== $vp[$b]) { return $vp[$b] - $vp[$a]; }
            if ($sc[$a] !== $sc[$b]) { return $sc[$b] - $sc[$a]; }
            return $order[$a] - $order[$b];
        });
        return $colors;
    }

    public static function movement(array $match, $isTopTier, $isBottomTier) {
        $ranked = self::rank($match); // [1st, 2nd, 3rd]
        $verdict = [];
        $verdict[$ranked[0]] = $isTopTier ? 'stays' : 'up';
        $verdict[$ranked[1]] = 'stays';
        $verdict[$ranked[2]] = $isBottomTier ? 'stays' : 'down';
        // Return in fixed display order so equality checks are deterministic.
        return [
            'red'   => $verdict['red'],
            'green' => $verdict['green'],
            'blue'  => $verdict['blue'],
        ];
    }

    public static function kdr($kills, $deaths) {
        $kills = (int) $kills; $deaths = (int) $deaths;
        if ($deaths === 0) { return (float) $kills; }
        return round($kills / $deaths, 2);
    }

    public static function ppk($kills, $deaths) {
        $kills = (int) $kills; $deaths = (int) $deaths;
        $total = $kills + $deaths;
        if ($total === 0) { return 0.0; }
        return round($kills / $total * 100, 2);
    }
}
