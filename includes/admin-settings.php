<?php
if (!defined('ABSPATH')) exit;

/**
 * Donation App - Admin settings
 * Adds a Settings -> Donation App page where the admin can toggle
 * whether to use the default WooCommerce currency symbol or the
 * plugin's custom symbol.
 */

add_action('admin_menu', 'donation_admin_menu');
function donation_admin_menu() {
    add_options_page(
        'Donation App',
        'Donation App',
        'manage_options',
        'donation-app-settings',
        'donation_render_settings_page'
    );
}

add_action('admin_init', 'donation_register_settings');
function donation_register_settings() {
    register_setting('donation_app_options', 'donation_use_default_currency_symbol', array(
        'type' => 'string',
        'sanitize_callback' => 'donation_sanitize_yes_no',
        'default' => 'no',
    ));

    add_settings_section('donation_general_section', 'General', '__return_false', 'donation-app-settings');

    add_settings_field(
        'donation_use_default_currency_symbol',
        'Use default currency symbol',
        'donation_setting_use_default_currency_symbol_cb',
        'donation-app-settings',
        'donation_general_section'
    );
}

function donation_sanitize_yes_no($val) {
    if ($val === 'yes') return 'yes';
    return 'no';
}

function donation_setting_use_default_currency_symbol_cb() {
    $val = get_option('donation_use_default_currency_symbol', 'no');
    ?>
    <label>
        <input type="checkbox" name="donation_use_default_currency_symbol" value="yes" <?php checked($val, 'yes'); ?>>
        Use the store's default WooCommerce currency symbol instead of the plugin custom symbol
    </label>
    <?php
}

function donation_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>Donation App Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('donation_app_options');
            do_settings_sections('donation-app-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
