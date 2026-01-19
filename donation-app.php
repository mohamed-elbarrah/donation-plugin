<?php
/**
 * Plugin Name: Donation App
 * Description: A trusted digital donation platform that connects donors with verified humanitarian cases and charitable projects. made with ♥ by Mohamed ElBarrah.
 * Author: Mohamed ElBarrah
 * Version: 1.1.7
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('Donation_Campaigns')) {
    class Donation_Campaigns {
        public function __construct() {
            add_action('plugins_loaded', [$this, 'init']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        }

        public function init() {

            // categories shortcode (shows categories and products) — load regardless so shortcode is available
            require_once plugin_dir_path(__FILE__) . 'includes/categories.php';
            // card template used by lists
            require_once plugin_dir_path(__FILE__) . 'includes/card-template.php';
            // projects page + filter UI (shortcode)
            require_once plugin_dir_path(__FILE__) . 'includes/projects.php';
            // admin settings (toggle for currency symbol, etc.)
            require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

            if (!class_exists('WooCommerce')) return;

            require_once plugin_dir_path(__FILE__) . 'includes/product-fields.php';
            require_once plugin_dir_path(__FILE__) . 'includes/quick-donate.php';
            require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
            require_once plugin_dir_path(__FILE__) . 'includes/currency.php';
            // single product renderer
            require_once plugin_dir_path(__FILE__) . 'includes/single-product.php';
            // use donation card layout inside the shop loop for donation products
            require_once plugin_dir_path(__FILE__) . 'includes/shop-cards.php';
        }

        public function enqueue_styles() {
            // core color variables (designer-provided palette)
            $colors_css = plugin_dir_path(__FILE__) . 'assets/css/donation-colors.css';
            $colors_ver = file_exists($colors_css) ? filemtime($colors_css) : '1.0.0';
            wp_enqueue_style(
                'donation-colors',
                plugin_dir_url(__FILE__) . 'assets/css/donation-colors.css',
                [],
                $colors_ver
            );
            $cards_css = plugin_dir_path(__FILE__) . 'assets/css/donation-cards.css';
            $cards_ver = file_exists($cards_css) ? filemtime($cards_css) : '1.0.0';
            wp_enqueue_style(
                'donation-cards',
                plugin_dir_url(__FILE__) . 'assets/css/donation-cards.css',
                [],
                $cards_ver
            );
            wp_enqueue_style(
                'donation-currency-symbol',
                plugin_dir_url(__FILE__) . 'assets/css/currency-symbol.css',
                [],
                file_exists(plugin_dir_path(__FILE__) . 'assets/css/currency-symbol.css') ? filemtime(plugin_dir_path(__FILE__) . 'assets/css/currency-symbol.css') : '1.0.0'
            );
            // categories grid & cards styling
            wp_enqueue_style(
                'donation-categories',
                plugin_dir_url(__FILE__) . 'assets/css/donation-categories.css',
                [],
                file_exists(plugin_dir_path(__FILE__) . 'assets/css/donation-categories.css') ? filemtime(plugin_dir_path(__FILE__) . 'assets/css/donation-categories.css') : '1.0.0'
            );
            // cards behavior script (handles presets, donate link, add-to-cart)
            $cards_js = plugin_dir_path(__FILE__) . 'assets/js/donation-cards.js';
            $cards_js_ver = file_exists($cards_js) ? filemtime($cards_js) : '1.0.0';
            wp_enqueue_script(
                'donation-cards',
                plugin_dir_url(__FILE__) . 'assets/js/donation-cards.js',
                ['jquery'],
                $cards_js_ver,
                true
            );
            // pass useful URLs to the frontend script so it works correctly on any host/path
            $wc_ajax_base = home_url('/?wc-ajax=');
            $checkout_url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
            wp_localize_script('donation-cards', 'donation_cards_params', [
                'wc_ajax_url' => esc_url_raw($wc_ajax_base),
                'checkout_url' => esc_url_raw($checkout_url),
                'site_url' => esc_url_raw(home_url('/')),
            ]);
            // single product assets (only load on product pages)
            if (is_product()) {
                wp_enqueue_style(
                    'donation-single',
                    plugin_dir_url(__FILE__) . 'assets/css/single-product.css',
                    [],
                    file_exists(plugin_dir_path(__FILE__) . 'assets/css/single-product.css') ? filemtime(plugin_dir_path(__FILE__) . 'assets/css/single-product.css') : '1.0.0'
                );
                wp_enqueue_script(
                    'donation-single',
                    plugin_dir_url(__FILE__) . 'assets/js/single-product.js',
                    ['jquery'],
                    file_exists(plugin_dir_path(__FILE__) . 'assets/js/single-product.js') ? filemtime(plugin_dir_path(__FILE__) . 'assets/js/single-product.js') : '1.0.0',
                    true
                );
            }

            // Quick-donate floating button assets (site-wide) — only load when a product is marked as quick-donation
            if (function_exists('donation_quick_get_product_id') && donation_quick_get_product_id()) {
                $quick_css = plugin_dir_path(__FILE__) . 'assets/css/quick-donate.css';
                $quick_css_ver = file_exists($quick_css) ? filemtime($quick_css) : '1.0.0';
                wp_enqueue_style(
                    'donation-quick',
                    plugin_dir_url(__FILE__) . 'assets/css/quick-donate.css',
                    [],
                    $quick_css_ver
                );

                $quick_js = plugin_dir_path(__FILE__) . 'assets/js/quick-donate.js';
                $quick_js_ver = file_exists($quick_js) ? filemtime($quick_js) : '1.0.0';
                wp_enqueue_script(
                    'donation-quick',
                    plugin_dir_url(__FILE__) . 'assets/js/quick-donate.js',
                    ['jquery'],
                    $quick_js_ver,
                    true
                );
                wp_localize_script('donation-quick', 'donation_quick_params', [
                    'site_url' => esc_url_raw(home_url('/')),
                    'ajax_url' => esc_url_raw(admin_url('admin-ajax.php')),
                    'checkout_url' => esc_url_raw(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/')),
                ]);
            }
        }
    }
}

new Donation_Campaigns();
// Load Zakat include if present (registers admin page, shortcode, AJAX handlers)
if (file_exists(plugin_dir_path(__FILE__) . 'includes/zakat.php')) {
    require_once plugin_dir_path(__FILE__) . 'includes/zakat.php';
}

// Shortcode: Display upcoming services as cards
add_shortcode('donation_app_upcoming_services', 'donation_app_render_upcoming_services');

function donation_app_render_upcoming_services() {
    $services = [
        [
            'title' => 'الزكاة',
            'desc' => 'برنامج يتيح لك إمكانية حساب الزكاة بأنواعها المختلفة ودفعها عبر طرق سهلة وسريعة لتصل إلى مستحقيها.',
            'icon' => '<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#667eea"/><stop offset="100%" stop-color="#764ba2"/></linearGradient></defs><circle cx="16" cy="16" r="16" fill="url(#grad1)"/><path d="M8 20h16v4H8z" fill="white" opacity="0.9"/><path d="M10 20v-8l6-3 6 3v8" fill="white" opacity="0.8"/><circle cx="16" cy="16" r="2" fill="white"/></svg>',
            'badge_color' => '#6C63FF',
        ],
        [
            'title' => 'الإهداء',
            'desc' => 'خدمة لتقديم التبرعات عن الغير كهدية للأهل والأصدقاء، في مختلف المناسبات الاجتماعية.',
            'icon' => '<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#ff9a56"/><stop offset="100%" stop-color="#ff6b35"/></linearGradient></defs><circle cx="16" cy="16" r="16" fill="url(#grad2)"/><rect x="9" y="16" width="14" height="8" rx="2" fill="white" opacity="0.9"/><rect x="15" y="8" width="2" height="8" fill="white" opacity="0.8"/><path d="M12 16c0-2 2-4 4-4s4 2 4 4" stroke="white" stroke-width="2" fill="none" opacity="0.8"/></svg>',
            'badge_color' => '#FF9800',
        ],
        [
            'title' => 'غِراس',
            'desc' => 'خدمة تتيح لك نشر فرص التبرع عبر وسائل التواصل، وكسب نقاط لكل عملية تبرع حصلت من الاخرين عن طريق نشرك.',
            'icon' => '<svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="grad3" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#56ab2f"/><stop offset="100%" stop-color="#a8e6cf"/></linearGradient></defs><circle cx="16" cy="16" r="16" fill="url(#grad3)"/><path d="M16 22v-8" stroke="white" stroke-width="2" stroke-linecap="round"/><ellipse cx="13" cy="12" rx="3" ry="2" fill="white" opacity="0.8"/><ellipse cx="19" cy="14" rx="2.5" ry="1.5" fill="white" opacity="0.7"/><circle cx="16" cy="22" r="2" fill="white" opacity="0.6"/></svg>',
            'badge_color' => '#4CAF50',
        ],
    ];

    ob_start();
    ?>
    <style>
        .donation-app-services {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
            justify-content: center;
            margin: 40px 0 0 0;
        }
        .donation-app-card {
            background: var(--donation-surface);
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.07);
            padding: 0 0 24px 0;
            position: relative;
            text-align: right;
            overflow: hidden;
            border: 1px solid var(--donation-border);
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .donation-app-card:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,0.13);
            transform: translateY(-4px) scale(1.02);
        }
        .donation-app-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--donation-white);
            padding: 18px 24px 12px 24px;
            border-bottom: 1px solid var(--donation-border);
        }
        .donation-app-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .donation-app-badge {
            background: var(--badge-color, var(--donation-gold));
            color: var(--donation-white);
            padding: 6px 18px;
            border-radius: 24px;
            font-size: 1rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-right: 8px;
        }
        .donation-app-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--donation-blue);
            margin: 18px 24px 10px 24px;
        }
        .donation-app-desc {
            font-size: 1rem;
            color: #555;
            margin: 0 24px 0 24px;
            min-height: 56px;
            line-height: 1.7;
        }
    </style>
    <div class="donation-app-services">
        <?php foreach ($services as $service): ?>
            <div class="donation-app-card">
                <div class="donation-app-card-header">
                    <span class="donation-app-icon"><?php echo $service['icon']; ?></span>
                    <span class="donation-app-badge" style="background: <?php echo $service['badge_color']; ?>;">قريبًا</span>
                </div>
                <div class="donation-app-title"><?php echo $service['title']; ?></div>
                <div class="donation-app-desc"><?php echo $service['desc']; ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
