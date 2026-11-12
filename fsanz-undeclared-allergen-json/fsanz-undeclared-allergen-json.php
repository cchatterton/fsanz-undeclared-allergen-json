<?php
/**
 * Plugin Name: AlphaSys FSANZ Alerts
 * Description: Publishes an hourly JSON feed of Australian undeclared allergen recalls.
 * Version: 1.4.1
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/cchatterton/fsanz-undeclared-allergen-json
 * AlphaSys Controller API: 1
 * Author: AlphaSys
 * Author URI: https://alphasys.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsanz-undeclared-allergen-json
 */

if (!defined('ABSPATH')) { exit; }

define('FSANZ_ALLERGEN_VERSION', '1.4.1');
define('FSANZ_ALLERGEN_PLUGIN_FILE', __FILE__);
define('FSANZ_ALLERGEN_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once FSANZ_ALLERGEN_PLUGIN_DIR . 'functions/core.php';
require_once FSANZ_ALLERGEN_PLUGIN_DIR . 'functions/importer.php';
require_once FSANZ_ALLERGEN_PLUGIN_DIR . 'functions/designer.php';
require_once FSANZ_ALLERGEN_PLUGIN_DIR . 'functions/controller-client.php';

register_activation_hook(__FILE__, array('FSANZ_Undeclared_Allergen_JSON', 'activate'));
register_activation_hook(__FILE__, array('FSANZ_Alerts_Importer', 'activate'));
register_deactivation_hook(__FILE__, array('FSANZ_Undeclared_Allergen_JSON', 'deactivate'));
register_deactivation_hook(__FILE__, array('FSANZ_Alerts_Importer', 'deactivate'));
FSANZ_Undeclared_Allergen_JSON::init();
FSANZ_Alerts_Importer::init();
FSANZ_Alerts_Designer::init();
asuc_client_register(__FILE__, 'fsanz-undeclared-allergen-json');
