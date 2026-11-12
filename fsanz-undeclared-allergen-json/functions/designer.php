<?php
if (!defined('ABSPATH')) { exit; }

final class FSANZ_Alerts_Designer {
    public static function init() {
        add_action('admin_post_fsanz_alerts_save_template', array(__CLASS__, 'save_template'));
        add_action('wp_ajax_fsanz_alerts_preview', array(__CLASS__, 'ajax_preview'));
    }

    public static function example_template() {
        return '<h2>%item_title%</h2>' . "\n"
            . '<p><strong>Recall date:</strong> %item_date%</p>' . "\n"
            . '<p><strong>Product:</strong> %item_details_recall_information_product%</p>' . "\n"
            . '<p><strong>Business:</strong> %item_business%</p>' . "\n"
            . '<p><strong>Distributed in:</strong> %item_details_recall_information_distributed_in%</p>' . "\n"
            . '<p><strong>Recall reason:</strong> %item_details_recall_information_recall_reason%</p>' . "\n"
            . '<p><strong>Date marking(s):</strong> %item_details_recall_information_date_markings%</p>' . "\n"
            . '<h3>Problem</h3><p>%item_problem%</p>' . "\n"
            . '<h3>Food safety hazard</h3><p>%item_details_recall_information_food_safety_hazard%</p>' . "\n"
            . '<h3>What to do</h3><p>%item_details_recall_information_what_to_do%</p>' . "\n"
            . '<p>Source: Food Standards Australia New Zealand. <a href="%item_url%">View the original recall</a></p>';
    }

    public static function save_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        check_admin_referer('fsanz_alerts_save_template');
        $raw = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
        $template = is_string($raw) ? $raw : '';
        update_option(FSANZ_Alerts_Importer::TEMPLATE_OPTION, $template, false);
        wp_safe_redirect(admin_url('options-general.php?page=fsanz-allergen-json&template_saved=1'));
        exit;
    }

    public static function flatten_value($value, $prefix, &$tokens) {
        if (is_array($value)) {
            $all_scalar = true;
            foreach ($value as $child) {
                if (is_array($child)) {
                    $all_scalar = false;
                    break;
                }
            }
            if ($all_scalar && array_keys($value) === range(0, count($value) - 1)) {
                $tokens[$prefix] = implode(', ', array_map(array(__CLASS__, 'scalar_string'), $value));
            } else {
                $tokens[$prefix] = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            foreach ($value as $key => $child) {
                self::flatten_value($child, $prefix . '_' . self::token_part($key), $tokens);
            }
            return;
        }
        $tokens[$prefix] = self::scalar_string($value);
    }

    private static function token_part($part) {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', (string) $part)), '_');
    }

    private static function scalar_string($value) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return $value === null ? '' : (string) $value;
    }

    public static function tokens_for_record($feed, $item) {
        $tokens = array();
        self::flatten_value($feed, 'feed', $tokens);
        self::flatten_value($item, 'item', $tokens);
        return $tokens;
    }

    public static function token_catalog($feed) {
        $catalog = array();
        if (!is_array($feed) || empty($feed['items']) || !is_array($feed['items'])) {
            return $catalog;
        }
        self::flatten_value($feed, 'feed', $catalog);
        foreach ($feed['items'] as $item) {
            $item_tokens = array();
            self::flatten_value($item, 'item', $item_tokens);
            foreach ($item_tokens as $key => $value) {
                if (!isset($catalog[$key]) || ($catalog[$key] === '' && $value !== '')) {
                    $catalog[$key] = $value;
                }
            }
        }
        uksort($catalog, function ($a, $b) {
            $first = array('item_problem', 'item_title', 'item_url');
            $a_priority = array_search($a, $first, true);
            $b_priority = array_search($b, $first, true);
            $a_group = $a_priority !== false ? $a_priority : (strpos($a, 'item_') === 0 ? 3 : 4);
            $b_group = $b_priority !== false ? $b_priority : (strpos($b, 'item_') === 0 ? 3 : 4);
            return $a_group === $b_group ? strcmp($a, $b) : $a_group - $b_group;
        });
        return $catalog;
    }

    public static function render_template($template, $feed, $item) {
        $template = (string) $template;
        $values = self::tokens_for_record($feed, $item);
        $catalog = self::token_catalog($feed);
        $unknown = array();
        $html = preg_replace_callback('/%([a-z][a-z0-9_]*)%/i', function ($match) use ($values, $catalog, &$unknown) {
            $key = strtolower($match[1]);
            if (!array_key_exists($key, $catalog)) {
                $unknown[$key] = '%' . $key . '%';
                return $match[0];
            }
            return esc_html(isset($values[$key]) ? $values[$key] : '');
        }, $template);
        return array('html' => wp_kses_post($html), 'unknown' => array_values($unknown));
    }

    public static function ajax_preview() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Not allowed.'), 403);
        }
        check_ajax_referer('fsanz_alerts_preview', 'nonce');
        $feed = get_option('fsanz_undeclared_allergen_feed');
        $index = isset($_POST['record']) ? absint($_POST['record']) : 0;
        if (!is_array($feed) || !isset($feed['items'][$index])) {
            wp_send_json_error(array('message' => 'The selected recall is unavailable.'), 404);
        }
        $template = isset($_POST['template']) ? wp_unslash($_POST['template']) : '';
        $rendered = self::render_template($template, $feed, $feed['items'][$index]);
        $rendered['document'] = self::preview_document($rendered['html']);
        wp_send_json_success($rendered);
    }

    public static function render_admin($feed) {
        $saved = FSANZ_Alerts_Importer::saved_template();
        $template = $saved !== '' ? $saved : self::example_template();
        $status = get_option(FSANZ_Alerts_Importer::STATUS_OPTION);
        $next = wp_next_scheduled(FSANZ_Alerts_Importer::CRON);
        $missing = is_array($feed) ? FSANZ_Alerts_Importer::missing_count($feed) : 0;
        echo '<div class="fsanz-alerts-panel"><h2>Alert imports</h2>';
        echo '<p>New records become published Alert posts. A recall is identified by its FSANZ detail URL, including posts in Trash.</p>';
        if ($next) {
            echo '<p>Next overnight import: ' . esc_html(wp_date('j F Y, g:i a T', $next)) . ' (site time).</p>';
        }
        if (is_array($feed)) {
            echo '<p>Missing records in the current feed: ' . esc_html((string) $missing) . '.</p>';
        }
        if (is_array($status)) {
            $summary = $status['error'] !== '' ? $status['error'] : 'Imported ' . $status['imported'] . ' record(s); skipped ' . $status['skipped'] . ' existing record(s).';
            echo '<p role="status">Last ' . esc_html($status['trigger']) . ' import: ' . esc_html($summary) . '</p>';
            if (!empty($status['post_id'])) {
                echo '<p><a href="' . esc_url(get_edit_post_link($status['post_id'])) . '">Edit last imported Alert</a></p>';
            }
        }
        if ($saved === '') {
            echo '<p class="description">Save the HTML merge template before importing. Overnight imports remain inactive until then.</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fsanz_alerts_import_next">';
        wp_nonce_field('fsanz_alerts_import_next');
        $button_attributes = $saved === '' || $missing === 0 ? array('disabled' => 'disabled') : array();
        submit_button('Import next missing record', 'secondary', 'submit', false, $button_attributes);
        echo '</form></div>';

        echo '<div class="fsanz-alerts-panel fsanz-alerts-designer"><h2>Page designer</h2>';
        echo '<p>Write an HTML merge template using the tokens below. Preview it with any recall, then save it for manual and overnight imports. Each imported post uses a WordPress Custom HTML block.</p>';
        if (isset($_GET['template_saved'])) {
            echo '<div class="notice notice-success inline"><p>HTML merge template saved.</p></div>';
        }
        if (!is_array($feed) || empty($feed['items'])) {
            echo '<p>Refresh the FSANZ feed to load records and tokens for preview.</p></div>';
            return;
        }
        echo '<div class="fsanz-alerts-accordion-stack">';
        echo '<details class="fsanz-alerts-accordion" id="fsanz-alerts-tokens-section" open><summary>Available tokens</summary><div class="fsanz-alerts-accordion-content">';
        echo '<p>Use a token to insert a value from the selected recall or the full JSON feed into the HTML merge template.</p>';
        echo '<label for="fsanz-alerts-token-search" class="screen-reader-text">Search available tokens</label>';
        echo '<input id="fsanz-alerts-token-search" class="widefat" type="search" placeholder="Search tokens">';
        echo '<div class="fsanz-alerts-token-list"><table class="widefat striped"><thead><tr><th>Token</th><th>Example value</th></tr></thead><tbody>';
        foreach (self::token_catalog($feed) as $token => $example) {
            $display = strlen($example) > 90 ? wp_html_excerpt($example, 90, '…') : $example;
            echo '<tr><td><button type="button" class="button-link fsanz-alerts-token" data-token="%' . esc_attr($token) . '%" title="Insert token">%' . esc_html($token) . '%</button></td><td>' . esc_html($display) . '</td></tr>';
        }
        echo '</tbody></table></div></div></details>';
        echo '<details class="fsanz-alerts-accordion" id="fsanz-alerts-template-section" open><summary>HTML merge template</summary><div class="fsanz-alerts-accordion-content">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fsanz_alerts_save_template">';
        wp_nonce_field('fsanz_alerts_save_template');
        echo '<label for="fsanz-alerts-template" class="screen-reader-text">HTML merge template code</label>';
        echo '<textarea id="fsanz-alerts-template" name="template" class="large-text code" rows="16">' . esc_textarea($template) . '</textarea>';
        submit_button('Save HTML merge template', 'primary', 'submit', false);
        echo '</form></div></details>';
        echo '<details class="fsanz-alerts-accordion" id="fsanz-alerts-preview-section" open><summary>Live preview</summary><div class="fsanz-alerts-accordion-content">';
        echo '<label for="fsanz-alerts-record"><strong>Preview record</strong></label>';
        echo '<select id="fsanz-alerts-record" class="widefat">';
        foreach ($feed['items'] as $index => $item) {
            echo '<option value="' . esc_attr((string) $index) . '">' . esc_html($item['title']) . '</option>';
        }
        echo '</select>';
        echo '<p id="fsanz-alerts-preview-status" role="status" aria-live="polite">Previewing the selected recall.</p>';
        $initial = self::render_template($template, $feed, $feed['items'][0]);
        echo '<iframe id="fsanz-alerts-preview" title="Alert page preview" sandbox="" srcdoc="' . esc_attr(self::preview_document($initial['html'])) . '"></iframe>';
        echo '</div></details></div></div>';
    }

    public static function preview_document($html) {
        return '<!doctype html><html><head><meta charset="utf-8"><style>body{font:1rem/1.55 system-ui,sans-serif;color:#1d2327;padding:1.5rem;max-width:47.5rem;margin:auto}img{max-width:100%;height:auto}a{color:#135e96}</style></head><body>' . $html . '</body></html>';
    }
}
