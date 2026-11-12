<?php
define('ABSPATH', __DIR__);
class WP_Error {
    private $message;
    public function __construct($code, $message) { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
require dirname(__DIR__) . '/fsanz-undeclared-allergen-json/functions/core.php';

function assert_value($actual, $expected, $label) {
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . var_export($actual, true));
    }
}

$listing = '<article class="recall-card"><span class="recall-card__type-label">Foreign matter</span></article>'
    . '<article class="recall-card recall-card--allergen"><span class="recall-card__type-label">Undeclared allergen</span>'
    . '<h3 class="recall-card__title"><a href="/food-recalls/recall-alert/test">Test recall</a></h3>'
    . '<span class="recall-card__date">12 November 2026</span></article>';
$list = FSANZ_Undeclared_Allergen_JSON::parse_page($listing);
assert_value(count($list['items']), 1, 'Filtered card count');
assert_value($list['items'][0]['date_iso'], '2026-11-12', 'ISO date');

$modern = '<article class="food-recall"><section class="recall-summary"><div class="field-bare"><p>Summary</p></div></section>'
    . '<section class="recall-information">'
    . '<div class="recall-information__row"><div class="recall-information__label">Product</div><div class="recall-information__value">Cake</div></div>'
    . '<div class="field field-location"><h3 class="field-label">Distributed in</h3><div class="field-items"><div class="field-item">NSW</div><div class="field-item">VIC</div></div></div>'
    . '<div class="field field-problem"><h3 class="field-label">Problem</h3><div class="field-item"><p>Undeclared egg and soy.</p></div></div>'
    . '<div class="field field-what-to-do"><h3 class="field-label">What to do</h3><div class="field-item"><p>Return the product.</p></div></div>'
    . '</section></article>';
$detail = FSANZ_Undeclared_Allergen_JSON::parse_detail($modern);
assert_value($detail['recall_information']['problem'], 'Undeclared egg and soy.', 'Modern problem');
assert_value($detail['recall_information']['distributed_in'], array('NSW', 'VIC'), 'Multiple locations');
assert_value(count($detail['recall_information_rows']), 4, 'All displayed rows');

$legacy = '<article class="food-recall"><section class="recall-summary"><div class="field-bare">'
    . '<p>Summary</p><h2>Problem:</h2><p>Undeclared milk.</p><h2>Food safety hazard:</h2><p>Allergic reaction.</p>'
    . '<h2>What to do:</h2><p>Do not consume.</p></div></section>'
    . '<section class="recall-information"><div class="recall-information__row"><div class="recall-information__label">Product</div><div class="recall-information__value">Milk</div></div></section></article>';
$detail = FSANZ_Undeclared_Allergen_JSON::parse_detail($legacy);
assert_value($detail['recall_information']['problem'], 'Undeclared milk.', 'Legacy problem');
assert_value($detail['recall_information']['food_safety_hazard'], 'Allergic reaction.', 'Legacy hazard');
assert_value($detail['recall_information']['what_to_do'], 'Do not consume.', 'Legacy action');

$GLOBALS['mock_listing'] = $listing . '<nav class="pager-nav"><a rel="last" href="?page=18">Last</a></nav>';
$GLOBALS['mock_detail'] = $modern;
$GLOBALS['requests'] = array();
$GLOBALS['options'] = array('fsanz_undeclared_allergen_feed' => array('items' => array('legacy multi-page item')));
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = $url;
    if ($url === 'https://www.foodstandards.gov.au/food-recalls/recall-alert') {
        return array('body' => $GLOBALS['mock_listing'], 'code' => 200);
    }
    if ($url === 'https://www.foodstandards.gov.au/food-recalls/recall-alert/test') {
        return array('body' => $GLOBALS['mock_detail'], 'code' => 200);
    }
    throw new RuntimeException('Unexpected request: ' . $url);
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function get_option($key) { return $GLOBALS['options'][$key] ?? false; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
assert_value(FSANZ_Undeclared_Allergen_JSON::current_feed(), false, 'Old multi-page cache ignored');
assert_value(FSANZ_Undeclared_Allergen_JSON::refresh(), true, 'Single listing refresh succeeded');
assert_value($GLOBALS['requests'], array(
    'https://www.foodstandards.gov.au/food-recalls/recall-alert',
    'https://www.foodstandards.gov.au/food-recalls/recall-alert/test',
), 'Only the base listing and matching detail are fetched');
assert_value(FSANZ_Undeclared_Allergen_JSON::current_feed()['count'], 1, 'First-page feed contains one matching recall');
echo "Parser tests passed\n";
