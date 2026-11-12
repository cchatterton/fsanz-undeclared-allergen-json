# AlphaSys FSANZ Alerts

- Author: AlphaSys
- Version: 1.2.0
- Status: Beta
- Branding Mode: Author Branded

Publishes public JSON of FSANZ recall cards labelled "Undeclared allergen". Each item includes structured recall information from its detail page; `problem` is also available directly on the item. The plugin retains the last successful feed if a refresh fails.

Install the release ZIP through WordPress and visit **Settings → FSANZ Alerts** to refresh, edit the HTML merge template, and preview any recall. Click a token to insert it into the template. Tokens follow the `%item_problem%` pattern; nested JSON fields use underscore-separated paths, and the complete feed is available under `%feed_*%`. The REST namespace remains `fsanz-allergens/v1` for existing consumers.

Save the template before importing. **Import next missing record** publishes one `alert` post. A single-event WP-Cron job runs at about 2 a.m. in the site timezone and imports every missing record. It refreshes the JSON first when the last successful feed is older than 24 hours. Existing Alerts, including those in Trash, are identified by their FSANZ detail URL and are not overwritten.

Normal feature operation does not require AS Update Controller. The guarded client exposes installation, activation, and update actions on the Plugins screen when needed. Updates are published through [AS Update Controller](https://github.com/cchatterton/as-update-controller).

FSANZ source: https://www.foodstandards.gov.au/food-recalls/recall-alert . Attribution: Food Standards Australia New Zealand. This plugin is independently maintained and is not endorsed by FSANZ. See the FSANZ [copyright](https://www.foodstandards.gov.au/legal-policies/copyright) and [privacy](https://www.foodstandards.gov.au/legal-policies/privacy) pages.
