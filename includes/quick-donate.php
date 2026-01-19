<?php
if (!defined('ABSPATH')) exit;

/**
 * Helpers for the quick-donate floating button
 */
function donation_quick_get_product_id() {
    if (!class_exists('WC_Product')) return false;
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => '_quick_donation',
                'value' => 'yes',
                'compare' => '='
            ]
        ]
    ];
    $posts = get_posts($args);
    if (empty($posts)) return false;
    return (int) $posts[0]->ID;
}

function donation_quick_get_product_data() {
    $id = donation_quick_get_product_id();
    if (!$id) return false;
    $product = wc_get_product($id);
    if (!$product) return false;

    $presets_raw = get_post_meta($id, '_donation_presets', true);
    $presets = [];
    if (!empty($presets_raw)) {
        $parts = array_filter(array_map('trim', explode(',', $presets_raw)));
        foreach ($parts as $p) {
            if (is_numeric($p)) $presets[] = floatval($p);
        }
    }

    return [
        'id' => $id,
        'title' => get_the_title($id),
        'permalink' => get_permalink($id),
        'presets' => $presets,
        'currency' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : get_woocommerce_currency(),
    ];
}

// Print inline JS data in the footer so frontend can populate the quick UI
add_action('wp_footer', function(){
    $data = donation_quick_get_product_data();
    if (!$data) return;
    // include donation mode so frontend can adapt labels if needed
    $mode = get_post_meta($data['id'], '_donation_mode', true);
    if (!$mode) $mode = 'wakf';
    $data['mode'] = $mode;
    $export = wp_json_encode($data);
    echo "\n<script>window.donation_quick_product = $export;</script>\n";
    // Render server-side quick-donate root so currency symbol (and initial markup)
    // match the card template output and are available before JS runs.
    $symbol = function_exists('get_woocommerce_currency_symbol') ? esc_html( get_woocommerce_currency_symbol() ) : esc_html( get_woocommerce_currency() );
    // Minimal server-rendered markup mirrors the JS template so attachHandlers() will attach to it.
    // mode-aware strings
    $panel_label = ($mode === 'donation') ? 'تبرع سريع' : 'وقف سريع';
    $amount_label = ($mode === 'donation') ? 'مبلغ التبرع' : 'مبلغ الوقف';
    $donate_btn_label = ($mode === 'donation') ? 'تبرع الآن' : 'أضف للسلة';

    echo "\n<div id=\"donation-quick-root\" class=\"donation-quick-btn\">\n  <div class=\"donation-quick-backdrop hidden\" aria-hidden=\"true\"></div>\n  <div class=\"donation-quick-panel hidden\" role=\"dialog\" aria-hidden=\"true\" aria-label=\"" . esc_attr($panel_label) . "\">\n    <div class=\"panel-inner\">\n      <div class=\"panel-header\" style=\"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px\">\n        <div class=\"card-title\" style=\"margin:0;font-weight:800;font-size:14px\">" . esc_html($panel_label) . "</div>\n        <button class=\"quick-close\" aria-label=\"اغلاق\">اغلاق</button>\n      </div>\n      <p class=\"panel-help\">اختر نوع التبرع الذي تريد اجراءه</p>\n      <div class=\"preset-amounts types\" aria-label=\"نوع التبرع\">\n        <button class=\"preset-amount\" data-type=\"general\"><span class=\"preset-value\">وجبات الافطار</span><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span></button>\n        <button class=\"preset-amount\" data-type=\"zakat\"><span class=\"preset-value\">سقيا الماء</span><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span></button>\n        <button class=\"preset-amount\" data-type=\"masajid\"><span class=\"preset-value\">المصاحف</span><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span></button>\n        <button class=\"preset-amount\" data-type=\"neediest\"><span class=\"preset-value\">تمور المطاف</span><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span></button>\n      </div>\n      <div class=\"preset-amounts presets\" aria-label=\"مبالغ سريعة\">\n        <button class=\"preset-amount\" data-amount=\"10\"><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span><span class=\"preset-value\">10</span><span class=\"preset-side preset-left\"><span class=\"preset-currency\">" . $symbol . "</span></span></button>\n        <button class=\"preset-amount\" data-amount=\"50\"><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span><span class=\"preset-value\">50</span><span class=\"preset-side preset-left\"><span class=\"preset-currency\">" . $symbol . "</span></span></button>\n        <button class=\"preset-amount\" data-amount=\"100\"><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span><span class=\"preset-value\">100</span><span class=\"preset-side preset-left\"><span class=\"preset-currency\">" . $symbol . "</span></span></button>\n        <button class=\"preset-amount preset-other\" data-amount=\"\"><span class=\"preset-value\">مبلغ مخصص</span><span class=\"preset-side preset-right\"><span class=\"preset-check\">✓</span></span></button>\n      </div>\n      <div class=\"card-actions\" style=\"margin-top:8px\">\n        <div class=\"currency-input\">\n          <span class=\"currency-symbol\">" . $symbol . "</span>\n          <input class=\"amount-input\" type=\"number\" placeholder=\"" . esc_attr($amount_label) . "\" aria-label=\"" . esc_attr($amount_label) . "\" />\n        </div>\n        <button class=\"donate-btn\">" . esc_html($donate_btn_label) . "</button>\n      </div>\n    </div>\n  </div>\n  <div class=\"quick-toggle\" aria-controls=\"donation-quick-root\" aria-expanded=\"false\">\n    <span class=\"icon\">\n      <svg class=\"quick-toggle-icon\" width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.6\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\" focusable=\"false\">\n        <path d=\"M12 5v14\"></path>\n        <path d=\"M5 12h14\"></path>\n      </svg>\n    </span>\n    <span class=\"label\">" . esc_html($panel_label) . "</span>\n  </div>\n</div>\n";
});


/**
 * If the quick-donate UI redirected to checkout with product_id & donation_amount,
 * add the product to cart programmatically (avoid duplicates) then redirect to checkout.
 */
add_action('template_redirect', function(){
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) return;
    if (!function_exists('WC')) return;
    if (empty($_GET['product_id']) || empty($_GET['donation_amount'])) return;

    $product_id = intval($_GET['product_id']);
    $amount = floatval($_GET['donation_amount']);
    if ($product_id <= 0 || $amount <= 0) return;

    $cart = WC()->cart;
    if (!$cart) return;

    // Check if cart already contains the same donation product with same amount
    $exists = false;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['product_id']) && intval($cart_item['product_id']) === $product_id) {
            if (isset($cart_item['donation_amount']) && floatval($cart_item['donation_amount']) === $amount) {
                $exists = true;
                break;
            }
        }
    }

    if (!$exists) {
        $cart_item_data = ['donation_amount' => $amount];
        $cart->add_to_cart($product_id, 1, 0, [], $cart_item_data);
    }

    // Redirect to checkout without the query parameters
    $checkout = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
    wp_safe_redirect(remove_query_arg(['product_id','donation_amount'] , $checkout));
    exit;
});


/**
 * Exclude quick-donation products from public product archives / shop loops
 */
add_action('pre_get_posts', function($query){
    if (is_admin() || !$query->is_main_query()) return;

    // Only target product archives / shop / taxonomy / search where product lists appear
    if ( ! (is_shop() || is_post_type_archive('product') || is_tax('product_cat') || is_tax('product_tag') || is_search()) ) return;

    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => '_quick_donation',
                'value' => 'yes',
                'compare' => '='
            ]
        ]
    ];
    $posts = get_posts($args);
    if (empty($posts)) return;

    $exclude = array_map('intval', $posts);
    $existing = $query->get('post__not_in');
    if (!is_array($existing)) $existing = [];
    $existing = array_merge($existing, $exclude);
    $query->set('post__not_in', $existing);
});


/**
 * Also exclude quick-donation products from WooCommerce product queries
 * This catches loops that use WooCommerce query objects (widgets, shortcodes, templates).
 */
add_action('woocommerce_product_query', function($q){
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => '_quick_donation',
                'value' => 'yes',
                'compare' => '='
            ]
        ]
    ];
    $posts = get_posts($args);
    if (empty($posts)) return;
    $exclude = array_map('intval', $posts);
    $existing = $q->get('post__not_in');
    if (!is_array($existing)) $existing = [];
    $existing = array_merge($existing, $exclude);
    $q->set('post__not_in', $existing);
}, 10, 1);

?>
