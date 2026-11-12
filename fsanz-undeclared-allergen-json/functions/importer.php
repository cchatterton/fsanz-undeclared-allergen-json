<?php
if (!defined('ABSPATH')) { exit; }

final class FSANZ_Alerts_Importer {
    const POST_TYPE = 'alert';
    const TEMPLATE_OPTION = 'fsanz_alerts_html_template';
    const STATUS_OPTION = 'fsanz_alerts_import_status';
    const CRON = 'fsanz_alerts_nightly_import';
    const SOURCE_META = '_fsanz_alert_source_url';
    const LOCK_OPTION = 'fsanz_alerts_import_lock';
    private static $registered_by_us = false;

    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'), 0);
        add_action('init', array(__CLASS__, 'ensure_schedule'));
        add_action(self::CRON, array(__CLASS__, 'nightly_import'));
        add_action('admin_post_fsanz_alerts_import_next', array(__CLASS__, 'manual_import_next'));
    }

    public static function activate() {
        self::register_post_type();
        self::ensure_schedule();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON);
        if (self::$registered_by_us && post_type_exists(self::POST_TYPE)) {
            unregister_post_type(self::POST_TYPE);
        }
        flush_rewrite_rules();
    }

    public static function register_post_type() {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => 'Alerts',
                'singular_name' => 'Alert',
                'add_new_item' => 'Add New Alert',
                'edit_item' => 'Edit Alert',
                'all_items' => 'All Alerts',
            ),
            'public' => true,
            'show_in_rest' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'alerts'),
            'menu_icon' => 'dashicons-warning',
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail'),
        ));
        self::$registered_by_us = true;
    }

    public static function ensure_schedule() {
        if (!wp_next_scheduled(self::CRON)) {
            self::schedule_next();
        }
    }

    private static function schedule_next() {
        $now = new DateTimeImmutable('now', wp_timezone());
        $next = $now->setTime(2, 0);
        if ($next->getTimestamp() <= $now->getTimestamp()) {
            $next = $next->modify('+1 day');
        }
        wp_schedule_single_event($next->getTimestamp(), self::CRON);
    }

    public static function nightly_import() {
        try {
            self::run(0, true, 'overnight');
        } finally {
            self::ensure_schedule();
        }
    }

    public static function manual_import_next() {
        if (!current_user_can('manage_options')) {
            wp_die('Not allowed.');
        }
        check_admin_referer('fsanz_alerts_import_next');
        self::run(1, false, 'manual');
        wp_safe_redirect(admin_url('options-general.php?page=fsanz-allergen-json'));
        exit;
    }

    public static function saved_template() {
        $template = get_option(self::TEMPLATE_OPTION, '');
        return is_string($template) ? $template : '';
    }

    public static function existing_post_id($url) {
        $ids = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::SOURCE_META,
            'meta_value' => $url,
            'suppress_filters' => true,
        ));
        return $ids ? (int) $ids[0] : 0;
    }

    public static function missing_count($feed) {
        if (!is_array($feed) || !isset($feed['items']) || !is_array($feed['items'])) {
            return 0;
        }
        $count = 0;
        foreach ($feed['items'] as $item) {
            if (isset($item['url']) && !self::existing_post_id($item['url'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Import up to $limit missing records. Zero imports all missing records.
     */
    public static function run($limit, $refresh, $trigger) {
        $status = array(
            'ran_at' => gmdate('c'),
            'trigger' => $trigger,
            'imported' => 0,
            'skipped' => 0,
            'post_id' => 0,
            'title' => '',
            'error' => '',
        );
        $lock_time = (int) get_option(self::LOCK_OPTION, 0);
        if ($lock_time && $lock_time < time() - 3600) {
            delete_option(self::LOCK_OPTION);
        }
        if (!add_option(self::LOCK_OPTION, time(), '', false)) {
            $status['error'] = 'An import is already running.';
            return $status;
        }
        try {
            $template = self::saved_template();
            if ($template === '') {
                $status['error'] = 'Save an HTML merge template before importing.';
                return $status;
            }
            $feed = get_option('fsanz_undeclared_allergen_feed');
            $generated = is_array($feed) && !empty($feed['generated_at']) ? strtotime($feed['generated_at']) : false;
            if ($refresh && (!$generated || $generated < time() - DAY_IN_SECONDS)) {
                if (!FSANZ_Undeclared_Allergen_JSON::refresh()) {
                    $status['error'] = 'The FSANZ feed refresh failed. No records were imported.';
                    return $status;
                }
                $feed = get_option('fsanz_undeclared_allergen_feed');
            }
            if (!is_array($feed) || !isset($feed['items']) || !is_array($feed['items'])) {
                $status['error'] = 'No successful JSON feed is available yet.';
                return $status;
            }
            foreach ($feed['items'] as $item) {
                if (empty($item['url']) || empty($item['title'])) {
                    $status['error'] = 'A feed record is missing its URL or title.';
                    break;
                }
                if (self::existing_post_id($item['url'])) {
                    $status['skipped']++;
                    continue;
                }
                $rendered = FSANZ_Alerts_Designer::render_template($template, $feed, $item);
                if ($rendered['unknown']) {
                    $status['error'] = 'The template contains unknown tokens: ' . implode(', ', $rendered['unknown']);
                    break;
                }
                if (trim($rendered['html']) === '') {
                    $status['error'] = 'The HTML merge template rendered empty content.';
                    break;
                }
                $post_id = wp_insert_post(array(
                    'post_type' => self::POST_TYPE,
                    'post_status' => 'publish',
                    'post_title' => $item['title'],
                    'post_content' => "<!-- wp:html -->\n" . $rendered['html'] . "\n<!-- /wp:html -->",
                ), true);
                if (is_wp_error($post_id)) {
                    $status['error'] = $post_id->get_error_message();
                    break;
                }
                update_post_meta($post_id, self::SOURCE_META, $item['url']);
                update_post_meta($post_id, '_fsanz_alert_recall_date', isset($item['date_iso']) ? $item['date_iso'] : '');
                update_post_meta($post_id, '_fsanz_alert_json', wp_json_encode($item));
                $status['imported']++;
                $status['post_id'] = $post_id;
                $status['title'] = $item['title'];
                if ($limit > 0 && $status['imported'] >= $limit) {
                    break;
                }
            }
            return $status;
        } finally {
            update_option(self::STATUS_OPTION, $status, false);
            delete_option(self::LOCK_OPTION);
        }
    }
}
