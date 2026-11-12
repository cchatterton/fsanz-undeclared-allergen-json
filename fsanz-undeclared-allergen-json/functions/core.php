<?php
if (!defined('ABSPATH')) { exit; }

final class FSANZ_Undeclared_Allergen_JSON {
    private const SOURCE = 'https://www.foodstandards.gov.au/food-recalls/recall-alert';
    private const OPTION = 'fsanz_undeclared_allergen_feed';
    private const ERROR_OPTION = 'fsanz_undeclared_allergen_error';
    private const CRON = 'fsanz_undeclared_allergen_refresh';
    private const MAX_PAGES = 100;
    public const AUTOMATION_OPTION = 'fsanz_alerts_hourly_enabled';

    public static function init() {
        add_action(self::CRON, array(__CLASS__, 'scheduled_refresh'));
        add_action('rest_api_init', array(__CLASS__, 'register_route'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('admin_post_fsanz_undeclared_allergen_refresh', array(__CLASS__, 'manual_refresh'));
        add_action('admin_post_fsanz_alerts_toggle_automation', array(__CLASS__, 'toggle_automation'));
        add_action('init', array(__CLASS__, 'ensure_schedule'));
    }

    public static function activate() {
        self::ensure_schedule();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON);
    }

    public static function ensure_schedule() {
        if (!self::automation_enabled()) {
            wp_clear_scheduled_hook(self::CRON);
            return;
        }
        if (wp_get_schedule(self::CRON) !== 'hourly') {
            wp_clear_scheduled_hook(self::CRON);
            wp_schedule_event(time() + 60, 'hourly', self::CRON);
        }
    }

    public static function automation_enabled() {
        return get_option(self::AUTOMATION_OPTION, '0') === '1';
    }

    public static function scheduled_refresh() {
        if (self::automation_enabled()) {
            self::refresh();
        }
    }

    public static function toggle_automation() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        check_admin_referer('fsanz_alerts_toggle_automation');
        update_option(self::AUTOMATION_OPTION, isset($_POST['enabled']) && $_POST['enabled'] === '1' ? '1' : '0', false);
        self::ensure_schedule();
        FSANZ_Alerts_Importer::ensure_schedule();
        wp_safe_redirect(admin_url('options-general.php?page=fsanz-allergen-json'));
        exit;
    }

    public static function register_route() {
        register_rest_route('fsanz-allergens/v1', '/recalls', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'serve_feed'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function serve_feed() {
        $feed = get_option(self::OPTION);
        if (!is_array($feed)) {
            return new WP_Error('feed_not_ready', 'The first FSANZ refresh has not completed yet.', array('status' => 503));
        }
        return rest_ensure_response($feed);
    }

    public static function refresh() {
        if (!class_exists('DOMDocument')) {
            update_option(self::ERROR_OPTION, 'PHP DOM extension is required.', false);
            return false;
        }

        $items = array();
        $seen = array();
        $last_page = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $url = $page ? self::SOURCE . '?page=' . $page : self::SOURCE;
            $response = wp_remote_get($url, array(
                'timeout' => 20,
                'redirection' => 3,
                'headers' => array('Accept' => 'text/html'),
            ));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return self::fail('Failed to fetch FSANZ page ' . ($page + 1) . '.');
            }

            $parsed = self::parse_page(wp_remote_retrieve_body($response), $page === 0);
            if (is_wp_error($parsed)) {
                return self::fail('Could not parse FSANZ page ' . ($page + 1) . ': ' . $parsed->get_error_message());
            }
            if ($page === 0) {
                $last_page = $parsed['last_page'];
                if ($last_page >= self::MAX_PAGES) {
                    return self::fail('FSANZ pagination exceeds the plugin page limit.');
                }
            }
            foreach ($parsed['items'] as $item) {
                if (!isset($seen[$item['url']])) {
                    $seen[$item['url']] = true;
                    $items[] = $item;
                }
            }
            if ($page >= $last_page) {
                break;
            }
        }

        foreach ($items as &$item) {
            $parts = parse_url($item['url']);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'www.foodstandards.gov.au' || strpos($parts['path'] ?? '', '/food-recalls/recall-alert/') !== 0) {
                return self::fail('An allergen card has an unexpected detail URL.');
            }
            $response = wp_remote_get($item['url'], array(
                'timeout' => 20,
                'redirection' => 3,
                'headers' => array('Accept' => 'text/html'),
            ));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return self::fail('Failed to fetch detail page: ' . $item['url']);
            }
            $details = self::parse_detail(wp_remote_retrieve_body($response));
            if (is_wp_error($details)) {
                return self::fail('Could not parse detail page: ' . $item['url'] . ': ' . $details->get_error_message());
            }
            $item['problem'] = $details['recall_information']['problem'];
            $item['details'] = $details;
        }
        unset($item);

        $feed = array(
            'source' => self::SOURCE,
            'attribution' => 'Food Standards Australia New Zealand',
            'type' => 'Undeclared allergen',
            'generated_at' => gmdate('c'),
            'count' => count($items),
            'items' => $items,
        );
        update_option(self::OPTION, $feed, false);
        delete_option(self::ERROR_OPTION);
        return true;
    }

    private static function fail($message) {
        update_option(self::ERROR_OPTION, $message, false);
        return false;
    }

    public static function parse_page($html, $require_last_page = false) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return new WP_Error('bad_html', 'Invalid HTML');
        }
        $xpath = new DOMXPath($dom);
        $cards = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " recall-card ")]');
        if (!$cards || $cards->length === 0) {
            return new WP_Error('no_cards', 'No recall cards found');
        }

        $items = array();
        foreach ($cards as $card) {
            $type = self::node_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " recall-card__type-label ")]', $card);
            if ($type !== 'Undeclared allergen') {
                continue;
            }
            $link = self::first_node($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " recall-card__title ")]/a', $card);
            $date = self::node_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " recall-card__date ")]', $card);
            if (!$link || !$link->getAttribute('href') || !$date) {
                return new WP_Error('missing_field', 'An allergen card is missing its link or date');
            }
            $date_object = DateTimeImmutable::createFromFormat('!j F Y', $date, new DateTimeZone('UTC'));
            if (!$date_object) {
                return new WP_Error('bad_date', 'An allergen card has an unexpected date');
            }
            $locations = array();
            foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " recall-card__location-pill ")]', $card) as $location) {
                $locations[] = trim(preg_replace('/\s+/u', ' ', $location->textContent));
            }
            $image = self::first_node($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " recall-card__image ")]//img', $card);
            $items[] = array(
                'title' => trim(preg_replace('/\s+/u', ' ', $link->textContent)),
                'url' => self::absolute_url($link->getAttribute('href')),
                'business' => self::node_text($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " recall-card__business ")]', $card),
                'locations' => $locations,
                'date' => $date,
                'date_iso' => $date_object->format('Y-m-d'),
                'updated' => (bool) $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " recall-card__footer ") and contains(translate(normalize-space(.), "abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ"), "UPDATED")]', $card)->length,
                'image_url' => $image ? self::absolute_url($image->getAttribute('src')) : null,
                'image_alt' => $image ? $image->getAttribute('alt') : null,
            );
        }

        $last_page = 0;
        $last = self::first_node($xpath, '//a[@rel="last"]');
        if ($last && preg_match('/(?:\?|&)page=(\d+)/', $last->getAttribute('href'), $matches)) {
            $last_page = (int) $matches[1];
        } elseif ($require_last_page && $xpath->query('//nav[contains(concat(" ", normalize-space(@class), " "), " pager-nav ")]')->length) {
            return new WP_Error('bad_pagination', 'Pagination could not be determined');
        }
        return array('items' => $items, 'last_page' => $last_page);
    }

    private static function first_node($xpath, $query, $context = null) {
        $nodes = $xpath->query($query, $context);
        return $nodes && $nodes->length ? $nodes->item(0) : null;
    }

    private static function node_text($xpath, $query, $context = null) {
        $node = self::first_node($xpath, $query, $context);
        return $node ? trim(preg_replace('/\s+/u', ' ', $node->textContent)) : '';
    }

    public static function parse_detail($html) {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return new WP_Error('bad_detail_html', 'Invalid HTML');
        }
        $xpath = new DOMXPath($dom);
        $article = self::first_node($xpath, '//article[contains(concat(" ", normalize-space(@class), " "), " food-recall ")]');
        if (!$article) {
            return new WP_Error('missing_detail', 'Recall article not found');
        }
        $information = self::first_node($xpath, './/section[contains(concat(" ", normalize-space(@class), " "), " recall-information ")]', $article);
        if (!$information) {
            return new WP_Error('missing_detail', 'Recall information section not found');
        }

        $summary = self::first_node($xpath, './/section[contains(concat(" ", normalize-space(@class), " "), " recall-summary ")]//*[contains(concat(" ", normalize-space(@class), " "), " field-bare ")]', $article);
        $rows = array();
        $values = array(
            'product' => null,
            'business' => null,
            'distributed_in' => array(),
            'recall_reason' => null,
            'date_markings' => null,
            'problem' => null,
            'food_safety_hazard' => null,
            'what_to_do' => null,
        );
        $keys = array(
            'product' => 'product',
            'business' => 'business',
            'distributed in' => 'distributed_in',
            'recall reason' => 'recall_reason',
            'date marking(s)' => 'date_markings',
            'problem' => 'problem',
            'food safety hazard' => 'food_safety_hazard',
            'what to do' => 'what_to_do',
        );
        foreach ($xpath->query('./div[contains(concat(" ", normalize-space(@class), " "), " recall-information__row ") or contains(concat(" ", normalize-space(@class), " "), " field ")]', $information) as $row) {
            $label = self::node_text($xpath, './*[contains(concat(" ", normalize-space(@class), " "), " recall-information__label ") or contains(concat(" ", normalize-space(@class), " "), " field-label ")]', $row);
            if (!$label) {
                continue;
            }
            $field_items = $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " field-items ")]/*[contains(concat(" ", normalize-space(@class), " "), " field-item ")]', $row);
            if ($field_items && $field_items->length > 1) {
                $value = array();
                foreach ($field_items as $field_item) {
                    $value[] = self::plain_text($field_item);
                }
            } else {
                $value_node = self::first_node($xpath, './*[contains(concat(" ", normalize-space(@class), " "), " recall-information__value ") or contains(concat(" ", normalize-space(@class), " "), " field-item ")]', $row);
                if (!$value_node && $field_items && $field_items->length) {
                    $value_node = $field_items->item(0);
                }
                $value = $value_node ? self::plain_text($value_node) : '';
            }
            $rows[] = array('label' => $label, 'value' => $value);
            $key = $keys[strtolower(trim($label, " :\t\n\r\0\x0B"))] ?? null;
            if ($key) {
                $values[$key] = $key === 'distributed_in' ? (array) $value : $value;
            }
        }

        // Older recall pages put these headings and their values in the summary.
        foreach (array('problem' => 'Problem', 'food_safety_hazard' => 'Food safety hazard', 'what_to_do' => 'What to do') as $key => $heading) {
            if ($values[$key] === null && $summary) {
                $values[$key] = self::summary_heading_value($summary, $heading);
            }
        }
        if ($values['date_markings'] === null && $summary) {
            $values['date_markings'] = self::summary_date_markings($summary);
        }
        if (!$values['problem']) {
            return new WP_Error('missing_problem', 'Problem could not be extracted');
        }

        $published = self::first_node($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " field-publish-date ")]//*[@id="publish-date"]//time', $article);
        $updated = self::first_node($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " recall-updated-date ")]//time', $article);
        $image = self::first_node($xpath, './/*[contains(concat(" ", normalize-space(@class), " "), " food-recall-image-display ")]//a[contains(concat(" ", normalize-space(@class), " "), " photoswipe ")]', $article);
        $further = self::first_node($xpath, './/section[contains(concat(" ", normalize-space(@class), " "), " further-information ")]//*[contains(concat(" ", normalize-space(@class), " "), " field-item ")]', $article);
        $notices = array();
        foreach ($xpath->query('.//a[contains(translate(@href, "PDF", "pdf"), ".pdf")]', $article) as $link) {
            $notices[] = array('title' => self::plain_text($link), 'url' => self::absolute_url($link->getAttribute('href')));
        }

        return array(
            'summary' => $summary ? self::plain_text($summary) : null,
            'recall_information' => $values,
            'recall_information_rows' => $rows,
            'further_information' => $further ? self::plain_text($further) : ($summary ? self::summary_heading_value($summary, 'For further information please contact') : null),
            'published_at' => $published ? $published->getAttribute('datetime') : null,
            'updated_at' => $updated ? $updated->getAttribute('datetime') : null,
            'full_image_url' => $image ? self::absolute_url($image->getAttribute('href')) : null,
            'recall_notices' => $notices,
        );
    }

    private static function summary_heading_value($summary, $heading) {
        foreach ($summary->childNodes as $child) {
            if (!($child instanceof DOMElement) || !preg_match('/^h[2-6]$/i', $child->tagName)) {
                continue;
            }
            $label = trim(preg_replace('/\s+/u', ' ', $child->textContent), " :\t\n\r\0\x0B");
            if (strcasecmp($label, $heading) !== 0) {
                continue;
            }
            for ($next = $child->nextSibling; $next; $next = $next->nextSibling) {
                if ($next instanceof DOMElement) {
                    return self::plain_text($next);
                }
            }
        }
        return null;
    }

    private static function summary_date_markings($summary) {
        foreach ($summary->childNodes as $child) {
            if (!($child instanceof DOMElement)) {
                continue;
            }
            $text = self::plain_text($child);
            if (preg_match('/^(?:date markings?|best before)\s*:/i', $text)) {
                return $text;
            }
        }
        return null;
    }

    private static function plain_text($node) {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->nodeValue;
            } elseif ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
                $text .= "\n";
            } elseif ($child instanceof DOMElement) {
                $text .= self::plain_text($child);
                if (preg_match('/^(p|div|h[1-6]|li)$/i', $child->tagName)) {
                    $text .= "\n";
                }
            }
        }
        $lines = preg_split('/\R/u', $text);
        $lines = array_map(function ($line) { return trim(preg_replace('/[\h]+/u', ' ', $line)); }, $lines);
        return trim(implode("\n", array_filter($lines, function ($line) { return $line !== ''; })));
    }

    private static function absolute_url($url) {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }
        return 'https://www.foodstandards.gov.au/' . ltrim($url, '/');
    }

    public static function admin_menu() {
        add_options_page('FSANZ Alerts', 'FSANZ Alerts', 'manage_options', 'fsanz-allergen-json', array(__CLASS__, 'admin_page'));
    }

    public static function admin_assets($hook) {
        if ($hook === 'settings_page_fsanz-allergen-json') {
            wp_enqueue_style('fsanz-allergen-admin', plugin_dir_url(FSANZ_ALLERGEN_PLUGIN_FILE) . 'styles/admin.css', array(), FSANZ_ALLERGEN_VERSION);
            wp_enqueue_script('fsanz-allergen-designer', plugin_dir_url(FSANZ_ALLERGEN_PLUGIN_FILE) . 'scripts/admin.js', array(), FSANZ_ALLERGEN_VERSION, true);
            wp_localize_script('fsanz-allergen-designer', 'FSANZAlertsDesigner', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fsanz_alerts_preview'),
            ));
        }
    }

    public static function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $feed = get_option(self::OPTION);
        $error = get_option(self::ERROR_OPTION);
        echo '<div class="wrap fsanz-alerts-admin"><h1 class="screen-reader-text">FSANZ Alerts</h1>';
        if ($error) {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . ' The last successful feed remains available.</p></div>';
        }
        if (isset($_GET['template_saved'])) {
            echo '<div class="notice notice-success"><p>HTML merge template saved.</p></div>';
        }
        echo '<header class="fsanz-alerts-hero"><div class="fsanz-alerts-hero-copy"><p class="fsanz-alerts-eyebrow">FOOD RECALL MONITORING</p><h2>FSANZ Alerts</h2><p>Undeclared allergen recalls, ready for review.</p></div>';
        echo '<span class="fsanz-alerts-version" aria-label="Version ' . esc_attr(FSANZ_ALLERGEN_VERSION) . '">v' . esc_html(FSANZ_ALLERGEN_VERSION) . '</span>';
        echo '<div class="fsanz-alerts-capabilities" aria-label="Capabilities"><span>' . (self::automation_enabled() ? 'Hourly refresh' : 'Automation paused') . '</span><span>Draft imports</span><span>Public JSON</span></div></header>';
        FSANZ_Alerts_Designer::render_status($feed);
        FSANZ_Alerts_Designer::render_admin($feed);
        echo '</div>';
    }

    public static function manual_refresh() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        check_admin_referer('fsanz_undeclared_allergen_refresh');
        self::refresh();
        wp_safe_redirect(admin_url('options-general.php?page=fsanz-allergen-json'));
        exit;
    }
}

