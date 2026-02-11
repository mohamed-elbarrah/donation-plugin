<?php
if (!defined('ABSPATH')) exit;

/**
 * Donation App - Site Activity Statistics Tracker
 * Responsibilities:
 * - Create a scalable custom table to store lightweight event rows
 * - Track page views and unique sessions (via cookie)
 * - Track checkout starts (abandoned checkout candidates)
 * - Track completed orders via WooCommerce hooks
 * - Expose an AJAX endpoint for admin pages to query aggregated stats
 *
 * Implementation notes:
 * - Uses a single events table `{$wpdb->prefix}donation_app_stats` with an indexed
 *   `event_type` and `created_at` columns for fast aggregation.
 * - For very high-traffic sites consider adding an aggregation worker that
 *   batches events into daily aggregates instead of inserting every page view.
 */

global $donation_app_stats_included;
$donation_app_stats_included = true;

class Donation_App_Stats {
    const COOKIE_NAME = 'donation_app_sid';
    const COOKIE_TTL = DAY_IN_SECONDS * 30; // 30 days

    public static function init() {
        add_action('template_redirect', [__CLASS__, 'maybe_track_page_view'], 0);

        // WooCommerce hooks
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'record_checkout_start']);
        add_action('woocommerce_thankyou', [__CLASS__, 'record_order_complete'], 10, 1);
        add_action('woocommerce_order_status_completed', [__CLASS__, 'record_order_complete_on_change'], 10, 1);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'record_order_complete_on_change'], 10, 1);

        // Admin AJAX: only available to privileged users (admins)
        add_action('wp_ajax_donation_app_get_stats', [__CLASS__, 'ajax_get_stats']);
    }

    /**
     * Create a stable session id for the visitor and return it.
     */
    public static function get_or_set_session_id() {
        if (isset($_COOKIE[self::COOKIE_NAME]) && is_string($_COOKIE[self::COOKIE_NAME])) {
            return sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]));
        }
        $sid = wp_generate_password(40, false, false);
        $secure = is_ssl();
        setcookie(self::COOKIE_NAME, $sid, time() + self::COOKIE_TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', $secure, true);
        // Also populate PHP superglobal so same-request reads work
        $_COOKIE[self::COOKIE_NAME] = $sid;
        return $sid;
    }

    /**
     * Lightweight page view tracking.
     * Avoid heavy work on REST/ADMIN/AJAX requests.
     */
    public static function maybe_track_page_view() {
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST') && REST_REQUEST) return;

        $sid = self::get_or_set_session_id();
        $url = ( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
        $ref = isset($_SERVER['HTTP_REFERER']) ? wp_get_raw_referer() : '';

        $event = [
            'event_type' => 'page_view',
            'session_id' => $sid,
            'user_id' => get_current_user_id() ?: null,
            'url' => esc_url_raw($url),
            'referrer' => esc_url_raw($ref),
        ];

        self::record_event($event);

        // If this is checkout page (customer reached checkout) record checkout_start
        if (function_exists('is_checkout') && is_checkout() && !is_order_received_page()) {
            self::record_event([
                'event_type' => 'checkout_start',
                'session_id' => $sid,
                'user_id' => get_current_user_id() ?: null,
                'url' => esc_url_raw($url),
            ]);
        }
    }

    /**
     * Called when an order is completed via thankyou hook.
     */
    public static function record_order_complete($order_id) {
        if (!$order_id) return;
        $order_id = absint($order_id);
        $sid = isset($_COOKIE[self::COOKIE_NAME]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME])) : null;
        self::record_event([
            'event_type' => 'order_complete',
            'session_id' => $sid,
            'user_id' => get_current_user_id() ?: null,
            'order_id' => $order_id,
        ]);
    }

    public static function record_order_complete_on_change($order_id) {
        self::record_order_complete($order_id);
    }

    /**
     * Called when customer reaches the checkout form (hooked to WooCommerce).
     * Accepts the optional `$checkout` object passed by WooCommerce.
     */
    public static function record_checkout_start($checkout = null) {
        $sid = self::get_or_set_session_id();
        $url = ( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

        self::record_event([
            'event_type' => 'checkout_start',
            'session_id' => $sid,
            'user_id' => get_current_user_id() ?: null,
            'url' => esc_url_raw($url),
        ]);
    }

    /**
     * Generic DB insert for lightweight events.
     * Accepts keys: event_type, session_id, user_id, url, referrer, order_id, product_id
     */
    public static function record_event($data = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_app_stats';

        $allowed = [
            'event_type' => '',
            'session_id' => '',
            'user_id' => null,
            'url' => '',
            'referrer' => '',
            'order_id' => null,
            'product_id' => null,
            'meta' => null,
        ];

        $row = array_intersect_key($data, $allowed) + $allowed;

        $insert = [
            'event_type' => sanitize_text_field($row['event_type']),
            'session_id' => isset($row['session_id']) ? sanitize_text_field($row['session_id']) : null,
            'user_id' => $row['user_id'] ? absint($row['user_id']) : null,
            'url' => isset($row['url']) ? esc_url_raw($row['url']) : null,
            'referrer' => isset($row['referrer']) ? esc_url_raw($row['referrer']) : null,
            'order_id' => $row['order_id'] ? absint($row['order_id']) : null,
            'product_id' => $row['product_id'] ? absint($row['product_id']) : null,
            'meta' => $row['meta'] ? wp_json_encode($row['meta']) : null,
            'created_at' => current_time('mysql', 1),
        ];

        $formats = ['%s','%s','%d','%s','%s','%d','%d','%s','%s'];
        // Use $wpdb->insert (safe, uses prepared statements)
        $wpdb->insert($table, $insert, $formats);
    }

    /**
     * AJAX endpoint for admin to fetch aggregated stats.
     * Expects: period=daily|weekly|monthly|yearly (defaults to monthly)
     */
    public static function ajax_get_stats() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }

        $period = isset($_GET['period']) ? sanitize_key($_GET['period']) : 'monthly';
        $allowed = ['daily','weekly','monthly','yearly'];
        if (!in_array($period, $allowed, true)) $period = 'monthly';

        $data = self::get_aggregated_stats($period);
        wp_send_json_success($data);
    }

    /**
     * Aggregate stats for the requested period.
     * Returns keys: total_visits, unique_visits, checkout_starts, abandoned_checkouts, orders
     */
    public static function get_aggregated_stats($period = 'monthly') {
        global $wpdb;
        $table = $wpdb->prefix . 'donation_app_stats';

        // Determine date range
        // Use GMT timestamp to match events stored with GMT `created_at`
        $now = current_time('timestamp', 1);
        switch ($period) {
            case 'daily':
                $start = date('Y-m-d 00:00:00', $now);
                break;
            case 'weekly':
                $start = date('Y-m-d 00:00:00', strtotime('-7 days', $now));
                break;
            case 'yearly':
                $start = date('Y-01-01 00:00:00', $now);
                break;
            case 'monthly':
            default:
                $start = date('Y-m-01 00:00:00', $now);
                break;
        }

        // Let $wpdb->prepare handle escaping of the date string
        $results = [];

        // total visits
        $results['total_visits'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= %s", 'page_view', $start));

        // unique visits by session
        $results['unique_visits'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(session_id,''), 'anon')) FROM {$table} WHERE event_type = %s AND created_at >= %s", 'page_view', $start));

        // checkout starts
        $results['checkout_starts'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_type = %s AND created_at >= %s", 'checkout_start', $start));

        // successful orders (processing|completed) — approximate by counting order_complete events
        $results['orders'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT order_id) FROM {$table} WHERE event_type = %s AND created_at >= %s", 'order_complete', $start));

        // abandoned checkouts = checkout_starts - orders (per session)
        // compute sessions that started checkout and did not produce an order in range
        $abandoned_sql = "SELECT COUNT(DISTINCT cs.session_id) FROM (SELECT session_id FROM {$table} WHERE event_type='checkout_start' AND created_at >= %s) cs LEFT JOIN (SELECT DISTINCT session_id FROM {$table} WHERE event_type='order_complete' AND created_at >= %s) os ON cs.session_id = os.session_id WHERE os.session_id IS NULL";
        $results['abandoned_checkouts'] = (int) $wpdb->get_var($wpdb->prepare($abandoned_sql, $start, $start));

        return $results;
    }

}

// Activation: create events table
function donation_app_stats_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'donation_app_stats';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      event_type VARCHAR(32) NOT NULL,
      session_id VARCHAR(120) DEFAULT NULL,
      user_id BIGINT(20) DEFAULT NULL,
      url TEXT DEFAULT NULL,
      referrer TEXT DEFAULT NULL,
      order_id BIGINT(20) DEFAULT NULL,
      product_id BIGINT(20) DEFAULT NULL,
      meta LONGTEXT DEFAULT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY  (id),
      KEY event_type (event_type),
      KEY session_id (session_id(60)),
      KEY created_at (created_at),
      KEY order_id (order_id)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Bootstrap
add_action('plugins_loaded', function() {
    if (class_exists('Donation_App_Stats')) {
        Donation_App_Stats::init();
    }
});
