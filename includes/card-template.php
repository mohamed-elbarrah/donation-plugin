<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('donation_render_card')) {
    /**
     * Render a donation product card for a given post ID.
     * Mirrors the markup used by the main campaigns shortcode.
     * @param int $post_id
     */
    function donation_render_card($post_id) {
        $post = get_post($post_id);
        if (!$post) return;
        $product = wc_get_product($post_id);
        $target = (float) get_post_meta($post_id, '_donation_target', true);
        $collected = (float) get_post_meta($post_id, '_donation_collected', true);
        $location = get_post_meta($post_id, '_donation_location', true);
        $badge = get_post_meta($post_id, '_donation_badge', true);

        $progress = $target > 0 ? min(100, round(($collected / $target) * 100)) : 0;
        $remaining = $target - $collected;
        $product_id = $post_id;
        $donation_mode = get_post_meta($post_id, '_donation_mode', true);
        if (!$donation_mode) $donation_mode = 'wakf'; // default

        // Mode-aware labels (تبرع vs وقف)
        $collected_label = ($donation_mode === 'donation') ? 'التبرعات الحالية' : 'إجمالي الوقف';
        $amount_title = ($donation_mode === 'donation') ? 'مبلغ التبرع' : 'اختر مبلغ المساهمة';
        $cta_label = ($donation_mode === 'donation') ? 'تبرع الآن' : 'أضف للسلة';

        // Determine progress message texts (main + optional sub) so we can render
        // them together with the percent inside the progress bar overlay.
        $is_complete = ($progress >= 100);
        $progress_message_main = '';
        $progress_message_sub = '';
        if ($is_complete) {
            $progress_message_main = 'اكتملت بعطائكم';
            $progress_message_sub = 'هنيئًا لكم الأجر';
        } elseif ($progress >= 90) {
            $progress_message_main = 'أوشَكَت على الاكتمال';
        } elseif ($progress >= 75) {
            $progress_message_main = 'ما زالت الفرصة أمامك';
        } elseif ($progress >= 50) {
            $progress_message_main = 'عطاؤك يصنع الفارق';
        } elseif ($progress >= 25) {
            $progress_message_main = 'بادر وكن بالأجر ظافر';
        } else {
            $progress_message_main = 'كن من أوائل المبادرين';
        }
        // Prefer real checkout page if published; otherwise fall back to cart or safe path
        $checkout_url = '';
        if (function_exists('wc_get_checkout_url')) {
            $checkout_url = wc_get_checkout_url();
            $checkout_id = get_option('woocommerce_checkout_page_id');
            if ($checkout_id && get_post_status($checkout_id) !== 'publish') {
                // not published, ignore
                $checkout_url = '';
            }
        }
        if (empty($checkout_url) && function_exists('wc_get_cart_url')) {
            $checkout_url = wc_get_cart_url();
        }
        if (empty($checkout_url)) {
            $checkout_url = site_url('/checkout/');
        }

        ?>
        <div class="donation-card">
            <div class="card-header">
                <?php if ($product): ?>
                    <a class="card-link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
                        <?php echo $product->get_image('medium'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($badge): ?>
                    <span class="donation-badge"><?php echo esc_html($badge); ?></span>
                <?php endif; ?>
            </div>

            <div class="progress-bar-container" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($progress); ?>" style="position:relative;">
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" data-percent="<?php echo esc_attr($progress); ?>" style="width: <?php echo esc_attr($progress); ?>%;">
                    </div>
                </div>

                <div class="progress-overlay" aria-hidden="true">
                    <div class="progress-bar-label"><?php echo esc_html($progress); ?>%</div>
                    <div class="progress-message-line">
                        <span class="progress-message-main"><?php echo esc_html($progress_message_main); ?></span>
                        <?php if (!empty($progress_message_sub)): ?>
                            <span class="progress-message-sub"><?php echo esc_html($progress_message_sub); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="card-top-row">
                    <div class="share-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M18 8c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.03.47.07.7L8.82 9.35C8.22 8.53 7.2 8 6 8c-1.66 0-3 1.34-3 3s1.34 3 3 3c1.2 0 2.22-.53 2.82-1.35l6.25 3.65c-.04.23-.07.46-.07.7 0 1.66 1.34 3 3 3s3-1.34 3-3-1.34-3-3-3c-1.2 0-2.22.53-2.82 1.35L8.93 11.7c.04-.23.07-.46.07-.7 0-.24-.03-.47-.07-.7l6.25-3.65C15.78 7.47 16.8 8 18 8z"/>
                        </svg>
                    </div>

                    <div class="title-wrap">
                        <h3 class="card-title"><a class="card-link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>

                        <div class="title-meta-row">
                            <div class="target-badge-inline" aria-hidden="true">
                                <span class="target-icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false" role="img">
                                        <circle class="pulse" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.2" fill="none" />
                                        <circle class="ring" cx="12" cy="12" r="6" stroke="currentColor" stroke-width="1.6" fill="none" />
                                        <circle class="core" cx="12" cy="12" r="2" fill="currentColor" />
                                    </svg>
                                </span>
                                <span class="target-value"><?php echo function_exists('wc_price') ? wc_price($target) : esc_html( number_format_i18n( $target ) ); ?></span>
                            </div>
                            
                            <?php if ($location): ?>
                                <div class="location-badge-inline">
                                    <svg class="location-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true" focusable="false">
                                        <path d="M21 10c0 6-9 13-9 13S3 16 3 10a9 9 0 1118 0z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <circle cx="12" cy="10" r="2.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></circle>
                                    </svg>
                                    <?php echo esc_html($location); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <div class="amounts-row">
                    <div class="amount-box collected">
                        <div class="amount-label"><?php echo esc_html( $collected_label ); ?>
                        </div>
                            <div class="amount-value shimmer-amount"><?php echo wc_price($collected); ?></div>
                    </div>
                    <div class="amount-box remaining">
                        <div class="amount-label">المبلغ المتبقي</div>
                        <div class="amount-value"><?php echo wc_price($remaining); ?></div>
                    </div>
                </div>

                <div class="donation-amount-title"><?php echo esc_html( $amount_title ); ?></div>

                <?php
                $presets_raw = get_post_meta($post_id, '_donation_presets', true);
                $presets = [];
                if ($presets_raw) {
                    $parts = explode(',', $presets_raw);
                    foreach ($parts as $p) {
                        $n = floatval(trim($p));
                        if ($n > 0) $presets[] = $n;
                    }
                }
                if (empty($presets)) {
                    $presets = [10,50,100];
                }

                // If a donation_amount is provided in the request, try to honor it.
                $requested_amount = null;
                if (isset($_REQUEST['donation_amount']) && is_numeric($_REQUEST['donation_amount'])) {
                    $requested_amount = floatval($_REQUEST['donation_amount']);
                }

                // Determine which preset (if any) should be active initially.
                $active_preset_index = 0;
                $active_is_other = false;
                if ($requested_amount !== null) {
                    $found = false;
                    foreach ($presets as $i => $amt) {
                        if (abs(floatval($amt) - $requested_amount) < 0.0001) { // numeric match
                            $active_preset_index = $i;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $active_is_other = true;
                    }
                }
                ?>

                <div class="preset-amounts" role="list">
                    <?php foreach ($presets as $idx => $amt): $is_active = ($active_is_other ? false : ($idx === $active_preset_index));
                        $btn_disabled_attr = $is_complete ? ' disabled aria-disabled="true"' : '';
                        $pressed = ($is_active && !$is_complete) ? 'true' : 'false';
                    ?>
                        <button type="button" class="preset-amount<?php echo $is_active ? ' active' : ''; ?>" data-amount="<?php echo esc_attr($amt); ?>" aria-pressed="<?php echo $pressed; ?>" <?php echo $btn_disabled_attr; ?>>
                            <span class="preset-side preset-right">
                                <span class="preset-check">✓</span>
                            </span>
                            <span class="preset-value"><?php echo esc_html($amt); ?></span>
                            <span class="preset-side preset-left">
                                <span class="preset-currency"><?php echo function_exists('get_woocommerce_currency_symbol') ? esc_html( get_woocommerce_currency_symbol() ) : '&#36;'; ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                    <button type="button" class="preset-amount preset-other<?php echo $active_is_other ? ' active' : ''; ?>" data-other="1" aria-pressed="<?php echo $active_is_other ? 'true' : 'false'; ?>" <?php echo $is_complete ? 'disabled aria-disabled="true"' : ''; ?> >
                        <span class="preset-side preset-right">
                            <span class="preset-check">✓</span>
                        </span>
                        <span class="preset-value">مبلغ مخصص</span>
                    </button>
                </div>

                <style>
                    /* Compact CTA so Arabic label fits on one line */
                    .donation-card .card-actions { display:flex; gap:10px; align-items:center; }
                    .donation-card .donate-btn { white-space:nowrap; font-size:14px; padding:8px 12px; border-radius:8px; }
                    .donation-card .amount-input{ width:100px; }
                    @media (max-width:720px){ .donation-card .card-actions{ flex-direction:column; align-items:stretch; } .donation-card .amount-input{ width:100%; } }
                    .donation-card .disabled { pointer-events:none; opacity:0.6; filter:grayscale(20%); }
                    .donation-card .progress-message-line { text-align:center; margin-top:0; font-weight:600; direction:rtl; }
                    .donation-card .progress-message-sub { font-weight:500; font-size:0.95em; margin-right:8px; }
                    .donation-card .progress-bar-container { position:relative; }
                    .donation-card .progress-overlay { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none; color:var(--wc-primary-color, inherit); display:flex; align-items:center; gap:5px; white-space:nowrap; font-size: 14px; color:#085931;}
                    .donation-card .progress-overlay .progress-bar-label { font-weight:800; font-size:0.95em; display:block; }
                    .donation-card .progress-message-main { display:inline-block; }
                </style>
                <div class="card-actions">
                    <?php $cta_label = $donation_mode === 'donation' ? 'تبرع الآن' : 'اضف للسلة'; ?>
                    <?php if ($is_complete): ?>
                        <button type="button" class="donate-btn disabled" disabled aria-disabled="true"><?php echo esc_html('مكتمل'); ?></button>
                    <?php else: ?>
                        <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="donate-btn" data-mode="<?php echo esc_attr($donation_mode); ?>" data-checkout="<?php echo esc_attr($checkout_url); ?>"><?php echo esc_html($cta_label); ?></a>
                    <?php endif; ?>
                    <div class="currency-input">
                        <span class="currency-symbol"><?php echo function_exists('get_woocommerce_currency_symbol') ? esc_html( get_woocommerce_currency_symbol() ) : '&#36;'; ?></span>
                        <?php
                        // initialize input value/disabled state according to active preset or requested amount
                        $input_value = '';
                        $input_disabled = false;
                        if ($active_is_other) {
                            $input_value = $requested_amount !== null ? $requested_amount : '';
                            $input_disabled = false;
                        } else {
                            // use the active preset amount
                            $preset_amt = isset($presets[$active_preset_index]) ? $presets[$active_preset_index] : null;
                            if ($preset_amt !== null) {
                                $input_value = $preset_amt;
                                $input_disabled = true;
                            }
                        }
                        ?>
                        <input type="number" min="1" step="0.01" class="amount-input" placeholder="أدخل المبلغ" aria-label="<?php echo esc_attr( $amount_title ); ?>" data-product-id="<?php echo esc_attr($product_id); ?>" value="<?php echo esc_attr($input_value); ?>" <?php echo ($input_disabled || $is_complete) ? 'disabled' : ''; ?>>
                    </div>
                    <!-- cart button removed per request -->
                </div>
            </div>
        </div>
        <?php
    }
}
