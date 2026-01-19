<?php
if (!defined('ABSPATH')) exit;

/**
 * Replace the WooCommerce currency symbol for Saudi Riyal (SAR)
 * with the requested Unicode symbol U+20C1.
 */
add_filter('woocommerce_currency_symbol', function ($symbol, $currency) {
    // Respect admin setting: allow using the store's default symbol
    $use_default = get_option('donation_use_default_currency_symbol', 'no');
    if ($use_default === 'yes') {
        return $symbol;
    }

    if ($currency === 'SAR') {
        // Use html_entity_decode to ensure correct UTF-8 character regardless of PHP escape support
        return html_entity_decode('&#x20C1;', ENT_NOQUOTES, 'UTF-8');
    }
    return $symbol;
}, 10, 2);
