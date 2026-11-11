# AlphaSys FSANZ Alerts

WordPress plugin for a daily JSON feed of FSANZ undeclared allergen recalls. The plugin folder and main file retain the original identity for in-place upgrades.

- **Plugin slug:** `fsanz-undeclared-allergen-json`
- **Main file:** `fsanz-undeclared-allergen-json/fsanz-undeclared-allergen-json.php`
- **Release asset:** `fsanz-undeclared-allergen-json.zip`
- **REST route:** `/wp-json/fsanz-allergens/v1/recalls`
- **Author:** AlphaSys
- **Controller:** AS Update Controller, API 1
- **Branding Mode:** Author Branded

Run `bash scripts/build-plugin-zip.sh` to generate the root ZIP. The release tag and ZIP version must match the plugin header, version constant, readme stable tag, and changelog. After publishing a release, run the AS controller catalogue publisher and verify the public catalogue entry.
