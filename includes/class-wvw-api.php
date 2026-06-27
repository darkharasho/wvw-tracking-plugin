<?php
if (!defined('ABSPATH')) { exit; }

class WVW_Api {

    const CRON_HOOK = 'wvw_refresh_event';

    public static function interval() {
        $opts = get_option('wvw_settings', []);
        $i = isset($opts['interval']) ? (int) $opts['interval'] : WVW_DEFAULT_INTERVAL;
        return $i >= 60 ? $i : WVW_DEFAULT_INTERVAL;
    }

    /** Returns decoded matches array; refreshes when stale or empty. */
    public static function get_matches() {
        $cached = get_transient(WVW_CACHE_KEY);
        if (is_array($cached) && !empty($cached['data']) && !empty($cached['fetched'])) {
            if ((time() - (int) $cached['fetched']) < self::interval()) {
                return $cached['data'];
            }
            // stale: try refresh, but fall back to stale data if refresh fails
            $fresh = self::fetch_matches();
            if (!empty($fresh)) {
                self::store($fresh);
                return $fresh;
            }
            return $cached['data'];
        }
        // empty cache: must fetch
        $fresh = self::fetch_matches();
        if (!empty($fresh)) {
            self::store($fresh);
        }
        return $fresh;
    }

    public static function refresh() {
        $fresh = self::fetch_matches();
        if (!empty($fresh)) {
            self::store($fresh);
        }
    }

    private static function store($data) {
        // Keep the transient longer than the interval so stale data survives API outages.
        set_transient(WVW_CACHE_KEY, ['data' => $data, 'fetched' => time()], DAY_IN_SECONDS);
    }

    private static function fetch_matches() {
        $res = wp_remote_get(WVW_API_URL, ['timeout' => 8]);
        if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
            return [];
        }
        $decoded = json_decode(wp_remote_retrieve_body($res), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Legacy world names, cached a day; used as raw fallback for naming. */
    public static function get_world_names() {
        $cached = get_transient(WVW_WORLDS_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $res = wp_remote_get(WVW_WORLDS_URL, ['timeout' => 8]);
        $map = [];
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
            $worlds = json_decode(wp_remote_retrieve_body($res), true);
            if (is_array($worlds)) {
                foreach ($worlds as $w) {
                    if (isset($w['id'], $w['name'])) {
                        $map[(string) $w['id']] = $w['name'];
                    }
                }
            }
        }
        set_transient(WVW_WORLDS_KEY, $map, !empty($map) ? DAY_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
        return $map;
    }

    /** Register a cron schedule matching the configured interval. */
    public static function add_schedule($schedules) {
        $schedules['wvw_interval'] = [
            'interval' => self::interval(),
            'display'  => __('WvW refresh interval', 'wvw-tracking'),
        ];
        return $schedules;
    }

    public static function activate() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'wvw_interval', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }
}
