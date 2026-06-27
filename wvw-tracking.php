<?php
/**
 * Plugin Name: WvW Tracking
 * Description: GW2 World vs World matchup shortcodes (score, PPT, skirmish, objectives, standings).
 * Version: 0.1.0
 * Author: darkharasho
 * License: GPL-2.0-or-later
 * Text Domain: wvw-tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WVW_VERSION', '0.1.0');
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
add_action('rest_api_init', ['WVW_Rest', 'register']);

add_filter('cron_schedules', ['WVW_Api', 'add_schedule']);
add_action(WVW_Api::CRON_HOOK, ['WVW_Api', 'refresh']);
register_activation_hook(__FILE__, ['WVW_Api', 'activate']);
register_deactivation_hook(__FILE__, ['WVW_Api', 'deactivate']);
