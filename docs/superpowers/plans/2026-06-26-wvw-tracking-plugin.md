# GW2 WvW Tracking Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A WordPress plugin that renders GW2 WvW matchup data (score, PPT, skirmish, objectives, region ladder) via shortcodes, fed by one cached fetch of the official GW2 API.

**Architecture:** PHP fetches `https://api.guildwars2.com/v2/wvw/matches?ids=all` and caches the blob in a transient (refreshed by WP-Cron, with stale-on-request as the source of truth). Pure data-slicing classes (`WVW_Data`, `WVW_Names`) carry no WP dependency and are unit-tested against committed fixtures. WP-coupled classes (`WVW_Api`, REST route, shortcodes, settings, assets) consume those pure functions and render a server-side snapshot plus a client-side poll.

**Tech Stack:** PHP 7.4+, WordPress 5.8+, PHPUnit 9 (via Composer), vanilla JS, plain CSS.

## Global Constraints

- PHP version floor: **7.4** (no PHP 8-only syntax — no named args, no `match` expression, no enums).
- WordPress version floor: **5.8** (REST + shortcode APIs assumed available).
- No external runtime PHP dependencies — Composer is **dev-only** (PHPUnit). The plugin must run with zero `vendor/` present.
- GW2 API base: `https://api.guildwars2.com/v2/wvw/matches?ids=all` — **no API key**.
- All user-facing strings wrapped in WP i18n (`__()` / `esc_html__()`) with text domain `wvw-tracking`.
- Team color keys are always the three strings `red`, `green`, `blue` (matching the API), in that display order.
- All public-facing output escaped (`esc_html`, `esc_attr`).
- Pure classes (`WVW_Data`, `WVW_Names`) MUST NOT call any WordPress function, so they remain testable without the WP test harness.

---

## File Structure

```
wvw-tracking.php                  Main plugin file: header, constants, requires, hooks bootstrap
includes/
  class-wvw-data.php              PURE: slice score/kills/deaths/vp/ppt/skirmish/objectives, find match, rank, movement, kdr/ppk. No WP.
  class-wvw-names.php             PURE: resolve team id -> friendly name with fallbacks. No WP.
  class-wvw-api.php               WP: fetch + transient cache + stale-on-request + worlds-name fetch
  class-wvw-rest.php              WP: register REST route exposing cached data
  class-wvw-render.php            WP: HTML builders for each widget (consumes WVW_Data/WVW_Names)
  class-wvw-shortcodes.php        WP: register shortcodes, resolve attrs, call render
  class-wvw-settings.php          WP: admin settings page (team map, defaults, interval)
assets/
  wvw.css                         Default styling (option B), class-hookable
  wvw.js                          Client poll of REST endpoint, in-place update
tests/
  bootstrap.php                   Loads the two pure classes only
  fixtures/matches-sample.json    Committed sample API payload (2 matches)
  WvwDataTest.php                 Unit tests for WVW_Data
  WvwNamesTest.php                Unit tests for WVW_Names
composer.json                     Dev-only PHPUnit + autoload for tests
phpunit.xml.dist                  PHPUnit config
```

---

### Task 1: Project tooling + plugin bootstrap

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `wvw-tracking.php`
- Create: `includes/class-wvw-data.php` (empty class shell, filled in Task 2)

**Interfaces:**
- Produces: PHPUnit runnable via `vendor/bin/phpunit`; global constants `WVW_VERSION`, `WVW_PATH`, `WVW_URL`, `WVW_API_URL`, `WVW_CACHE_KEY`, `WVW_DEFAULT_INTERVAL`.

- [ ] **Step 1: Write `composer.json`**

```json
{
  "name": "darkharasho/wvw-tracking-plugin",
  "description": "GW2 WvW matchup shortcodes for WordPress",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": ">=7.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.6"
  },
  "autoload-dev": {
    "classmap": ["includes/class-wvw-data.php", "includes/class-wvw-names.php"]
  }
}
```

- [ ] **Step 2: Write `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="tests/bootstrap.php"
         colors="true">
  <testsuites>
    <testsuite name="unit">
      <directory>tests</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 3: Write `tests/bootstrap.php`**

```php
<?php
// Pure classes only — no WordPress required.
require_once __DIR__ . '/../includes/class-wvw-data.php';
require_once __DIR__ . '/../includes/class-wvw-names.php';
```

- [ ] **Step 4: Write `includes/class-wvw-data.php` shell**

```php
<?php
if (!defined('ABSPATH') && !defined('WVW_TEST')) {
    // Allow standalone test loading; in WP, ABSPATH is defined.
}

class WVW_Data {
    // Methods added in Task 2 and Task 3.
}
```

- [ ] **Step 5: Write `includes/class-wvw-names.php` shell** (so bootstrap loads)

```php
<?php
class WVW_Names {
    // Method added in Task 4.
}
```

- [ ] **Step 6: Write `wvw-tracking.php` (main plugin file)**

```php
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
```

- [ ] **Step 7: Verify tooling runs**

Run: `composer install && vendor/bin/phpunit`
Expected: PHPUnit runs, reports `No tests executed!` (exit 0). No fatal errors loading the two pure classes.

- [ ] **Step 8: Commit**

```bash
git add composer.json phpunit.xml.dist tests/bootstrap.php wvw-tracking.php includes/class-wvw-data.php includes/class-wvw-names.php
git commit -m "chore: scaffold plugin bootstrap and PHPUnit tooling"
```

---

### Task 2: WVW_Data extraction (score, PPT, skirmish, objectives) + fixture

**Files:**
- Create: `tests/fixtures/matches-sample.json`
- Create: `tests/WvwDataTest.php`
- Modify: `includes/class-wvw-data.php`

**Interfaces:**
- Consumes: a decoded match array (one element of the API `?ids=all` array).
- Produces:
  - `WVW_Data::scores(array $match): array` → `['red'=>int,'green'=>int,'blue'=>int]`
  - `WVW_Data::kills(array $match): array` → same shape; API `kills`
  - `WVW_Data::deaths(array $match): array` → same shape; API `deaths`
  - `WVW_Data::victory_points(array $match): array` → same shape; API `victory_points`
  - `WVW_Data::ppt(array $match): array` → same shape; sum of objective `points_tick` per owner
  - `WVW_Data::skirmish(array $match): array` → same shape; the latest skirmish's scores
  - `WVW_Data::objective_counts(array $match): array` → `['red'=>['Camp'=>int,'Tower'=>int,'Keep'=>int,'Castle'=>int],'green'=>[...],'blue'=>[...]]`

- [ ] **Step 1: Write `tests/fixtures/matches-sample.json`**

A trimmed but structurally faithful two-match payload. Match `2-1` (EU T1) and `1-1` (NA T1).

```json
[
  {
    "id": "2-1",
    "worlds": { "red": 2001, "blue": 2002, "green": 2003 },
    "all_worlds": { "red": [2001, 2101], "blue": [2002], "green": [2003] },
    "scores": { "red": 1000, "blue": 2000, "green": 3000 },
    "kills": { "red": 1000, "blue": 2000, "green": 3000 },
    "deaths": { "red": 2000, "blue": 4000, "green": 1000 },
    "victory_points": { "red": 30, "blue": 20, "green": 50 },
    "skirmishes": [
      { "id": 1, "scores": { "red": 50, "blue": 60, "green": 70 } },
      { "id": 2, "scores": { "red": 11, "blue": 22, "green": 33 } }
    ],
    "maps": [
      {
        "id": 38, "type": "Center",
        "objectives": [
          { "id": "38-1", "type": "Camp",   "owner": "Red",   "points_tick": 2 },
          { "id": "38-2", "type": "Tower",  "owner": "Blue",  "points_tick": 4 },
          { "id": "38-3", "type": "Keep",   "owner": "Green", "points_tick": 8 },
          { "id": "38-4", "type": "Castle", "owner": "Red",   "points_tick": 12 },
          { "id": "38-5", "type": "Camp",   "owner": "Neutral","points_tick": 0 }
        ]
      },
      {
        "id": 95, "type": "RedHome",
        "objectives": [
          { "id": "95-1", "type": "Tower", "owner": "Red", "points_tick": 4 },
          { "id": "95-2", "type": "Camp",  "owner": "Red", "points_tick": 2 }
        ]
      }
    ]
  },
  {
    "id": "1-1",
    "worlds": { "red": 1001, "blue": 1002, "green": 1003 },
    "all_worlds": { "red": [1001], "blue": [1002], "green": [1003] },
    "scores": { "red": 9, "blue": 8, "green": 7 },
    "kills": { "red": 5, "blue": 6, "green": 7 },
    "deaths": { "red": 7, "blue": 6, "green": 5 },
    "victory_points": { "red": 21, "blue": 32, "green": 43 },
    "skirmishes": [ { "id": 1, "scores": { "red": 1, "blue": 2, "green": 3 } } ],
    "maps": [
      { "id": 38, "type": "Center", "objectives": [
        { "id": "38-1", "type": "Keep", "owner": "Blue", "points_tick": 8 }
      ] }
    ]
  }
]
```

- [ ] **Step 2: Write the failing tests `tests/WvwDataTest.php`**

```php
<?php
use PHPUnit\Framework\TestCase;

final class WvwDataTest extends TestCase {
    private function match($id) {
        $all = json_decode(file_get_contents(__DIR__ . '/fixtures/matches-sample.json'), true);
        foreach ($all as $m) { if ($m['id'] === $id) return $m; }
        $this->fail("fixture match $id not found");
    }

    public function test_scores() {
        $this->assertSame(
            ['red' => 1000, 'green' => 3000, 'blue' => 2000],
            WVW_Data::scores($this->match('2-1'))
        );
    }

    public function test_kills() {
        $this->assertSame(
            ['red' => 1000, 'green' => 3000, 'blue' => 2000],
            WVW_Data::kills($this->match('2-1'))
        );
    }

    public function test_deaths() {
        $this->assertSame(
            ['red' => 2000, 'green' => 1000, 'blue' => 4000],
            WVW_Data::deaths($this->match('2-1'))
        );
    }

    public function test_victory_points() {
        $this->assertSame(
            ['red' => 30, 'green' => 50, 'blue' => 20],
            WVW_Data::victory_points($this->match('2-1'))
        );
    }

    public function test_skirmish_returns_latest() {
        $this->assertSame(
            ['red' => 11, 'green' => 33, 'blue' => 22],
            WVW_Data::skirmish($this->match('2-1'))
        );
    }

    public function test_ppt_sums_points_tick_by_owner() {
        // Red: Camp2 + Castle12 + Tower4 + Camp2 = 20; Blue: Tower4; Green: Keep8
        $this->assertSame(
            ['red' => 20, 'green' => 8, 'blue' => 4],
            WVW_Data::ppt($this->match('2-1'))
        );
    }

    public function test_objective_counts() {
        $counts = WVW_Data::objective_counts($this->match('2-1'));
        $this->assertSame(['Camp' => 2, 'Tower' => 1, 'Keep' => 0, 'Castle' => 1], $counts['red']);
        $this->assertSame(['Camp' => 0, 'Tower' => 1, 'Keep' => 0, 'Castle' => 0], $counts['blue']);
        $this->assertSame(['Camp' => 0, 'Tower' => 0, 'Keep' => 1, 'Castle' => 0], $counts['green']);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter WvwDataTest`
Expected: FAIL — `Call to undefined method WVW_Data::scores()` (and the others).

- [ ] **Step 4: Implement the four methods in `includes/class-wvw-data.php`**

```php
<?php
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
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter WvwDataTest`
Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
git add tests/fixtures/matches-sample.json tests/WvwDataTest.php includes/class-wvw-data.php
git commit -m "feat: WVW_Data score/kills/deaths/vp/ppt/skirmish/objective extraction"
```

---

### Task 3: WVW_Data match selection, ranking, movement, and ratios

**Files:**
- Modify: `includes/class-wvw-data.php`
- Modify: `tests/WvwDataTest.php`

**Interfaces:**
- Consumes: the full decoded matches array (all elements) and single matches.
- Produces:
  - `WVW_Data::find_match(array $matches, array $args): ?array`
    - `$args['match']` (string id) takes precedence — exact id match.
    - else `$args['team']` (int|string world id) — first match whose `all_worlds`
      (any color) contains that id.
    - returns `null` if nothing matches.
  - `WVW_Data::tier_number(array $match): int` — integer after the `-` in the id
    (`2-1` → 1); `0` if unparseable.
  - `WVW_Data::rank(array $match): array` — colors ordered by victory points
    descending, e.g. `['green','red','blue']`; ties broken by war score desc then
    fixed order red, green, blue (deterministic).
  - `WVW_Data::movement(array $match, bool $isTopTier, bool $isBottomTier): array`
    — `['red'=>'up'|'stays'|'down', ...]`; 1st→up (stays if top tier), 2nd→stays,
    3rd→down (stays if bottom tier).
  - `WVW_Data::kdr(int $kills, int $deaths): float` — `kills/deaths` rounded to 2;
    deaths 0 → returns `(float) $kills`.
  - `WVW_Data::ppk(int $kills, int $deaths): float` — `kills/(kills+deaths)*100`
    rounded to 2; both 0 → `0.0`. (Best-effort PPK; isolated for easy correction.)

- [ ] **Step 1: Write failing tests (append to `tests/WvwDataTest.php`)**

```php
    private function all() {
        return json_decode(file_get_contents(__DIR__ . '/fixtures/matches-sample.json'), true);
    }

    public function test_find_match_by_id() {
        $m = WVW_Data::find_match($this->all(), ['match' => '1-1']);
        $this->assertSame('1-1', $m['id']);
    }

    public function test_find_match_by_team_follows_all_worlds() {
        // 2101 is a linked world inside match 2-1's red all_worlds.
        $m = WVW_Data::find_match($this->all(), ['team' => 2101]);
        $this->assertSame('2-1', $m['id']);
    }

    public function test_find_match_id_wins_over_team() {
        $m = WVW_Data::find_match($this->all(), ['match' => '1-1', 'team' => 2101]);
        $this->assertSame('1-1', $m['id']);
    }

    public function test_find_match_returns_null_when_absent() {
        $this->assertNull(WVW_Data::find_match($this->all(), ['team' => 999999]));
    }

    public function test_tier_number() {
        $this->assertSame(1, WVW_Data::tier_number($this->match('2-1')));
    }

    public function test_rank_orders_by_victory_points_desc() {
        // VP: green 50, red 30, blue 20
        $this->assertSame(['green', 'red', 'blue'], WVW_Data::rank($this->match('2-1')));
    }

    public function test_movement_top_tier() {
        // top tier (not bottom): 1st green stays, 2nd red stays, 3rd blue down
        $this->assertSame(
            ['red' => 'stays', 'green' => 'stays', 'blue' => 'down'],
            WVW_Data::movement($this->match('2-1'), true, false)
        );
    }

    public function test_movement_middle_tier() {
        // not top, not bottom: 1st green up, 2nd red stays, 3rd blue down
        $this->assertSame(
            ['red' => 'stays', 'green' => 'up', 'blue' => 'down'],
            WVW_Data::movement($this->match('2-1'), false, false)
        );
    }

    public function test_kdr() {
        $this->assertSame(0.5, WVW_Data::kdr(1000, 2000));
        $this->assertSame(3.0, WVW_Data::kdr(3000, 1000));
        $this->assertSame(5.0, WVW_Data::kdr(5, 0)); // deaths 0 guard
    }

    public function test_ppk() {
        $this->assertSame(33.33, WVW_Data::ppk(1000, 2000));
        $this->assertSame(75.0, WVW_Data::ppk(3000, 1000));
        $this->assertSame(0.0, WVW_Data::ppk(0, 0)); // both zero guard
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter WvwDataTest`
Expected: FAIL — `Call to undefined method WVW_Data::find_match()`.

- [ ] **Step 3: Implement `find_match` in `includes/class-wvw-data.php`** (add inside the class)

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter WvwDataTest`
Expected: PASS (17 tests total).

- [ ] **Step 5: Commit**

```bash
git add includes/class-wvw-data.php tests/WvwDataTest.php
git commit -m "feat: WVW_Data find_match, tier/rank/movement, and kdr/ppk ratios"
```

---

### Task 4: WVW_Names resolver (friendly map + raw fallback)

**Files:**
- Modify: `includes/class-wvw-names.php`
- Create: `tests/WvwNamesTest.php`

**Interfaces:**
- Produces: `WVW_Names::resolve($id, array $friendly, array $raw): string`
  - `$friendly` and `$raw` are `[ id => name ]` maps (ids may be int or string keys).
  - precedence: friendly → raw → `"Team {id}"`. Never returns empty string.

- [ ] **Step 1: Write failing tests `tests/WvwNamesTest.php`**

```php
<?php
use PHPUnit\Framework\TestCase;

final class WvwNamesTest extends TestCase {
    public function test_friendly_wins() {
        $this->assertSame('Our Guild', WVW_Names::resolve(2001, [2001 => 'Our Guild'], [2001 => 'Raw Name']));
    }
    public function test_falls_back_to_raw() {
        $this->assertSame('Raw Name', WVW_Names::resolve(2001, [], [2001 => 'Raw Name']));
    }
    public function test_falls_back_to_generic_when_unknown() {
        $this->assertSame('Team 2001', WVW_Names::resolve(2001, [], []));
    }
    public function test_empty_friendly_value_is_ignored() {
        $this->assertSame('Raw Name', WVW_Names::resolve(2001, [2001 => ''], [2001 => 'Raw Name']));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter WvwNamesTest`
Expected: FAIL — `Call to undefined method WVW_Names::resolve()`.

- [ ] **Step 3: Implement `resolve` in `includes/class-wvw-names.php`**

```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter WvwNamesTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS (21 tests total).

- [ ] **Step 6: Commit**

```bash
git add includes/class-wvw-names.php tests/WvwNamesTest.php
git commit -m "feat: WVW_Names::resolve friendly/raw/generic fallback"
```

---

### Task 5: WVW_Api — fetch, transient cache, stale-on-request, cron, worlds names

**Files:**
- Create: `includes/class-wvw-api.php`
- Modify: `wvw-tracking.php`

**Interfaces:**
- Consumes: `WVW_CACHE_KEY`, `WVW_API_URL`, `WVW_WORLDS_URL`, `WVW_WORLDS_KEY`, settings interval.
- Produces:
  - `WVW_Api::get_matches(): array` — decoded matches array, refreshing if stale/empty; `[]` on hard failure.
  - `WVW_Api::get_world_names(): array` — `[ id => name ]` from `/v2/worlds`, cached ~1 day.
  - `WVW_Api::refresh(): void` — force-fetch and store (used by cron).
  - `WVW_Api::interval(): int` — configured refresh seconds (settings or `WVW_DEFAULT_INTERVAL`).
  - Registers WP-Cron event `wvw_refresh_event` on a custom `wvw_interval` schedule.

This task is WordPress-coupled; verification is manual (no WP unit harness).

- [ ] **Step 1: Write `includes/class-wvw-api.php`**

```php
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
        set_transient(WVW_WORLDS_KEY, $map, DAY_IN_SECONDS);
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
```

- [ ] **Step 2: Wire it into `wvw-tracking.php`** (append after the existing `require_once` lines)

```php
require_once WVW_PATH . 'includes/class-wvw-api.php';

add_filter('cron_schedules', ['WVW_Api', 'add_schedule']);
add_action(WVW_Api::CRON_HOOK, ['WVW_Api', 'refresh']);
register_activation_hook(__FILE__, ['WVW_Api', 'activate']);
register_deactivation_hook(__FILE__, ['WVW_Api', 'deactivate']);
```

- [ ] **Step 3: Lint the new PHP**

Run: `php -l includes/class-wvw-api.php && php -l wvw-tracking.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Manual verification (record result in commit/PR notes)**

In a WordPress install with the plugin active, add this to a scratch page template or use WP-CLI:
Run: `wp eval 'var_dump(count(WVW_Api::get_matches()));'`
Expected: prints an int > 0 (live matches fetched and cached). Re-running within the interval should not re-hit the API (transient hit).

- [ ] **Step 5: Commit**

```bash
git add includes/class-wvw-api.php wvw-tracking.php
git commit -m "feat: WVW_Api transient cache, stale-on-request, cron, worlds names"
```

---

### Task 6: REST endpoint exposing cached data

**Files:**
- Create: `includes/class-wvw-rest.php`
- Modify: `wvw-tracking.php`

**Interfaces:**
- Consumes: `WVW_Api::get_matches()`, `WVW_Data`, `WVW_Names`, settings.
- Produces:
  - `WVW_Rest::build_match_payload(array $match): array` — per-match object
    `{ id, tier, names, scores, kills, deaths, kdr, ppk, vp, ppt, skirmish,
    objectives, rank, move }` (each stat keyed `red/green/blue`; `rank` is the
    `[1st,2nd,3rd]` color order; `move` empty here, filled per region).
  - `WVW_Rest::build_region_payloads(string $prefix): array` — payloads for every
    tier in a region, tier-sorted, with `move` set from rank + tier bounds.
  - GET route `wvw/v1/match` (query `team`, `match`) → one payload.
  - GET route `wvw/v1/region` (query `region`) → array of payloads. Public (no auth).

- [ ] **Step 1: Write `includes/class-wvw-rest.php`**

```php
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
```

- [ ] **Step 2: Wire into `wvw-tracking.php`**

```php
require_once WVW_PATH . 'includes/class-wvw-rest.php';
add_action('rest_api_init', ['WVW_Rest', 'register']);
```

- [ ] **Step 3: Lint**

Run: `php -l includes/class-wvw-rest.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual verification**

In a live WP install:
Run: `curl -s "http://localhost/wp-json/wvw/v1/region?region=na" | head -c 400`
Expected: JSON array of match payloads with `names`, `scores`, `ppt`, `skirmish`, `objectives`.
Run: `curl -s "http://localhost/wp-json/wvw/v1/match?match=1-1"`
Expected: single match payload object (or `{"error":"match_not_found"}` if that tier id isn't live — try a current id).

- [ ] **Step 5: Commit**

```bash
git add includes/class-wvw-rest.php wvw-tracking.php
git commit -m "feat: REST routes for single match and region ladder"
```

---

### Task 7: Render helpers, shortcodes, and default CSS

**Files:**
- Create: `includes/class-wvw-render.php`
- Create: `includes/class-wvw-shortcodes.php`
- Create: `assets/wvw.css`
- Modify: `wvw-tracking.php`

**Interfaces:**
- Consumes: `WVW_Rest::build_match_payload()`, `WVW_Rest::build_region_payloads()`, `WVW_Api::get_matches()`, `WVW_Data::find_match()`, settings.
- Produces:
  - `WVW_Render::widget($type, $payload): string` — HTML for `score|ppt|skirmish`.
  - `WVW_Render::kills(array $payload): string` — kills/deaths/KDR table.
  - `WVW_Render::objectives(array $payload): string` — camps/towers/keeps/SMC table.
  - `WVW_Render::standings(array $payloads): string` — full wvw.gg-style ladder
    (Tier · Rank+Team · Skirmish · Kills · Deaths · KDR · PPK · VP · Move).
  - Shortcodes `[wvw_score]`, `[wvw_ppt]`, `[wvw_skirmish]`, `[wvw_kills]`, `[wvw_objectives]`, `[wvw_standings]`.
  - Each match shortcode wraps output in a container with `data-wvw-*` attributes for the JS poller.

- [ ] **Step 1: Write `includes/class-wvw-render.php`**

```php
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
        $values = isset($p[$type]) ? $p[$type] : [];
        $rows = '';
        foreach (self::order() as $c) {
            $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
            $val  = isset($values[$c]) ? (int) $values[$c] : 0;
            $rows .= '<div class="wvw-team wvw-' . esc_attr($c) . '">'
                . '<span class="wvw-name">' . esc_html($name) . '</span>'
                . '<span class="wvw-value" data-team="' . esc_attr($c) . '">' . esc_html(number_format_i18n($val)) . '</span>'
                . '</div>';
        }
        $label = self::label($type);
        return '<div class="wvw-widget wvw-' . esc_attr($type) . '">'
            . '<div class="wvw-widget-label">' . esc_html($label) . '</div>'
            . $rows . '</div>';
    }

    private static function label($type) {
        switch ($type) {
            case 'score':    return __('War Score', 'wvw-tracking');
            case 'ppt':      return __('Points per Tick', 'wvw-tracking');
            case 'skirmish': return __('Skirmish', 'wvw-tracking');
            default:         return ucfirst($type);
        }
    }

    /** Objectives: per team, counts of each structure type. */
    public static function objectives(array $p) {
        $obj = isset($p['objectives']) ? $p['objectives'] : [];
        $types = ['Camp', 'Tower', 'Keep', 'Castle'];
        $rows = '';
        foreach (self::order() as $c) {
            $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
            $cells = '';
            foreach ($types as $t) {
                $n = isset($obj[$c][$t]) ? (int) $obj[$c][$t] : 0;
                $cells .= '<td class="wvw-obj-' . esc_attr(strtolower($t)) . '" data-team="' . esc_attr($c) . '" data-type="' . esc_attr($t) . '">' . esc_html($n) . '</td>';
            }
            $rows .= '<tr class="wvw-' . esc_attr($c) . '"><th>' . esc_html($name) . '</th>' . $cells . '</tr>';
        }
        $head = '<tr><th></th><th>' . esc_html__('Camps', 'wvw-tracking') . '</th><th>'
            . esc_html__('Towers', 'wvw-tracking') . '</th><th>'
            . esc_html__('Keeps', 'wvw-tracking') . '</th><th>'
            . esc_html__('SMC', 'wvw-tracking') . '</th></tr>';
        return '<div class="wvw-widget wvw-objectives"><table>'
            . '<thead>' . $head . '</thead><tbody>' . $rows . '</tbody></table></div>';
    }

    /** Kills / deaths / KDR per team. */
    public static function kills(array $p) {
        $rows = '';
        foreach (self::order() as $c) {
            $name = isset($p['names'][$c]) ? $p['names'][$c] : ucfirst($c);
            $k = isset($p['kills'][$c]) ? (int) $p['kills'][$c] : 0;
            $d = isset($p['deaths'][$c]) ? (int) $p['deaths'][$c] : 0;
            $kdr = isset($p['kdr'][$c]) ? $p['kdr'][$c] : 0;
            $rows .= '<tr class="wvw-' . esc_attr($c) . '">'
                . '<th>' . esc_html($name) . '</th>'
                . '<td data-team="' . esc_attr($c) . '" data-field="kills">' . esc_html(number_format_i18n($k)) . '</td>'
                . '<td data-team="' . esc_attr($c) . '" data-field="deaths">' . esc_html(number_format_i18n($d)) . '</td>'
                . '<td data-team="' . esc_attr($c) . '" data-field="kdr">' . esc_html(number_format_i18n($kdr, 2)) . '</td>'
                . '</tr>';
        }
        $head = '<tr><th></th><th>' . esc_html__('Kills', 'wvw-tracking') . '</th><th>'
            . esc_html__('Deaths', 'wvw-tracking') . '</th><th>'
            . esc_html__('KDR', 'wvw-tracking') . '</th></tr>';
        return '<div class="wvw-widget wvw-kills"><table><thead>' . $head
            . '</thead><tbody>' . $rows . '</tbody></table></div>';
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
```

- [ ] **Step 2: Write `includes/class-wvw-shortcodes.php`**

```php
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

    private static function resolve_payload($atts) {
        $a = shortcode_atts(['team' => '', 'match' => ''], $atts);
        if ($a['team'] === '' && $a['match'] === '') {
            $settings = get_option('wvw_settings', []);
            $a['team'] = isset($settings['default_team']) ? $settings['default_team'] : '';
        }
        $matches = WVW_Api::get_matches();
        $m = WVW_Data::find_match($matches, ['match' => $a['match'], 'team' => $a['team']]);
        if (!$m) {
            return null;
        }
        return WVW_Rest::build_match_payload($m);
    }

    private static function wrap($html, $type, $atts) {
        $a = shortcode_atts(['team' => '', 'match' => ''], $atts);
        return '<div class="wvw-container" data-wvw-type="' . esc_attr($type) . '"'
            . ' data-wvw-team="' . esc_attr($a['team']) . '"'
            . ' data-wvw-match="' . esc_attr($a['match']) . '">' . $html . '</div>';
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
```

- [ ] **Step 3: Write `assets/wvw.css`** (option B default look; class-overridable)

```css
.wvw-widget { font-family: inherit; margin: 1em 0; border: 1px solid #e2e2e2; border-radius: 8px; overflow: hidden; }
.wvw-widget-label { font-weight: 600; padding: .5em .75em; background: #f6f6f6; border-bottom: 1px solid #e2e2e2; }
.wvw-team { display: flex; justify-content: space-between; padding: .5em .75em; border-bottom: 1px solid #f0f0f0; }
.wvw-team:last-child { border-bottom: 0; }
.wvw-team.wvw-red   { border-left: 4px solid #c0392b; }
.wvw-team.wvw-green { border-left: 4px solid #27ae60; }
.wvw-team.wvw-blue  { border-left: 4px solid #2980b9; }
.wvw-value { font-variant-numeric: tabular-nums; font-weight: 600; }
.wvw-widget table { width: 100%; border-collapse: collapse; }
.wvw-widget th, .wvw-widget td { padding: .4em .6em; text-align: left; border-bottom: 1px solid #f0f0f0; }
.wvw-unavailable { padding: .75em; color: #777; font-style: italic; }

/* Region ladder — wvw.gg-style dark table */
.wvw-standings { border: 0; background: #0e1726; color: #d8dee9; border-radius: 10px; }
.wvw-standings table { font-variant-numeric: tabular-nums; }
.wvw-standings thead th { font-size: .72em; letter-spacing: .04em; text-transform: uppercase;
  color: #8a94a6; border-bottom: 1px solid #233048; }
.wvw-standings td, .wvw-standings th { border-bottom: 1px solid #1a2740; text-align: right; }
.wvw-standings th:first-child, .wvw-standings .wvw-team-cell { text-align: left; }
.wvw-standings .wvw-tier { font-size: 1.1em; font-weight: 700; text-align: center; color: #c9d3e3;
  border-right: 1px solid #233048; vertical-align: middle; }
.wvw-standings .wvw-name { font-weight: 600; }
.wvw-standings .wvw-red   .wvw-name { color: #e06a5b; }
.wvw-standings .wvw-green .wvw-name { color: #54c98a; }
.wvw-standings .wvw-blue  .wvw-name { color: #5aa6e0; }
/* Rank badge tinted by team side color */
.wvw-rank { display: inline-block; min-width: 2.4em; text-align: center; padding: .1em .4em;
  margin-right: .5em; border-radius: 4px; font-size: .72em; font-weight: 700; color: #fff; }
.wvw-rank.wvw-red   { background: #c0392b; }
.wvw-rank.wvw-green { background: #27ae60; }
.wvw-rank.wvw-blue  { background: #2980b9; }
/* Move indicators */
.wvw-move-up    { color: #54c98a; font-weight: 600; }
.wvw-move-down  { color: #e06a5b; font-weight: 600; }
.wvw-move-stays { color: #8a94a6; }
```

- [ ] **Step 4: Wire into `wvw-tracking.php`**

```php
require_once WVW_PATH . 'includes/class-wvw-render.php';
require_once WVW_PATH . 'includes/class-wvw-shortcodes.php';
add_action('init', ['WVW_Shortcodes', 'register']);
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('wvw-tracking', WVW_URL . 'assets/wvw.css', [], WVW_VERSION);
});
```

- [ ] **Step 5: Lint**

Run: `php -l includes/class-wvw-render.php && php -l includes/class-wvw-shortcodes.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Manual verification**

On a WP page, add `[wvw_score]`, `[wvw_kills]`, `[wvw_standings region="na"]`, `[wvw_objectives match="1-1"]`.
Expected: score widget renders three colored team rows with names + numbers; kills widget shows Kills/Deaths/KDR; the standings ladder shows, per tier, three teams ranked 1st/2nd/3rd with Skirmish · Kills · Deaths · KDR · PPK% · VP · Move (▲ Moves Up / — Stays / ▼ Moves Down), the bottom tier never showing "down" and the top tier never "up". Objectives shows a Camps/Towers/Keeps/SMC table. Removing the API (e.g. invalid URL) shows the "temporarily unavailable" message, not a fatal error.

- [ ] **Step 7: Commit**

```bash
git add includes/class-wvw-render.php includes/class-wvw-shortcodes.php assets/wvw.css wvw-tracking.php
git commit -m "feat: render helpers, shortcodes, and default styling"
```

---

### Task 8: Admin settings page

**Files:**
- Create: `includes/class-wvw-settings.php`
- Modify: `wvw-tracking.php`

**Interfaces:**
- Produces: a settings page under **Settings → WvW Tracking** writing option
  `wvw_settings` with keys: `default_team` (string), `default_region` (`na|eu`),
  `interval` (int seconds, min 60), `team_names` (`[ id => name ]`).
- Consumes: nothing from earlier tasks; earlier tasks already read `wvw_settings`.

- [ ] **Step 1: Write `includes/class-wvw-settings.php`**

```php
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
```

- [ ] **Step 2: Wire into `wvw-tracking.php`**

```php
require_once WVW_PATH . 'includes/class-wvw-settings.php';
add_action('admin_menu', ['WVW_Settings', 'menu']);
add_action('admin_init', ['WVW_Settings', 'register']);
```

- [ ] **Step 3: Lint**

Run: `php -l includes/class-wvw-settings.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual verification**

In WP admin → Settings → WvW Tracking: set a default team, region, interval, and a couple of team-name rows; save. Reload — values persist. A `[wvw_score]` with no attributes now resolves to the default team's match.

- [ ] **Step 5: Commit**

```bash
git add includes/class-wvw-settings.php wvw-tracking.php
git commit -m "feat: admin settings page for defaults and team-name map"
```

---

### Task 9: Client-side live poll

**Files:**
- Create: `assets/wvw.js`
- Modify: `wvw-tracking.php`

**Interfaces:**
- Consumes: REST routes from Task 6; `data-wvw-*` attributes from Task 7.
- Produces: in-place updates of `.wvw-value`, kills (`data-field`), and objective
  (`data-type`) cells for match-scoped widgets on the configured interval, no page
  reload. Localized config via `wvwConfig` (`{ root, interval }`).
- Scope note: the **region ladder does not live-update via JS** in v1 (rank/move
  reordering client-side is out of scope) — it stays current via the server-side
  snapshot on each page load plus the stale-on-request cache refresh.

- [ ] **Step 1: Write `assets/wvw.js`**

```js
(function () {
  var cfg = window.wvwConfig || {};
  var root = (cfg.root || '/wp-json/').replace(/\/$/, '/');
  var interval = (cfg.interval || 300) * 1000;

  function url(path, params) {
    var q = Object.keys(params)
      .filter(function (k) { return params[k] !== '' && params[k] != null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
      .join('&');
    return root + 'wvw/v1/' + path + (q ? '?' + q : '');
  }

  function updateValues(el, payload) {
    // score / ppt / skirmish: .wvw-value[data-team], keyed by container type
    el.querySelectorAll('.wvw-value[data-team]').forEach(function (node) {
      var team = node.getAttribute('data-team');
      var type = el.getAttribute('data-wvw-type');
      var map = payload[type];
      if (map && map[team] != null) { node.textContent = Number(map[team]).toLocaleString(); }
    });
    // kills widget: td[data-team][data-field] where field is a payload key (kills/deaths/kdr)
    el.querySelectorAll('td[data-team][data-field]').forEach(function (node) {
      var team = node.getAttribute('data-team');
      var field = node.getAttribute('data-field');
      if (payload[field] && payload[field][team] != null) {
        node.textContent = Number(payload[field][team]).toLocaleString();
      }
    });
    // objectives: td[data-team][data-type] where type is a structure type
    el.querySelectorAll('td[data-team][data-type]').forEach(function (node) {
      var team = node.getAttribute('data-team');
      var type = node.getAttribute('data-type');
      if (payload.objectives && payload.objectives[team]) {
        node.textContent = payload.objectives[team][type];
      }
    });
  }

  function refreshMatch(el) {
    var params = { team: el.getAttribute('data-wvw-team'), match: el.getAttribute('data-wvw-match') };
    fetch(url('match', params))
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (p) { if (p && !p.error) { updateValues(el, p); } })
      .catch(function () {});
  }

  function tick() {
    document.querySelectorAll('.wvw-container[data-wvw-type]').forEach(function (el) {
      if (el.getAttribute('data-wvw-type') !== 'standings') { refreshMatch(el); }
    });
  }

  if (document.querySelector('.wvw-container')) {
    setInterval(tick, interval);
  }
})();
```

- [ ] **Step 2: Wire enqueue + localize into `wvw-tracking.php`** (extend the existing `wp_enqueue_scripts` callback)

```php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('wvw-tracking', WVW_URL . 'assets/wvw.js', [], WVW_VERSION, true);
    wp_localize_script('wvw-tracking', 'wvwConfig', [
        'root'     => esc_url_raw(rest_url()),
        'interval' => WVW_Api::interval(),
    ]);
});
```

- [ ] **Step 3: Lint PHP**

Run: `php -l wvw-tracking.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Manual verification**

Load a page with `[wvw_score]`. Open browser dev tools → Network: confirm a request to `wp-json/wvw/v1/match` fires on the interval and that `.wvw-value` numbers update if scores changed. No console errors.

- [ ] **Step 5: Commit**

```bash
git add assets/wvw.js wvw-tracking.php
git commit -m "feat: client-side live poll of REST endpoint"
```

---

### Task 10: README usage docs + final full-suite check

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Write `README.md`** with install + shortcode reference

```markdown
# WvW Tracking

WordPress plugin: GW2 World vs World matchup shortcodes.

## Install
1. Copy this folder into `wp-content/plugins/`.
2. Activate **WvW Tracking** in Plugins.
3. Go to **Settings → WvW Tracking**: set your default team (world id),
   default region, refresh interval, and any friendly team-name mappings.

## Shortcodes
- `[wvw_score]` — three-way war score
- `[wvw_ppt]` — points per tick
- `[wvw_skirmish]` — current skirmish scores
- `[wvw_kills]` — kills, deaths, and KDR
- `[wvw_objectives]` — camps/towers/keeps/SMC counts
- `[wvw_standings region="na|eu"]` — full wvw.gg-style region ladder
  (Tier · Rank+Team · Skirmish · Kills · Deaths · KDR · PPK · VP · Move)

Note: `PPK` is computed as kill-efficiency `kills / (kills + deaths)` — the GW2
API exposes no PPK field; adjust `WVW_Data::ppk()` if you want a different formula.

Match-scoped shortcodes accept `team="2001"` (auto-follows up/down tiers),
`match="2-1"` (fixed tier), or fall back to the default team in settings.

## Development
- `composer install`
- `vendor/bin/phpunit` — runs the pure-logic unit tests (no WordPress needed).
```

- [ ] **Step 2: Run the full test suite once more**

Run: `vendor/bin/phpunit`
Expected: PASS (21 tests).

- [ ] **Step 3: Lint every PHP file**

Run: `for f in wvw-tracking.php includes/*.php; do php -l "$f"; done`
Expected: `No syntax errors detected` for each.

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: README install and shortcode reference"
```

---

## Self-Review Notes

- **Spec coverage:** Architecture (Task 5 cache/cron/stale + Task 6 REST + Task 7 server snapshot + Task 9 poll); 6 shortcodes incl. `[wvw_kills]` (Task 7); wvw.gg-style ladder with Skirmish/Kills/Deaths/KDR/PPK/VP/Move (Task 6 payload + region assembly, Task 7 render/CSS); VP ranking + rank-movement with tier bounds (Task 3 + Task 6); match selection precedence match→team→default (Tasks 3, 6, 7); admin page with team map/default team/region/interval (Task 8); option-B styling incl. dark ladder (Task 7 CSS); team naming with fallback (Task 4 + Task 5 worlds fetch); error handling unavailable/stale-survives-outage (Tasks 5, 7); PHPUnit fixture tests for all slicing + kills/deaths/vp + kdr/ppk + rank/movement + auto-follow + naming (Tasks 2–4). All spec sections mapped.
- **Placeholder scan:** No TBD/TODO; every code step contains complete code.
- **Type consistency:** Triple shape `['red','green','blue']` used uniformly; `build_match_payload` keys (`id,tier,names,scores,kills,deaths,kdr,ppk,vp,ppt,skirmish,objectives,rank,move`) consumed identically by REST, render, and JS; `find_match` signature `(array,$args['match'|'team'])` consistent across Tasks 3/6/7; `move` values `up|stays|down` produced by `WVW_Data::movement` and consumed by `WVW_Render::move_label`.
- **Notes:**
  - PPT is derived by summing objective `points_tick` (API has no direct PPT field) — documented in Task 2.
  - PPK is a best-effort kill-efficiency derivation isolated in `WVW_Data::ppk()` (API has no PPK field) — documented in spec, Task 3, and README so the formula is easy to correct.
  - Region ladder does not live-update via JS in v1 (rank/move reordering client-side is out of scope) — refreshed by server snapshot + stale-on-request cache (Task 9 scope note).
