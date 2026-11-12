# AlphaSys FSANZ Alerts

WordPress plugin for a daily JSON feed of FSANZ undeclared allergen recalls. The plugin folder and main file retain the original identity for in-place upgrades.

The Settings > FSANZ Alerts screen now provides a token browser, an HTML merge template editor, and a live preview. Saved templates publish missing recalls as `alert` posts through a one-record manual action or a site-time 2 a.m. WP-Cron job. The source detail URL is the unique record key.

- **Plugin slug:** `fsanz-undeclared-allergen-json`
- **Main file:** `fsanz-undeclared-allergen-json/fsanz-undeclared-allergen-json.php`
- **Release asset:** `fsanz-undeclared-allergen-json.zip`
- **REST route:** `/wp-json/fsanz-allergens/v1/recalls`
- **Author:** AlphaSys
- **Controller:** AS Update Controller, API 1
- **Branding Mode:** Author Branded

Run `bash scripts/build-plugin-zip.sh` to generate the root ZIP. The release tag and ZIP version must match the plugin header, version constant, readme stable tag, and changelog. After publishing a release, run the AS controller catalogue publisher and verify the public catalogue entry.

The 1.2.0 designer, manual import, overnight batch and REST feed were exercised in a disposable WordPress 7.1.4 installation with the SQLite Database Integration plugin and PHP 8.5.7. PHP 7.4 and the deployed PHP 8.1 fleet have not been run in this workspace.
