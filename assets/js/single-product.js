(function(){
    document.addEventListener('DOMContentLoaded', function(){
        // Remove leftover theme elements we don't need on product pages
        if(document.body.classList.contains('post-type-product') || document.querySelector('[data-product-id]')){
            document.querySelectorAll('.ct-product-divider, .product_meta').forEach(function(el){
                try{ el.remove(); }catch(e){ el.parentNode && el.parentNode.removeChild(el); }
            });
        }
        // handle product-specific elements by querying within the product wrapper
        var productWrappers = document.querySelectorAll('[data-product-id]');

        // gallery behavior: swap main image when a thumbnail is clicked
        document.querySelectorAll('.donation-card-header').forEach(function(header){
            var mainImg = header.querySelector('.donation-main-image') || header.querySelector('img');
            var thumbs = header.querySelectorAll('.gallery-thumb');
            if(!mainImg || !thumbs || thumbs.length === 0) return;
            thumbs.forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var full = btn.getAttribute('data-full');
                    if(full){
                        // swap src and update active state
                        try{ mainImg.src = full; } catch(er){}
                        // update active state on thumbs
                        thumbs.forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-pressed','false'); });
                        btn.classList.add('active'); btn.setAttribute('aria-pressed','true');
                    }
                });
            });
        });

        // donation inputs and presets are per-product; initialize per wrapper
        var productWrappers = document.querySelectorAll('[data-product-id]');
        if (productWrappers && productWrappers.length) {
            productWrappers.forEach(function(wrapper){
                var presets = wrapper.querySelectorAll('.donation-presets .preset-amount');
                var localInput = wrapper.querySelector('.donation-amount-input');

                // set initial state based on which preset is active (template sets first active)
                var active = null;
                presets.forEach(function(b){ if(b.classList.contains('active')) active = b; });
                if(!active && presets.length) active = presets[0];

                if(localInput){
                    if(active && active.classList.contains('preset-other')){
                        localInput.disabled = false;
                        // leave value as is or clear to encourage custom entry
                        localInput.value = '';
                    } else if(active && active.getAttribute('data-amount')){
                        localInput.value = active.getAttribute('data-amount');
                        localInput.disabled = true;
                    } else if(presets.length){
                        // fallback: use first preset value
                        var first = presets[0];
                        if(first && first.getAttribute('data-amount')){
                            localInput.value = first.getAttribute('data-amount');
                            localInput.disabled = true;
                        }
                    }
                }

                // attach handlers scoped to this wrapper so other products aren't affected
                presets.forEach(function(btn){
                    btn.addEventListener('click', function(e){
                        e.preventDefault();
                        presets.forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
                        btn.classList.add('active'); btn.setAttribute('aria-pressed','true');
                        if(!localInput) return;
                        if(btn.classList.contains('preset-other')){
                            localInput.value = '';
                            localInput.disabled = false;
                            try{ localInput.focus(); }catch(err){}
                        } else {
                            var amt = btn.getAttribute('data-amount') || '';
                            localInput.value = amt;
                            localInput.disabled = true;
                        }
                    });
                });
            });
        } else {
            // fallback to existing global behaviour if no wrappers found
            var presetsGlobal = document.querySelectorAll('.donation-presets .preset-amount');
            var inputGlobal = document.querySelector('.donation-amount-input');
            if(presetsGlobal){
                presetsGlobal.forEach(function(btn){
                    btn.addEventListener('click', function(e){
                        var localInput = inputGlobal;
                        presetsGlobal.forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
                        btn.classList.add('active'); btn.setAttribute('aria-pressed','true');
                        if(btn.classList.contains('preset-other')){ if(localInput){ localInput.value=''; localInput.disabled=false; localInput.focus(); } }
                        else { if(localInput){ localInput.value = btn.getAttribute('data-amount') || ''; localInput.disabled=true; } }
                    });
                });
            }
        }

        var addToCart = document.getElementById('donation_add_to_cart');
        // donate-now buttons may be multiple; use delegated handlers below per product
        var donateNowButtons = document.querySelectorAll('.donation-donate-now');
        var pid = document.body.dataset && document.body.dataset.productId ? document.body.dataset.productId : null;
        if(!pid){
            // try meta from element
            var el = document.querySelector('[data-product-id]');
            if(el) pid = el.getAttribute('data-product-id');
        }

        // decide destination (cart vs checkout) per product wrapper
        if(addToCart){
            addToCart.addEventListener('click', function(e){
                if(!pid) return;
                var wrapper = document.querySelector('[data-product-id="' + pid + '"]');
                var badge = wrapper ? (wrapper.getAttribute('data-donation-badge') || '').trim() : '';
                var mode = wrapper ? (wrapper.getAttribute('data-donation-mode') || '').trim() : '';
                var checkoutUrl = wrapper ? (wrapper.getAttribute('data-checkout-url') || '') : '';
                var localInput = wrapper ? wrapper.querySelector('.donation-amount-input') : (document.querySelector('.donation-amount-input')||null);
                var qs = '?add-to-cart=' + encodeURIComponent(pid);
                if(localInput && localInput.value) qs += '&donation_amount=' + encodeURIComponent(localInput.value);
                if((mode === 'donation' || (badge && badge.indexOf('تبرع') !== -1)) && checkoutUrl){
                    window.location.href = checkoutUrl + (checkoutUrl.indexOf('?')===-1? qs : '&' + qs.replace('?',''));
                } else {
                    window.location.href = window.location.origin + window.location.pathname + qs;
                }
            });
        }

        if(donateNowButtons && donateNowButtons.length){
            donateNowButtons.forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    var wrapper = btn.closest('[data-product-id]');
                    var localPid = wrapper ? wrapper.getAttribute('data-product-id') : pid;
                    var localInput = wrapper ? wrapper.querySelector('.donation-amount-input') : (document.querySelector('.donation-amount-input')||null);
                    if(!localPid) return;
                    var badge = wrapper ? (wrapper.getAttribute('data-donation-badge') || '').trim() : '';
                    var mode = wrapper ? (wrapper.getAttribute('data-donation-mode') || '').trim() : '';
                    var checkoutUrl = wrapper ? (wrapper.getAttribute('data-checkout-url') || '') : '';
                    var qs = '?add-to-cart=' + encodeURIComponent(localPid);
                    if(localInput && localInput.value){ qs += '&donation_amount=' + encodeURIComponent(localInput.value); }
                    if((mode === 'donation' || (badge && badge.indexOf('تبرع') !== -1)) && checkoutUrl){
                        window.location.href = checkoutUrl + (checkoutUrl.indexOf('?')===-1? qs : '&' + qs.replace('?',''));
                    } else {
                        window.location.href = window.location.origin + window.location.pathname + qs;
                    }
                });
            });
        }

        // animate progress bar
        var fill = document.querySelector('.progress-bar-fill');
        if(fill){ var target = parseFloat(fill.getAttribute('data-percent')) || 0; fill.style.width='0%'; setTimeout(function(){ fill.style.transition='width 800ms ease'; fill.style.width = target + '%'; }, 50); }
    });
})();
