<?php
if (!defined('ABSPATH')) { exit; }

class WVW_Render {

    private static function order() { return ['red', 'green', 'blue']; }

    public static function unavailable() {
        return '<div class="wvw-widget wvw-unavailable">'
            . esc_html__('Scores temporarily unavailable.', 'wvw-tracking')
            . '</div>';
    }

    /** $type in score|ppt|skirmish. Renders a three-team row of values. */
    public static function widget($type, array $p) {
        $key = ($type === 'score') ? 'scores' : $type;
        $values = isset($p[$key]) ? $p[$key] : [];
        $rows = '';
        foreach (self::order() as $c) {
            $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
            $val  = isset($values[$c]) ? (int) $values[$c] : 0;
            $rows .= '<div class="wvw-team wvw-' . esc_attr($c) . '">'
                . '<span class="wvw-name">' . esc_html($name) . '</span>'
                . '<span class="wvw-value" data-team="' . esc_attr($c) . '">' . esc_html(number_format_i18n($val)) . '</span>'
                . '</div>';
        }
        return '<div class="wvw-widget wvw-' . esc_attr($type) . '">'
            . self::header(self::label($type), $p)
            . $rows . '</div>';
    }

    /** Card header with the widget title and, when known, a tier tag. */
    private static function header($title, array $p) {
        $tier = isset($p['tier']) ? $p['tier'] : '';
        $tag = '';
        if ($tier !== '' && (int) $tier > 0) {
            $tag = '<span class="wvw-tier-tag">'
                . esc_html(sprintf(__('Tier %s', 'wvw-tracking'), $tier)) . '</span>';
        }
        return '<div class="wvw-widget-label">' . esc_html($title) . $tag . '</div>';
    }

    /** Render one team's card row: colored name + a right-aligned set of stats. */
    private static function team_row($color, $name, $stats) {
        $cells = '';
        foreach ($stats as $s) {
            $cells .= '<span class="wvw-stat">'
                . '<span class="wvw-stat-label">' . esc_html($s['label']) . '</span>'
                . '<span class="wvw-stat-val" data-team="' . esc_attr($color) . '" data-' . esc_attr($s['attr']) . '="' . esc_attr($s['key']) . '">'
                . esc_html($s['value']) . '</span></span>';
        }
        return '<div class="wvw-team wvw-' . esc_attr($color) . '">'
            . '<span class="wvw-name">' . esc_html($name) . '</span>'
            . '<span class="wvw-stats">' . $cells . '</span></div>';
    }

    private static function label($type) {
        switch ($type) {
            case 'score':    return __('War Score', 'wvw-tracking');
            case 'ppt':      return __('Points per Tick', 'wvw-tracking');
            case 'skirmish': return __('Skirmish', 'wvw-tracking');
            default:         return ucfirst($type);
        }
    }

    /** Objectives: per team, counts of each structure type — score-card style. */
    public static function objectives(array $p) {
        $obj = isset($p['objectives']) ? $p['objectives'] : [];
        $types = [
            'Camp'   => __('Camps', 'wvw-tracking'),
            'Tower'  => __('Towers', 'wvw-tracking'),
            'Keep'   => __('Keeps', 'wvw-tracking'),
            'Castle' => __('SMC', 'wvw-tracking'),
        ];
        $rows = '';
        foreach (self::order() as $c) {
            $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
            $stats = [];
            foreach ($types as $key => $label) {
                $n = isset($obj[$c][$key]) ? (int) $obj[$c][$key] : 0;
                $stats[] = ['label' => $label, 'attr' => 'type', 'key' => $key, 'value' => $n];
            }
            $rows .= self::team_row($c, $name, $stats);
        }
        return '<div class="wvw-widget wvw-objectives">'
            . self::header(__('Objectives', 'wvw-tracking'), $p) . $rows . '</div>';
    }

    /** Kills / deaths / KDR per team — score-card style. */
    public static function kills(array $p) {
        $rows = '';
        foreach (self::order() as $c) {
            $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
            $k = isset($p['kills'][$c]) ? (int) $p['kills'][$c] : 0;
            $d = isset($p['deaths'][$c]) ? (int) $p['deaths'][$c] : 0;
            $kdr = isset($p['kdr'][$c]) ? $p['kdr'][$c] : 0;
            $stats = [
                ['label' => __('Kills', 'wvw-tracking'),  'attr' => 'field', 'key' => 'kills',  'value' => number_format_i18n($k)],
                ['label' => __('Deaths', 'wvw-tracking'), 'attr' => 'field', 'key' => 'deaths', 'value' => number_format_i18n($d)],
                ['label' => __('KDR', 'wvw-tracking'),    'attr' => 'field', 'key' => 'kdr',    'value' => number_format_i18n($kdr, 2)],
            ];
            $rows .= self::team_row($c, $name, $stats);
        }
        return '<div class="wvw-widget wvw-kills">'
            . self::header(__('Kills', 'wvw-tracking'), $p) . $rows . '</div>';
    }

    private static function move_label($move) {
        switch ($move) {
            case 'up':   return ['wvw-move-up', '▲ ' . __('Moves Up', 'wvw-tracking')];
            case 'down': return ['wvw-move-down', '▼ ' . __('Moves Down', 'wvw-tracking')];
            default:     return ['wvw-move-stays', '— ' . __('Stays', 'wvw-tracking')];
        }
    }

    /** Full wvw.gg-style region ladder. One <tr> per team, grouped by tier. */
    public static function standings(array $payloads) {
        if (empty($payloads)) {
            return self::unavailable();
        }
        $head = '<thead><tr>'
            . '<th>' . esc_html__('Tier', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('Team', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('Skirmish', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('Kills', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('Deaths', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('KDR', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('PPK', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('VP', 'wvw-tracking') . '</th>'
            . '<th>' . esc_html__('Move', 'wvw-tracking') . '</th>'
            . '</tr></thead>';

        $body = '';
        $rankLabels = [
            0 => __('1st', 'wvw-tracking'),
            1 => __('2nd', 'wvw-tracking'),
            2 => __('3rd', 'wvw-tracking'),
        ];
        foreach ($payloads as $p) {
            $ranked = isset($p['rank']) ? $p['rank'] : self::order();
            $first = true;
            foreach ($ranked as $i => $c) {
                $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
                $sk   = isset($p['skirmish'][$c]) ? (int) $p['skirmish'][$c] : 0;
                $k    = isset($p['kills'][$c]) ? (int) $p['kills'][$c] : 0;
                $d    = isset($p['deaths'][$c]) ? (int) $p['deaths'][$c] : 0;
                $kdr  = isset($p['kdr'][$c]) ? $p['kdr'][$c] : 0;
                $ppk  = isset($p['ppk'][$c]) ? $p['ppk'][$c] : 0;
                $vp   = isset($p['vp'][$c]) ? (int) $p['vp'][$c] : 0;
                $mv   = isset($p['move'][$c]) ? $p['move'][$c] : 'stays';
                list($mvClass, $mvText) = self::move_label($mv);
                $tierCell = $first
                    ? '<th class="wvw-tier" rowspan="' . count($ranked) . '">' . esc_html($p['tier']) . '</th>'
                    : '';
                $rankLabel = isset($rankLabels[$i]) ? $rankLabels[$i] : ($i + 1);
                $body .= '<tr class="wvw-row wvw-' . esc_attr($c) . '">'
                    . $tierCell
                    . '<td class="wvw-team-cell"><span class="wvw-rank wvw-' . esc_attr($c) . '">' . esc_html($rankLabel) . '</span> '
                    . '<span class="wvw-name">' . esc_html($name) . '</span></td>'
                    . '<td>' . esc_html(number_format_i18n($sk)) . '</td>'
                    . '<td>' . esc_html(number_format_i18n($k)) . '</td>'
                    . '<td>' . esc_html(number_format_i18n($d)) . '</td>'
                    . '<td>' . esc_html(number_format_i18n($kdr, 2)) . '</td>'
                    . '<td>' . esc_html(number_format_i18n($ppk, 2)) . '%</td>'
                    . '<td>' . esc_html(number_format_i18n($vp)) . '</td>'
                    . '<td class="' . esc_attr($mvClass) . '">' . esc_html($mvText) . '</td>'
                    . '</tr>';
                $first = false;
            }
        }
        return '<div class="wvw-widget wvw-standings"><table>' . $head . '<tbody>' . $body . '</tbody></table></div>';
    }
}
