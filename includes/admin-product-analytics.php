<?php
if (!defined('ABSPATH')) exit;

/**
 * Product Orders Analytics — Admin
 * - Registers submenu under Platform Statistics
 * - Provides AJAX endpoints for DataTables server-side processing
 * - Uses WooCommerce analytics tables for performance
 */

class Donation_Product_Analytics {
    private $nonce_action = 'donation_product_analytics_nonce';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_donation_product_analytics_list', [$this, 'ajax_products_list']);
        add_action('wp_ajax_donation_product_orders_list', [$this, 'ajax_product_orders_list']);
        add_action('wp_ajax_donation_product_analytics_diag', [$this, 'ajax_diag']);
    }

    public function add_admin_menu() {
        $cap = 'manage_woocommerce';
        // parent slug is donation-app-stats (added in admin-stats.php)
        add_submenu_page(
            'donation-app-stats',
            'Product Orders Analytics',
            'Product Orders Analytics',
            $cap,
            'donation-product-analytics',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_assets($hook) {
        // sometimes $hook can vary; check substring
        if (false === strpos($hook, 'donation-product-analytics')) return;

        // Register DataTables (CDN) and enqueue
        wp_register_style('datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], null);
        wp_register_script('datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], null, true);
        wp_enqueue_style('datatables-css');
        wp_enqueue_script('datatables-js');

        wp_enqueue_style('donation-analytics', plugin_dir_url(__FILE__) . '../assets/css/product-analytics.css', [], file_exists(plugin_dir_path(__FILE__) . '../assets/css/product-analytics.css') ? filemtime(plugin_dir_path(__FILE__) . '../assets/css/product-analytics.css') : null);

        wp_enqueue_script('donation-analytics-dt', plugin_dir_url(__FILE__) . '../assets/js/product-analytics.js', ['jquery','datatables-js'], file_exists(plugin_dir_path(__FILE__) . '../assets/js/product-analytics.js') ? filemtime(plugin_dir_path(__FILE__) . '../assets/js/product-analytics.js') : null, true);

        wp_localize_script('donation-analytics-dt', 'donation_analytics_params', [
            'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce' => wp_create_nonce($this->nonce_action),
        ]);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }
        include plugin_dir_path(__FILE__) . 'templates/product-analytics-page.php';
    }

    private function parse_date_range() {
        $start = isset($_REQUEST['start_date']) ? sanitize_text_field($_REQUEST['start_date']) : '';
        $end = isset($_REQUEST['end_date']) ? sanitize_text_field($_REQUEST['end_date']) : '';
        $start_ts = $end_ts = null;
        if ($start) $start_ts = date('Y-m-d 00:00:00', strtotime($start));
        if ($end) $end_ts = date('Y-m-d 23:59:59', strtotime($end));
        return [$start_ts, $end_ts];
    }

    public function ajax_products_list() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('permission');
        check_ajax_referer($this->nonce_action, 'nonce');

        global $wpdb;
        $prefix = $wpdb->prefix;
        $opl = $prefix . 'wc_order_product_lookup';
        $os = $prefix . 'wc_order_stats';
        $posts = $wpdb->posts;

        // DataTables params
        $draw = intval($_REQUEST['draw'] ?? 0);
        $start = intval($_REQUEST['start'] ?? 0);
        $length = intval($_REQUEST['length'] ?? 25);
        $search = sanitize_text_field($_REQUEST['search']['value'] ?? '');

        list($start_date, $end_date) = $this->parse_date_range();
        $where_date = '';
        if ($start_date && $end_date) {
            $where_date = $wpdb->prepare("AND os.date_created BETWEEN %s AND %s", $start_date, $end_date);
        } elseif ($start_date) {
            $where_date = $wpdb->prepare("AND os.date_created >= %s", $start_date);
        } elseif ($end_date) {
            $where_date = $wpdb->prepare("AND os.date_created <= %s", $end_date);
        }

        $search_sql = '';
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_sql = $wpdb->prepare("AND (p.post_title LIKE %s OR opl.product_id = %s)", $like, $search);
        }

        // Build base query: aggregate by product_id
        $select = "SELECT opl.product_id AS product_id, p.post_title AS product_name, 
            SUM(opl.product_net_revenue) AS product_net_revenue, 
            SUM(opl.product_qty) AS qty_sold,
            COUNT(DISTINCT CASE WHEN os.status = 'completed' THEN opl.order_id END) AS completed_orders,
            COUNT(DISTINCT CASE WHEN os.status = 'processing' THEN opl.order_id END) AS processing_orders,
            COUNT(DISTINCT CASE WHEN os.status = 'failed' THEN opl.order_id END) AS failed_orders,
            COUNT(DISTINCT CASE WHEN os.status = 'cancelled' THEN opl.order_id END) AS cancelled_orders,
            COUNT(DISTINCT CASE WHEN os.status = 'refunded' THEN opl.order_id END) AS refunded_orders
            FROM {$opl} opl
            JOIN {$os} os ON opl.order_id = os.order_id
            JOIN {$posts} p ON p.ID = opl.product_id
            WHERE 1=1 {$where_date} {$search_sql}
            GROUP BY opl.product_id";

        // Count total distinct products matching filters
        $count_sql = "SELECT COUNT(*) FROM (SELECT 1 FROM {$opl} opl JOIN {$os} os ON opl.order_id = os.order_id JOIN {$posts} p ON p.ID = opl.product_id WHERE 1=1 {$where_date} {$search_sql} GROUP BY opl.product_id) tmp";
        $recordsTotal = intval($wpdb->get_var($count_sql));

        // Ordering
        $order_col = 'product_net_revenue';
        if (isset($_REQUEST['order'][0]['column'])) {
            $col_idx = intval($_REQUEST['order'][0]['column']);
            $cols = ['product_name','product_net_revenue','qty_sold','completed_orders','processing_orders','failed_orders','cancelled_orders','refunded_orders'];
            $order_col = isset($cols[$col_idx]) ? $cols[$col_idx] : $order_col;
        }
        $order_dir = (isset($_REQUEST['order'][0]['dir']) && in_array($_REQUEST['order'][0]['dir'], ['asc','desc'])) ? $_REQUEST['order'][0]['dir'] : 'desc';

        $sql = $select . " ORDER BY " . esc_sql($order_col) . " " . esc_sql($order_dir) . " LIMIT %d, %d";
        $prepared = $wpdb->prepare($sql, $start, $length);

        // Cache key based on query params
        $cache_key = 'don_analytics_products_' . md5($prepared);
        $rows = get_transient($cache_key);
        if ($rows === false) {
            $rows = $wpdb->get_results($prepared, ARRAY_A);
            set_transient($cache_key, $rows, MINUTE_IN_SECONDS * 5);
        }

        // diagnostic log when no rows returned (helps debugging empty UI)
        if (empty($rows) && $recordsTotal === 0) {
            error_log('[donation-analytics] products query returned 0 rows; count_sql=' . $count_sql);
        }

        $data = [];
        foreach ($rows as $r) {
            $product_link = get_edit_post_link($r['product_id']);
            $view_btn = '<button class="button donation-analytics-view-orders" data-product-id="' . esc_attr($r['product_id']) . '">View Orders</button>';
            $data[] = [
                'product' => sprintf('<a href="%s" target="_blank">%s</a>', esc_url($product_link), esc_html($r['product_name'])),
                'revenue' => function_exists('wc_price') ? wc_price(floatval($r['product_net_revenue'])) : number_format_i18n(floatval($r['product_net_revenue']), 2),
                'qty' => intval($r['qty_sold']),
                'completed' => intval($r['completed_orders']),
                'processing' => intval($r['processing_orders']),
                'failed' => intval($r['failed_orders']),
                'cancelled' => intval($r['cancelled_orders']),
                'refunded' => intval($r['refunded_orders']),
                'actions' => $view_btn,
            ];
        }

        wp_send_json_success([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data,
        ]);
    }

    public function ajax_update_order_status() {
        // Removed: this endpoint previously updated WooCommerce order statuses.
        wp_send_json_error('disabled');
    }

    public function ajax_product_orders_list() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('permission');
        check_ajax_referer($this->nonce_action, 'nonce');

        global $wpdb;
        $prefix = $wpdb->prefix;
        $opl = $prefix . 'wc_order_product_lookup';
        $os = $prefix . 'wc_order_stats';
        $postmeta = $wpdb->postmeta;

        $product_id = intval($_REQUEST['product_id'] ?? 0);
        if (!$product_id) wp_send_json_error('missing_product');

        $draw = intval($_REQUEST['draw'] ?? 0);
        $start = intval($_REQUEST['start'] ?? 0);
        $length = intval($_REQUEST['length'] ?? 25);
        $status_filter = sanitize_text_field($_REQUEST['status'] ?? '');
        list($start_date, $end_date) = $this->parse_date_range();

        $where = $wpdb->prepare(" AND opl.product_id = %d", $product_id);
        if ($start_date && $end_date) {
            $where .= $wpdb->prepare(" AND os.date_created BETWEEN %s AND %s", $start_date, $end_date);
        }
        if ($status_filter) {
            $where .= $wpdb->prepare(" AND os.status = %s", $status_filter);
        }

        // Count
        $count_sql = "SELECT COUNT(DISTINCT opl.order_id) FROM {$opl} opl JOIN {$os} os ON opl.order_id = os.order_id WHERE 1=1 " . $where;
        $recordsTotal = intval($wpdb->get_var($count_sql));

        $sql = "SELECT DISTINCT opl.order_id AS order_id, os.status AS status, os.date_created AS date_created, 
            (SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = opl.order_id AND pm.meta_key = '_billing_email' LIMIT 1) AS billing_email,
            (SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = opl.order_id AND pm.meta_key = '_billing_first_name' LIMIT 1) AS billing_first_name,
            (SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = opl.order_id AND pm.meta_key = '_billing_last_name' LIMIT 1) AS billing_last_name,
            (SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = opl.order_id AND pm.meta_key = '_billing_country' LIMIT 1) AS billing_country,
            (SELECT pm.meta_value FROM {$postmeta} pm WHERE pm.post_id = opl.order_id AND pm.meta_key = '_payment_method' LIMIT 1) AS payment_method,
            (SELECT SUM(product_qty) FROM {$opl} opl2 WHERE opl2.order_id = opl.order_id AND opl2.product_id = %d) AS qty_in_order,
            (SELECT SUM(product_net_revenue) FROM {$opl} opl3 WHERE opl3.order_id = opl.order_id AND opl3.product_id = %d) AS product_subtotal,
            (SELECT pm2.meta_value FROM {$postmeta} pm2 WHERE pm2.post_id = opl.order_id AND pm2.meta_key = '_order_total' LIMIT 1) AS order_total,
            (SELECT pm3.meta_value FROM {$postmeta} pm3 WHERE pm3.post_id = opl.order_id AND pm3.meta_key = '_order_currency' LIMIT 1) AS order_currency
            FROM {$opl} opl
            JOIN {$os} os ON opl.order_id = os.order_id
            WHERE 1=1 " . $where . " GROUP BY opl.order_id ORDER BY os.date_created DESC LIMIT %d, %d";

        $prepared = $wpdb->prepare($sql, $product_id, $product_id, $start, $length);

        $rows = $wpdb->get_results($prepared, ARRAY_A);

        $data = [];
        foreach ($rows as $r) {
            $order_id = intval($r['order_id']);
            $customer = trim($r['billing_first_name'] . ' ' . $r['billing_last_name']);
            if (!$customer) $customer = $r['billing_email'] ?: __('Guest', 'donation-app');
            $actions = sprintf('<a href="%s" class="button" target="_blank">Edit</a>', esc_url(get_edit_post_link($order_id)));
            $data[] = [
            'order' => sprintf('<a href="%s" target="_blank">#%d</a>', esc_url(get_edit_post_link($order_id)), $order_id),
            'order_id' => $order_id,
                'customer' => esc_html($customer),
            'status' => esc_html($r['status']),
                'qty' => intval($r['qty_in_order']),
                'subtotal' => function_exists('wc_price') ? wc_price(floatval($r['product_subtotal'])) : number_format_i18n(floatval($r['product_subtotal']), 2),
                'total' => function_exists('wc_price') ? wc_price(floatval($r['order_total'])) : number_format_i18n(floatval($r['order_total']), 2),
                'payment' => esc_html($r['payment_method']),
                'date' => esc_html($r['date_created']),
                'action' => $actions,
            ];
        }

        wp_send_json_success([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data,
        ]);
    }

    public function ajax_diag() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('permission');
        check_ajax_referer($this->nonce_action, 'nonce');

        global $wpdb;
        $prefix = $wpdb->prefix;
        $opl = $prefix . 'wc_order_product_lookup';
        $os = $prefix . 'wc_order_stats';

        $res = [];
        // check tables exist
        $tables = [$opl, $os];
        foreach ($tables as $t) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
            $res['tables'][$t] = $exists ? 'exists' : 'missing';
            if ($exists) {
                $res['counts'][$t] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$t}"));
                $res['sample_' . $t] = $wpdb->get_row("SELECT * FROM {$t} ORDER BY 1 DESC LIMIT 1", ARRAY_A);
            } else {
                $res['counts'][$t] = 0;
            }
        }

        // earliest and latest order dates from stats
        if ($res['counts'][$os] > 0) {
            $res['earliest'] = $wpdb->get_var("SELECT MIN(date_created) FROM {$os}");
            $res['latest'] = $wpdb->get_var("SELECT MAX(date_created) FROM {$os}");
        }

        wp_send_json_success($res);
    }
}

new Donation_Product_Analytics();
