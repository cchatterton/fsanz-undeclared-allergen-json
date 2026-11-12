=== AlphaSys FSANZ Alerts ===
Contributors: alphasys
Tags: food-recall, allergens, json, fsanz
Requires at least: 7.0
Tested up to: 7.1.4
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishes a daily JSON feed of FSANZ undeclared allergen recalls and their detail-page information.

== Description ==

Fetches all pages of the FSANZ recall listing daily. It selects cards labelled "Undeclared allergen", then fetches each recall detail page. The public REST feed contains the full recall information table and a top-level problem field for each item. A failed refresh leaves the last successful feed available.

The Settings > FSANZ Alerts page includes a token browser, HTML merge template editor, and live record preview. Tokens use the form `%item_problem%` or `%item_details_recall_information_what_to_do%`. `item_*` is the selected recall; `feed_*` exposes every path in the full cached JSON, including indexed items. Array values are available as a combined token and by index. Click a token to insert it at the cursor. Templates are sanitised on render and inserted into a WordPress Custom HTML block.

The plugin registers an `alert` post type. After saving a template, select Import next missing record to publish one Alert. The overnight import checks the feed at about 2 a.m. site time and publishes all missing records. A record is identified by its FSANZ detail URL; published, draft, private, future, pending, and trashed Alerts are treated as existing. Existing Alerts are never overwritten by the import.

Feed URL: /wp-json/fsanz-allergens/v1/recalls

The feed attributes its source to Food Standards Australia New Zealand. AlphaSys FSANZ Alerts is independently maintained and is not endorsed by FSANZ.

== Installation ==

1. Upload the ZIP through Plugins > Add New > Upload Plugin, then activate it.
2. Open Settings > FSANZ Alerts and select Refresh now.
3. Use the JSON endpoint shown on that page.
4. Review the page designer preview and save the HTML merge template before importing Alerts.

WP-Cron requires site traffic. Use a host scheduled task to trigger wp-cron.php on low-traffic sites. PHP's DOM extension is required.

== External services ==

This plugin requests public recall listing and detail pages from https://www.foodstandards.gov.au at activation, on daily WP-Cron refreshes, when the overnight importer finds the cached feed older than 24 hours, and when an administrator clicks Refresh now. Each request exposes the site's server IP address to FSANZ as a normal web request. No visitor, account, or site content is sent in the request. FSANZ privacy policy: https://www.foodstandards.gov.au/legal-policies/privacy . FSANZ copyright and reuse conditions: https://www.foodstandards.gov.au/legal-policies/copyright .

== Changelog ==

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
