 (function($){
  var $body = $(document.body);
  function buildQuickUI(){
      if ($('#donation-quick-root').length) return;
      var tpl = '\n<div id="donation-quick-root" class="donation-quick-btn">\n  <div class="donation-quick-backdrop hidden" aria-hidden="true"></div>\n  <div class="donation-quick-panel hidden" role="dialog" aria-hidden="true" aria-label="تبرع سريع">\n    <div class="panel-inner">\n      <button class="quick-close" aria-label="إغلاق">✕</button>\n      <div class="card-title" style="text-align:center;margin-bottom:8px">تبرع سريع</div>\n      <div class="preset-amounts types" aria-label="نوع التبرع">\n        <button class="preset-amount" data-type="general"><span class="preset-value">وجبات الافطار</span><span class="preset-side preset-right"><span class="preset-check">✓</span></span></button>\n        <button class="preset-amount" data-type="zakat"><span class="preset-value">سقيا ماء</span><span class="preset-side preset-right"><span class="preset-check">✓</span></span></button>\n        <button class="preset-amount" data-type="masajid"><span class="preset-value">المصاحف</span><span class="preset-side preset-right"><span class="preset-check">✓</span></span></button>\n        <button class="preset-amount" data-type="neediest"><span class="preset-value">تمور المطاف</span><span class="preset-side preset-right"><span class="preset-check">✓</span></span></button>\n      </div>\n      <div class="preset-amounts presets" aria-label="مبالغ سريعة">\n        <button class="preset-amount" data-amount="10"><span class="preset-side preset-right"><span class="preset-check">✓</span></span><span class="preset-value">10</span><span class="preset-side preset-left"><span class="preset-currency">د.إ</span></span></button>\n        <button class="preset-amount" data-amount="50"><span class="preset-side preset-right"><span class="preset-check">✓</span></span><span class="preset-value">50</span><span class="preset-side preset-left"><span class="preset-currency">د.إ</span></span></button>\n        <button class="preset-amount" data-amount="100"><span class="preset-side preset-right"><span class="preset-check">✓</span></span><span class="preset-value">100</span><span class="preset-side preset-left"><span class="preset-currency">د.إ</span></span></button>\n        <button class="preset-amount preset-other" data-amount=""><span class="preset-value">مبلغ مخصص</span><span class="preset-side preset-right"><span class="preset-check">✓</span></span></button>\n      </div>\n      <div class="card-actions" style="margin-top:8px">\n        <div class="currency-input">\n          <span class="currency-symbol">د.إ</span>\n          <input class="amount-input" type="number" placeholder="مبلغ التبرع" aria-label="مبلغ التبرع" />\n        </div>\n        <button class="donate-btn">تبرع الآن</button>\n      </div>\n    </div>\n  </div>\n  <div class="quick-toggle" aria-controls="donation-quick-root" aria-expanded="false">\n    <span class="icon">\n      <svg class="quick-toggle-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">\n        <path d="M12 5v14"></path>\n        <path d="M5 12h14"></path>\n      </svg>\n    </span>\n    <span class="label">تبرع سريع</span>\n  </div>\n</div>\n';
    $body.append(tpl);
  }

  function attachHandlers(){
    var $root = $('#donation-quick-root');
    if (!$root.length) return;
    // adapt server-provided mode labels (وقف vs تبرع) when available
    if (window.donation_quick_product && window.donation_quick_product.mode) {
      var mode = window.donation_quick_product.mode;
      var panelLabel = mode === 'donation' ? 'تبرع سريع' : 'وقف سريع';
      var amountLabel = mode === 'donation' ? 'مبلغ التبرع' : 'اختر مبلغ المساهمة';
      var donateBtnLabel = mode === 'donation' ? 'تبرع الآن' : 'أضف للسلة';
      $root.find('.donation-quick-panel').attr('aria-label', panelLabel);
      $root.find('.card-title').text(panelLabel);
      $root.find('.amount-input').attr('placeholder', amountLabel).attr('aria-label', amountLabel);
      $root.find('.donate-btn').text(donateBtnLabel);
      $root.find('.quick-toggle .label').text(panelLabel);
    }
    var $panel = $root.find('.donation-quick-panel');
    var $toggle = $root.find('.quick-toggle');

    // Ensure the help paragraph exists when the client template is used
    if (!$root.find('.panel-help').length) {
      $root.find('.panel-inner').find('.panel-header').after('<p class="panel-help">اختر نوع التبرع الذي تريد اجراءه</p>');
    }

    // Normalize server- or template-rendered type buttons to use a dedicated class
    // so amount-specific styles don't affect them. Types are identified by the
    // presence of `data-type` (vs presets which use `data-amount`).
    $root.find('.types [data-type]').each(function(){
      var $el = $(this);
      if ($el.hasClass('preset-amount')){
        $el.removeClass('preset-amount').addClass('preset-type');
      } else if (!$el.hasClass('preset-type')){
        $el.addClass('preset-type');
      }
    });

    // If no type is active, default-select the 'general' type for clarity
    var $types = $root.find('.types .preset-type, .types .preset-amount');
    if ($types.length && !$types.filter('.active').length){
      var $general = $types.filter('[data-type="general"]');
      if ($general.length) $general.addClass('active');
      else $types.first().addClass('active');
    }

    // If server provided numeric presets
    if (window.donation_quick_product && Array.isArray(window.donation_quick_product.presets) && window.donation_quick_product.presets.length){
      var $pres = $root.find('.presets').empty();
        window.donation_quick_product.presets.forEach(function(p){
          var html = '<span class="preset-side preset-right"><span class="preset-check">✓</span></span>' +
                     '<span class="preset-value">'+p+'</span>' +
                     '<span class="preset-side preset-left"><span class="preset-currency">'+(window.donation_quick_product.currency || 'د.إ')+'</span></span>';
          var btn = $('<button>').addClass('preset-amount').attr('data-amount', p).html(html);
          $pres.append(btn);
        });
      // add a custom amount button after server-provided presets
      $pres.append($('<button>').addClass('preset-amount preset-other').attr('data-amount','').html('<span class="preset-value">مبلغ مخصص</span><span class="preset-side preset-right"><span class="preset-check">✓</span></span>'));
      if (window.donation_quick_product.currency){
        $root.find('.currency-symbol').text(window.donation_quick_product.currency);
          $root.find('.preset-currency').text(window.donation_quick_product.currency);
      }
      // Default-select preset amount 50 if nothing is active
      var $presetBtns = $root.find('.presets .preset-amount');
      if ($presetBtns.length && !$presetBtns.filter('.active').length){
        var $fifty = $presetBtns.filter('[data-amount="50"]');
        if ($fifty.length){
          $fifty.addClass('active');
          $root.find('.amount-input').val($fifty.data('amount'));
        } else {
          $presetBtns.first().addClass('active');
          var a = $presetBtns.first().data('amount');
          if (a) $root.find('.amount-input').val(a);
        }
      }
    }

    // General fallback: if presets exist in markup but none active, pick 50 or first
    var $allPresetBtns = $root.find('.presets .preset-amount');
    if ($allPresetBtns.length && !$allPresetBtns.filter('.active').length){
      var $fiftyFallback = $allPresetBtns.filter('[data-amount="50"]');
      if ($fiftyFallback.length){
        $fiftyFallback.addClass('active');
        $root.find('.amount-input').val($fiftyFallback.data('amount'));
      } else {
        $allPresetBtns.first().addClass('active');
        var a2 = $allPresetBtns.first().data('amount');
        if (a2) $root.find('.amount-input').val(a2);
      }
    }

    $toggle.on('click', function(){
      var isOpen = $root.hasClass('modal-open');
      if (isOpen){
        closeModal();
      } else {
        openModal();
      }
    });

    // open/close helpers
    function openModal(){
      $root.addClass('modal-open');
      $panel.removeClass('hidden').attr('aria-hidden','false');
      $root.find('.donation-quick-backdrop').removeClass('hidden').attr('aria-hidden','false');
      $toggle.attr('aria-expanded','true');
      // prevent body scroll while modal open
      $(document.body).addClass('donation-quick-modal-open');
    }

    function closeModal(){
      $root.removeClass('modal-open');
      $panel.addClass('hidden').attr('aria-hidden','true');
      $root.find('.donation-quick-backdrop').addClass('hidden').attr('aria-hidden','true');
      $toggle.attr('aria-expanded','false');
      $(document.body).removeClass('donation-quick-modal-open');
    }

    // clicking backdrop or close button closes modal
    $root.on('click', '.donation-quick-backdrop, .quick-close', function(){
      closeModal();
    });

    // Preset amount click: set input value, focus for custom, and toggle active class
    var $input = $root.find('.amount-input');
    $root.on('click', '.presets .preset-amount', function(){
      var $btn = $(this);
      var val = $btn.data('amount');
      $root.find('.presets .preset-amount').removeClass('active');
      $btn.addClass('active');
      if ($btn.hasClass('preset-other')){
        $input.val('');
        $input.focus();
      } else {
        $input.val(val);
      }
    });

    // Sync input with presets: if value matches a preset, activate it; otherwise activate custom
    $root.on('input', '.amount-input', function(){
      var val = $(this).val();
      var matched = false;
      $root.find('.presets .preset-amount').each(function(){
        var a = $(this).data('amount');
        if (a !== undefined && a !== null && String(a) === String(val)){
          $root.find('.presets .preset-amount').removeClass('active');
          $(this).addClass('active');
          matched = true;
          return false;
        }
      });
      if (!matched){
        $root.find('.presets .preset-amount').removeClass('active');
        $root.find('.presets .preset-amount.preset-other').addClass('active');
      }
    });

    // Donation type click: toggle active state among types
    $root.on('click', '.types .preset-type, .types .preset-amount', function(){
      $root.find('.types .preset-type, .types .preset-amount').removeClass('active');
      $(this).addClass('active');
    });

    // donate action — currently navigates to checkout with query params; plugin can later map to product
    $root.on('click', '.donate-btn', function(e){
      var amount = parseFloat($root.find('.amount-input').val());
      if (!amount || amount <= 0){
        $root.find('.amount-input').focus();
        return;
      }
      // redirect to checkout with query param donation_amount for add-to-cart handling
      var checkout = (window.donation_quick_params && donation_quick_params.checkout_url) ? donation_quick_params.checkout_url : '/checkout/';
      var sep = checkout.indexOf('?') === -1 ? '?' : '&';
      // If the server exposed a quick product id, append it so add-to-cart handlers can map the amount
      var url = checkout + sep + 'donation_amount=' + encodeURIComponent(amount);
      if (window.donation_quick_product && window.donation_quick_product.id){
        url += '&product_id=' + encodeURIComponent(window.donation_quick_product.id);
      }
      window.location.href = url;
    });

    // close on ESC
    $(document).on('keydown', function(e){
      if (e.key === 'Escape'){
        $panel.addClass('hidden').attr('aria-hidden','true');
        $toggle.attr('aria-expanded','false');
      }
    });
  }

  $(function(){
    buildQuickUI();
    attachHandlers();
  });
})(jQuery);
