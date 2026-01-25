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
    global $post;

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

    // Preset donation amounts are rendered below as repeatable label/value pairs

    $existing_presets = [];
    if (!empty($post->ID)) {
        $existing_presets = get_post_meta($post->ID, '_donation_presets', true);
        if (!is_array($existing_presets)) {
            if (!empty($existing_presets) && is_string($existing_presets)) {
                $parts = explode(',', $existing_presets);
                $tmp = [];
                foreach ($parts as $p) {
                    $n = floatval(trim($p));
                    if ($n > 0) $tmp[] = ['label' => (string)$n, 'value' => $n];
                }
                $existing_presets = $tmp;
            } else {
                $existing_presets = [];
            }
        }
    }

    echo '<div class="options_group">';
    echo '<p class="form-field"><label>Preset donation amounts - label and price</label><span class="description">Add preset amounts shown on the product card (label and numeric value).</span></p>';
    echo '<div id="presets-options-rows">';
    if (!empty($existing_presets) && is_array($existing_presets)) {
        foreach ($existing_presets as $row) {
            $pl = isset($row['label']) ? esc_attr($row['label']) : '';
            $pv = isset($row['value']) ? esc_attr($row['value']) : '';
            echo '<p class="form-field preset-option-row">'
                . '<input type="text" name="preset_label[]" value="' . $pl . '" placeholder="Label (e.g. 50 تبرع)" style="width:60%; margin-right:8px;">'
                . '<input type="number" step="0.01" min="0" name="preset_value[]" value="' . $pv . '" placeholder="قيمة" style="width:30%; margin-right:8px;">'
                . '<button type="button" class="button remove-preset-row">Remove</button>'
                . '</p>';
        }
    } else {
        echo '<p class="form-field preset-option-row">'
            . '<input type="text" name="preset_label[]" value="" placeholder="Label (e.g. 50 تبرع)" style="width:60%; margin-right:8px;">'
            . '<input type="number" step="0.01" min="0" name="preset_value[]" value="" placeholder="قيمة" style="width:30%; margin-right:8px;">'
            . '<button type="button" class="button remove-preset-row">Remove</button>'
            . '</p>';
    }
    echo '</div>';
    echo '<p><button type="button" class="button" id="add-preset-row">Add Preset</button></p>';
    echo '</div>';

    echo "<script>
    (function(){
        var container = document.getElementById('presets-options-rows');
        if(!container) return;
        document.getElementById('add-preset-row').addEventListener('click', function(){
            var p = document.createElement('p'); p.className='form-field preset-option-row';
            p.innerHTML = '<input type=\'text\' name=\'preset_label[]\' value=\'\' placeholder=\'Label (e.g. 50 تبرع)\' style=\'width:60%; margin-right:8px;\'>' +
                          '<input type=\'number\' step=\'0.01\' min=\'0\' name=\'preset_value[]\' value=\'\' placeholder=\'قيمة\' style=\'width:30%; margin-right:8px;\'>' +
                          '<button type=\'button\' class=\'button remove-preset-row\'>Remove</button>';
            container.appendChild(p);
        });
        container.addEventListener('click', function(e){
            if(e.target && e.target.classList && e.target.classList.contains('remove-preset-row')){
                var row = e.target.closest('.preset-option-row'); if(row) row.parentNode.removeChild(row);
            }
        });
    })();
    </script>";

    
    $existing_waqf = [];
    if (!empty($post->ID)) {
        $existing_waqf = get_post_meta($post->ID, '_waqf_options', true);
        if (!is_array($existing_waqf)) $existing_waqf = [];
    }

    echo '<div class="options_group">';
    echo '<p class="form-field"><label>Waqf Options (وقف) - label and price</label><span class="description">Add options such as "100 عبوة ماء" and its numeric value. These will be shown on the product card and used as the amount when added to cart.</span></p>';
    echo '<div id="waqf-options-rows">';
    if (!empty($existing_waqf) && is_array($existing_waqf)) {
        foreach ($existing_waqf as $row) {
            $lbl = isset($row['label']) ? esc_attr($row['label']) : '';
            $val = isset($row['value']) ? esc_attr($row['value']) : '';
            echo '<p class="form-field waqf-option-row">'
                . '<input type="text" name="waqf_label[]" value="' . $lbl . '" placeholder="Label (e.g. 100 عبوة ماء)" style="width:60%; margin-right:8px;">'
                . '<input type="number" step="0.01" min="0" name="waqf_value[]" value="' . $val . '" placeholder="قيمة" style="width:30%; margin-right:8px;">'
                . '<button type="button" class="button remove-waqf-row">Remove</button>'
                . '</p>';
        }
    } else {
        // one empty row to start
        echo '<p class="form-field waqf-option-row">'
            . '<input type="text" name="waqf_label[]" value="" placeholder="Label (e.g. 100 عبوة ماء)" style="width:60%; margin-right:8px;">'
            . '<input type="number" step="0.01" min="0" name="waqf_value[]" value="" placeholder="قيمة" style="width:30%; margin-right:8px;">'
            . '<button type="button" class="button remove-waqf-row">Remove</button>'
            . '</p>';
    }
    echo '</div>'; // #waqf-options-rows
    echo '<p><button type="button" class="button" id="add-waqf-row">Add Waqf Option</button></p>';
    echo '</div>';

    // Minimal JS to add/remove rows in the product admin panel
    echo "<script>
    (function(){
        var container = document.getElementById('waqf-options-rows');
        if(!container) return;
        document.getElementById('add-waqf-row').addEventListener('click', function(){
            var p = document.createElement('p'); p.className='form-field waqf-option-row';
            p.innerHTML = '<input type=\'text\' name=\'waqf_label[]\' value=\'\' placeholder=\'Label (e.g. 100 عبوة ماء)\' style=\'width:60%; margin-right:8px;\'>' +
                          '<input type=\'number\' step=\'0.01\' min=\'0\' name=\'waqf_value[]\' value=\'\' placeholder=\'قيمة\' style=\'width:30%; margin-right:8px;\'>' +
                          '<button type=\'button\' class=\'button remove-waqf-row\'>Remove</button>';
            container.appendChild(p);
        });
        container.addEventListener('click', function(e){
            if(e.target && e.target.classList && e.target.classList.contains('remove-waqf-row')){
                var row = e.target.closest('.waqf-option-row'); if(row) row.parentNode.removeChild(row);
            }
        });
    })();
    </script>";

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
        '_quick_donation'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $product->update_meta_data($field, sanitize_text_field($_POST[$field]));
        }
    }

    // Save waqf options (repeatable label/value pairs)
    if (isset($_POST['waqf_label']) && is_array($_POST['waqf_label'])) {
        $labels = $_POST['waqf_label'];
        $values = isset($_POST['waqf_value']) && is_array($_POST['waqf_value']) ? $_POST['waqf_value'] : [];
        $options = [];
        foreach ($labels as $i => $lbl) {
            $lbl = sanitize_text_field($lbl);
            $val = isset($values[$i]) ? floatval(str_replace(',', '.', $values[$i])) : 0;
            if ($lbl === '' && $val <= 0) continue;
            $options[] = [ 'label' => $lbl, 'value' => $val ];
        }
        if (!empty($options)) {
            $product->update_meta_data('_waqf_options', $options);
        } else {
            // remove meta if empty
            $product->delete_meta_data('_waqf_options');
        }
    }

    // Save preset donation options (repeatable label/value pairs)
    if (isset($_POST['preset_label']) && is_array($_POST['preset_label'])) {
        $labels = $_POST['preset_label'];
        $values = isset($_POST['preset_value']) && is_array($_POST['preset_value']) ? $_POST['preset_value'] : [];
        $options = [];
        foreach ($labels as $i => $lbl) {
            $lbl = sanitize_text_field($lbl);
            $val = isset($values[$i]) ? floatval(str_replace(',', '.', $values[$i])) : 0;
            if ($lbl === '' && $val <= 0) continue;
            $options[] = [ 'label' => $lbl, 'value' => $val ];
        }
        if (!empty($options)) {
            $product->update_meta_data('_donation_presets', $options);
        } else {
            $product->delete_meta_data('_donation_presets');
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
