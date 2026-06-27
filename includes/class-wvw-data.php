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
}
