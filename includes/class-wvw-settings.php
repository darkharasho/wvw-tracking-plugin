<?php
if (!defined('ABSPATH')) { exit; }

class WVW_Settings {

    public static function menu() {
        add_options_page(
            __('WvW Tracking', 'wvw-tracking'),
            __('WvW Tracking', 'wvw-tracking'),
            'manage_options',
            'wvw-tracking',
            ['WVW_Settings', 'render_page']
        );
    }

    public static function register() {
        register_setting('wvw_settings_group', 'wvw_settings', ['WVW_Settings', 'sanitize']);
    }

    public static function sanitize($input) {
        $out = [];
        $out['default_team']   = isset($input['default_team']) ? sanitize_text_field($input['default_team']) : '';
        $region = isset($input['default_region']) ? strtolower(sanitize_text_field($input['default_region'])) : 'na';
        $out['default_region'] = in_array($region, ['na', 'eu'], true) ? $region : 'na';
        $interval = isset($input['interval']) ? (int) $input['interval'] : WVW_DEFAULT_INTERVAL;
        $out['interval'] = $interval >= 60 ? $interval : WVW_DEFAULT_INTERVAL;

        // team_names arrives as parallel id[] and name[] arrays.
        $out['team_names'] = [];
        if (!empty($input['team_id']) && is_array($input['team_id'])) {
            $ids = $input['team_id'];
            $names = isset($input['team_name']) ? $input['team_name'] : [];
            foreach ($ids as $i => $id) {
                $id = sanitize_text_field($id);
                $nm = isset($names[$i]) ? sanitize_text_field($names[$i]) : '';
                if ($id !== '' && $nm !== '') {
                    $out['team_names'][$id] = $nm;
                }
            }
        }
        return $out;
    }

    public static function render_page() {
        $o = get_option('wvw_settings', []);
        $team   = isset($o['default_team']) ? $o['default_team'] : '';
        $region = isset($o['default_region']) ? $o['default_region'] : 'na';
        $interval = isset($o['interval']) ? (int) $o['interval'] : WVW_DEFAULT_INTERVAL;
        $names  = isset($o['team_names']) && is_array($o['team_names']) ? $o['team_names'] : [];
        // ensure at least 5 blank rows to fill in
        $rows = $names;
        for ($i = count($rows); $i < 5; $i++) { $rows[''] = ''; }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WvW Tracking', 'wvw-tracking'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wvw_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php echo esc_html__('Default team (world id)', 'wvw-tracking'); ?></th>
                        <td><input type="text" name="wvw_settings[default_team]" value="<?php echo esc_attr($team); ?>" />
                        <p class="description"><?php echo esc_html__('Used when a shortcode has no team/match attribute.', 'wvw-tracking'); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Default region', 'wvw-tracking'); ?></th>
                        <td>
                            <select name="wvw_settings[default_region]">
                                <option value="na" <?php selected($region, 'na'); ?>>NA</option>
                                <option value="eu" <?php selected($region, 'eu'); ?>>EU</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Refresh interval (seconds)', 'wvw-tracking'); ?></th>
                        <td><input type="number" min="60" name="wvw_settings[interval]" value="<?php echo esc_attr($interval); ?>" /></td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Team name mappings', 'wvw-tracking'); ?></h2>
                <p class="description"><?php echo esc_html__('Map GW2 world/team IDs to friendly names. Unknown IDs fall back to the raw API name.', 'wvw-tracking'); ?></p>
                <table class="widefat" style="max-width:600px">
                    <thead><tr><th><?php echo esc_html__('World ID', 'wvw-tracking'); ?></th><th><?php echo esc_html__('Friendly name', 'wvw-tracking'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $id => $nm): ?>
                        <tr>
                            <td><input type="text" name="wvw_settings[team_id][]" value="<?php echo esc_attr($id); ?>" /></td>
                            <td><input type="text" name="wvw_settings[team_name][]" value="<?php echo esc_attr($nm); ?>" /></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
