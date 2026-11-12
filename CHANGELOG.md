# Changelog

All notable changes to AlphaSys FSANZ Alerts are recorded here.

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
