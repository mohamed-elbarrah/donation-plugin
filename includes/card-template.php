<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('donation_render_card_standard')) {
    /**
     * Render a donation product card for a given post ID.
     * Mirrors the markup used by the main campaigns shortcode.
     * @param int $post_id
     */
    function donation_render_card_standard($post_id) {
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
                // Prefer structured presets (array of ['label'=>..,'value'=>..])
                $presets = [];
                $presets_raw = get_post_meta($post_id, '_donation_presets', true);
                if (!empty($presets_raw)) {
                    if (is_array($presets_raw)) {
                        foreach ($presets_raw as $opt) {
                            $label = isset($opt['label']) ? $opt['label'] : '';
                            $value = isset($opt['value']) ? floatval($opt['value']) : 0;
                            if ($value > 0) $presets[] = ['label' => $label ?: wc_price($value), 'value' => $value];
                        }
                    } else {
                        $parts = explode(',', $presets_raw);
                        foreach ($parts as $p) {
                            $n = floatval(trim($p));
                            if ($n > 0) $presets[] = ['label' => (string)$n, 'value' => $n];
                        }
                    }
                }
                if (empty($presets)) {
                    $presets = [['label' => '10', 'value' => 10], ['label' => '50', 'value' => 50], ['label' => '100', 'value' => 100]];
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
                    foreach ($presets as $i => $opt) {
                        $amt = isset($opt['value']) ? floatval($opt['value']) : 0;
                        if (abs($amt - $requested_amount) < 0.0001) {
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

                <div class="waqf-options" role="list">
                    <?php foreach ($presets as $idx => $opt):
                        $is_active = ($active_is_other ? false : ($idx === $active_preset_index));
                        $btn_disabled_attr = $is_complete ? ' disabled aria-disabled="true"' : '';
                        $pressed = ($is_active && !$is_complete) ? 'true' : 'false';
                        $amt = isset($opt['value']) ? $opt['value'] : 0;
                        $label = isset($opt['label']) ? $opt['label'] : (string)$amt;
                    ?>
                            <button type="button" class="waqf-option<?php echo $is_active ? ' active' : ''; ?>" data-amount="<?php echo esc_attr($amt); ?>" aria-pressed="<?php echo $pressed; ?>" <?php echo $btn_disabled_attr; ?>>
                                <span class="waqf-value"><?php echo esc_html($label); ?></span>
                            </button>
                    <?php endforeach; ?>
                    <button type="button" class="waqf-option waqf-other<?php echo $active_is_other ? ' active' : ''; ?>" data-other="1" aria-pressed="<?php echo $active_is_other ? 'true' : 'false'; ?>" <?php echo $is_complete ? 'disabled aria-disabled="true"' : ''; ?> >
                            <span class="waqf-value">مبلغ مخصص</span>
                        </button>
                </div>

                <style>
                    /* Render waqf options inline and wrap to next line when needed */
                    .donation-card .waqf-options { display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin:8px 0 6px; justify-content:center; }
                    .donation-card .waqf-option{
                        background: transparent;
                        border: 1px solid #e6e6e6;
                        border-radius: 8px;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        gap: 4px;
                        cursor: pointer;
                        color: #122;
                        font-weight: 700;
                        padding: 6px 10px;
                        box-sizing: border-box;
                        font-size:13px;
                        min-width:56px;
                        flex: 0 0 auto;
                        white-space:nowrap;
                    }
                    .donation-card .waqf-value{ font-size:12px; flex:1 1 auto; text-align:center; padding:0 0px; font-weight:700 }
                    .donation-card .waqf-option.active{
                        background: linear-gradient(180deg, var(--donation-blue) 0%, #223f53 100%);
                        border-color: transparent;
                        color: var(--donation-white);
                        box-shadow: 0 8px 20px rgba(41,77,103,0.08);
                    }
                </style>

                <script>
                (function(){
                    var card = document.currentScript && document.currentScript.parentNode ? document.currentScript.parentNode : document.querySelector('.donation-card');
                    if(!card) return;
                    var options = Array.prototype.slice.call(card.querySelectorAll('.waqf-option'));
                    var input = card.querySelector('.amount-input');

                    function removeChecks(){
                        options.forEach(function(b){
                            var s = b.querySelector('.waqf-side');
                            if(s) s.remove();
                            b.setAttribute('aria-pressed','false');
                        });
                    }

                    function addCheckTo(btn){
                        if(!btn) return;
                        var s = btn.querySelector('.waqf-side');
                        if(!s){
                            s = document.createElement('span');
                            s.className = 'waqf-side waqf-right';
                            var inner = document.createElement('span');
                            inner.className = 'waqf-check';
                            inner.textContent = '✓';
                            s.appendChild(inner);
                            var val = btn.querySelector('.waqf-value');
                            if(val){
                                btn.insertBefore(s, val);
                            } else {
                                btn.appendChild(s);
                            }
                        }
                        btn.setAttribute('aria-pressed','true');
                    }

                    options.forEach(function(btn){
                        btn.addEventListener('click', function(){
                            if(btn.classList.contains('waqf-other')){
                                options.forEach(function(b){ b.classList.remove('active'); });
                                removeChecks();
                                btn.classList.add('active');
                                addCheckTo(btn);
                                if(input){ input.disabled = false; input.value = ''; input.focus(); }
                                return;
                            }
                            var amt = btn.getAttribute('data-amount');
                            options.forEach(function(b){ b.classList.remove('active'); });
                            removeChecks();
                            btn.classList.add('active');
                            addCheckTo(btn);
                            if(input){ input.value = (amt ? amt : ''); input.disabled = true; }
                        });
                    });

                    // On load, if an option is already active, ensure input reflects it and render check
                    var active = card.querySelector('.waqf-option.active');
                    if(active){
                        removeChecks();
                        addCheckTo(active);
                        if(active.classList.contains('waqf-other')){
                            if(input) { input.disabled = false; }
                        } else {
                            var a = active.getAttribute('data-amount'); if(input) { input.value = a; input.disabled = true; }
                        }
                    }
                })();
                </script>
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
                                    if (is_array($preset_amt) && isset($preset_amt['value'])) {
                                        $input_value = $preset_amt['value'];
                                    } elseif (is_numeric($preset_amt)) {
                                        $input_value = $preset_amt;
                                    } else {
                                        $input_value = '';
                                    }
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
