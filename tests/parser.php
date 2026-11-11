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
echo "Parser tests passed\n";
