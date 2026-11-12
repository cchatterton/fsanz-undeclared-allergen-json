# Changelog

All notable changes to AlphaSys FSANZ Alerts are recorded here.

## 1.7.0 - 2026-11-12

- Added separate on/off and Draft/Published switches plus a minutes interval input on one row.
- Schedule both refresh and import at the selected interval (1–1440 minutes, default 60).
- Preserve automation off and Draft as the default settings.

## 1.6.0 - 2026-11-12

- Fetch only the specified FSANZ recall-alert listing page, without following listing pagination.
- Continue fetching detail pages for matching Undeclared allergen cards.
- Ignore the former multi-page cache until a single-page refresh replaces it; existing Alert posts remain unchanged.

## 1.5.0 - 2026-11-12

- Added a Draft or Published choice for new manual and hourly Alert imports, with Draft as the default.
- Updated manual action labels and selected-record feedback for published imports.
- Existing Alert post statuses remain unchanged.

## 1.4.1 - 2026-11-12

- Default hourly automation to off, including upgrades where no on/off choice was saved.
- Clear feed and import schedules when off, and guard callbacks against queued runs.
- Clarify that enabling automation imports all missing recalls as drafts.

## 1.4.0 - 2026-11-12

- Restored the compact Author Branded navy hero, version watermark, and capability chips.
- Kept the full-width status panel concise and moved save notices above the branded area.
- Replaced designer accordions with Template and Preview tabs.
- Placed tokens and HTML editor side by side, and added AJAX Previous and Next preview controls for all cached recalls.
- Kept the featured image separate from the HTML preview.
- Added a targeted Add now action for the missing recall currently shown in Preview.

## 1.3.0 - 2026-11-12

- Create imported Alerts as drafts and add a selectable featured-image token with preview.
- Refresh the feed and import missing drafts hourly, with an on/off switch.
- Combine feed and import status into one concise full-width block with two action buttons.

## 1.2.1 - 2026-11-12

- Stacked Available tokens, HTML merge template, and Live preview as independent full-width accordions.
- Opening a token while the template accordion is closed now opens the editor before inserting it.

## 1.2.0 - 2026-11-12

- Added token discovery for the complete cached JSON, an HTML merge template editor, and a live per-record preview.
- Registered the `alert` post type and added manual import of the next missing recall.
- Added overnight import of all missing recalls at about 2 a.m. in the site timezone, refreshing stale feed data first.
- Deduplicated by FSANZ detail URL across published, unpublished, and trashed Alerts; existing posts are not overwritten.

## 1.1.3 - 2026-11-12

- Added the verified AlphaSys WordPress.org contributor username to the readme metadata.

## 1.1.2 - 2026-11-12

- Declared AlphaSys Controller API 1 in the plugin header for controller discovery and compatibility checks.

## 1.1.1 - 2026-11-12

- Added AlphaSys author, licensing, readme and GitHub release metadata.
- Added the guarded AS Update Controller client and branded admin status screen.
- Preserved the existing plugin folder, feed route and stored options for upgrades.
- Added FSANZ attribution to the JSON feed.
