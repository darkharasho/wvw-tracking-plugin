<?php
class WVW_Names {
    public static function resolve($id, array $friendly, array $raw) {
        $key = (string) $id;
        if (isset($friendly[$key]) && $friendly[$key] !== '') {
            return $friendly[$key];
        }
        // tolerate int-keyed arrays
        if (isset($friendly[(int) $id]) && $friendly[(int) $id] !== '') {
            return $friendly[(int) $id];
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
