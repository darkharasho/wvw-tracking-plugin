# GW2 WvW Tracking — WordPress Plugin Design

**Date:** 2026-06-26
**Status:** Approved (design phase)

## Purpose

A WordPress plugin that lets the site owner drop shortcodes into pages and
posts to display Guild Wars 2 World vs. World (WvW) matchup data — scores,
points-per-tick, skirmish standings, and objectives held. Inspired by
wvw.gg/matches and gw2mists, but deliberately "basics only": a clean,
low-maintenance widget rather than a full analytics site.

## Goals

- Drop-in shortcodes that render immediately and never show a blank page.
- Live-ish numbers that update without a manual page reload.
- "Set it and forget it" — follow a team up and down the WvW ladder
  automatically, so pages don't need editing every relink.
- Maintainable team naming via the WordPress admin, not code edits.

## Non-Goals (v1)

- Kills/deaths (K/D) display — data exists but is deferred; presenting it well
  is more effort than it's worth for a basics widget.
- Detailed per-objective maps/metadata beyond simple counts.
- Historical data, charts, or trends.
- Multi-site / multi-language concerns beyond standard WP i18n hooks.

## Architecture

Hybrid server-fetch + client-poll:

1. **Fetch.** PHP calls the official public GW2 API:
   `https://api.guildwars2.com/v2/wvw/matches?ids=all`
   This returns every WvW match for both regions (NA + EU) in one response.
   No API key is required for WvW match data.
2. **Cache.** The whole response is stored in a WordPress transient (single
   blob). Every shortcode slices what it needs from this blob — no shortcode
   makes its own API call.
3. **Refresh.** WP-Cron refreshes the transient on a ~5-minute schedule.
   Because WP-Cron is traffic-dependent, **the transient's age is the source of
   truth**: if a request finds the cache stale (older than the refresh
   interval), it refreshes synchronously then. A quiet day never serves
   yesterday's scores.
4. **REST endpoint.** One small registered REST route exposes the cached blob
   (or the slices needed) as JSON.
5. **Server-side snapshot.** Each shortcode renders the current data on the
   server at page-load, so the page is never blank.
6. **Client poll.** A lightweight JS script re-polls the REST endpoint on the
   refresh cadence and updates the rendered numbers in place — no full reload.

### Data flow

```
GW2 API ──(WP-Cron / stale-on-request)──> transient (blob)
                                              │
                          ┌───────────────────┼───────────────────┐
                          ▼                                        ▼
                  shortcode render (PHP)                    REST endpoint (JSON)
                  = server-side snapshot                          │
                          │                                       ▼
                          └────────────── page ──────────── client JS poll
                                                            (updates in place)
```

## Shortcodes

Four **match-scoped** shortcodes plus one **region** shortcode.

| Shortcode | Renders |
|---|---|
| `[wvw_score]` | Three-way running war-score total per team |
| `[wvw_ppt]` | Current points-per-tick per team |
| `[wvw_skirmish]` | Current skirmish standings |
| `[wvw_objectives]` | Counts of camps / towers / keeps / SMC per team |
| `[wvw_standings region="na"]` | Full region ladder, all tiers (wvw.gg-style table) |

### Match selection (the four match-scoped shortcodes)

Each accepts attributes, resolved in this precedence:

1. `match="2-1"` — a fixed match ID (e.g. EU tier 1). Always shows that tier,
   whoever is in it.
2. `team="2202"` — auto-follow. The plugin scans the cached blob for whichever
   match currently contains that team, following the server up/down the ladder.
3. **No attribute** — falls back to the **default team** configured on the
   admin settings page, so a bare `[wvw_score]` just works.

If `match` is supplied it wins; otherwise `team`; otherwise the default team.

### Region shortcode

`[wvw_standings region="na|eu"]` renders all tiers for one region as a table.
Defaults to the **default region** from settings if `region` is omitted.

## Admin Settings Page

A settings screen under the WordPress dashboard providing:

- **Team-name mappings** — editable list mapping raw GW2 team IDs to friendly
  display names. World Restructuring reshuffles teams each relink, so this is a
  simple editable list, not anything clever. **Unknown IDs fall back to the raw
  API name** so nothing ever renders blank.
- **Default team** — used when a match-scoped shortcode is called with no
  `team`/`match` attribute.
- **Default region** — used by `[wvw_standings]` when `region` is omitted.
- **Refresh interval** — the cache freshness window (default ~5 minutes),
  driving both WP-Cron and the stale-on-request check and the client poll
  cadence.

## Styling

Lightweight built-in styling (chosen option B):

- Clean default look: GW2 team colors (red / green / blue), simple cards and
  tables.
- Renders nicely the moment a shortcode is dropped in.
- Every element carries CSS classes so the host theme can override freely.

## Team Naming

Raw API team IDs run through the editable admin map to produce friendly names.
Any ID not present in the map falls back to its raw API-provided name. The
result is never blank.

## Error Handling

- **API down, cache present:** serve the cached blob (even if past its refresh
  window) — stale data beats no data.
- **API down, cache empty:** shortcodes render a small, styled
  "scores temporarily unavailable" message — never a fatal error or blank space.
- **Malformed / partial payload:** defensive slicing; a missing field renders a
  neutral placeholder for that field only, not a broken widget.

## Testing

- **PHP unit tests** for the pure data-slicing logic, run against **saved sample
  API payloads** (fixtures) so tests never hit the live API:
  - score extraction
  - points-per-tick extraction
  - skirmish standings extraction
  - objective counts (camps / towers / keeps / SMC) per team
  - team auto-follow (find the match containing a given team)
  - friendly-name mapping incl. fallback to raw name
- Fixtures captured once from a real API response and committed.

## Open Questions / Future

- K/D shortcode (deferred, data already available).
- Detailed named-objective view (needs the separate objectives metadata
  endpoint).
- Caching strategy could later split per-region if `ids=all` payload size
  becomes a concern; single-blob is fine for v1.
