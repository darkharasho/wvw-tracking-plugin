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

## Team names (World Restructuring)

Each side is named by its **World Restructuring team id** (the `>= 11000` entry
in the API's `all_worlds`), not the legacy world id. The **NA** teams
(`11001`–`11012`, e.g. *Rall's Rest*, *Yohlon Haven*) are built in — see
`WVW_Names::wr_defaults()`. ArenaNet publishes no name for these team ids, so:

- To rename a team or add **EU** teams (`12xxx`), use the team-name map under
  **Settings → WvW Tracking** (keyed by team id) — it overrides the built-ins.
- Anything still unmapped falls back to the legacy `/v2/worlds` name, then to
  `Team {id}`, so nothing renders blank.

## Development
- `composer install`
- `vendor/bin/phpunit` — runs the pure-logic unit tests (no WordPress needed).
