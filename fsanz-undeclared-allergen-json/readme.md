# AlphaSys FSANZ Alerts

- Author: AlphaSys
- Version: 1.4.1
- Status: Beta
- Branding Mode: Author Branded

Publishes public JSON of FSANZ recall cards labelled "Undeclared allergen". Each item includes structured recall information from its detail page; `problem` is also available directly on the item. The plugin retains the last successful feed if a refresh fails.

Install the release ZIP through WordPress and visit **Settings → FSANZ Alerts** to refresh, edit the HTML merge template, and preview any recall. The Template tab places available tokens beside the HTML editor; the full-width Preview tab browses every cached recall with Previous and Next controls and offers Add now for the selected missing recall. Click a token to insert it into the template. Tokens follow the `%item_problem%` pattern; nested JSON fields use underscore-separated paths, and the complete feed is available under `%feed_*%`. The REST namespace remains `fsanz-allergens/v1` for existing consumers.

Save the template before importing. **Import next missing record** creates one draft `alert` post. Select a featured-image token in the template editor; it is downloaded into the Media Library when a draft is created. Hourly automation is off until an administrator enables it. When enabled, it refreshes the JSON and imports all missing recalls as drafts; the switch pauses or resumes both jobs. Existing Alerts, including those in Trash, are identified by their FSANZ detail URL and are not overwritten.

Normal feature operation does not require AS Update Controller. The guarded client exposes installation, activation, and update actions on the Plugins screen when needed. Updates are published through [AS Update Controller](https://github.com/cchatterton/as-update-controller).

FSANZ source: https://www.foodstandards.gov.au/food-recalls/recall-alert . Attribution: Food Standards Australia New Zealand. This plugin is independently maintained and is not endorsed by FSANZ. See the FSANZ [copyright](https://www.foodstandards.gov.au/legal-policies/copyright) and [privacy](https://www.foodstandards.gov.au/legal-policies/privacy) pages.
