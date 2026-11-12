=== AlphaSys FSANZ Alerts ===
Contributors: alphasys
Tags: food-recall, allergens, json, fsanz
Requires at least: 7.0
Tested up to: 7.1.4
Stable tag: 1.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishes an hourly JSON feed of FSANZ undeclared allergen recalls and their detail-page information.

== Description ==

Fetches all pages of the FSANZ recall listing hourly when automation is enabled. It selects cards labelled "Undeclared allergen", then fetches each recall detail page. The public REST feed contains the full recall information table and a top-level problem field for each item. A failed refresh leaves the last successful feed available.

The Settings > FSANZ Alerts page includes a token browser, HTML merge template editor, and live record preview in two tabs. The Template tab places tokens beside the HTML editor; the Preview tab uses the full width and browses every recall with Previous and Next controls and offers Add now for the selected missing recall. Tokens use the form `%item_problem%` or `%item_details_recall_information_what_to_do%`. `item_*` is the selected recall; `feed_*` exposes every path in the full cached JSON, including indexed items. Array values are available as a combined token and by index. Click a token to insert it at the cursor. Templates are sanitised on render and inserted into a WordPress Custom HTML block.

Choose an item image URL token for the imported post’s featured image; the HTML preview excludes that image. The plugin registers an `alert` post type. After saving a template, select Import next missing record to create one draft Alert. Hourly automation refreshes the feed and imports all missing records as drafts. The automation switch can pause or resume both scheduled jobs. A record is identified by its FSANZ detail URL; published, draft, private, future, pending, and trashed Alerts are treated as existing. Existing Alerts are never overwritten by the import.

Feed URL: /wp-json/fsanz-allergens/v1/recalls

The feed attributes its source to Food Standards Australia New Zealand. AlphaSys FSANZ Alerts is independently maintained and is not endorsed by FSANZ.

== Installation ==

1. Upload the ZIP through Plugins > Add New > Upload Plugin, then activate it.
2. Open Settings > FSANZ Alerts and select Refresh now.
3. Use /wp-json/fsanz-allergens/v1/recalls for the JSON feed.
4. Review the page designer preview and save the HTML merge template before importing Alerts.

WP-Cron requires site traffic. Use a host scheduled task to trigger wp-cron.php on low-traffic sites. PHP's DOM extension is required.

== External services ==

This plugin requests public recall listing and detail pages from https://www.foodstandards.gov.au on hourly WP-Cron refreshes, when the hourly importer finds the cached feed older than one hour, and when an administrator clicks Refresh now. Featured-image imports also download the selected FSANZ product image. Each request exposes the site's server IP address to FSANZ as a normal web request. No visitor, account, or site content is sent in the request. FSANZ privacy policy: https://www.foodstandards.gov.au/legal-policies/privacy . FSANZ copyright and reuse conditions: https://www.foodstandards.gov.au/legal-policies/copyright .

== Changelog ==

= 1.4.0 =
* Restored the compact Author Branded header and version watermark.
* Replaced the designer accordions with Template and Preview tabs.
* Placed tokens and HTML side by side; added AJAX Previous and Next preview controls.
* Excluded the featured image from the HTML preview.
* Added Add now for the missing recall currently shown in Preview.

= 1.3.0 =
* Import draft Alerts and select a featured-image token.
* Run feed refresh and missing-record import hourly with an on/off switch.
* Combine status and actions into one concise full-width panel.

= 1.2.0 =
* Added a token browser, HTML merge template editor, and live preview for each recall.
* Added the Alert post type, one-record manual import, and overnight missing-record import.
* Added source URL deduplication, including Alerts in Trash.

= 1.1.3 =
* Added the verified AlphaSys WordPress.org contributor username to the readme metadata.

= 1.1.2 =
* Declared AlphaSys Controller API 1 so the controller recognises the integration.

= 1.1.1 =
* Added AlphaSys author metadata, GitHub release metadata, and AS Update Controller client.
* Kept the existing plugin identity and REST endpoint.
* Added FSANZ attribution and a branded admin status screen.
