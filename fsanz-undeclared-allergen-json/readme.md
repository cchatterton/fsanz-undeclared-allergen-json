# AlphaSys FSANZ Alerts

Author: AlphaSys  
Version: 1.1.3  
Status: Beta  
Branding Mode: Author Branded

Publishes public JSON of FSANZ recall cards labelled "Undeclared allergen". Each item includes structured recall information from its detail page; `problem` is also available directly on the item. The plugin retains the last successful feed if a refresh fails.

Install the release ZIP through WordPress and visit **Settings → FSANZ Alerts** to refresh and see the endpoint. The REST namespace remains `fsanz-allergens/v1` for existing consumers.

Normal feature operation does not require AS Update Controller. The guarded client exposes installation, activation, and update actions on the Plugins screen when needed. Updates are published through [AS Update Controller](https://github.com/cchatterton/as-update-controller).

FSANZ source: https://www.foodstandards.gov.au/food-recalls/recall-alert . Attribution: Food Standards Australia New Zealand. This plugin is independently maintained and is not endorsed by FSANZ. See the FSANZ [copyright](https://www.foodstandards.gov.au/legal-policies/copyright) and [privacy](https://www.foodstandards.gov.au/legal-policies/privacy) pages.
