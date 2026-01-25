/* Donation cards JS */
(function(){
  'use strict';

  // Handle preset clicks (support legacy .preset-amount and new .waqf-option)
  document.addEventListener('click', function(e){
    var btn = e.target.closest && (e.target.closest('.preset-amount') || e.target.closest('.waqf-option'));
    if(!btn) return;
    var container = btn.closest && btn.closest('.donation-card');
    if(!container) return;

    // deactivate siblings for both selectors
    container.querySelectorAll('.preset-amount, .waqf-option').forEach(function(b){ b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
    btn.classList.add('active'); btn.setAttribute('aria-pressed','true');

    var input = container.querySelector('.amount-input');

    // Determine if this button should act as the "other"/custom input
    var isOther = false;
    if (btn.classList.contains('preset-other') || btn.classList.contains('waqf-other')) isOther = true;
    // also treat labels containing Arabic "مخصص" or "اختياري" or english "optional" as other
    try{
      var labelText = '';
      var labelEl = btn.querySelector('.preset-value, .waqf-value, .preset-amount-text');
      if(labelEl) labelText = (labelEl.textContent||'').toString().trim().toLowerCase();
      if(labelText.indexOf('مخصص') !== -1 || labelText.indexOf('اختياري') !== -1 || labelText.indexOf('optional') !== -1) isOther = true;
    }catch(err){ }

    if(isOther){
      if(input){ input.disabled = false; input.value = input.value || ''; try{ input.focus(); }catch(e){} }
    } else {
      // prefer data-amount attribute, fallback to dataset.amount
      var amt = btn.getAttribute('data-amount') || (btn.dataset && btn.dataset.amount ? btn.dataset.amount : '');
      if(input && amt !== ''){ input.value = amt; input.disabled = true; }
    }
  }, false);

  // Initialize inputs based on active preset (useful when server-side set)
  function initCards(){
    document.querySelectorAll('.donation-card').forEach(function(card){
      var input = card.querySelector('.amount-input');
      if(!input) return;
      // check for active preset in either legacy or waqf markup
      var active = card.querySelector('.preset-amount.active') || card.querySelector('.waqf-option.active');
      if(active){
        // detect other/custom
        var isOther = active.classList.contains('preset-other') || active.classList.contains('waqf-other');
        try{
          var labelEl = active.querySelector('.preset-value, .waqf-value, .preset-amount-text');
          var labelText = labelEl ? (labelEl.textContent||'').toString().trim().toLowerCase() : '';
          if(labelText.indexOf('مخصص') !== -1 || labelText.indexOf('اختياري') !== -1 || labelText.indexOf('optional') !== -1) isOther = true;
        }catch(err){}

        if(isOther){
          input.disabled = false; // keep any server-populated value
        } else {
          var amt = active.getAttribute('data-amount') || (active.dataset && active.dataset.amount ? active.dataset.amount : '');
          if(amt !== ''){ input.value = amt; input.disabled = true; }
        }
      } else {
        // no active, enable input
        input.disabled = false;
      }
    });
  }

  // Simple toast helper
  function showDonationToast(message){
    try{
      var existing = document.getElementById('donation-toast');
      if(existing){ existing.parentNode.removeChild(existing); }
      var el = document.createElement('div');
      el.id = 'donation-toast';
      el.className = 'donation-toast';
      el.textContent = message || 'تمت الإضافة إلى السلة';
      document.body.appendChild(el);
      // auto close
      setTimeout(function(){ if(el && el.parentNode) el.parentNode.removeChild(el); }, 3000);
    }catch(err){ console.warn(err); }
  }

  // Attach donate link behavior: append donation_amount if input has value
  document.addEventListener('click', function(e){
    var donateBtn = e.target.closest && e.target.closest('.donate-btn');
    if(!donateBtn) return;
    var btn = donateBtn;
    var card = btn.closest && btn.closest('.donation-card');
    if(card){
      var input = card.querySelector('.amount-input');
      var mode = btn.dataset && btn.dataset.mode ? btn.dataset.mode : (card.dataset && card.dataset.mode ? card.dataset.mode : 'wakf');
      var productId = card.querySelector('.amount-input') && card.querySelector('.amount-input').dataset ? card.querySelector('.amount-input').dataset.productId : null;

      // if donation mode, build checkout URL and redirect (AJAX add then go to checkout)
      if (mode === 'donation'){
        e.preventDefault();
        var checkout = btn.dataset && btn.dataset.checkout ? btn.dataset.checkout : (typeof donation_cards_params !== 'undefined' && donation_cards_params.checkout_url ? donation_cards_params.checkout_url : (window.location.origin + '/checkout/'));
        var addUrl = (typeof donation_cards_params !== 'undefined' && donation_cards_params.wc_ajax_url ? donation_cards_params.wc_ajax_url + 'add_to_cart' : (window.location.origin + '/?wc-ajax=add_to_cart'));
        var form = new URLSearchParams();
        if(productId) form.append('product_id', productId);
        form.append('quantity', '1');
        if(input && input.value && input.value.trim() !== '') form.append('donation_amount', input.value.trim());

        fetch(addUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: form.toString(), credentials: 'same-origin' })
          .then(function(resp){ return resp.text(); })
          .then(function(){
            try{
              var cUrl = new URL(checkout, window.location.origin);
              window.location.href = cUrl.toString();
            }catch(err){
              window.location.href = window.location.origin + '/checkout/';
            }
          }).catch(function(){
            try{
              var cUrl = new URL(checkout, window.location.origin);
              if(productId) cUrl.searchParams.set('add-to-cart', productId);
              if(input && input.value && input.value.trim() !== '') cUrl.searchParams.set('donation_amount', input.value.trim());
              window.location.href = cUrl.toString();
            }catch(err){
              window.location.href = window.location.origin + '/checkout/';
            }
          });
        return;
      }

      // non-donation mode: if wakf (add to cart), perform AJAX add-to-cart and show success toast
      if (mode === 'wakf'){
        e.preventDefault();
        var addUrl = (typeof donation_cards_params !== 'undefined' && donation_cards_params.wc_ajax_url ? donation_cards_params.wc_ajax_url + 'add_to_cart' : (window.location.origin + '/?wc-ajax=add_to_cart'));
        var form = new URLSearchParams();
        if(productId) form.append('product_id', productId);
        form.append('quantity', '1');
        if(input && input.value && input.value.trim() !== '') form.append('donation_amount', input.value.trim());

        fetch(addUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: form.toString(), credentials: 'same-origin' })
          .then(function(resp){ return resp.text(); })
          .then(function(){
            showDonationToast('تمت الإضافة إلى السلة');
            try{ if (window.jQuery) { jQuery(document.body).trigger('wc_fragment_refresh'); } }catch(err){}
          }).catch(function(){
            try{
              var url = new URL(btn.href, window.location.origin);
              url.searchParams.set('add-to-cart', productId);
              if(input && input.value && input.value.trim() !== '') url.searchParams.set('donation_amount', input.value.trim());
              window.location.href = url.toString();
            }catch(err){
              window.location.href = btn.href;
            }
          });
        return;
      }

      // other non-donation modes: append donation_amount to product page link so user can choose quantity etc.
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
  }, false);

  // Add-to-cart behavior: include donation_amount param (handles any remaining cart-btn usage)
  document.addEventListener('click', function(e){
    var cartBtn = e.target.closest && e.target.closest('.cart-btn');
    if(!cartBtn) return;
    e.preventDefault();
    var btn = cartBtn;
    var card = btn.closest && btn.closest('.donation-card');
    if(!card) return;
    var input = card.querySelector('.amount-input');
    var productId = btn.dataset && btn.dataset.productId ? btn.dataset.productId : (input && input.dataset ? input.dataset.productId : null);
    if(!productId) return;
    var mode = btn.dataset && btn.dataset.mode ? btn.dataset.mode : (card.querySelector('.donate-btn') && card.querySelector('.donate-btn').dataset ? card.querySelector('.donate-btn').dataset.mode : 'wakf');

    // If product is donation-mode, redirect to checkout with add-to-cart param
    if(mode === 'donation'){
      var checkout = card.querySelector('.donate-btn') && card.querySelector('.donate-btn').dataset ? card.querySelector('.donate-btn').dataset.checkout : (window.location.origin + '/checkout/');
      var addUrl = (typeof donation_cards_params !== 'undefined' && donation_cards_params.wc_ajax_url ? donation_cards_params.wc_ajax_url + 'add_to_cart' : (window.location.origin + '/?wc-ajax=add_to_cart'));
      var form = new URLSearchParams();
      form.append('product_id', productId);
      form.append('quantity', '1');
      if(input && input.value && input.value.trim() !== '') form.append('donation_amount', input.value.trim());
      fetch(addUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: form.toString(), credentials: 'same-origin' })
        .then(function(){ window.location.href = checkout; })
        .catch(function(){
          var base = window.location.origin + '/checkout/';
          var qs = '?add-to-cart=' + encodeURIComponent(productId);
          if(input && input.value && input.value.trim() !== '') qs += '&donation_amount=' + encodeURIComponent(input.value.trim());
          window.location.href = base + qs;
        });
      return;
    }

    try{
      var url = new URL(window.location.href, window.location.origin);
      url.search = '';
      url.hash = '';
      url.searchParams.set('add-to-cart', productId);
      if(input && input.value && input.value.trim() !== ''){
        url.searchParams.set('donation_amount', input.value.trim());
      }
      // perform AJAX add-to-cart to avoid redirect
      var addUrl2 = (typeof donation_cards_params !== 'undefined' && donation_cards_params.wc_ajax_url ? donation_cards_params.wc_ajax_url + 'add_to_cart' : (window.location.origin + '/?wc-ajax=add_to_cart'));
      var form2 = new URLSearchParams();
      form2.append('product_id', productId);
      form2.append('quantity', '1');
      if(input && input.value && input.value.trim() !== '') form2.append('donation_amount', input.value.trim());
      fetch(addUrl2, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: form2.toString(), credentials: 'same-origin' })
        .then(function(){
          showDonationToast('تمت الإضافة إلى السلة');
          try{ if (window.jQuery) { jQuery(document.body).trigger('wc_fragment_refresh'); } }catch(err){}
        }).catch(function(){ window.location.href = url.toString(); });
    }catch(err){
      var base = window.location.origin + window.location.pathname;
      var qs = '?add-to-cart=' + encodeURIComponent(productId);
      if(input && input.value && input.value.trim() !== ''){
        qs += '&donation_amount=' + encodeURIComponent(input.value.trim());
      }
      // fallback: try AJAX then redirect
      var addUrl2 = (typeof donation_cards_params !== 'undefined' && donation_cards_params.wc_ajax_url ? donation_cards_params.wc_ajax_url + 'add_to_cart' : (window.location.origin + '/?wc-ajax=add_to_cart'));
      var form2 = new URLSearchParams();
      form2.append('product_id', productId);
      form2.append('quantity', '1');
      if(input && input.value && input.value.trim() !== '') form2.append('donation_amount', input.value.trim());
      fetch(addUrl2, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: form2.toString(), credentials: 'same-origin' })
        .then(function(){ showDonationToast('تمت الإضافة إلى السلة'); try{ if (window.jQuery) { jQuery(document.body).trigger('wc_fragment_refresh'); } }catch(err){} })
        .catch(function(){ window.location.href = base + qs; });
    }
  }, false);

  // Run init on DOMContentLoaded and after short delay for dynamic content
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCards); else initCards();
  setTimeout(initCards, 600);

})();
