<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('donation_register_categories_shortcode')) {
    function donation_register_categories_shortcode() {
        add_shortcode('donation_categories', 'donation_categories_shortcode');
    }
    add_action('init', 'donation_register_categories_shortcode');
}

if (!function_exists('donation_categories_shortcode')) {
    function donation_categories_shortcode($atts = []) {
        if (!function_exists('get_terms')) return '<p>No categories available.</p>';

        $atts = shortcode_atts([
            'columns' => 4,
            // show empty categories by default so all categories appear
            'hide_empty' => false,
            'per_page' => 12,
        ], $atts, 'donation_categories');

        $columns = absint($atts['columns']);

        // If a category is requested via query param, show products for that category
        $requested = isset($_GET['donation_cat']) ? sanitize_text_field(wp_unslash($_GET['donation_cat'])) : '';

        ob_start();

        if ($requested) {
            // Show products for the requested product_cat
            if (!class_exists('WooCommerce')) {
                echo '<p>WooCommerce is required to display products.</p>';
                return ob_get_clean();
            }

            $term = get_term_by('slug', $requested, 'product_cat');
            if (!$term || is_wp_error($term)) {
                echo '<p>Category not found.</p>';
                return ob_get_clean();
            }

            $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
            $args = [
                'post_type' => 'product',
                'posts_per_page' => absint($atts['per_page']),
                'paged' => $paged,
                'tax_query' => [[
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $term->slug,
                ]],
            ];

            $query = new WP_Query($args);

            echo '<div class="donation-products-list">';
            echo '<h2 class="donation-products-title">' . esc_html($term->name) . '</h2>';

            if ($query->have_posts()) {
                echo '<div class="donation-cards-grid donation-products-grid columns-' . esc_attr($columns) . '">';
                while ($query->have_posts()) {
                    $query->the_post();
                    if (function_exists('donation_render_card')) {
                        donation_render_card(get_the_ID());
                    } else {
                        echo '<div class="donation-card"><h3>' . esc_html(get_the_title()) . '</h3></div>';
                    }
                }
                echo '</div>'; // grid
                // pagination
                $big = 999999999; // need an unlikely integer
                $pagination = paginate_links([
                    'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                    'format' => '?paged=%#%',
                    'current' => max(1, $paged),
                    'total' => $query->max_num_pages,
                    'type' => 'list',
                ]);
                if ($pagination) {
                    echo '<nav class="donation-pagination">' . $pagination . '</nav>';
                }

            } else {
                echo '<p>No products found in this category.</p>';
            }

            echo '</div>'; // donation-products-list

            wp_reset_postdata();
            return ob_get_clean();
        }

        // Otherwise, show categories grid
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => (bool) $atts['hide_empty'],
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            echo '<p>No categories found.</p>';
            return ob_get_clean();
        }

        echo '<div class="donation-categories-grid columns-' . esc_attr($columns) . '">';

        $current_url = (is_front_page() || is_home()) ? home_url(add_query_arg([], '')) : get_permalink();
        $current_url = remove_query_arg('donation_cat');

        foreach ($terms as $term) {
            $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            $img = '';
            if ($thumb_id) {
                $img = wp_get_attachment_image($thumb_id, 'medium', false, ['class' => 'donation-category-image']);
            } else {
                $img = '<div class="donation-category-placeholder"></div>';
            }

            // ensure the projects page exists and link to it with pre-selected category via ?cats=slug
            $projects_page = get_page_by_path('donation-projects');
            if (!$projects_page) {
                // create the page so links go to a dedicated projects page
                $page_id = wp_insert_post([
                    'post_title' => 'مشاريع عامة',
                    'post_name' => 'donation-projects',
                    'post_content' => '[donation_projects]',
                    'post_status' => 'publish',
                    'post_type' => 'page',
                ]);
                if ($page_id && !is_wp_error($page_id)) {
                    $projects_page = get_post($page_id);
                }
            }

            if ($projects_page) {
                $link = add_query_arg('cats', $term->slug, get_permalink($projects_page->ID));
            } else {
                // fallback to current page if something goes wrong
                $link = add_query_arg('cats', $term->slug, get_permalink());
            }

            echo '<article class="donation-category-card">';
            echo '<a class="donation-category-link" href="' . esc_url($link) . '">';
            echo '<div class="donation-category-image-wrap">' . $img . '</div>';
            echo '<div class="donation-category-body">';
            echo '<h3 class="donation-category-title">' . esc_html($term->name) . '</h3>';
            if (!empty($term->description)) {
                echo '<div class="donation-category-desc">' . esc_html(wp_trim_words($term->description, 12)) . '</div>';
            }
            echo '</div>'; // body
            echo '</a>';
            echo '</article>';
        }

        echo '</div>'; // grid

        return ob_get_clean();
    }
}
