<?php
if (!defined('ABSPATH')) exit;

// Render our donation-style card inside the shop loop for donation products
add_action('woocommerce_before_shop_loop_item', function() {
    global $product;
    if (!$product) return;
    $post_id = (int) $product->get_id();
    $has_target = get_post_meta($post_id, '_donation_target', true);
    if (empty($has_target)) return;

    // Output our card markup
    if (function_exists('donation_render_card')) {
        // Wrap in a container so styles can target it if needed
        echo '<div class="donation-card-replacement">';
        donation_render_card($post_id);
        echo '</div>';
    }

    // Add scoped CSS to hide the default WooCommerce loop pieces for this product
    // Use the post-{id} class that WooCommerce adds to product containers
            $css = "<style>\n"
            // hide common WooCommerce loop elements for this product container
            . ".post-" . $post_id . " .woocommerce-loop-product__link,\n"
            . ".post-" . $post_id . " .woocommerce-loop-product__title,\n"
            . ".post-" . $post_id . " .price,\n"
            . ".post-" . $post_id . " .button,\n"
            . ".post-" . $post_id . " .wc-loop-product__title { display: none !important; }\n"
                // hide product meta and category lists that some themes render under the card
                . ".post-" . $post_id . " .entry-meta,\n"
                . ".post-" . $post_id . " ul.entry-meta,\n"
                . ".post-" . $post_id . " .meta-categories,\n"
                . ".post-" . $post_id . " li.meta-categories,\n"
                . ".post-" . $post_id . " .posted_in,\n"
                . ".post-" . $post_id . " .product_meta,\n"
                . ".post-" . $post_id . " .woocommerce-loop-product__meta { display: none !important; }\n"
            // hide theme-specific media containers (direct children) to prevent duplicate images
            . ".post-" . $post_id . " > figure,\n"
            . ".post-" . $post_id . " > a.ct-media-container,\n"
            . ".post-" . $post_id . " figure.ct-media-container { display: none !important; }\n"
            // reset padding on product container and style our replacement
            . ".post-" . $post_id . " { padding: 0 !important; }\n"
            . ".post-" . $post_id . " > .donation-card-replacement { margin: 6px; }\n"
            . "</style>\n";
    echo $css;
}, 5);

// Optionally, remove the default thumbnail output for donation products (some themes use this hook)
add_action('woocommerce_before_shop_loop_item_title', function() {
    global $product;
    if (!$product) return;
    $post_id = (int) $product->get_id();
    $has_target = get_post_meta($post_id, '_donation_target', true);
    if ($has_target) {
        // prevent further default thumbnail output by echoing nothing here (we already output our image)
        // Note: this does not stop themes that print images directly in template files.
        return;
    }
}, 5);
