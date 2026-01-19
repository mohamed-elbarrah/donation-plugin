<?php
if (!defined('ABSPATH')) exit;

/**
 * Render donation details on the single product page.
 */
// Hide the default WooCommerce product price when viewing a single product page
add_filter('woocommerce_get_price_html', function($price_html, $product){
    if (is_product()) return '';
    return $price_html;
}, 10, 2);
// On product pages remove the default WooCommerce summary and images so we can
// render our custom layout exclusively.
add_action('wp', function () {
    if (!is_product()) return;

    // remove default summary callbacks
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50);

    // remove default product images (theme may use this hook)
    remove_action('woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20);
    // remove product data tabs (prevents woocommerce-tabs wc-tabs-wrapper output)
    remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
}, 1);

add_action('woocommerce_single_product_summary', function () {
    if (!is_product()) return;

    global $product;
    $product_id = $product->get_id();

    $target = (float) get_post_meta($product_id, '_donation_target', true);
    $collected = (float) get_post_meta($product_id, '_donation_collected', true);
    $location = get_post_meta($product_id, '_donation_location', true);
    $badge = get_post_meta($product_id, '_donation_badge', true);
    $donation_mode = get_post_meta($product_id, '_donation_mode', true);
    if (!$donation_mode) $donation_mode = 'wakf';

    // currency symbol for stat formatting
    $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    $price_decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

    $progress = $target > 0 ? min(100, round(($collected / $target) * 100)) : 0;
    $remaining = max(0, $target - $collected);

    // presets
    $presets_raw = get_post_meta($product_id, '_donation_presets', true);
    $presets = [];
    if ($presets_raw) {
        $parts = explode(',', $presets_raw);
        foreach ($parts as $p) {
            $n = floatval(trim($p));
            if ($n > 0) $presets[] = $n;
        }
    }
    if (empty($presets)) $presets = [10,50,100];

    // Labels that vary by mode (تبرع vs وقف)
    $collected_label = ($donation_mode === 'donation') ? 'التبرعات الحالية' : 'إجمالي الوقف';
    $amount_aria = ($donation_mode === 'donation') ? 'مبلغ التبرع' : 'مبلغ الوقف';

    ?>
    <div class="donation-single-container">
        <div class="donation-single-wrap" data-product-id="<?php echo esc_attr($product_id); ?>" data-donation-badge="<?php echo esc_attr($badge); ?>" data-donation-mode="<?php echo esc_attr($donation_mode); ?>" data-checkout-url="<?php echo esc_attr( function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '' ); ?>">

            <div class="donation-single-left">
                <div class="donation-meta">
                    <h1 class="donation-title"><?php echo esc_html( get_the_title() ); ?></h1>

                    <div class="progress-bar-container" role="progressbar" aria-label="<?php echo esc_attr__('Progress', 'donation-app'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progress); ?>">
                        <div class="progress-bar-track"><div class="progress-bar-fill" data-percent="<?php echo esc_attr($progress); ?>" style="width: <?php echo esc_attr($progress); ?>%;"><span class="progress-bar-label"><?php echo esc_html($progress); ?>%</span></div></div>
                    </div>

                    <?php
                    // Message and completion flag for single product view
                    $is_complete = ($progress >= 100);
                    if ($is_complete) {
                        echo '<div class="progress-message">\n  <div>اكتملت بعطائكم</div>\n  <div class="progress-sub">هنيئًا لكم الأجر</div>\n</div>';
                    } elseif ($progress >= 90) {
                        echo '<div class="progress-message">أوشَكَت على الاكتمال</div>';
                    } elseif ($progress >= 75) {
                        echo '<div class="progress-message">ما زالت الفرصة أمامك</div>';
                    } elseif ($progress >= 50) {
                        echo '<div class="progress-message">عطاؤك يصنع الفارق</div>';
                    } elseif ($progress >= 25) {
                        echo '<div class="progress-message">بادر وكن بالأجر ظافر</div>';
                    } else {
                        echo '<div class="progress-message">كن من أوائل المبادرين</div>';
                    }
                    ?>

                    <div class="donation-stats">
                        <div class="stat collected"><span class="label"><?php echo esc_html( $collected_label ); ?></span>
                            <span class="value"><span class="currency-inline"><?php echo esc_html( $currency_symbol ); ?></span><span class="stat-amount"><?php echo esc_html( number_format_i18n( $collected, $price_decimals ) ); ?></span></span>
                        </div>
                        <div class="stat remaining"><span class="label">المبلغ المتبقي</span>
                            <span class="value"><span class="currency-inline"><?php echo esc_html( $currency_symbol ); ?></span><span class="stat-amount"><?php echo esc_html( number_format_i18n( $remaining, $price_decimals ) ); ?></span></span>
                        </div>
                        <div class="stat target"><span class="label">الهدف</span>
                            <span class="value"><span class="currency-inline"><?php echo esc_html( $currency_symbol ); ?></span><span class="stat-amount"><?php echo esc_html( number_format_i18n( $target, $price_decimals ) ); ?></span></span>
                        </div>
                        <?php if ($location): ?>
                            <div class="stat location"><span class="label">الموقع</span>
                                <span class="value">
                                        <span class="location-text"><?php echo esc_html( $location ); ?></span>
                                        <span class="location-icon" aria-hidden="true">
                                            <!-- inline location pin SVG -->
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false" role="img">
                                                <path d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" fill="currentColor"/>
                                            </svg>
                                        </span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="donation-details">
                        <h2 class="donation-details-title">التفاصيل</h2>
                        <div class="donation-details-content">
                            <?php echo wp_kses_post( wpautop( $product->get_description() ) ); ?>
                        </div>
                    </div>

                    <div class="donation-amount-box">
                                <div class="donation-presets-header"><?php echo esc_html__("اختر المبلغ", 'donation-app'); ?></div>
                        <div class="donation-presets" role="tablist">
                            <?php foreach ($presets as $i => $amt): $amt_val = floatval($amt);
                                $btn_disabled_attr = $is_complete ? ' disabled aria-disabled="true"' : '';
                                $pressed = ($i===0 && !$is_complete) ? 'true' : 'false';
                            ?>
                                <button type="button" class="preset-amount<?php echo $i===0 ? ' active' : ''; ?>" data-amount="<?php echo esc_attr($amt_val); ?>" aria-pressed="<?php echo $pressed; ?>" <?php echo $btn_disabled_attr; ?>>
                                    <span class="preset-currency"><?php echo esc_html( $currency_symbol ); ?></span>
                                    <span class="preset-amount-text"><?php echo esc_html( number_format_i18n( $amt_val ) ); ?></span>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="preset-amount preset-other" <?php echo $is_complete ? 'disabled aria-disabled="true"' : ''; ?>>مخصص</button>
                        </div>

                        <div class="donation-input-row">
                            <?php
                            $cta_label = ($donation_mode === 'donation') ? 'تبرع الآن' : 'أضف للسلة';
                            if ($is_complete): ?>
                                    <button type="button" id="donation_donate_now-<?php echo esc_attr($product_id); ?>" class="button alt donation-donate-now disabled" disabled aria-disabled="true"><?php echo esc_html('مكتمل'); ?></button>
                                <?php else: ?>
                                    <a id="donation_donate_now-<?php echo esc_attr($product_id); ?>" class="button alt donation-donate-now" href="#" role="button"><?php echo esc_html($cta_label); ?></a>
                                <?php endif; ?>

                                <div class="currency-input">
                                <input type="number" min="1" step="0.01" id="donation_amount_input-<?php echo esc_attr($product_id); ?>" class="amount-input donation-amount-input" placeholder="أدخل المبلغ" value="<?php echo esc_attr($presets[0]); ?>" aria-label="<?php echo esc_attr( $amount_aria ); ?>" <?php echo $is_complete ? 'disabled' : ''; ?>>
                                <span class="currency-symbol" aria-hidden="true"><?php echo function_exists('get_woocommerce_currency_symbol') ? esc_html( get_woocommerce_currency_symbol() ) : '$'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="donation-single-right">
                <div class="donation-card-header">
                    <?php
                    $main_image_id = $product->get_image_id();
                    $gallery_ids = method_exists($product, 'get_gallery_image_ids') ? $product->get_gallery_image_ids() : [];

                    if ($main_image_id) {
                        echo wp_get_attachment_image($main_image_id, 'large', false, [
                            'class' => 'donation-main-image',
                            'id' => 'donation_main_image-' . $product_id,
                            'alt' => get_the_title($product_id),
                        ]);
                    } else {
                        // fallback to default HTML output
                        echo $product->get_image('large');
                    }

                    // render gallery thumbnails if present
                    if (!empty($gallery_ids) && is_array($gallery_ids)) {
                        echo '<div class="donation-gallery-thumbs" role="tablist" aria-label="صور المنتج">';
                        // include main image as first thumb for convenience
                        if ($main_image_id) {
                            $main_full = wp_get_attachment_image_url($main_image_id, 'large');
                            echo '<button type="button" class="gallery-thumb active" data-full="' . esc_url($main_full) . '" aria-pressed="true">' . wp_get_attachment_image($main_image_id, 'thumbnail', false, ['alt' => '']) . '</button>';
                        }
                        foreach ($gallery_ids as $gid) {
                            $full = wp_get_attachment_image_url($gid, 'large');
                            echo '<button type="button" class="gallery-thumb" data-full="' . esc_url($full) . '" aria-pressed="false">' . wp_get_attachment_image($gid, 'thumbnail', false, ['alt' => '']) . '</button>';
                        }
                        echo '</div>';
                    }

                    if ($badge): ?><span class="donation-badge"><?php echo esc_html($badge); ?></span><?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Styles and behavior moved to assets: assets/css/single-product.css and assets/js/single-product.js -->
    <style>
        .donation-single-wrap .disabled { pointer-events:none; opacity:0.6; filter:grayscale(20%); }
        .donation-single-wrap .progress-message { text-align:center; margin-top:8px; font-weight:700; direction:rtl; }
        .donation-single-wrap .progress-message .progress-sub { font-weight:500; margin-top:4px; }
        /* Position currency symbol inside the input on the visual left and vertically centered */
        .donation-single-wrap .currency-input { position:relative; display:inline-block; }
        .donation-single-wrap .currency-input .currency-symbol {
            position:absolute !important;
            left:12px !important;
            top:50% !important;
            transform:translateY(-50%) !important;
            pointer-events:none;
            line-height:1;
            font-size:1rem;
            color:inherit;
        }
        .donation-single-wrap .currency-input .donation-amount-input,
        .donation-single-wrap .currency-input .amount-input {
            padding-left:44px;
        }
        /* Ensure placement remains visually left in RTL contexts */
        [dir="rtl"] .donation-single-wrap .currency-input .currency-symbol {
            left:12px !important;
            right:auto !important;
        }
        /* Details section styling */
        .donation-single-wrap .donation-details {
            margin:20px 0;
            padding:16px 18px;
            background:var(--donation-panel-bg, #ffffff);
            border-radius:10px;
            box-shadow:0 6px 18px rgba(32,55,73,0.06);
            border:1px solid rgba(16,24,32,0.04);
        }
        .donation-single-wrap .donation-details-title{
            margin:0 0 10px;
            font-size:1.2rem;
            line-height:1;
            font-weight:800;
            color:var(--donation-heading, #123644);
            text-align:right;
        }
        .donation-single-wrap .donation-details-content{
            margin:0;
            color:#374151;
            font-size:1rem;
            direction:rtl;
        }
        .donation-single-wrap .donation-details-content p{
            margin-block-end:0px;
        }
        @media (max-width:720px){
            .donation-single-wrap .donation-details{ padding:14px; }
            .donation-single-wrap .donation-details-title{ font-size:1.4rem; }
            .donation-single-wrap .currency-input .donation-amount-input,
            .donation-single-wrap .currency-input .amount-input { padding-left:38px; }
        }
    </style>
    <?php
}, 20);


/**
 * Replace default related products with our donation-style cards (shortcode).
 */
add_action('wp', function () {
    if (!is_product()) return;
    remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
}, 2);

add_action('woocommerce_after_single_product', function () {
    if (!is_product()) return;

    echo '<section class="donation-related-wrap">';
    echo '<h4 class="related-title">فرص مشابهة</h4>';
    echo do_shortcode('[donation_campaigns limit="3"]');
    echo '</section>';

}, 20);
