=== AlphaSys FSANZ Alerts ===
Contributors: alphasys
Tags: food-recall, allergens, json, fsanz
Requires at least: 7.0
Tested up to:
Stable tag: 1.1.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishes a daily JSON feed of FSANZ undeclared allergen recalls and their detail-page information.

== Description ==

Fetches all pages of the FSANZ recall listing daily. It selects cards labelled "Undeclared allergen", then fetches each recall detail page. The public REST feed contains the full recall information table and a top-level problem field for each item. A failed refresh leaves the last successful feed available.

Feed URL: /wp-json/fsanz-allergens/v1/recalls

The feed attributes its source to Food Standards Australia New Zealand. AlphaSys FSANZ Alerts is independently maintained and is not endorsed by FSANZ.

== Installation ==

1. Upload the ZIP through Plugins > Add New > Upload Plugin, then activate it.
2. Open Settings > FSANZ Alerts and select Refresh now.
3. Use the JSON endpoint shown on that page.

WP-Cron requires site traffic. Use a host scheduled task to trigger wp-cron.php on low-traffic sites. PHP's DOM extension is required.

== External services ==

This plugin requests public recall listing and detail pages from https://www.foodstandards.gov.au at activation, on daily WP-Cron refreshes, and when an administrator clicks Refresh now. Each request exposes the site's server IP address to FSANZ as a normal web request. No visitor, account, or site content is sent in the request. FSANZ privacy policy: https://www.foodstandards.gov.au/legal-policies/privacy . FSANZ copyright and reuse conditions: https://www.foodstandards.gov.au/legal-policies/copyright .

== Changelog ==

= 1.1.3 =
* Added the verified AlphaSys WordPress.org contributor username to the readme metadata.

= 1.1.2 =
* Declared AlphaSys Controller API 1 so the controller recognises the integration.

= 1.1.1 =
* Added AlphaSys author metadata, GitHub release metadata, and AS Update Controller client.
* Kept the existing plugin identity and REST endpoint.
* Added FSANZ attribution and a branded admin status screen.
