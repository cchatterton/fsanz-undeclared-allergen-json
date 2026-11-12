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
        $feed = get_option('fsanz_undeclared_allergen_feed');
        $image_token = isset($_POST['featured_image_token']) ? wp_unslash($_POST['featured_image_token']) : '';
        $options = self::image_token_options($feed);
        if (!is_string($image_token) || ($image_token !== '' && !isset($options[$image_token]))) {
            wp_die('Invalid featured image token.');
        }
        update_option(FSANZ_Alerts_Importer::TEMPLATE_OPTION, $template, false);
        update_option(FSANZ_Alerts_Importer::IMAGE_TOKEN_OPTION, $image_token, false);
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

    public static function image_token_options($feed) {
        $options = array();
        foreach (self::token_catalog($feed) as $token => $value) {
            if (strpos($token, 'item_') === 0 && self::valid_image_url($value)) {
                $options[$token] = '%' . $token . '%';
            }
        }
        return $options;
    }

    public static function saved_image_token($feed) {
        $saved = get_option(FSANZ_Alerts_Importer::IMAGE_TOKEN_OPTION, null);
        $options = self::image_token_options($feed);
        if ($saved === null) {
            return isset($options['item_details_full_image_url']) ? 'item_details_full_image_url' : (isset($options['item_image_url']) ? 'item_image_url' : '');
        }
        return is_string($saved) && isset($options[$saved]) ? $saved : '';
    }

    public static function image_url_for_record($feed, $item, $token) {
        if ($token === '' || !isset(self::image_token_options($feed)[$token])) {
            return '';
        }
        $values = self::tokens_for_record($feed, $item);
        $url = isset($values[$token]) ? $values[$token] : '';
        return self::valid_image_url($url) ? $url : '';
    }

    private static function valid_image_url($url) {
        if (!is_string($url)) { return false; }
        $parts = wp_parse_url($url);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'], $parts['path'])
            && strtolower($parts['scheme']) === 'https'
            && strtolower($parts['host']) === 'www.foodstandards.gov.au'
            && (bool) preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/i', $parts['path']);
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
        $image_token = isset($_POST['featured_image_token']) ? wp_unslash($_POST['featured_image_token']) : '';
        $image_url = is_string($image_token) ? self::image_url_for_record($feed, $feed['items'][$index], $image_token) : '';
        $rendered['document'] = self::preview_document($rendered['html'], $image_url, $feed['items'][$index]['title']);
        wp_send_json_success($rendered);
    }

    public static function render_status($feed) {
        $saved = FSANZ_Alerts_Importer::saved_template();
        $status = get_option(FSANZ_Alerts_Importer::STATUS_OPTION);
        $missing = is_array($feed) ? FSANZ_Alerts_Importer::missing_count($feed) : 0;
        echo '<div class="fsanz-alerts-panel fsanz-alerts-status"><h2>Recall feed</h2>';
        if (is_array($feed)) {
            echo '<p>' . esc_html((string) $feed['count']) . ' recalls · ' . esc_html((string) $missing) . ' missing · Updated ' . esc_html(wp_date('j M Y, g:i a', strtotime($feed['generated_at']))) . '</p>';
        } else {
            echo '<p>No feed yet.</p>';
        }
        if (is_array($status) && !empty($status['error'])) {
            echo '<p role="alert">Import: ' . esc_html($status['error']) . '</p>';
        }
        if (is_array($status) && !empty($status['warnings'])) {
            echo '<p role="status">' . esc_html(implode(' ', $status['warnings'])) . '</p>';
        }
        echo '<div class="fsanz-alerts-actions">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fsanz_undeclared_allergen_refresh">';
        wp_nonce_field('fsanz_undeclared_allergen_refresh');
        submit_button('Refresh now', 'secondary', 'submit', false);
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="fsanz_alerts_import_next">';
        wp_nonce_field('fsanz_alerts_import_next');
        $button_attributes = $saved === '' || $missing === 0 ? array('disabled' => 'disabled') : array();
        submit_button('Import next missing record', 'secondary', 'submit', false, $button_attributes);
        echo '</form></div>';
        echo '<form id="fsanz-alerts-automation" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fsanz_alerts_toggle_automation">';
        wp_nonce_field('fsanz_alerts_toggle_automation');
        echo '<label><input type="checkbox" name="enabled" value="1" ' . checked(FSANZ_Undeclared_Allergen_JSON::automation_enabled(), true, false) . '> Automatic hourly refresh and draft import</label>';
        echo '<noscript><button type="submit" class="button">Save automation</button></noscript></form></div>';
    }

    public static function render_admin($feed) {
        $saved = FSANZ_Alerts_Importer::saved_template();
        $template = $saved !== '' ? $saved : self::example_template();

        echo '<div class="fsanz-alerts-panel fsanz-alerts-designer"><h2>Page designer</h2>';
        echo '<p>Design draft Alert content with tokens, then preview and save it.</p>';
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
        echo '<label for="fsanz-alerts-featured-image"><strong>Featured image token</strong></label>';
        echo '<select id="fsanz-alerts-featured-image" name="featured_image_token" class="widefat"><option value="">No featured image</option>';
        $selected_image_token = self::saved_image_token($feed);
        foreach (self::image_token_options($feed) as $token => $label) {
            echo '<option value="' . esc_attr($token) . '" ' . selected($selected_image_token, $token, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
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
        $initial_image = self::image_url_for_record($feed, $feed['items'][0], $selected_image_token);
        echo '<iframe id="fsanz-alerts-preview" title="Alert page preview" sandbox="" srcdoc="' . esc_attr(self::preview_document($initial['html'], $initial_image, $feed['items'][0]['title'])) . '"></iframe>';
        echo '</div></details></div></div>';
    }

    public static function preview_document($html, $image_url = '', $title = '') {
        $image = $image_url !== '' ? '<figure><img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '"></figure>' : '';
        return '<!doctype html><html><head><meta charset="utf-8"><style>body{font:1rem/1.55 system-ui,sans-serif;color:#1d2327;padding:1.5rem;margin:0}img{max-width:100%;height:auto}figure{margin:0 0 1.5rem}a{color:#135e96}</style></head><body>' . $image . $html . '</body></html>';
    }
}
