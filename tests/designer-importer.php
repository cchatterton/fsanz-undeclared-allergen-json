<?php
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['options'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['events'] = array();
$GLOBALS['thumbnails'] = array();

class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
class FSANZ_Undeclared_Allergen_JSON {
    public static $refreshes = 0;
    public const AUTOMATION_OPTION = 'fsanz_alerts_hourly_enabled';
    public const MINUTES_OPTION = 'fsanz_alerts_refresh_minutes';
    public const RECURRENCE = 'fsanz_alerts_interval';
    public static function automation_enabled() { return get_option(self::AUTOMATION_OPTION, '0') === '1'; }
    public static function refresh_minutes() { return (int) get_option(self::MINUTES_OPTION, 60); }
    public static function interval_seconds() { return self::refresh_minutes() * MINUTE_IN_SECONDS; }
    public static function schedule_matches($hook) {
        $event = wp_get_scheduled_event($hook);
        return $event && $event->schedule === self::RECURRENCE && $event->interval === self::interval_seconds();
    }
    public static function current_feed() {
        $feed = get_option('fsanz_undeclared_allergen_feed');
        return is_array($feed) && ($feed['scope'] ?? '') === 'listing_first_page' ? $feed : false;
    }
    public static function refresh() {
        self::$refreshes++;
        $feed = get_option('fsanz_undeclared_allergen_feed');
        $feed['generated_at'] = gmdate('c');
        update_option('fsanz_undeclared_allergen_feed', $feed);
        return true;
    }
}
function add_action(...$args) {}
function get_option($key, $default = false) { return isset($GLOBALS['options'][$key]) ? $GLOBALS['options'][$key] : $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function add_option($key, $value) { if (isset($GLOBALS['options'][$key])) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_kses_post($value) { return strip_tags($value, '<h2><h3><p><strong><a>'); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function get_posts($args) {
    $matches = array();
    foreach ($GLOBALS['posts'] as $id => $post) {
        if ($post['post_type'] === $args['post_type'] && in_array($post['post_status'], $args['post_status'], true) && ($GLOBALS['meta'][$id][$args['meta_key']] ?? null) === $args['meta_value']) {
            $matches[] = $id;
        }
    }
    return $matches;
}
function wp_insert_post($post) { $id = count($GLOBALS['posts']) + 1; $GLOBALS['posts'][$id] = $post; return $id; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$id][$key] = $value; }
function wp_timezone() { return new DateTimeZone('Australia/Sydney'); }
function wp_next_scheduled($hook) { return $GLOBALS['events'][$hook] ?? false; }
function wp_schedule_single_event($timestamp, $hook) { $GLOBALS['events'][$hook] = $timestamp; }
function wp_schedule_event($timestamp, $recurrence, $hook) { $GLOBALS['events'][$hook] = array($timestamp, $recurrence, FSANZ_Undeclared_Allergen_JSON::interval_seconds()); }
function wp_get_schedule($hook) { return isset($GLOBALS['events'][$hook]) && is_array($GLOBALS['events'][$hook]) ? $GLOBALS['events'][$hook][1] : false; }
function wp_get_scheduled_event($hook) { return isset($GLOBALS['events'][$hook]) ? (object) array('schedule' => $GLOBALS['events'][$hook][1], 'interval' => $GLOBALS['events'][$hook][2]) : false; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['events'][$hook]); }
function wp_parse_url($url) { return parse_url($url); }
function media_sideload_image($url, $post_id, $title, $return) { return 100 + $post_id; }
function set_post_thumbnail($post_id, $attachment_id) { $GLOBALS['thumbnails'][$post_id] = $attachment_id; return true; }

require dirname(__DIR__) . '/fsanz-undeclared-allergen-json/functions/importer.php';
require dirname(__DIR__) . '/fsanz-undeclared-allergen-json/functions/designer.php';

function same($actual, $expected, $label) {
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}

$one = array(
    'title' => 'Cake & milk',
    'url' => 'https://www.foodstandards.gov.au/food-recalls/recall-alert/one',
    'problem' => 'Undeclared egg & soy',
    'image_url' => 'https://www.foodstandards.gov.au/sites/default/files/cake.png?itok=test',
    'locations' => array('NSW', 'VIC'),
    'details' => array('recall_information' => array('problem' => 'Undeclared egg & soy', 'what_to_do' => 'Do not consume')),
);
$two = array(
    'title' => 'Noodles',
    'url' => 'https://www.foodstandards.gov.au/food-recalls/recall-alert/two',
    'problem' => 'Undeclared milk',
    'image_url' => 'https://www.foodstandards.gov.au/sites/default/files/noodles.png',
    'locations' => array('QLD'),
    'details' => array('recall_information' => array('problem' => 'Undeclared milk', 'what_to_do' => 'Return the product')),
);
$feed = array('source' => 'https://www.foodstandards.gov.au/food-recalls/recall-alert', 'scope' => 'listing_first_page', 'count' => 2, 'generated_at' => gmdate('c'), 'items' => array($one, $two));
update_option('fsanz_undeclared_allergen_feed', array('items' => array($one, $two)));
same(FSANZ_Undeclared_Allergen_JSON::current_feed(), false, 'Older multi-page feed is unavailable');
update_option('fsanz_undeclared_allergen_feed', $feed);
same(FSANZ_Undeclared_Allergen_JSON::automation_enabled(), false, 'Automation defaults off');
same(FSANZ_Alerts_Importer::post_status(), 'draft', 'New Alerts default to draft');
FSANZ_Alerts_Importer::ensure_schedule();
same(wp_next_scheduled(FSANZ_Alerts_Importer::CRON), false, 'No import schedule by default');

$catalog = FSANZ_Alerts_Designer::token_catalog($feed);
same($catalog['item_problem'], 'Undeclared egg & soy', 'Problem token');
same($catalog['item_details_recall_information_what_to_do'], 'Do not consume', 'Nested token');
same($catalog['item_locations'], 'NSW, VIC', 'Array token');
same($catalog['item_locations_1'], 'VIC', 'Array index token');
same($catalog['feed_count'], '2', 'Feed token');
same($catalog['feed_items_0_problem'], 'Undeclared egg & soy', 'Full-feed item token');

$template = '<h2>%item_title%</h2><p>%item_problem%</p><p>%item_details_recall_information_what_to_do%</p>';
$rendered = FSANZ_Alerts_Designer::render_template($template, $feed, $one);
same($rendered['unknown'], array(), 'Known tokens');
if (strpos($rendered['html'], 'Cake &amp; milk') === false || strpos($rendered['html'], 'Undeclared egg &amp; soy') === false) {
    throw new RuntimeException('Template did not escape source values');
}
same(FSANZ_Alerts_Designer::render_template('%item_missing%', $feed, $one)['unknown'], array('%item_missing%'), 'Unknown token');

update_option(FSANZ_Alerts_Importer::TEMPLATE_OPTION, $template);
update_option(FSANZ_Alerts_Importer::IMAGE_TOKEN_OPTION, 'item_image_url');
$first = FSANZ_Alerts_Importer::run(1, false, 'manual');
same($first['imported'], 1, 'First manual import');
same($GLOBALS['posts'][1]['post_status'], 'draft', 'Imported post is draft');
same($GLOBALS['thumbnails'][1], 101, 'Selected image becomes featured');
same($GLOBALS['meta'][1][FSANZ_Alerts_Importer::SOURCE_META], $one['url'], 'Source URL identity');
if (strpos($GLOBALS['posts'][1]['post_content'], '<!-- wp:html -->') !== 0) {
    throw new RuntimeException('Imported content is not a Custom HTML block');
}
$second = FSANZ_Alerts_Importer::run(1, false, 'manual');
same($second['imported'], 1, 'Second manual import');
same($second['skipped'], 1, 'Existing first record skipped');
$GLOBALS['posts'][2]['post_status'] = 'trash';
$third = FSANZ_Alerts_Importer::run(1, false, 'manual');
same($third['imported'], 0, 'Trashed record remains a duplicate');
same(FSANZ_Alerts_Importer::missing_count($feed), 0, 'No missing records');
$three = array(
    'title' => 'Chocolate',
    'url' => 'https://www.foodstandards.gov.au/food-recalls/recall-alert/three',
    'problem' => 'Undeclared nuts',
    'locations' => array('SA'),
    'details' => array('recall_information' => array('problem' => 'Undeclared nuts', 'what_to_do' => 'Return the product')),
);
$feed['items'][] = $three;
$feed['count'] = 3;
update_option('fsanz_undeclared_allergen_feed', $feed);
$night = FSANZ_Alerts_Importer::run(0, true, 'overnight');
same($night['imported'], 1, 'Nightly import includes all missing records');
same($night['skipped'], 2, 'Nightly import skips existing records');
same($GLOBALS['meta'][3][FSANZ_Alerts_Importer::SOURCE_META], $three['url'], 'Nightly identity');
same(FSANZ_Undeclared_Allergen_JSON::$refreshes, 0, 'Fresh feed reused overnight');
$feed['generated_at'] = gmdate('c', time() - 90000);
update_option('fsanz_undeclared_allergen_feed', $feed);
$stale = FSANZ_Alerts_Importer::run(0, true, 'overnight');
same($stale['imported'], 0, 'Stale feed import still avoids duplicates');
same(FSANZ_Undeclared_Allergen_JSON::$refreshes, 1, 'Stale feed refreshed overnight');
$four = $three;
$four['url'] = 'https://www.foodstandards.gov.au/food-recalls/recall-alert/four';
$four['title'] = 'Fourth recall';
$five = $three;
$five['url'] = 'https://www.foodstandards.gov.au/food-recalls/recall-alert/five';
$five['title'] = 'Fifth recall';
$feed['items'][] = $four;
$feed['items'][] = $five;
$feed['count'] = 5;
update_option('fsanz_undeclared_allergen_feed', $feed);
$selected = FSANZ_Alerts_Importer::run(1, false, 'manual selected', $five['url']);
same($selected['imported'], 1, 'Selected recall imported');
same($GLOBALS['meta'][4][FSANZ_Alerts_Importer::SOURCE_META], $five['url'], 'Selected URL imported exactly');
same(FSANZ_Alerts_Importer::existing_post_id($four['url']), 0, 'Earlier missing recall left untouched');
same($GLOBALS['posts'][4]['post_status'], 'draft', 'Selected recall is draft');
update_option(FSANZ_Undeclared_Allergen_JSON::AUTOMATION_OPTION, '1');
FSANZ_Alerts_Importer::ensure_schedule();
same(wp_get_schedule(FSANZ_Alerts_Importer::CRON), FSANZ_Undeclared_Allergen_JSON::RECURRENCE, 'Custom import schedule');
same(wp_get_scheduled_event(FSANZ_Alerts_Importer::CRON)->interval, 3600, 'Default 60-minute interval');
update_option(FSANZ_Undeclared_Allergen_JSON::MINUTES_OPTION, 25);
FSANZ_Alerts_Importer::ensure_schedule();
same(wp_get_scheduled_event(FSANZ_Alerts_Importer::CRON)->interval, 1500, 'Changed interval reschedules import');
update_option(FSANZ_Undeclared_Allergen_JSON::AUTOMATION_OPTION, '0');
FSANZ_Alerts_Importer::ensure_schedule();
same(wp_next_scheduled(FSANZ_Alerts_Importer::CRON), false, 'Automation toggle clears schedule');
FSANZ_Alerts_Importer::scheduled_import();
same(FSANZ_Alerts_Importer::existing_post_id($four['url']), 0, 'Paused cron callback imports nothing');
update_option(FSANZ_Alerts_Importer::POST_STATUS_OPTION, 'publish');
$published = FSANZ_Alerts_Importer::run(1, false, 'manual selected', $four['url']);
same($published['post_status'], 'publish', 'Selected status is reported');
same($GLOBALS['posts'][5]['post_status'], 'publish', 'Explicit published choice applies to new import');
same($GLOBALS['posts'][1]['post_status'], 'draft', 'Existing Alert status is unchanged');
echo "Designer and importer tests passed\n";
