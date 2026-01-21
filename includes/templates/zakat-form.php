<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$opts = donation_app_get_zakat_options();
?>
<?php $is_rtl = 0 === strpos( get_locale(), 'ar' ); ?>
<div id="donation-zakat-wrap" class="donation-zakat-wrap<?php echo $is_rtl ? ' donation-zakat-rtl' : ''; ?>">
    <form id="donation-zakat-form" class="donation-zakat-form" action="#" method="post">
        <fieldset>
            <p class="dz-mode-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'Zakat mode', 'donation-app' ); ?>">
                <label class="dz-seg">
                    <input type="radio" name="donation_zakat_mode" value="pay" checked />
                    <span class="dz-seg-text"><?php esc_html_e( 'Pay Zakat directly', 'donation-app' ); ?></span>
                </label>
                <label class="dz-seg">
                    <input type="radio" name="donation_zakat_mode" value="calc" />
                    <span class="dz-seg-text"><?php esc_html_e( 'Calculate Zakat first', 'donation-app' ); ?></span>
                </label>
            </p>
        </fieldset>

        <div id="donation-zakat-pay" class="donation-zakat-section">
            <p>
                <label for="donation-zakat-pay-amount"><?php esc_html_e( 'Amount to pay', 'donation-app' ); ?></label>
                <input id="donation-zakat-pay-amount" name="pay_amount" type="number" step="0.01" inputmode="decimal" class="regular-text" placeholder="<?php esc_attr_e( '00.00', 'donation-app' ); ?>" />
            </p>
            <p>
                <button type="button" id="donation-zakat-pay-btn" class="button button-primary"><?php esc_html_e( 'Pay Zakat', 'donation-app' ); ?></button>
            </p>
        </div>

        <div id="donation-zakat-calc" class="donation-zakat-section" style="display:none;">
            <div class="dz-grid">
                <div class="dz-type-block">
                    <label for="donation-zakat-type"><?php esc_html_e( 'Zakat Type', 'donation-app' ); ?></label>
                    <select id="donation-zakat-type" name="zakat_type" style="display:none;">
                        <option value="cash"><?php esc_html_e( 'Cash', 'donation-app' ); ?></option>
                        <option value="gold"><?php esc_html_e( 'Gold', 'donation-app' ); ?></option>
                        <option value="silver"><?php esc_html_e( 'Silver', 'donation-app' ); ?></option>
                        <option value="stocks"><?php esc_html_e( 'Stocks', 'donation-app' ); ?></option>
                    </select>

                    <div class="dz-type-buttons" role="tablist" aria-label="<?php esc_attr_e( 'Zakat types', 'donation-app' ); ?>">
                        <button type="button" class="dz-type-btn" data-type="cash"><?php esc_html_e( 'المال', 'donation-app' ); ?></button>
                        <button type="button" class="dz-type-btn" data-type="gold"><?php esc_html_e( 'ذهب', 'donation-app' ); ?></button>
                        <button type="button" class="dz-type-btn" data-type="silver"><?php esc_html_e( 'فضة', 'donation-app' ); ?></button>
                        <button type="button" class="dz-type-btn" data-type="stocks"><?php esc_html_e( 'أسهم', 'donation-app' ); ?></button>
                    </div>

                
                </div>

                <div class="dz-amount-block">
                    <label for="donation-zakat-amount"><?php esc_html_e( 'المبلغ', 'donation-app' ); ?></label>
                    <input id="donation-zakat-amount" name="amount" type="number" step="0.01" inputmode="decimal" class="regular-text" placeholder="<?php esc_attr_e( 'أدخل مبلغا', 'donation-app' ); ?>" />
                </div>
            </div>
            <div class="dz-actions">
                <button type="submit" id="donation-zakat-calc-btn" class="button button-secondary"><?php esc_html_e( 'احسب', 'donation-app' ); ?></button>
            </div>

            <div class="dz-result-row">
                <button type="button" id="donation-zakat-pay-calc-btn" class="button button-primary" style="display:none;" aria-hidden="true"><?php esc_html_e( 'ادفع الزكاة المحتسبة', 'donation-app' ); ?></button>
                <div class="dz-result-input">
                    <label for="donation-zakat-calculated" class="dz-calculated-label"><?php esc_html_e( 'الزكاة المستحقة', 'donation-app' ); ?></label>
                    <input id="donation-zakat-calculated" name="calculated_amount" type="text" class="regular-text donation-zakat-calculated" readonly="readonly" aria-readonly="true" aria-live="polite" placeholder="0.00" />
                </div>
            </div>

            <div class="dz-info-bar" aria-hidden="false">
                <div class="donation-zakat-info-item"><strong class="dz-label-nisab"><?php esc_html_e( 'النصاب', 'donation-app' ); ?>:</strong> <span id="donation-zakat-nisab">—</span></div>
                <div class="donation-zakat-info-item"><strong class="dz-label-rate"><?php esc_html_e( 'النسبة', 'donation-app' ); ?>:</strong> <span id="donation-zakat-rate">—</span></div>
            </div>
            
        </div>

    </form>
</div>

<script type="text/template" id="donation-zakat-template">
    <!-- template placeholder (kept minimal; behaviour in JS) -->
</script>
