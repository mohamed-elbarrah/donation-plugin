<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('donation_register_projects_shortcode')) {
    function donation_register_projects_shortcode() {
        add_shortcode('donation_projects', 'donation_projects_shortcode');
        add_action('wp_enqueue_scripts', 'donation_projects_enqueue_assets');
    }
    add_action('init', 'donation_register_projects_shortcode');
}

function donation_projects_enqueue_assets() {
    wp_register_script(
        'donation-projects-js',
        plugin_dir_url(__FILE__) . '../assets/js/projects-filter.js',
        ['jquery'],
        '1.0.0',
        true
    );
    wp_enqueue_script('donation-projects-js');
}

if (!function_exists('donation_projects_shortcode')) {
    function donation_projects_shortcode($atts = []) {
        $atts = shortcode_atts([
            'columns' => 3,
            'per_page' => 12,
        ], $atts, 'donation_projects');

        // Ensure page exists: slug donation-projects
        $slug = 'donation-projects';
        $page = get_page_by_path($slug);
        if (!$page) {
            // create a minimal page (published) so links work — user can edit later
            $page_id = wp_insert_post([
                'post_title' => "مشاريع عامة",
                'post_name' => $slug,
                'post_content' => '[donation_projects]',
                'post_status' => 'publish',
                'post_type' => 'page',
            ]);
            $page = get_post($page_id);
        }

        // read selected categories from query param 'cats' (comma separated)
        $selected = isset($_GET['cats']) ? sanitize_text_field(wp_unslash($_GET['cats'])) : '';
        $selected_slugs = array_filter(array_map('trim', explode(',', $selected)));

        ob_start();

        // Header
        echo '<div class="donation-projects-page">';
        echo '<header class="donation-projects-header">';
        echo '<h1 class="donation-projects-title">مشاريع عامة</h1>';
        echo '<p class="donation-projects-sub">فرص تبرع متنوعة تصنع أثراً مستداماً وتحقق أثراً اجتماعياً واسعاً للحالات الأشد احتياجاً.</p>';
        echo '</header>';

        // Filter toggle (improved markup + ARIA)
        echo '<div class="donation-projects-filters">';
        echo '<button class="donation-filter-toggle" aria-expanded="false" aria-controls="donation-filter-panel">تصفية</button>';
        echo '<div id="donation-filter-panel" class="donation-filter-panel" aria-hidden="true">';

        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            echo '<form id="donation-filter-form">';
            echo '<div class="donation-filter-grid">';
            foreach ($terms as $term) {
                $checked = in_array($term->slug, $selected_slugs) ? 'checked' : '';
                $term_id = intval($term->term_id);
                // Render chip with the checkbox inside so input appears within the chip (right side in RTL)
                echo '<label class="donation-filter-item" for="donation-term-' . $term_id . '">';
                echo '<span class="filter-chip">';
                echo esc_html($term->name);
                // checkbox inside the chip; keep id for label association
                echo '<input type="checkbox" id="donation-term-' . $term_id . '" name="cats[]" value="' . esc_attr($term->slug) . '" ' . $checked . ' />';
                echo '</span>';
                echo '</label>';
            }
            echo '</div>';
            echo '<div class="donation-filter-actions">';
            echo '<button type="button" id="donation-filter-apply">تطبيق</button>';
            echo '<button type="button" id="donation-filter-clear">مسح</button>';
            echo '</div>';
            echo '</form>';
        } else {
            echo '<p>لا توجد فئات</p>';
        }

        echo '</div>'; // panel
        echo '</div>'; // filters

        // Products list (server-rendered based on selected)
        $paged = get_query_var('paged') ? absint(get_query_var('paged')) : 1;
        $args = [
            'post_type' => 'product',
            'posts_per_page' => absint($atts['per_page']),
            'paged' => $paged,
        ];
        if (!empty($selected_slugs)) {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $selected_slugs,
            ]];
        }

        $query = new WP_Query($args);

        echo '<div class="donation-projects-list">';
        if ($query->have_posts()) {
            echo '<div class="donation-cards-grid donation-products-grid columns-' . esc_attr($atts['columns']) . '">';
            while ($query->have_posts()) {
                $query->the_post();
                if (function_exists('donation_render_card')) {
                    donation_render_card(get_the_ID());
                } else {
                    echo '<div class="donation-card"><h3>' . esc_html(get_the_title()) . '</h3></div>';
                }
            }
            echo '</div>'; // grid
        } else {
            echo '<p>لا توجد منتجات لعرضها.</p>';
        }
        echo '</div>'; // projects-list

        echo '</div>'; // page

        wp_reset_postdata();
        return ob_get_clean();
    }
}
