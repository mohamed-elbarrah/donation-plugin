<?php
if (!defined('ABSPATH')) {
    exit; // Protect from direct access
}

/**
 * AFFILIATE SYSTEM
 * - Relationship: affiliate <-> order only (no product-level relationship)
 * - Stores affiliate id in cookie + WC session for 30 days on arrival via ?ref=slug
 * - Records clicks and sales into a dedicated affiliate_log table
 * - Persists affiliate id onto orders during checkout/create
 * - Records sale on payment complete or when order status becomes processing/completed
 * - Prevents duplicate sale records
 * - Sanitizes inputs and uses dbDelta for table creation
 */

register_activation_hook(__FILE__, 'affiliate_sys_install');
function affiliate_sys_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'affiliate_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        affiliate_id BIGINT(20) NOT NULL,
        event_type VARCHAR(20) NOT NULL,
        order_id BIGINT(20) DEFAULT NULL,
        amount DOUBLE DEFAULT 0,
        time DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/* ------------------------------------------------------------------------
 * User profile fields for affiliate slug and commission
 * --------------------------------------------------------------------- */
add_action('show_user_profile', 'affiliate_sys_user_fields');
add_action('edit_user_profile', 'affiliate_sys_user_fields');
function affiliate_sys_user_fields($user) {
    $slug = esc_attr(get_the_author_meta('affiliate_slug', $user->ID));
    $commission = esc_attr(get_the_author_meta('affiliate_commission', $user->ID));
    ?>
    <h3>إعدادات نظام الإحالة</h3>
    <table class="form-table">
        <tr>
            <th><label for="affiliate_slug">Affiliate Slug</label></th>
            <td>
                <input type="text" name="affiliate_slug" id="affiliate_slug" value="<?php echo $slug; ?>" class="regular-text" />
                <p class="description">الرابط الكامل: <input type="text" id="affiliate_full_url" readonly class="regular-text" value="<?php echo esc_attr(home_url('/?ref=' . $slug)); ?>" style="width:60%;"/></p>
                <button type="button" class="button" id="copy_affiliate_url">نسخ الرابط</button>
            </td>
        </tr>
        <tr>
            <th><label for="affiliate_commission">Commission %</label></th>
            <td>
                <input type="number" name="affiliate_commission" id="affiliate_commission" value="<?php echo $commission; ?>" class="regular-text" step="0.01" min="0" max="100" />
                <p class="description">النسبة المئوية التي يحصل عليها المسوّق عن كل عملية ناجحة (0-100).</p>
            </td>
        </tr>
    </table>
    <script>
    (function(){
        var slug = document.getElementById('affiliate_slug');
        var url = document.getElementById('affiliate_full_url');
        var btn = document.getElementById('copy_affiliate_url');
        function update(){ if(!url || !slug) return; url.value = '<?php echo esc_js(home_url('/')); ?>' + (slug.value ? '?ref='+encodeURIComponent(slug.value) : ''); }
        if(slug) slug.addEventListener('input', update);
        if(btn) btn.addEventListener('click', function(){ if(!url) return; try{ navigator.clipboard.writeText(url.value); btn.textContent='تم النسخ'; setTimeout(function(){btn.textContent='نسخ الرابط';},2000); }catch(e){} });
    })();
    </script>
    <?php
}

add_action('personal_options_update', 'affiliate_sys_save_fields');
add_action('edit_user_profile_update', 'affiliate_sys_save_fields');
function affiliate_sys_save_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;
    if (isset($_POST['affiliate_slug'])) {
        update_user_meta($user_id, 'affiliate_slug', sanitize_text_field(wp_unslash($_POST['affiliate_slug'])));
    }
    if (isset($_POST['affiliate_commission'])) {
        $comm = floatval(wp_unslash($_POST['affiliate_commission']));
        $comm = max(0, min(100, $comm));
        update_user_meta($user_id, 'affiliate_commission', $comm);
    }
}

/* ------------------------------------------------------------------------
 * Click tracking: on arrival with ?ref=slug store cookie + WC session and log click
 * --------------------------------------------------------------------- */
add_action('template_redirect', 'affiliate_sys_track_click');
function affiliate_sys_track_click() {
    if (isset($_GET['ref'])) {
        $slug = sanitize_text_field(wp_unslash($_GET['ref']));
        if (empty($slug)) return;
        $users = get_users(array('meta_key' => 'affiliate_slug', 'meta_value' => $slug, 'number' => 1));
        if (empty($users)) return;
        $affiliate_id = intval($users[0]->ID);

        // Store in WooCommerce session (if available) for quick retrieval during checkout
        if (function_exists('WC')) {
            try {
                if (WC()->session && method_exists(WC()->session, 'set')) {
                    WC()->session->set('affiliate_ref', $affiliate_id);
                }
            } catch (Exception $e) {
                // ignore session problems
            }
        }

        // Set a 30-day cookie; use SameSite and Secure attributes where supported
        $expires = time() + (DAY_IN_SECONDS * 30);
        if (PHP_VERSION_ID >= 70300) {
            $opts = array('expires' => $expires, 'path' => '/', 'samesite' => 'Lax');
            if (is_ssl()) $opts['secure'] = true;
            setcookie('affiliate_ref', $affiliate_id, $opts);
        } else {
            setcookie('affiliate_ref', $affiliate_id, $expires, '/');
        }

        // Record the click in affiliate_log
        affiliate_sys_record_event($affiliate_id, 'click');
    }
}

/* ------------------------------------------------------------------------
 * Centralized recorder for clicks and sales
 * --------------------------------------------------------------------- */
function affiliate_sys_record_event($affiliate_id, $event_type = 'click', $order_id = null, $amount = 0.0) {
    $affiliate_id = intval($affiliate_id);
    if ($affiliate_id <= 0) return false;
    $event_type = sanitize_text_field($event_type);
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_log';
    $data = array(
        'affiliate_id' => $affiliate_id,
        'event_type' => $event_type,
        'order_id' => !empty($order_id) ? intval($order_id) : null,
        'amount' => floatval($amount),
    );
    $format = array('%d', '%s', '%d', '%f');
    // If order_id is null, provide null format
    if (empty($data['order_id'])) {
        $data['order_id'] = null;
        $format = array('%d', '%s', '%s', '%f');
    }
    $res = $wpdb->insert($table, $data, $format);
    return (bool) $res;
}

/* ------------------------------------------------------------------------
 * AJAX endpoint for cached pages: record click + set cookie/session
 * --------------------------------------------------------------------- */
add_action('wp_ajax_nopriv_affiliate_record_click', 'affiliate_sys_ajax_record_click');
add_action('wp_ajax_affiliate_record_click', 'affiliate_sys_ajax_record_click');
function affiliate_sys_ajax_record_click() {
    if (empty($_POST['ref'])) {
        wp_send_json_error(array('message' => 'missing_ref'));
    }
    $slug = sanitize_text_field(wp_unslash($_POST['ref']));
    if (empty($slug)) wp_send_json_error(array('message' => 'invalid_ref'));
    $users = get_users(array('meta_key' => 'affiliate_slug', 'meta_value' => $slug, 'number' => 1));
    if (empty($users)) wp_send_json_error(array('message' => 'invalid_ref'));
    $affiliate_id = intval($users[0]->ID);

    // set cookie
    $expires = time() + (DAY_IN_SECONDS * 30);
    if (PHP_VERSION_ID >= 70300) {
        $opts = array('expires' => $expires, 'path' => '/', 'samesite' => 'Lax');
        if (is_ssl()) $opts['secure'] = true;
        setcookie('affiliate_ref', $affiliate_id, $opts);
    } else {
        setcookie('affiliate_ref', $affiliate_id, $expires, '/');
    }

    // set WC session if available
    if (function_exists('WC')) {
        try { if (WC()->session && method_exists(WC()->session, 'set')) WC()->session->set('affiliate_ref', $affiliate_id); } catch (Exception $e) {}
    }

    $ok = affiliate_sys_record_event($affiliate_id, 'click');
    if ($ok) wp_send_json_success(array('message' => 'recorded'));
    wp_send_json_error(array('message' => 'db_error'));
}

/* ------------------------------------------------------------------------
 * Client-side beacon to notify server on cached pages (uses admin-ajax)
 * --------------------------------------------------------------------- */
add_action('wp_footer', 'affiliate_sys_print_click_beacon');
function affiliate_sys_print_click_beacon() {
    if (is_admin()) return;
    $ajax_url = esc_url_raw(admin_url('admin-ajax.php'));
    ?>
    <script>
    (function(){
        try{
            var params = new URLSearchParams(window.location.search);
            var ref = params.get('ref');
            if(!ref) return;
            // If cookie already present, skip sending beacon
            if(document.cookie && document.cookie.indexOf('affiliate_ref=') !== -1) return;
            var body = new URLSearchParams({action:'affiliate_record_click', ref: ref});
            if(navigator.sendBeacon){
                try{ navigator.sendBeacon('<?php echo $ajax_url; ?>', new Blob([body.toString()], {type:'application/x-www-form-urlencoded'})); }catch(e){ fetch('<?php echo $ajax_url; ?>',{method:'POST',credentials:'same-origin',body:body}).catch(function(){}); }
            } else {
                fetch('<?php echo $ajax_url; ?>',{method:'POST',credentials:'same-origin',body:body}).catch(function(){});
            }
        }catch(e){}
    })();
    </script>
    <?php
}

/* ------------------------------------------------------------------------
 * Persist affiliate on order: store _affiliate_ref during order creation and update
 * Hooks: woocommerce_checkout_create_order, woocommerce_checkout_update_order_meta
 * --------------------------------------------------------------------- */
add_action('woocommerce_checkout_create_order', 'affiliate_sys_store_affiliate_on_create_order', 20, 2);
function affiliate_sys_store_affiliate_on_create_order($order, $data) {
    if (!is_a($order, 'WC_Order')) return;
    $affiliate_id = 0;
    // Prefer session then cookie
    if (function_exists('WC')) {
        try { if (WC()->session && method_exists(WC()->session, 'get')) { $sid = WC()->session->get('affiliate_ref'); if ($sid) $affiliate_id = intval($sid); } } catch (Exception $e) {}
    }
    if (empty($affiliate_id) && isset($_COOKIE['affiliate_ref'])) {
        $affiliate_id = intval($_COOKIE['affiliate_ref']);
    }
    if ($affiliate_id) {
        $order->update_meta_data('_affiliate_ref', $affiliate_id);
    }
}

add_action('woocommerce_checkout_update_order_meta', 'affiliate_sys_store_affiliate_on_order');
function affiliate_sys_store_affiliate_on_order($order_id) {
    $affiliate_id = 0;
    if (isset($_COOKIE['affiliate_ref'])) $affiliate_id = intval($_COOKIE['affiliate_ref']);
    if (empty($affiliate_id) && function_exists('WC')) {
        try { if (WC()->session && method_exists(WC()->session, 'get')) { $sid = WC()->session->get('affiliate_ref'); if ($sid) $affiliate_id = intval($sid); } } catch (Exception $e) {}
    }
    if ($affiliate_id) update_post_meta($order_id, '_affiliate_ref', $affiliate_id);
}

/* ------------------------------------------------------------------------
 * Sale recording: record sale when payment completes or on status change
 * Use a single internal function to avoid duplication and ensure order meta is the source
 * Hooks: woocommerce_payment_complete, woocommerce_order_status_changed
 * --------------------------------------------------------------------- */
add_action('woocommerce_payment_complete', 'affiliate_sys_track_sale_payment_complete', 10, 1);
function affiliate_sys_track_sale_payment_complete($order_id) {
    affiliate_sys_process_order_sale($order_id, 'payment_complete');
}

add_action('woocommerce_order_status_changed', 'affiliate_sys_track_sale_status_changed', 10, 4);
function affiliate_sys_track_sale_status_changed($order_id, $old_status, $new_status, $order) {
    // when order moves into processing or completed, ensure sale is recorded
    if (in_array($new_status, array('processing', 'completed'), true)) {
        affiliate_sys_process_order_sale($order_id, 'status_changed');
    }
}

function affiliate_sys_process_order_sale($order_id, $source = '') {
    if (empty($order_id)) return;
    if (!function_exists('wc_get_order')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Read affiliate id strictly from order meta
    $affiliate_id = $order->get_meta('_affiliate_ref', true);
    if (empty($affiliate_id)) {
        // nothing to attribute
        return;
    }
    $affiliate_id = intval($affiliate_id);
    if ($affiliate_id <= 0) return;

    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_log';

    // prevent duplicate sale entries for same order
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE order_id = %d AND event_type = %s", $order_id, 'sale'));
    if ($exists) {
        return;
    }

    $amount = floatval($order->get_total());
    $ok = $wpdb->insert($table, array(
        'affiliate_id' => $affiliate_id,
        'event_type' => 'sale',
        'order_id' => intval($order_id),
        'amount' => $amount,
    ), array('%d','%s','%d','%f'));
    if ($ok) {
        // Optionally log for debug; remove in production
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("affiliate_sys: recorded sale for order {$order_id} affiliate {$affiliate_id} source={$source}");
        }
    }
}

/* ------------------------------------------------------------------------
 * Admin: menu + simple stats page (keeps code extensible for future dashboards)
 * --------------------------------------------------------------------- */
add_action('admin_menu', 'affiliate_sys_menu');
function affiliate_sys_menu() {
    add_menu_page('إحصائيات الإحالة', 'نظام الإحالة', 'manage_options', 'affiliate-stats', 'affiliate_sys_stats_page', 'dashicons-chart-line');
    add_submenu_page('affiliate-stats', 'إضافة مسوّق', 'إضافة مسوّق', 'manage_options', 'affiliate-add', 'affiliate_sys_add_page');
    add_submenu_page('affiliate-stats', 'إدارة المسوّقين', 'إدارة المسوّقين', 'manage_options', 'affiliate-manage', 'affiliate_sys_manage_page');
}

function affiliate_sys_add_page() {
    if (!current_user_can('manage_options')) return;
    if (!empty($_POST['affiliate_add_nonce'])) {
        if (!check_admin_referer('affiliate_add_action', 'affiliate_add_nonce')) {
            echo '<div class="notice notice-error"><p>Nonce verification failed.</p></div>';
        } else {
            $username = sanitize_user(wp_unslash($_POST['affiliate_username'] ?? ''));
            $email = sanitize_email(wp_unslash($_POST['affiliate_email'] ?? ''));
            $display = sanitize_text_field(wp_unslash($_POST['affiliate_display'] ?? ''));
            $slug = sanitize_text_field(wp_unslash($_POST['affiliate_slug'] ?? ''));
            $commission = isset($_POST['affiliate_commission']) ? floatval(wp_unslash($_POST['affiliate_commission'])) : 0;
            if (empty($username) || empty($email) || empty($slug)) {
                echo '<div class="notice notice-error"><p>Required fields missing.</p></div>';
            } elseif (username_exists($username) || email_exists($email)) {
                echo '<div class="notice notice-error"><p>Username or email already exists.</p></div>';
            } else {
                $password = wp_generate_password(12, false);
                $user_id = wp_create_user($username, $password, $email);
                if (is_wp_error($user_id)) {
                    echo '<div class="notice notice-error"><p>Unable to create user.</p></div>';
                } else {
                    wp_update_user(array('ID' => $user_id, 'display_name' => $display));
                    $u = new WP_User($user_id);
                    $u->set_role('subscriber');
                    update_user_meta($user_id, 'affiliate_slug', $slug);
                    $commission = max(0, min(100, $commission));
                    update_user_meta($user_id, 'affiliate_commission', $commission);
                    $full = esc_url(home_url('/?ref=' . rawurlencode($slug)));
                    echo '<div class="notice notice-success"><p>Affiliate created. <a href="user-edit.php?user_id=' . intval($user_id) . '">Edit user</a></p>';
                    echo '<p>Affiliate URL: <input type="text" readonly value="' . esc_attr($full) . '" style="width:50%;" /> <button class="button copy-new-affiliate" data-url="' . esc_attr($full) . '">Copy URL</button></p></div>';
                }
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>إضافة مسوّق جديد</h1>
        <form method="post">
            <?php wp_nonce_field('affiliate_add_action', 'affiliate_add_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="affiliate_username">Username</label></th>
                    <td><input name="affiliate_username" id="affiliate_username" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="affiliate_email">Email</label></th>
                    <td><input name="affiliate_email" id="affiliate_email" type="email" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="affiliate_display">Display Name</label></th>
                    <td><input name="affiliate_display" id="affiliate_display" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="affiliate_slug">Affiliate Slug</label></th>
                    <td>
                        <input name="affiliate_slug" id="affiliate_slug" class="regular-text" required>
                        <p class="description">مثال: <code>ahmed-mohamed</code></p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="affiliate_commission">Commission %</label></th>
                    <td><input name="affiliate_commission" id="affiliate_commission" type="number" step="0.01" min="0" max="100" value="0" class="regular-text"></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" class="button button-primary" value="Create Affiliate"></p>
        </form>
    </div>
    <script>
    (function(){
        var base = '<?php echo esc_js(home_url('/')); ?>';
        var slug = document.getElementById('affiliate_slug');
        var preview = document.getElementById('affiliate_preview');
        var previewBtn = document.getElementById('affiliate_preview_copy');
        function updatePreview(){ if(!preview) return; var s = slug?slug.value.trim():''; var url = s ? base + '?ref=' + encodeURIComponent(s) : ''; preview.value = url; if(previewBtn) previewBtn.setAttribute('data-url', url); }
        if(slug) { slug.addEventListener('input', updatePreview); updatePreview(); }
        function copyText(t){ if(!t) return; if(navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(t); var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(ta); return Promise.resolve(); }
        document.querySelectorAll('.copy-affiliate-url, .copy-new-affiliate').forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); var url = b.getAttribute('data-url'); if(!url){ var slugv = b.getAttribute('data-slug'); if(slugv) url = base + '?ref=' + encodeURIComponent(slugv); } if(!url) return; copyText(url).then(function(){ var old=b.textContent; b.textContent='Copied'; setTimeout(function(){b.textContent=old;},2000); }); }); });
    })();
    </script>
    <?php
}

function affiliate_sys_stats_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_log';
    echo '<div class="wrap"><h1>إحصائيات روابط الإحالة</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>المسوق</th><th>رابط الاحالة</th><th>المشاهدات (Clicks)</th><th>المبيعات الناجحة</th><th>نسبة التحويل (CR)</th><th>Commission %</th><th>صافي الأرباح</th><th>إجمالي المبيعات</th></tr></thead>';
    echo '<tbody>';
    $affiliates = get_users(array('meta_key' => 'affiliate_slug', 'meta_compare' => 'EXISTS', 'number' => -1));
    if (!empty($affiliates)) {
        foreach ($affiliates as $user) {
            $aid = intval($user->ID);
            $slug = get_user_meta($aid, 'affiliate_slug', true);
            $clicks = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE affiliate_id = %d AND event_type = %s", $aid, 'click')));
            $sales = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE affiliate_id = %d AND event_type = %s", $aid, 'sale')));
            $revenue = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE affiliate_id = %d AND event_type = %s", $aid, 'sale')));
            $cr = ($clicks > 0) ? round(($sales / $clicks) * 100, 2) : 0;
            $commission = get_user_meta($aid, 'affiliate_commission', true);
            $commission = ($commission === '' || $commission === false) ? 0 : floatval($commission);
            $net_profit = round( floatval($revenue) * ($commission / 100.0), 2 );
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            $full = esc_url(home_url('/?ref=' . rawurlencode($slug)));
            echo '<td><code>' . esc_html($slug) . '</code> <button class="button copy-affiliate-url" data-url="' . esc_attr($full) . '">Copy</button></td>';
            echo '<td>' . esc_html($clicks) . '</td>';
            echo '<td>' . esc_html($sales) . '</td>';
            echo '<td>' . esc_html($cr) . '%</td>';
            echo '<td>' . esc_html(number_format_i18n($commission, 2)) . '%</td>';
            echo '<td>' . (function_exists('wc_price') ? wc_price($net_profit) : esc_html(number_format_i18n($net_profit, 2))) . '</td>';
            echo '<td>' . (function_exists('wc_price') ? wc_price($revenue) : esc_html(number_format_i18n($revenue, 2))) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7">لا توجد مسوّقين مسجلين بعد.</td></tr>';
    }
    echo '</tbody></table></div>';
    ?>
    <script>
    (function(){
        function copyText(t){ if(!t) return; if(navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(t); var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(ta); }
        document.querySelectorAll('.copy-affiliate-url, .copy-new-affiliate').forEach(function(b){ b.addEventListener('click', function(e){ e.preventDefault(); var url = b.getAttribute('data-url'); if(!url) return; copyText(url).then? (function(){ var old=b.textContent; b.textContent='Copied'; setTimeout(function(){b.textContent=old;},2000); }): void 0; }); });
    })();
    </script>
    <?php
}

/**
 * Admin: Manage affiliates page
 * - Lists all users that have `affiliate_slug` meta
 * - Allows quick edit link and revoke (remove affiliate status)
 */
function affiliate_sys_manage_page() {
    if (!current_user_can('manage_options')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'affiliate_log';

    // Handle actions (revoke)
    if (!empty($_GET['action']) && !empty($_GET['uid'])) {
        $action = sanitize_text_field(wp_unslash($_GET['action']));
        $uid = intval($_GET['uid']);
        if ($action === 'revoke') {
            // verify nonce
            if (!empty($_REQUEST['_wpnonce']) && check_admin_referer('affiliate_manage_action')) {
                delete_user_meta($uid, 'affiliate_slug');
                delete_user_meta($uid, 'affiliate_commission');
                echo '<div class="notice notice-success"><p>تم سحب حالة المسوّق للمستخدم #' . intval($uid) . '.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>فشل التحقق (nonce).</p></div>';
            }
        }
    }

    echo '<div class="wrap"><h1>إدارة المسوّقين</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>المسوق</th><th>البريد</th><th>المعرف (Slug)</th><th>Commission %</th><th>Clicks</th><th>Sales</th><th>صافي الأرباح</th><th>Revenue</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    $affiliates = get_users(array('meta_key' => 'affiliate_slug', 'meta_compare' => 'EXISTS', 'number' => -1));
    if (!empty($affiliates)) {
        foreach ($affiliates as $user) {
            $aid = intval($user->ID);
            $slug = get_user_meta($aid, 'affiliate_slug', true);
            $commission = get_user_meta($aid, 'affiliate_commission', true);
            $commission = ($commission === '' || $commission === false) ? 0 : floatval($commission);
            $clicks = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE affiliate_id = %d AND event_type = %s", $aid, 'click')));
            $sales = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE affiliate_id = %d AND event_type = %s", $aid, 'sale')));

            $revenue = floatval($wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$table} WHERE affiliate_id = %d AND event_type = %s", $aid, 'sale')));

            $net_profit = round( floatval($revenue) * ($commission / 100.0), 2 );
            // build action links
            $edit_link = admin_url('user-edit.php?user_id=' . $aid);
            $nonce = wp_create_nonce('affiliate_manage_action');
            $revoke_link = add_query_arg(array('page' => 'affiliate-manage', 'action' => 'revoke', 'uid' => $aid, '_wpnonce' => $nonce), admin_url('admin.php'));

            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . ' (ID ' . $aid . ')</td>';
            echo '<td>' . esc_html($user->user_email) . '</td>';
            echo '<td><code>' . esc_html($slug) . '</code></td>';
            echo '<td>' . esc_html(number_format_i18n($commission, 2)) . '%</td>';
            echo '<td>' . esc_html($clicks) . '</td>';
            echo '<td>' . esc_html($sales) . '</td>';
            echo '<td>' . (function_exists('wc_price') ? wc_price($net_profit) : esc_html(number_format_i18n($net_profit, 2))) . '</td>';
            echo '<td>' . (function_exists('wc_price') ? wc_price($revenue) : esc_html(number_format_i18n($revenue, 2))) . '</td>';
            echo '<td><a class="button" href="' . esc_url($edit_link) . '">Edit</a> ';
            echo '<a class="button" href="' . esc_url($revoke_link) . '" onclick="return confirm(\'Revoke affiliate status?\')">Revoke</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="8">لا توجد مسوّقين مسجلين.</td></tr>';
    }

    echo '</tbody></table></div>';
}


