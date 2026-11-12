# AlphaSys FSANZ Alerts

WordPress plugin for a JSON feed of undeclared allergen recalls shown on the single FSANZ recall-alert listing page. It fetches matching detail pages but does not follow listing pagination. The plugin folder and main file retain the original identity for in-place upgrades.

The Settings > FSANZ Alerts screen provides a token browser, an HTML merge template editor, and a live preview in two tabs: a side-by-side token browser and HTML editor, and a full-width preview with AJAX Previous and Next controls, plus Add now or Publish now for the selected missing recall. Saved templates create `alert` posts through a one-record manual action or scheduled automation. New posts default to Draft; an administrator can choose Published for both import paths. The selected image token supplies the featured image; automation is off by default. A minutes input sets the refresh and import interval (default 60 minutes), and two switches control automation and Draft/Published status. When enabled, both jobs use the chosen interval and new posts use the chosen status. The source detail URL is the unique record key. After upgrading from the former multi-page feed, use Refresh now to create the single-page cache; older Alert posts remain unchanged.

- **Plugin slug:** `fsanz-undeclared-allergen-json`
- **Main file:** `fsanz-undeclared-allergen-json/fsanz-undeclared-allergen-json.php`
- **Release asset:** `fsanz-undeclared-allergen-json.zip`
- **REST route:** `/wp-json/fsanz-allergens/v1/recalls`
- **Author:** AlphaSys
- **Controller:** AS Update Controller, API 1
- **Branding Mode:** Author Branded

Run `bash scripts/build-plugin-zip.sh` to generate the root ZIP. The release tag and ZIP version must match the plugin header, version constant, readme stable tag, and changelog. After publishing a release, run the AS controller catalogue publisher and verify the public catalogue entry.

The designer, draft import, featured image, configurable schedules, and REST feed were exercised in a disposable WordPress 7.1.4 installation with the SQLite Database Integration plugin and PHP 8.5.7. PHP 7.4 and the deployed PHP 8.1 fleet have not been run in this workspace.
