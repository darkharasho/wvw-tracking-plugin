<?php
/**
 * Plugin Name: WvW Tracking
 * Description: GW2 World vs World matchup shortcodes (score, PPT, skirmish, objectives, standings).
 * Version: 0.1.1
 * Author: darkharasho
 * License: GPL-2.0-or-later
 * Text Domain: wvw-tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WVW_VERSION', '0.1.1');
define('WVW_PATH', plugin_dir_path(__FILE__));
define('WVW_URL', plugin_dir_url(__FILE__));
define('WVW_API_URL', 'https://api.guildwars2.com/v2/wvw/matches?ids=all');
define('WVW_WORLDS_URL', 'https://api.guildwars2.com/v2/worlds?ids=all');
define('WVW_CACHE_KEY', 'wvw_matches_blob');
define('WVW_WORLDS_KEY', 'wvw_worlds_names');
define('WVW_DEFAULT_INTERVAL', 300); // seconds

require_once WVW_PATH . 'includes/class-wvw-data.php';
require_once WVW_PATH . 'includes/class-wvw-names.php';
// Later tasks add: class-wvw-api, class-wvw-rest, class-wvw-render,
// class-wvw-shortcodes, class-wvw-settings — and their bootstrap wiring.
require_once WVW_PATH . 'includes/class-wvw-api.php';
require_once WVW_PATH . 'includes/class-wvw-rest.php';
require_once WVW_PATH . 'includes/class-wvw-render.php';
require_once WVW_PATH . 'includes/class-wvw-shortcodes.php';
require_once WVW_PATH . 'includes/class-wvw-settings.php';
add_action('admin_menu', ['WVW_Settings', 'menu']);
add_action('admin_init', ['WVW_Settings', 'register']);
add_action('rest_api_init', ['WVW_Rest', 'register']);
add_action('init', ['WVW_Shortcodes', 'register']);
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('wvw-tracking', WVW_URL . 'assets/wvw.css', [], WVW_VERSION);
    wp_enqueue_script('wvw-tracking', WVW_URL . 'assets/wvw.js', [], WVW_VERSION, true);
    wp_localize_script('wvw-tracking', 'wvwConfig', [
        'root'     => esc_url_raw(rest_url()),
        'interval' => WVW_Api::interval(),
    ]);
});

add_filter('cron_schedules', ['WVW_Api', 'add_schedule']);
add_action(WVW_Api::CRON_HOOK, ['WVW_Api', 'refresh']);
register_activation_hook(__FILE__, ['WVW_Api', 'activate']);
register_deactivation_hook(__FILE__, ['WVW_Api', 'deactivate']);
