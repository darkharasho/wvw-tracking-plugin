<?php
if (!defined('ABSPATH')) { exit; }

class WVW_Shortcodes {

    public static function register() {
        add_shortcode('wvw_score',      ['WVW_Shortcodes', 'score']);
        add_shortcode('wvw_ppt',        ['WVW_Shortcodes', 'ppt']);
        add_shortcode('wvw_skirmish',   ['WVW_Shortcodes', 'skirmish']);
        add_shortcode('wvw_kills',      ['WVW_Shortcodes', 'kills']);
        add_shortcode('wvw_objectives', ['WVW_Shortcodes', 'objectives']);
        add_shortcode('wvw_standings',  ['WVW_Shortcodes', 'standings']);
    }

    /**
     * Resolve a match-scoped shortcode's attributes to {match, team}. A friendly
     * region+tier pair (e.g. region="na" tier="1") is converted to a match id
     * ("1-1") when no explicit match is given.
     */
    private static function effective($atts) {
        $a = shortcode_atts(['team' => '', 'match' => '', 'region' => '', 'tier' => ''], $atts);
        $match = $a['match'];
        if ($match === '' && $a['region'] !== '' && $a['tier'] !== '') {
            $match = WVW_Data::match_id_from($a['region'], $a['tier']);
        }
        return ['match' => $match, 'team' => $a['team']];
    }

    private static function resolve_payload($atts) {
        $e = self::effective($atts);
        if ($e['match'] === '' && $e['team'] === '') {
            $settings = get_option('wvw_settings', []);
            $e['team'] = isset($settings['default_team']) ? $settings['default_team'] : '';
        }
        $matches = WVW_Api::get_matches();
        $m = WVW_Data::find_match($matches, ['match' => $e['match'], 'team' => $e['team']]);
        if (!$m) {
            return null;
        }
        return WVW_Rest::build_match_payload($m);
    }

    private static function wrap($html, $type, $atts) {
        $e = self::effective($atts);
        return '<div class="wvw-container" data-wvw-type="' . esc_attr($type) . '"'
            . ' data-wvw-team="' . esc_attr($e['team']) . '"'
            . ' data-wvw-match="' . esc_attr($e['match']) . '">' . $html . '</div>';
    }

    public static function score($atts) {
        $p = self::resolve_payload($atts);
        $html = $p ? WVW_Render::widget('score', $p) : WVW_Render::unavailable();
        return self::wrap($html, 'score', $atts);
    }
    public static function ppt($atts) {
        $p = self::resolve_payload($atts);
        $html = $p ? WVW_Render::widget('ppt', $p) : WVW_Render::unavailable();
        return self::wrap($html, 'ppt', $atts);
    }
    public static function skirmish($atts) {
        $p = self::resolve_payload($atts);
        $html = $p ? WVW_Render::widget('skirmish', $p) : WVW_Render::unavailable();
        return self::wrap($html, 'skirmish', $atts);
    }
    public static function kills($atts) {
        $p = self::resolve_payload($atts);
        $html = $p ? WVW_Render::kills($p) : WVW_Render::unavailable();
        return self::wrap($html, 'kills', $atts);
    }
    public static function objectives($atts) {
        $p = self::resolve_payload($atts);
        $html = $p ? WVW_Render::objectives($p) : WVW_Render::unavailable();
        return self::wrap($html, 'objectives', $atts);
    }
    public static function standings($atts) {
        $a = shortcode_atts(['region' => ''], $atts);
        $region = $a['region'];
        if ($region === '') {
            $settings = get_option('wvw_settings', []);
            $region = isset($settings['default_region']) ? $settings['default_region'] : 'na';
        }
        $prefix = (strtolower($region) === 'eu') ? '2-' : '1-';
        $payloads = WVW_Rest::build_region_payloads($prefix);
        return '<div class="wvw-container" data-wvw-type="standings" data-wvw-region="'
            . esc_attr($region) . '">' . WVW_Render::standings($payloads) . '</div>';
    }
}
