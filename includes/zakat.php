<?php
/**
 * Zakat feature module
 * - Shortcode to render UI
 * - AJAX handlers
 * - Centralized calculation logic
 * - Loads admin settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Options key
if ( ! defined( 'DONATION_APP_ZAKAT_OPTIONS' ) ) {
    define( 'DONATION_APP_ZAKAT_OPTIONS', 'donation_app_zakat_options' );
}

// Load admin settings UI
require_once dirname( __FILE__ ) . '/zakat-admin.php';

add_action( 'init', 'donation_app_register_zakat_shortcode' );
function donation_app_register_zakat_shortcode() {
    add_shortcode( 'donation_zakat', 'donation_app_zakat_shortcode' );
}

/**
 * Shortcode callback — renders template and enqueues frontend assets
 */
function donation_app_zakat_shortcode( $atts = [] ) {
    $plugin_root = dirname( dirname( __FILE__ ) );

    // enqueue JS/CSS for the zakat UI
        // enqueue JS/CSS for the zakat UI immediately so assets are available when shortcode renders
        donation_app_enqueue_zakat_assets();

    ob_start();
    include $plugin_root . '/includes/templates/zakat-form.php';
    return ob_get_clean();
}

/**
 * Enqueue frontend assets only when shortcode is present
 */
function donation_app_enqueue_zakat_assets() {
    $root = dirname( dirname( __FILE__ ) );
    $plugin_main = $root . '/donation-app.php';
    $ver = file_exists( $root . '/assets/js/zakat.js' ) ? filemtime( $root . '/assets/js/zakat.js' ) : '1.0.0';
    wp_enqueue_script( 'donation-zakat', plugin_dir_url( $plugin_main ) . 'assets/js/zakat.js', array( 'jquery' ), $ver, true );

    wp_localize_script( 'donation-zakat', 'donationZakat', array(
        'ajax_url' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
        'nonce'    => wp_create_nonce( 'donation_app_zakat_nonce' ),
        'checkout_url' => esc_url_raw( function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' ) ),
        'i18n'     => array(
            'not_due' => esc_html__( 'غير مستحقة حسب النصاب.', 'donation-app' ),
            'not_reached' => esc_html__( 'المبلغ لم يبلغ النصاب بعد.', 'donation-app' ),
            'label_nisab' => esc_html__( 'Nisab', 'donation-app' ),
            'label_rate' => esc_html__( 'Rate', 'donation-app' ),
            'confirm_pay_direct' => esc_html__( 'Are you sure you want to pay %s now?', 'donation-app' ),
            'modal_title' => esc_html__( 'Confirm Payment', 'donation-app' ),
            'modal_confirm' => esc_html__( 'Confirm', 'donation-app' ),
            'modal_cancel' => esc_html__( 'Cancel', 'donation-app' ),
        ),
        // expose configured nisab/rate per type so frontend can show values before calculation
        'types' => donation_app_get_zakat_options(),
    ) );

    $css_ver = file_exists( $root . '/assets/css/zakat.css' ) ? filemtime( $root . '/assets/css/zakat.css' ) : '1.0.0';
    if ( file_exists( $root . '/assets/css/zakat.css' ) ) {
        wp_enqueue_style( 'donation-zakat', plugin_dir_url( $plugin_main ) . 'assets/css/zakat.css', array(), $css_ver );
    }
}

/**
 * AJAX handler: calculate zakat server-side
 * Expects: nonce, amount, type
 */
add_action( 'wp_ajax_nopriv_donation_app_calculate_zakat', 'donation_app_ajax_calculate_zakat' );
add_action( 'wp_ajax_donation_app_calculate_zakat', 'donation_app_ajax_calculate_zakat' );
function donation_app_ajax_calculate_zakat() {
    check_ajax_referer( 'donation_app_zakat_nonce', 'nonce' );

    $type   = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'cash';
    $amount = isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : 0;

    // normalize amount
    $amount = floatval( str_replace( ',', '', $amount ) );

    // validate type
    $valid_types = array( 'cash', 'gold', 'silver', 'stocks' );
    if ( ! in_array( $type, $valid_types, true ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid zakat type.', 'donation-app' ) ) );
    }

    if ( $amount <= 0 ) {
        wp_send_json_error( array( 'message' => __( 'Please enter a valid amount.', 'donation-app' ) ) );
    }

    $opts = donation_app_get_zakat_options();

    // determine keys
    $nisab_key = $type . '_nisab';
    $rate_key  = $type . '_rate';

    $nisab = isset( $opts[ $nisab_key ] ) ? floatval( $opts[ $nisab_key ] ) : 0;
    $rate  = isset( $opts[ $rate_key ] ) ? floatval( $opts[ $rate_key ] ) : 0.025;

    // allow other code to override rate
    $rate = apply_filters( 'donation_app_zakat_rate', $rate, $type );

    $due = $amount >= $nisab;
    $zakat_amount = 0.0;

    if ( $due ) {
        $zakat_amount = donation_app_calculate_zakat( $amount, $rate );
        $zakat_amount = apply_filters( 'donation_app_zakat_amount', $zakat_amount, $amount, $rate, $type );
    }

    $response = array(
        'due'         => $due,
        'amount'      => $amount,
        'nisab'       => $nisab,
        'rate'        => $rate,
        'zakat'       => number_format_i18n( $zakat_amount, 2 ),
        'zakat_raw'   => $zakat_amount,
    );

    wp_send_json_success( $response );
}

    /**
     * Ensure a Zakat product exists and return its product ID.
     * Stores the product ID in an option for reuse.
     */
    function donation_app_get_or_create_zakat_product() {
        if ( ! class_exists( 'WC_Product' ) ) {
            return 0;
        }

        $opt_key = 'donation_app_zakat_product_id';
        $prod_id = intval( get_option( $opt_key, 0 ) );
        if ( $prod_id && get_post_type( $prod_id ) === 'product' ) {
            // ensure existing product uses the desired Arabic title/content
            $post = get_post( $prod_id );
            if ( $post ) {
                $desired_title   = __( 'أداء الزكاة', 'donation-app' );
                $desired_content = __( 'عنصر دفع الزكاة (مؤقت)', 'donation-app' );
                if ( $post->post_title !== $desired_title || $post->post_content !== $desired_content ) {
                    wp_update_post( array(
                        'ID'           => $prod_id,
                        'post_title'   => $desired_title,
                        'post_content' => $desired_content,
                    ) );
                }
            }
            return $prod_id;
        }

        // Create a simple virtual product post
        $post_id = wp_insert_post( array(
            'post_title'  => __( 'أداء الزكاة', 'donation-app' ),
            'post_content'=> __( 'عنصر دفع الزكاة (مؤقت)', 'donation-app' ),
            'post_status' => 'publish',
            'post_type'   => 'product',
        ) );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return 0;
        }

        // Set product type and visibility
        wp_set_object_terms( $post_id, 'simple', 'product_type' );
        update_post_meta( $post_id, '_visibility', 'hidden' );
        update_post_meta( $post_id, '_stock_status', 'instock' );
        update_post_meta( $post_id, '_manage_stock', 'no' );
        update_post_meta( $post_id, '_virtual', 'yes' );
        update_post_meta( $post_id, '_downloadable', 'no' );
        // default price 0 — will be overridden in cart
        update_post_meta( $post_id, '_regular_price', '0' );
        update_post_meta( $post_id, '_price', '0' );

        update_option( $opt_key, $post_id );

        return $post_id;
    }

    /**
     * When a cart item contains donation_zakat_amount in cart_item_data,
     * set the cart item's price to that amount before totals calculation.
     */
    add_action( 'woocommerce_before_calculate_totals', 'donation_app_set_cart_item_price', 20 );
    function donation_app_set_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        if ( ! class_exists( 'WC_Cart' ) ) return;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['donation_zakat_amount'] ) ) {
                $price = floatval( $cart_item['donation_zakat_amount'] );
                if ( $price > 0 ) {
                    $cart_item['data']->set_price( $price );
                }
            }
        }
    }

    /**
     * AJAX: add zakat item to cart and return checkout URL.
     * Expects nonce and amount.
     */
    add_action( 'wp_ajax_nopriv_donation_app_add_zakat_to_cart', 'donation_app_ajax_add_zakat_to_cart' );
    add_action( 'wp_ajax_donation_app_add_zakat_to_cart', 'donation_app_ajax_add_zakat_to_cart' );
    function donation_app_ajax_add_zakat_to_cart() {
        if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
            wp_send_json_error( array( 'message' => __( 'WooCommerce is required.', 'donation-app' ) ) );
        }

        check_ajax_referer( 'donation_app_zakat_nonce', 'nonce' );

        $amount = isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : 0;
        $amount = floatval( str_replace( ',', '', $amount ) );
        if ( $amount <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid amount', 'donation-app' ) ) );
        }

        $product_id = donation_app_get_or_create_zakat_product();
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Unable to create zakat product', 'donation-app' ) ) );
        }

        // Add to cart with custom price data
        $added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'donation_zakat_amount' => $amount ) );
        if ( ! $added ) {
            wp_send_json_error( array( 'message' => __( 'Could not add to cart', 'donation-app' ) ) );
        }

        // Return checkout URL
        $checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
        wp_send_json_success( array( 'checkout_url' => esc_url_raw( $checkout ) ) );
    }

/**
 * Centralized calculation logic — PHP only
 */
function donation_app_calculate_zakat( $amount, $rate = 0.025 ) {
    $amount = floatval( $amount );
    $rate   = floatval( $rate );
    return round( $amount * $rate, 2 );
}

/**
 * Read options with defaults
 */
function donation_app_get_zakat_options() {
    $defaults = array(
        'cash_nisab'   => 4000.00,
        'cash_rate'    => 0.025,
        'gold_nisab'   => 85.00,
        'gold_rate'    => 0.025,
        'silver_nisab' => 595.00,
        'silver_rate'  => 0.025,
        'stocks_nisab' => 4000.00,
        'stocks_rate'  => 0.025,
    );

    $opts = get_option( DONATION_APP_ZAKAT_OPTIONS, array() );
    if ( ! is_array( $opts ) ) {
        $opts = array();
    }

    return wp_parse_args( $opts, $defaults );
}

/**
 * Helper: render a money input safe value
 */
function donation_app_esc_money( $value ) {
    return esc_attr( number_format_i18n( floatval( $value ), 2 ) );
}

/**
 * Runtime Arabic translations fallback when no .mo is available.
 * This maps a small set of plugin strings to Arabic when site locale is Arabic.
 * It's a safe, minimal fallback — replaceable by proper .po/.mo files.
 */
add_filter( 'gettext', 'donation_app_runtime_ar_translations', 20, 3 );
function donation_app_runtime_ar_translations( $translated, $text, $domain ) {
    if ( 'donation-app' !== $domain ) {
        return $translated;
    }

    $locale = get_locale();
    if ( 0 !== strpos( $locale, 'ar' ) ) {
        // not Arabic locale
        return $translated;
    }

    static $map = null;
    if ( null === $map ) {
        $map = array(
            'Zakat' => 'الزكاة',
            'Pay Zakat directly' => 'ادفع الزكاة مباشرة',
            'Calculate Zakat first' => 'احسب الزكاة أولاً',
            'Amount to pay' => 'مبلغ الزكاة',
            'Pay Zakat' => 'ادفع الزكاة',
            'Zakat Type' => 'نوع الزكاة',
            'Cash' => 'المال',
            'Gold' => 'ذهب',
            'Silver' => 'فضة',
            'Stocks' => 'أسهم',
            'Amount' => 'المبلغ',
            'Calculate' => 'احسب',
            'Pay Calculated Zakat' => 'ادفع الزكاة المحتسبة',
            'Not due according to nisab.' => 'غير مستحقة حسب النصاب.',
            'Are you sure you want to pay %s now?' => 'هل أنت متأكد أنك تريد دفع %s الآن؟',
            'Confirm Payment' => 'تأكيد الدفع',
            'Confirm' => 'تأكيد',
            'Cancel' => 'إلغاء',
            'Invalid zakat type.' => 'نوع زكاة غير صالح.',
            'Please enter a valid amount.' => 'الرجاء إدخال مبلغ صالح.',
            'Donation Zakat' => 'زكاة التبرعات',
            'Zakat Settings' => 'إعدادات الزكاة',
            'Configure nisab and rates for Zakat types.' => 'حوّل وحدد النصاب ونسب الزكاة لأنواعها.',
            'Nisab' => 'النصاب',
            'Rate (e.g. 0.025)' => 'النسبة (مثلاً 0.025)',
            'Minimum amount required before Zakat is due for this type.' => 'الحد الأدنى المطلوب قبل استحقاق الزكاة لهذا النوع.',
            'Decimal percentage to apply (e.g. 0.025 = 2.5%).' => 'النسبة العشرية المطبقة (مثلاً 0.025 = 2.5%).',
            'Donation App — Zakat Settings' => 'التبرع — إعدادات الزكاة',
            'Progress' => 'التقدم',
        );
    }

    if ( isset( $map[ $text ] ) ) {
        return $map[ $text ];
    }

    return $translated;
}
