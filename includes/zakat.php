<?php
if (!defined('ABSPATH')) exit;

class Zakat_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu() {
        add_menu_page('حاسبة الزكاة', 'حاسبة الزكاة', 'manage_options', 'zakat-calculator', [$this, 'settings_page'], 'dashicons-calculator', 60);
    }

    public function register_settings() {
        register_setting('zakat_options', 'zakat_gold_nisab');
        register_setting('zakat_options', 'zakat_gold_rate');
        register_setting('zakat_options', 'zakat_cash_nisab');
        register_setting('zakat_options', 'zakat_cash_rate');
        register_setting('zakat_options', 'zakat_silver_nisab');
        register_setting('zakat_options', 'zakat_silver_rate');
        register_setting('zakat_options', 'zakat_stocks_nisab');
        register_setting('zakat_options', 'zakat_stocks_rate');

        add_settings_section('zakat_main', '', null, 'zakat-calculator');

        add_settings_field('zakat_gold_nisab', 'نصاب الذهب', function() {
            $v = esc_attr(get_option('zakat_gold_nisab', '85'));
            echo "<input type=\"number\" name=\"zakat_gold_nisab\" value=\"$v\" step=\"0.01\"> غرام";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_gold_rate', 'نسبة الزكاة (الذهب)', function() {
            $v = esc_attr(get_option('zakat_gold_rate', '2.5'));
            echo "<input type=\"number\" name=\"zakat_gold_rate\" value=\"$v\" step=\"0.01\"> %";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_cash_nisab', 'نصاب النقود (بالعملة المحلية)', function() {
            $v = esc_attr(get_option('zakat_cash_nisab', '1000'));
            echo "<input type=\"number\" name=\"zakat_cash_nisab\" value=\"$v\" step=\"0.01\">";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_cash_rate', 'نسبة الزكاة (النقود)', function() {
            $v = esc_attr(get_option('zakat_cash_rate', '2.5'));
            echo "<input type=\"number\" name=\"zakat_cash_rate\" value=\"$v\" step=\"0.01\"> %";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_silver_nisab', 'نصاب الفضة', function() {
            $v = esc_attr(get_option('zakat_silver_nisab', '595'));
            echo "<input type=\"number\" name=\"zakat_silver_nisab\" value=\"$v\" step=\"0.01\"> غرام";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_silver_rate', 'نسبة الزكاة (الفضة)', function() {
            $v = esc_attr(get_option('zakat_silver_rate', '2.5'));
            echo "<input type=\"number\" name=\"zakat_silver_rate\" value=\"$v\" step=\"0.01\"> %";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_stocks_nisab', 'نصاب الأسهم (قيمة نقدية)', function() {
            $v = esc_attr(get_option('zakat_stocks_nisab', '1000'));
            echo "<input type=\"number\" name=\"zakat_stocks_nisab\" value=\"$v\" step=\"0.01\">";
        }, 'zakat-calculator', 'zakat_main');

        add_settings_field('zakat_stocks_rate', 'نسبة الزكاة (الأسهم)', function() {
            $v = esc_attr(get_option('zakat_stocks_rate', '2.5'));
            echo "<input type=\"number\" name=\"zakat_stocks_rate\" value=\"$v\" step=\"0.01\"> %";
        }, 'zakat-calculator', 'zakat_main');
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>حاسبة الزكاة</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('zakat_options');
                do_settings_sections('zakat-calculator');
                submit_button('حفظ الإعدادات');
                ?>
            </form>
        </div>
        <?php
    }
}

class Zakat_Logic {
    public static function calculate($amount, $type = 'cash') {
        $type = in_array($type, ['gold','silver','stocks','cash']) ? $type : 'cash';

        $defaults = [
            'gold' => ['nisab' => 85, 'rate' => 2.5],
            'silver' => ['nisab' => 595, 'rate' => 2.5],
            'cash' => ['nisab' => 1000, 'rate' => 2.5],
            'stocks' => ['nisab' => 1000, 'rate' => 2.5],
        ];

        $nisab = floatval(get_option("zakat_{$type}_nisab", $defaults[$type]['nisab']));
        $rate = floatval(get_option("zakat_{$type}_rate", $defaults[$type]['rate'])) / 100.0;

        $amount = floatval($amount);
        $due = 0.0;
        $is_due = false;
        if ($amount >= $nisab) {
            $due = round($amount * $rate, 2);
            $is_due = true;
        }

        return [
            'amount' => $amount,
            'nisab' => $nisab,
            'rate_percent' => $rate * 100,
            'due' => $due,
            'is_due' => $is_due,
            'type' => $type,
        ];
    }
}

// Enqueue front assets and localize AJAX URL
function zakat_enqueue_assets() {
    $plugin_url = plugin_dir_url(__FILE__);
    // assets are in parent folder's assets directory
    $css = $plugin_url . '../assets/css/zakat.css';
    $js = $plugin_url . '../assets/js/zakat.js';

    if (file_exists(plugin_dir_path(__FILE__) . '../assets/css/zakat.css')) {
        wp_enqueue_style('donation-zakat', $css, [], filemtime(plugin_dir_path(__FILE__) . '../assets/css/zakat.css'));
    }
    if (file_exists(plugin_dir_path(__FILE__) . '../assets/js/zakat.js')) {
        wp_enqueue_script('donation-zakat', $js, ['jquery'], file_exists(plugin_dir_path(__FILE__) . '../assets/js/zakat.js') ? filemtime(plugin_dir_path(__FILE__) . '../assets/js/zakat.js') : '1.0.0', true);
        wp_localize_script('donation-zakat', 'zakat_params', [
            'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
        ]);
    }
}
add_action('wp_enqueue_scripts', 'zakat_enqueue_assets');

// Shortcode for frontend calculator
function zakat_frontend_html() {
    ob_start();
    ?>
    <div class="zakat-container">
        <div class="zakat-tabs">
                <button class="zakat-tab-btn active" data-type="cash">المال</button>
                <button class="zakat-tab-btn" data-type="gold">الذهب</button>
                <button class="zakat-tab-btn" data-type="silver">الفضة</button>
                <button class="zakat-tab-btn" data-type="stocks">الأسهم</button>
            </div>
        <div class="zakat-form-box">
            <label>المبلغ</label>
            <input id="zakat-value" type="number" step="0.01" placeholder="أدخل المبلغ">
            <button id="zakat-calc-btn" class="button">احسب</button>
            <div id="zakat-result" class="zakat-result-box"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('zakat_calculator', 'zakat_frontend_html');

// AJAX handler
function handle_zakat_ajax() {
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $type = isset($_POST['type']) && $_POST['type'] === 'gold' ? 'gold' : 'cash';

    $res = Zakat_Logic::calculate($amount, $type);
    wp_send_json_success($res);
}
add_action('wp_ajax_calculate_zakat', 'handle_zakat_ajax');
add_action('wp_ajax_nopriv_calculate_zakat', 'handle_zakat_ajax');

// Initialize admin
if (is_admin()) {
    new Zakat_Admin();
}
