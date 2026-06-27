<?php
if (!defined('ABSPATH')) { exit; }

class WVW_Rest {

    public static function register() {
        register_rest_route('wvw/v1', '/match', [
            'methods'  => 'GET',
            'callback' => ['WVW_Rest', 'match'],
            'permission_callback' => '__return_true',
            'args' => [
                'team'  => ['sanitize_callback' => 'sanitize_text_field'],
                'match' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        register_rest_route('wvw/v1', '/region', [
            'methods'  => 'GET',
            'callback' => ['WVW_Rest', 'region'],
            'permission_callback' => '__return_true',
            'args' => [
                'region' => ['sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    /** Build the per-match payload (shared by REST and render). */
    public static function build_match_payload(array $match) {
        $settings = get_option('wvw_settings', []);
        $friendly = isset($settings['team_names']) && is_array($settings['team_names'])
            ? $settings['team_names'] : [];
        $raw = WVW_Api::get_world_names();
        $worlds = isset($match['worlds']) ? $match['worlds'] : [];
        $names = [];
        foreach (['red', 'green', 'blue'] as $c) {
            $id = isset($worlds[$c]) ? $worlds[$c] : 0;
            $names[$c] = WVW_Names::resolve($id, $friendly, $raw);
        }
        $kills  = WVW_Data::kills($match);
        $deaths = WVW_Data::deaths($match);
        $kdr = []; $ppk = [];
        foreach (['red', 'green', 'blue'] as $c) {
            $kdr[$c] = WVW_Data::kdr($kills[$c], $deaths[$c]);
            $ppk[$c] = WVW_Data::ppk($kills[$c], $deaths[$c]);
        }
        return [
            'id'         => isset($match['id']) ? $match['id'] : '',
            'tier'       => WVW_Data::tier_number($match),
            'names'      => $names,
            'scores'     => WVW_Data::scores($match),
            'kills'      => $kills,
            'deaths'     => $deaths,
            'kdr'        => $kdr,
            'ppk'        => $ppk,
            'vp'         => WVW_Data::victory_points($match),
            'ppt'        => WVW_Data::ppt($match),
            'skirmish'   => WVW_Data::skirmish($match),
            'objectives' => WVW_Data::objective_counts($match),
            'rank'       => WVW_Data::rank($match), // [1st,2nd,3rd] colors
            'move'       => [],                     // filled by region assembly
        ];
    }

    /**
     * Build payloads for every match in a region, attaching rank movement
     * (needs region context: which tier is top / bottom).
     */
    public static function build_region_payloads($prefix) {
        $matches = [];
        foreach (WVW_Api::get_matches() as $m) {
            if (isset($m['id']) && strpos($m['id'], $prefix) === 0) {
                $matches[] = $m;
            }
        }
        // tier order
        usort($matches, function ($a, $b) {
            return WVW_Data::tier_number($a) - WVW_Data::tier_number($b);
        });
        $maxTier = 0;
        foreach ($matches as $m) { $maxTier = max($maxTier, WVW_Data::tier_number($m)); }

        $out = [];
        foreach ($matches as $m) {
            $tier = WVW_Data::tier_number($m);
            $payload = self::build_match_payload($m);
            $payload['move'] = WVW_Data::movement($m, $tier === 1, $tier === $maxTier);
            $out[] = $payload;
        }
        return $out;
    }

    public static function match($req) {
        $matches = WVW_Api::get_matches();
        $args = [
            'match' => $req->get_param('match'),
            'team'  => $req->get_param('team'),
        ];
        if (empty($args['match']) && empty($args['team'])) {
            $settings = get_option('wvw_settings', []);
            $args['team'] = isset($settings['default_team']) ? $settings['default_team'] : '';
        }
        $m = WVW_Data::find_match($matches, $args);
        if (!$m) {
            return new WP_REST_Response(['error' => 'match_not_found'], 404);
        }
        return new WP_REST_Response(self::build_match_payload($m), 200);
    }

    public static function region($req) {
        $region = strtolower((string) $req->get_param('region'));
        $prefix = ($region === 'eu') ? '2-' : '1-'; // NA ids start "1-", EU "2-"
        return new WP_REST_Response(self::build_region_payloads($prefix), 200);
    }
}
