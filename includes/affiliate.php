<?php


if (!defined('ABSPATH')) exit; // حماية الملف من الدخول المباشر

// 1. إنشاء جداول قاعدة البيانات عند تفعيل الإضافة
register_activation_hook(__FILE__, 'affiliate_sys_install');
function affiliate_sys_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'affiliate_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        affiliate_id bigint(20) NOT NULL,
        event_type varchar(20) NOT NULL, -- 'click' or 'sale'
        order_id bigint(20) DEFAULT NULL,
        amount float DEFAULT 0,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 2. إضافة حقل "المعرف" في بروفايل المستخدم
add_action('show_user_profile', 'affiliate_sys_user_fields');
add_action('edit_user_profile', 'affiliate_sys_user_fields');
function affiliate_sys_user_fields($user) {
    ?>
    <h3>إعدادات نظام الإحالة</h3>
    <table class="form-table">
        <tr>
            <th><label for="affiliate_slug">Affiliate ID</label></th>
            <td>
                <input type="text" name="affiliate_slug" id="affiliate_slug" value="<?php echo esc_attr(get_the_author_meta('affiliate_slug', $user->ID)); ?>" class="regular-text" />
                <div style="margin-top:8px;">
                    <label for="affiliate_full_url"><strong class="text-rghit">الرابط الكامل للإحالة</strong></label><br/>
                    <input type="text" id="affiliate_full_url" class="regular-text" readonly value="<?php echo esc_attr(home_url('/?ref=' . get_the_author_meta('affiliate_slug', $user->ID))); ?>" />
                    <button type="button" class="button" id="copy_affiliate_url" style="margin-left:8px;">نسخ الرابط</button>
                    <p class="description" id="affiliate_url_note" style="margin-top:6px;">انسخ الرابط لتشاركه مع الآخرين.</p>
                </div>
            </td>
        </tr>
    </table>

    <script type="text/javascript">
    (function(){
        var slugInput = document.getElementById('affiliate_slug');
        var urlInput = document.getElementById('affiliate_full_url');
        var copyBtn = document.getElementById('copy_affiliate_url');

        function updateUrl() {
            var slug = (slugInput && slugInput.value) ? slugInput.value.trim() : '';
            var base = '<?php echo esc_js(home_url('/')); ?>';
            var full = base + (slug ? ('?ref=' + encodeURIComponent(slug)) : '');
            if (urlInput) urlInput.value = full;
        }

        if (slugInput) {
            slugInput.addEventListener('input', updateUrl);
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function(e){
                e.preventDefault();
                if (!urlInput) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(urlInput.value).then(function(){
                        copyBtn.textContent = 'تم النسخ';
                        setTimeout(function(){ copyBtn.textContent = 'نسخ الرابط'; }, 2000);
                    });
                } else {
                    urlInput.select();
                    try { document.execCommand('copy'); copyBtn.textContent = 'تم النسخ'; setTimeout(function(){ copyBtn.textContent = 'نسخ الرابط'; }, 2000); } catch(e) {}
                }
            });
        }
    })();
    </script>
    <?php
}

add_action('personal_options_update', 'affiliate_sys_save_fields');
add_action('edit_user_profile_update', 'affiliate_sys_save_fields');
function affiliate_sys_save_fields($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'affiliate_slug', sanitize_text_field($_POST['affiliate_slug']));
    }
}

// 3. تتبع النقرات (Tracking Clicks)
add_action('template_redirect', 'affiliate_sys_track_click');
function affiliate_sys_track_click() {
    if (isset($_GET['ref'])) {
        $slug = sanitize_text_field($_GET['ref']);
        
        // البحث عن المستخدم صاحب هذا المعرف
        $users = get_users(array(
            'meta_key' => 'affiliate_slug',
            'meta_value' => $slug,
            'number' => 1
        ));

        if (!empty($users)) {
            $affiliate_id = $users[0]->ID;

            // ضبط الكوكيز لمدة 30 يوم
            if (!isset($_COOKIE['affiliate_ref'])) {
                setcookie('affiliate_ref', $affiliate_id, time() + (86400 * 30), "/");

                // تسجيل النقرة في قاعدة البيانات
                global $wpdb;
                $wpdb->insert($wpdb->prefix . 'affiliate_log', array(
                    'affiliate_id' => $affiliate_id,
                    'event_type' => 'click'
                ));
            }
        }
    }
}

// 4. تتبع المبيعات الناجحة (Tracking Sales)
add_action('woocommerce_order_status_completed', 'affiliate_sys_track_sale');
function affiliate_sys_track_sale($order_id) {
    // Try cookie first, fallback to order meta (stored at checkout)
    $affiliate_id = 0;
    if (isset($_COOKIE['affiliate_ref'])) {
        $affiliate_id = intval($_COOKIE['affiliate_ref']);
    }

    if (empty($affiliate_id)) {
        $meta = get_post_meta($order_id, '_affiliate_ref', true);
        if ($meta) $affiliate_id = intval($meta);
    }

    if (empty($affiliate_id)) {
        return; // no affiliate associated with this order
    }

    if (!function_exists('wc_get_order')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    global $wpdb;
    // التأكد من عدم تسجيل نفس الطلب مرتين
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}affiliate_log WHERE order_id = %d", $order_id));

    if (!$exists) {
        $wpdb->insert($wpdb->prefix . 'affiliate_log', array(
            'affiliate_id' => $affiliate_id,
            'event_type' => 'sale',
            'order_id' => $order_id,
            'amount' => $order->get_total()
        ));
    }
}

// Store affiliate id to order meta during checkout so we don't rely on cookie later
add_action('woocommerce_checkout_update_order_meta', 'affiliate_sys_store_affiliate_on_order');
function affiliate_sys_store_affiliate_on_order($order_id) {
    if (isset($_COOKIE['affiliate_ref'])) {
        $affiliate_id = intval($_COOKIE['affiliate_ref']);
        if ($affiliate_id) {
            update_post_meta($order_id, '_affiliate_ref', $affiliate_id);
        }
    }
}

// 5. لوحة تحكم الآدمن والإحصائيات
add_action('admin_menu', 'affiliate_sys_menu');
function affiliate_sys_menu() {
    add_menu_page('إحصائيات الإحالة', 'نظام الإحالة', 'manage_options', 'affiliate-stats', 'affiliate_sys_stats_page', 'dashicons-chart-line');
}

function affiliate_sys_stats_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'affiliate_log';
    
    // جلب بيانات مجمعة لكل مسوق
    $results = $wpdb->get_results("
        SELECT affiliate_id, 
        SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as total_clicks,
        SUM(CASE WHEN event_type = 'sale' THEN 1 ELSE 0 END) as total_sales,
        SUM(amount) as total_revenue
        FROM $table_name 
        GROUP BY affiliate_id
    ");

    echo '<div class="wrap"><h1>إحصائيات روابط الإحالة</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>المسوق</th><th>المعرف (Slug)</th><th>المشاهدات (Clicks)</th><th>المبيعات الناجحة</th><th>نسبة التحويل (CR)</th><th>إجمالي المبيعات</th></tr></thead>';
    echo '<tbody>';

    if ($results) {
        foreach ($results as $row) {
            $user_info = get_userdata($row->affiliate_id);
            $slug = get_user_meta($row->affiliate_id, 'affiliate_slug', true);
            $cr = ($row->total_clicks > 0) ? round(($row->total_sales / $row->total_clicks) * 100, 2) : 0;
            
            echo "<tr>
                <td>{$user_info->display_name}</td>
                <td><code>{$slug}</code></td>
                <td>{$row->total_clicks}</td>
                <td>{$row->total_sales}</td>
                <td>{$cr}%</td>
                <td>" . wc_price($row->total_revenue) . "</td>
            </tr>";
        }
    } else {
        echo '<tr><td colspan="6">لا توجد بيانات متاحة بعد.</td></tr>';
    }

    echo '</tbody></table></div>';
}