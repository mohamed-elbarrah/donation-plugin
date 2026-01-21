<?php
/**
 * Admin settings for Zakat feature
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'donation_app_zakat_admin_menu' );
add_action( 'admin_init', 'donation_app_zakat_settings_init' );

function donation_app_zakat_admin_menu() {
    // Add a top-level admin menu so Zakat appears in the admin sidebar
    add_menu_page(
        __( 'Donation Zakat', 'donation-app' ),
        __( 'Donation Zakat', 'donation-app' ),
        'manage_options',
        'donation-app-zakat',
        'donation_app_zakat_settings_page',
        'dashicons-money',
        58
    );
}

function donation_app_zakat_settings_init() {
    register_setting( 'donation_app_zakat', DONATION_APP_ZAKAT_OPTIONS, array( 'sanitize_callback' => 'donation_app_zakat_sanitize_options' ) );

    add_settings_section(
        'donation_app_zakat_section',
        __( 'Zakat Settings', 'donation-app' ),
        function() { echo '<p>' . esc_html__( 'Configure nisab and rates for Zakat types.', 'donation-app' ) . '</p>'; },
        'donation_app_zakat'
    );

    $types = array( 'cash' => 'Cash', 'gold' => 'Gold', 'silver' => 'Silver', 'stocks' => 'Stocks' );

    foreach ( $types as $key => $label ) {
        add_settings_field(
            $key . '_nisab',
            sprintf( '%s %s', $label, esc_html__( 'Nisab', 'donation-app' ) ),
            'donation_app_zakat_field_nisab_cb',
            'donation_app_zakat',
            'donation_app_zakat_section',
            array( 'label_for' => $key . '_nisab', 'type' => $key )
        );

        add_settings_field(
            $key . '_rate',
            sprintf( '%s %s', $label, esc_html__( 'Rate (e.g. 0.025)', 'donation-app' ) ),
            'donation_app_zakat_field_rate_cb',
            'donation_app_zakat',
            'donation_app_zakat_section',
            array( 'label_for' => $key . '_rate', 'type' => $key )
        );
    }
}

function donation_app_zakat_field_nisab_cb( $args ) {
    $opts = donation_app_get_zakat_options();
    $type = $args['type'];
    $name = DONATION_APP_ZAKAT_OPTIONS . '[' . $type . '_nisab]';
    $val  = isset( $opts[ $type . '_nisab' ] ) ? donation_app_esc_money( $opts[ $type . '_nisab' ] ) : '';
    printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />', esc_attr( $type . '_nisab' ), esc_attr( $name ), $val );
    printf( '<p class="description">%s</p>', esc_html__( 'Minimum amount required before Zakat is due for this type.', 'donation-app' ) );
}

function donation_app_zakat_field_rate_cb( $args ) {
    $opts = donation_app_get_zakat_options();
    $type = $args['type'];
    $name = DONATION_APP_ZAKAT_OPTIONS . '[' . $type . '_rate]';
    $val  = isset( $opts[ $type . '_rate' ] ) ? esc_attr( $opts[ $type . '_rate' ] ) : '0.025';
    printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />', esc_attr( $type . '_rate' ), esc_attr( $name ), $val );
    printf( '<p class="description">%s</p>', esc_html__( 'Decimal percentage to apply (e.g. 0.025 = 2.5%).', 'donation-app' ) );
}

function donation_app_zakat_sanitize_options( $input ) {
    $clean = array();
    $fields = array( 'cash_nisab', 'cash_rate', 'gold_nisab', 'gold_rate', 'silver_nisab', 'silver_rate', 'stocks_nisab', 'stocks_rate' );

    foreach ( $fields as $f ) {
        if ( isset( $input[ $f ] ) ) {
            // remove commas and cast
            $value = str_replace( ',', '', $input[ $f ] );
            $value = floatval( $value );
            $clean[ $f ] = $value;
        }
    }

    return $clean;
}

function donation_app_zakat_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Donation App — Zakat Settings', 'donation-app' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'donation_app_zakat' );
            do_settings_sections( 'donation_app_zakat' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
