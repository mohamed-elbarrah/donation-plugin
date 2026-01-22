<?php
if (!defined('ABSPATH')) exit;

add_shortcode('donation_campaigns', function ($atts) {

    $atts = shortcode_atts([
        'limit' => 4,
        // mode: can be 'donation' or 'wakf' (or Arabic 'تبرع' / 'وقف')
        'mode' => '',
        // optional category slugs (comma-separated) to restrict results to product categories
        'category' => '',
    ], $atts);

    // Build meta_query: always require _donation_target, optionally filter by _donation_mode
    $meta_query = [
        [
            'key' => '_donation_target',
            'compare' => 'EXISTS',
        ]
    ];

    // support Arabic labels as well as stored slugs
    $mode_raw = trim((string) $atts['mode']);
    if ($mode_raw !== '') {
        // allow comma-separated values
        $parts = array_filter(array_map('trim', explode(',', $mode_raw)));
        $mapped = [];
        $map = [
            'تبرع' => 'donation',
            'وقف' => 'wakf',
            'donation' => 'donation',
            'wakf' => 'wakf',
        ];
        foreach ($parts as $p) {
            if (isset($map[$p])) $mapped[] = $map[$p];
        }
        if (!empty($mapped)) {
            $meta_query[] = [
                'key' => '_donation_mode',
                'value' => array_values(array_unique($mapped)),
                'compare' => 'IN',
            ];
        }
    }

    $base_args = [
        'post_type' => 'product',
        'posts_per_page' => intval($atts['limit']),
        'meta_query' => $meta_query,
    ];

    // category attribute support (comma-separated slugs)
    $cat_raw = trim((string) $atts['category']);
    if ($cat_raw !== '') {
        $parts = array_filter(array_map('trim', explode(',', $cat_raw)));
        if (!empty($parts)) {
            $base_args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => array_values($parts),
            ]];
        }
    }

    $query = new WP_Query($base_args);
    // exclude any products marked as quick donation
    $quick_args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [[
            'key' => '_quick_donation',
            'value' => 'yes',
            'compare' => '='
        ]]
    ];
    $quick_ids = get_posts($quick_args);
    if (!empty($quick_ids)) {
        $q_args = $base_args;
        $q_args['post__not_in'] = $quick_ids;
        $query = new WP_Query($q_args);
    }

    if (!$query->have_posts()) return '';

    ob_start();
    echo '<div class="donation-grid">';

    while ($query->have_posts()) {
        $query->the_post();
        if (function_exists('donation_render_card')) {
            donation_render_card(get_the_ID());
        } else {
            echo '<div class="donation-card"><h3>' . esc_html(get_the_title()) . '</h3></div>';
        }
    }

    // Attach donation amount to donate link when present in the input
    ?>
    <script>
    (function(){
        document.addEventListener('click', function(e){
            var clicked = e.target;
            if(!clicked) return;

            // handle clicks on donate links (use closest to handle inner elements)
            var donateBtn = clicked.closest && clicked.closest('.donate-btn');
            if(donateBtn){
                var btn = donateBtn;
                var card = btn.closest('.donation-card');
                if(card){
                    var input = card.querySelector('.amount-input');
                    if(input && input.value && input.value.trim() !== ''){
                        try{
                            var url = new URL(btn.href, window.location.origin);
                            url.searchParams.set('donation_amount', input.value.trim());
                            btn.href = url.toString();
                        }catch(err){
                            var sep = btn.href.indexOf('?') === -1 ? '?' : '&';
                            btn.href = btn.href + sep + 'donation_amount=' + encodeURIComponent(input.value.trim());
                        }
                    }
                }
            }

            // handle clicks on add-to-cart button (use closest to catch svg clicks)
            var cartBtn = clicked.closest && clicked.closest('.cart-btn');
            if(cartBtn){
                e.preventDefault();
                var btn = cartBtn;
                var card = btn.closest && btn.closest('.donation-card');
                if(!card) return;
                var input = card.querySelector('.amount-input');
                var productId = btn.dataset && btn.dataset.productId ? btn.dataset.productId : (input && input.dataset ? input.dataset.productId : null);
                if(!productId) return;

                try{
                    var url = new URL(window.location.href, window.location.origin);
                    // ensure base path without query
                    url.search = '';
                    url.hash = '';
                    url.searchParams.set('add-to-cart', productId);
                    if(input && input.value && input.value.trim() !== ''){
                        url.searchParams.set('donation_amount', input.value.trim());
                    }
                    window.location.href = url.toString();
                }catch(err){
                    // fallback: build simple URL
                    var base = window.location.origin + window.location.pathname;
                    var qs = '?add-to-cart=' + encodeURIComponent(productId);
                    if(input && input.value && input.value.trim() !== ''){
                        qs += '&donation_amount=' + encodeURIComponent(input.value.trim());
                    }
                    window.location.href = base + qs;
                }
            }
        }, false);
    })();
    </script>

    <script>
    (function(){
        // make header, title, and amounts-row clickable to open the product page
        document.addEventListener('click', function(e){
            var tgt = e.target;
            if(!tgt) return;

            // ignore clicks on interactive controls to avoid interfering
            if(tgt.closest && tgt.closest('a, button, .share-icon, .cart-btn, .preset-amount, .donate-btn')) return;

            var clickable = tgt.closest && tgt.closest('.card-header, .card-title, .amounts-row');
            if(!clickable) return;

            var card = clickable.closest && clickable.closest('.donation-card');
            if(!card) return;

            var link = card.querySelector('.donate-btn');
            if(link && link.href){
                // navigate to product page
                window.location.href = link.href;
            }
        }, false);
    })();
    </script>

    <script>
    (function(){
        // Animate linear progress bars from 0 to their data-percent
        function animateLinearBar(container){
            var fill = container.querySelector('.progress-bar-fill');
            if(!fill) return;
            var target = parseFloat(fill.getAttribute('data-percent')) || 0;
            // start from 0 for animation
            fill.style.width = '0%';
            var label = fill.querySelector('.progress-bar-label');
            var duration = 900;
            var start = null;
            function step(ts){
                if(!start) start = ts;
                var t = Math.min(1, (ts - start) / duration);
                var eased = 1 - Math.pow(1 - t, 3);
                var value = eased * target;
                fill.style.width = value + '%';
                if(label) label.textContent = Math.round(value) + '%';
                if(t < 1) requestAnimationFrame(step);
                else if(label){
                    // add a subtle breath effect once finished
                    label.classList.add('breath');
                    setTimeout(function(){ label.classList.remove('breath'); }, 900);
                }
            }
            requestAnimationFrame(step);
        }

        document.querySelectorAll('.progress-bar-container').forEach(function(container){
            animateLinearBar(container);
        });

        // animate checkmark stroke drawing
        function animateChecks(){
            document.querySelectorAll('.collected-check path').forEach(function(path){
                var len = path.getTotalLength();
                path.style.strokeDasharray = len + ' ' + len;
                path.style.strokeDashoffset = len;
                path.getBoundingClientRect();
                path.style.transition = 'stroke-dashoffset 700ms ease 120ms';
                path.style.strokeDashoffset = '0';
            });
        }
        animateChecks();

        // shimmer effect for amount: retrigger periodically
        setInterval(function(){
            document.querySelectorAll('.shimmer-amount').forEach(function(el){
                // toggle shimmer on the amount itself
                el.classList.remove('shimmer-active');
                void el.offsetWidth;
                el.classList.add('shimmer-active');

                // also trigger shimmer on the parent collected box if present
                var box = el.closest && el.closest('.amount-box.collected');
                if(box){
                    box.classList.remove('shimmer-active');
                    void box.offsetWidth;
                    box.classList.add('shimmer-active');
                }

                // also trigger shimmer on the progress fill within the same card
                var card = el.closest && el.closest('.donation-card');
                if(card){
                    var fill = card.querySelector('.progress-bar-fill');
                    if(fill){
                        fill.classList.remove('shimmer-active');
                        void fill.offsetWidth;
                        fill.classList.add('shimmer-active');
                    }
                }
            });
        }, 3000);
    })();
    </script>

    <script>
    (function(){
        // preset amount selection handling
        document.addEventListener('click', function(e){
            var btn = e.target.closest && e.target.closest('.preset-amount');
            if(!btn) return;
            var container = btn.closest && btn.closest('.donation-card');
            if(!container) return;
            // remove active from siblings
            container.querySelectorAll('.preset-amount').forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
            // mark this active
            btn.classList.add('active'); btn.setAttribute('aria-pressed','true');
            // set input value or enable input for 'Other'
            var input = container.querySelector('.amount-input');
            if(btn.classList.contains('preset-other')){
                if(input){ input.disabled = false; input.value = ''; input.focus(); }
            }else{
                if(input && btn.dataset && btn.dataset.amount){ input.value = btn.dataset.amount; input.disabled = true; }
            }
        }, false);
    })();
    </script>

    <script>
    (function(){
        // quick custom options removed; users type into input when 'Other' selected
    })();
    </script>

    <script>
    (function(){
        // initialize input disabled state based on active preset for each card
        document.querySelectorAll('.donation-card').forEach(function(card){
            var input = card.querySelector('.amount-input');
            if(!input) return;
            var active = card.querySelector('.preset-amount.active');
            if(active && active.classList.contains('preset-other')){
                input.disabled = false;
            }else if(active && active.dataset && active.dataset.amount){
                input.disabled = true;
                input.value = active.dataset.amount;
            }else{
                // if no active, default to enabled
                input.disabled = false;
            }
        });
    })();
    </script>

    <!-- Share modal markup -->
    <div class="share-modal-overlay" id="donation-share-modal" aria-hidden="true">
        <div class="share-modal" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle">
            <button class="modal-close" type="button" aria-label="Close modal">×</button>
            <div class="modal-body">
                <div class="share-icon-lg" aria-hidden="true">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M18 8c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.03.47.07.7L8.82 9.35C8.22 8.53 7.2 8 6 8c-1.66 0-3 1.34-3 3s1.34 3 3 3c1.2 0 2.22-.53 2.82-1.35l6.25 3.65c-.04.23-.07.46-.07.7 0 1.66 1.34 3 3 3s3-1.34 3-3-1.34-3-3-3c-1.2 0-2.22.53-2.82 1.35L8.93 11.7c.04-.23.07-.46.07-.7 0-.24-.03-.47-.07-.7l6.25-3.65C15.78 7.47 16.8 8 18 8z"/>
                    </svg>
                </div>
                <h3 id="shareModalTitle">مشاركة عبر وسائل التواصل الاجتماعي</h3>
                <p>رابط المشاركة</p>
                <div class="share-link-row">
                    <input type="text" class="share-link-input" id="donation-share-link" readonly aria-label="Share link" />
                    <button class="share-copy-btn" id="donation-copy-link">نسخ الرابط</button>
                </div>
                <div class="share-socials" role="group" aria-label="share links">
                    <a href="#" class="social-btn facebook" id="share-facebook" target="_blank" rel="noopener noreferrer" aria-label="Facebook"></a>
                    <a href="#" class="social-btn twitter" id="share-twitter" target="_blank" rel="noopener noreferrer" aria-label="Twitter"></a>
                    <a href="#" class="social-btn whatsapp" id="share-whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"></a>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        var overlay = document.getElementById('donation-share-modal');
        var input = document.getElementById('donation-share-link');
        var copyBtn = document.getElementById('donation-copy-link');
        var closeBtn = overlay && overlay.querySelector('.modal-close');
        var fbBtn = document.getElementById('share-facebook');
        var twBtn = document.getElementById('share-twitter');
        var waBtn = document.getElementById('share-whatsapp');

        function openShareModal(url){
            if(!overlay) return;
            input.value = url || window.location.href;
            // set social hrefs
            var enc = encodeURIComponent(input.value);
            fbBtn.href = 'https://www.facebook.com/sharer/sharer.php?u=' + enc;
            twBtn.href = 'https://twitter.com/intent/tweet?url=' + enc;
            waBtn.href = 'https://api.whatsapp.com/send?text=' + enc;

            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden','false');
            // focus copy button for keyboard users
            copyBtn && copyBtn.focus();
            document.body.style.overflow = 'hidden';
        }

        function closeShareModal(){
            if(!overlay) return;
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden','true');
            document.body.style.overflow = '';
        }

        // open modal when clicking any share-icon inside a card
        document.addEventListener('click', function(e){
            var btn = e.target.closest && e.target.closest('.share-icon');
            if(!btn) return;
            e.preventDefault();
            var card = btn.closest && btn.closest('.donation-card');
            var permalinkEl = card && card.querySelector('.donate-btn');
            var url = permalinkEl ? permalinkEl.href : window.location.href;
            openShareModal(url);
        }, false);

        // copy action
        copyBtn && copyBtn.addEventListener('click', function(){
            if(!input) return;
            input.select();
            input.setSelectionRange(0, 99999);
            try{
                var ok = document.execCommand('copy');
                var prev = copyBtn.textContent;
                copyBtn.textContent = ok ? 'تم النسخ' : 'نسخ';
                setTimeout(function(){ copyBtn.textContent = prev; }, 1500);
            }catch(err){
                // fallback using clipboard API
                if(navigator.clipboard && navigator.clipboard.writeText){
                    navigator.clipboard.writeText(input.value).then(function(){
                        var prev = copyBtn.textContent;
                        copyBtn.textContent = 'تم النسخ';
                        setTimeout(function(){ copyBtn.textContent = prev; }, 1500);
                    });
                }
            }
        });

        // close handlers
        closeBtn && closeBtn.addEventListener('click', closeShareModal);
        overlay && overlay.addEventListener('click', function(e){ if(e.target === overlay) closeShareModal(); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeShareModal(); });
    })();
    </script>

    <?php

    echo '</div>';

    // show "مزيد من الفرص" button linking to the shop when there are more products than the limit
    $limit = intval($atts['limit']);
    if ($limit > 0 && isset($query->found_posts) && $query->found_posts > $limit) {
        // build shop URL
        $shop_url = '';
        if (function_exists('wc_get_page_id')) {
            $shop_id = wc_get_page_id('shop');
            if ($shop_id) $shop_url = get_permalink($shop_id);
        }
        if (!$shop_url) {
            $shop_url = home_url('/shop/');
        }

        // append donation_mode param if mode was provided to help the shop filter
        $mode_raw2 = trim((string) $atts['mode']);
        if ($mode_raw2 !== '') {
            $parts2 = array_filter(array_map('trim', explode(',', $mode_raw2)));
            $map2 = [
                'تبرع' => 'donation',
                'وقف' => 'wakf',
                'donation' => 'donation',
                'wakf' => 'wakf',
            ];
            $mapped2 = [];
            foreach ($parts2 as $p) {
                if (isset($map2[$p])) $mapped2[] = $map2[$p];
            }
            if (!empty($mapped2)) {
                $shop_url = add_query_arg('donation_mode', implode(',', array_values(array_unique($mapped2))), $shop_url);
            }
        }

        echo '<div class="donation-more-wrap">';
        echo '<a class="donation-more-btn" href="' . esc_url($shop_url) . '">مزيد من الفرص</a>';
        echo '</div>';
    }

    wp_reset_postdata();

    return ob_get_clean();
});

    /*
     * Shortcode: [donation_store]
     * Renders a products grid using `donation_render_card` and a category filter UI.
     * Attributes: per_page (int, default -1 = all), columns (int), show_filter (bool)
     */
    add_shortcode('donation_store', function($atts){
        $atts = shortcode_atts([
            'per_page' => -1,
            'columns' => 3,
            'show_filter' => 'true',
            // allow explicit category(s) via shortcode attribute (comma-separated slugs)
            'category' => '',
            // optional mode filter like 'donation' or 'wakf' (or Arabic labels)
            'mode' => '',
        ], $atts, 'donation_store');

        $show_filter = filter_var($atts['show_filter'], FILTER_VALIDATE_BOOLEAN);

        // selected categories: shortcode `category` attribute overrides query param `donation_cat`
        $selected = [];
        $cat_attr = trim((string) $atts['category']);
        if ($cat_attr !== '') {
            $parts = array_filter(array_map('trim', explode(',', $cat_attr)));
            if (!empty($parts)) $selected = $parts;
        } else {
            if (!empty($_GET['donation_cat'])) {
                $raw = sanitize_text_field(wp_unslash($_GET['donation_cat']));
                $parts = array_filter(array_map('trim', explode(',', $raw)));
                if (!empty($parts)) $selected = $parts;
            }
        }

        // fetch product categories for the filter
        $cats = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
        ]);

        // prepare query args
        $query_args = [
            'post_type' => 'product',
            'posts_per_page' => intval($atts['per_page']),
        ];

        // optional mode filter passed via shortcode attribute
        $mode_attr = trim((string) $atts['mode']);
        if ($mode_attr !== '') {
            $partsm = array_filter(array_map('trim', explode(',', $mode_attr)));
            $mapm = [
                'تبرع' => 'donation',
                'وقف' => 'wakf',
                'donation' => 'donation',
                'wakf' => 'wakf',
            ];
            $mappedm = [];
            foreach ($partsm as $p) { if (isset($mapm[$p])) $mappedm[] = $mapm[$p]; }
            if (!empty($mappedm)) {
                $query_args['meta_query'] = [[
                    'key' => '_donation_mode',
                    'value' => array_values(array_unique($mappedm)),
                    'compare' => 'IN',
                ]];
            }
        }
        // exclude quick-donation products from this store shortcode
        $quick_args2 = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_quick_donation',
                'value' => 'yes',
                'compare' => '='
            ]]
        ];
        $quick_ids2 = get_posts($quick_args2);
        if (!empty($quick_ids2)) {
            $query_args['post__not_in'] = $quick_ids2;
        }
        if (!empty($selected)) {
            $query_args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $selected,
            ]];
        }

        $query = new WP_Query($query_args);

        ob_start();

        echo '<div class="donation-store-wrap">';

        if ($show_filter && !is_wp_error($cats) && !empty($cats)) {
            echo '<div class="donation-filter-bar">';
            echo '<button type="button" class="donation-filter-toggle">فلاتر التصنيف</button>';
            // panel has no fixed id and is initially closed; visibility controlled via .is-open
            echo '<div class="donation-filter-panel">';
            echo '<div class="donation-filter-grid">';
            foreach ($cats as $cat) {
                $checked = in_array($cat->slug, $selected) ? 'checked' : '';
                $id = 'donation-filter-' . esc_attr($cat->term_id);
                echo '<label class="donation-filter-item" for="' . esc_attr($id) . '">';
                echo '<span class="filter-chip">';
                echo '<input type="checkbox" id="' . esc_attr($id) . '" data-slug="' . esc_attr($cat->slug) . '" ' . $checked . ' />';
                echo esc_html($cat->name);
                echo '</span>';
                echo '</label>';
            }
            echo '</div>'; // grid
            echo '<div class="donation-filter-actions">';
            // use class names (not IDs) so multiple shortcodes won't conflict
            echo '<button type="button" class="donation-filter-apply">تطبيق</button>';
            echo '<button type="button" class="donation-filter-clear">مسح</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        echo '<div class="donation-products-list donation-grid columns-' . intval($atts['columns']) . '">';

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                if (function_exists('donation_render_card')) {
                    donation_render_card(get_the_ID());
                } else {
                    echo '<div class="donation-card"><h3>' . esc_html(get_the_title()) . '</h3></div>';
                }
            }
        } else {
            echo '<div class="donation-empty">لا توجد منتجات للعرض.</div>';
        }

        echo '</div>'; // products list

        // small JS for toggling filter panel and applying/clearing filters (instance-scoped)
        ?>
        <script>
        (function(){
            // delegate: handle each filter-bar separately so multiple shortcodes work
            document.querySelectorAll('.donation-filter-bar').forEach(function(bar){
                var toggle = bar.querySelector('.donation-filter-toggle');
                var panel = bar.querySelector('.donation-filter-panel');
                var applyBtn = bar.querySelector('.donation-filter-apply');
                var clearBtn = bar.querySelector('.donation-filter-clear');

                if(toggle && panel){
                    toggle.addEventListener('click', function(e){
                        e.preventDefault(); e.stopPropagation();
                        panel.classList.toggle('is-open');
                    });
                }

                function buildAndNavigate(selected){
                    var base = window.location.pathname;
                    var params = new URLSearchParams(window.location.search);
                    if(selected.length){
                        params.set('donation_cat', selected.join(','));
                    }else{
                        params.delete('donation_cat');
                    }
                    window.location.href = base + (params.toString() ? '?' + params.toString() : '');
                }

                if(applyBtn && panel){
                    applyBtn.addEventListener('click', function(){
                        var checks = panel.querySelectorAll('input[type="checkbox"]:checked');
                        var selected = [];
                        checks.forEach(function(c){ if(c.dataset && c.dataset.slug) selected.push(c.dataset.slug); });
                        buildAndNavigate(selected);
                    });
                }

                if(clearBtn && panel){
                    clearBtn.addEventListener('click', function(){
                        panel.querySelectorAll('input[type="checkbox"]').forEach(function(c){ c.checked = false; });
                        buildAndNavigate([]);
                    });
                }
            });

            // close open panels when clicking outside
            document.addEventListener('click', function(e){
                document.querySelectorAll('.donation-filter-panel.is-open').forEach(function(p){
                    if(!p.closest('.donation-filter-bar').contains(e.target)){
                        p.classList.remove('is-open');
                    }
                });
            }, false);
        })();
        </script>
        <script>
        (function(){
            // delegated handler for filter toggle buttons (works even if theme moves the button)
            document.addEventListener('click', function(e){
                var btn = e.target.closest && e.target.closest('.donation-filter-toggle');
                if(!btn) return;
                e.preventDefault();
                // Find panel inside nearest .donation-filter-bar, otherwise try aria-controls
                var bar = btn.closest && btn.closest('.donation-filter-bar');
                var panel = null;
                if(bar) panel = bar.querySelector('.donation-filter-panel');
                if(!panel && btn.getAttribute('aria-controls')){
                    try{ panel = document.getElementById(btn.getAttribute('aria-controls')); }catch(err){ panel = null; }
                }
                if(panel){
                    var isOpen = panel.classList.toggle('is-open');
                    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                }
            }, false);

            // make header, title, and amounts-row clickable to open the product page for store cards
            document.addEventListener('click', function(e){
                var tgt = e.target;
                if(!tgt) return;

                // ignore clicks on interactive controls to avoid interfering
                if(tgt.closest && tgt.closest('a, button, .share-icon, .cart-btn, .preset-amount, .donate-btn')) return;

                var clickable = tgt.closest && tgt.closest('.card-header, .card-title, .amounts-row');
                if(!clickable) return;

                var card = clickable.closest && clickable.closest('.donation-card');
                if(!card) return;

                var link = card.querySelector('.donate-btn');
                if(link && link.href){
                    window.location.href = link.href;
                }
            }, false);
        })();
        </script>
        <?php

        echo '</div>'; // wrap
        wp_reset_postdata();

        return ob_get_clean();
    });
