<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('donation_app_get_collected_amount')) {
    function donation_app_get_collected_amount($product_id)
    {
        $manual = floatval(get_post_meta($product_id, '_donation_collected', true));
        $sum = 0.0;
        $statuses = ['completed', 'processing', 'on-hold'];

        $orders = wc_get_orders([
            'limit' => -1,
            'status' => $statuses,
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id) {
                    $don = $item->get_meta('donation_amount');
                    if (is_numeric($don)) $sum += floatval($don);
                }
            }
        }

        return $manual + $sum;
    }
}

/**
 * New fields for Product
 */
add_action('woocommerce_product_options_general_product_data', function () {

    woocommerce_wp_text_input([
        'id' => '_donation_target',
        'label' => 'Donation Target',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01'],
    ]);

    woocommerce_wp_text_input([
        'id' => '_donation_collected',
        'label' => 'Manual Amount Collected (starting)',
        'type' => 'number',
        'description' => 'Optional starting amount you want to show; actual collected donations are summed from orders below.',
        'custom_attributes' => ['step' => '0.01'],
    ]);

    woocommerce_wp_text_input([
        'id' => '_donation_location',
        'label' => 'Location',
        'type' => 'text',
    ]);

    woocommerce_wp_text_input([
        'id' => '_donation_badge',
        'label' => 'Badge Text (optional)',
        'type' => 'text',
        'description' => 'e.g. Urgent, Medical, Housing',
    ]);

    woocommerce_wp_text_input([
        'id' => '_donation_presets',
        'label' => 'Preset donation amounts (comma separated)',
        'type' => 'text',
        'description' => 'Example: 10,50,100 — values should be numbers without currency symbols',
    ]);

    // Mark this product as available for the quick-donation floating button
    woocommerce_wp_checkbox([
        'id' => '_quick_donation',
        'label' => 'Quick Donate (تبرع سريع)',
        'description' => 'Enable this product to be used as the quick donation target for the site-wide floating button.',
    ]);

    // Donation mode: تبرع (direct donate) or وقف (add to cart)
    woocommerce_wp_select([
        'id' => '_donation_mode',
        'label' => 'نوع المنتج',
        'options' => [
            'wakf' => 'وقف (أضف للسلة)',
            'donation' => 'تبرع (تحويل مباشر إلى الدفع)'
        ],
        'description' => 'اختر ما إذا كان المنتج للتبرع مباشرة أو كوقف يُضاف للسلة',
    ]);

    // Display calculated collected amount (read-only): manual meta + donations from orders
    global $post;
    if (!empty($post->ID)) {
        $calculated = donation_app_get_collected_amount($post->ID);
        echo '<p class="form-field">
            <label>' . esc_html__('Amount Collected (calculated)', 'donation-app') . '</label>
            <span class="amount-collected">' . wc_price($calculated) . '</span>
        </p>';
    }
});

/**
 * Save fields
 */
add_action('woocommerce_admin_process_product_object', function ($product) {
    $fields = [
        '_donation_target',
        '_donation_collected',
        '_donation_location',
        '_donation_badge',
        '_donation_mode',
        '_donation_presets',
        '_quick_donation'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $product->update_meta_data($field, sanitize_text_field($_POST[$field]));
        }
    }
});


/**
 * When adding to cart, capture `donation_amount` from request and store in cart item data
 */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if (isset($_REQUEST['donation_amount']) && is_numeric($_REQUEST['donation_amount'])) {
        $amount = floatval($_REQUEST['donation_amount']);
        if ($amount > 0) {
            $cart_item_data['donation_amount'] = $amount;
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
    }
    return $cart_item_data;
}, 10, 3);


/**
 * Show donation amount in cart item meta
 */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (isset($cart_item['donation_amount'])) {
        $product_id = 0;
        if (isset($cart_item['product_id'])) {
            $product_id = intval($cart_item['product_id']);
        } elseif (isset($cart_item['data']) && method_exists($cart_item['data'], 'get_id')) {
            $product_id = intval($cart_item['data']->get_id());
        }

        $mode = $product_id ? get_post_meta($product_id, '_donation_mode', true) : '';
        $key = ($mode === 'wakf') ? 'اختر مبلغ المساهمة' : 'مبلغ التبرع';

        $item_data[] = array(
            'key' => $key,
            'value' => wc_price($cart_item['donation_amount'])
        );
    }
    return $item_data;
}, 10, 2);


/**
 * Set cart item price to the donation amount before totals calculation
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['donation_amount']) && $cart_item['donation_amount'] > 0) {
            $price = floatval($cart_item['donation_amount']);
            $cart_item['data']->set_price($price);
        }
    }
});
