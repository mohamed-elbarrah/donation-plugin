(function($){
    'use strict';

    $(function(){
        var $form = $('#donation-zakat-form');
        if ( !$form.length ) return;

        // helper: update UI visibility based on selected mode
        function updateZakatModeVisibility() {
            var mode = $form.find('input[name="donation_zakat_mode"]:checked').val() || 'pay';
            if ( mode === 'pay' ) {
                $('#donation-zakat-pay').show();
                $('#donation-zakat-calc').hide();
            } else {
                $('#donation-zakat-pay').hide();
                $('#donation-zakat-calc').show();
            }
        }

        // mode toggles
        $form.find('input[name="donation_zakat_mode"]').on('change', function(){
            updateZakatModeVisibility();
        });

        // initialize visibility on load
        updateZakatModeVisibility();
        // show nisab/rate for selected type before calculation
        function updateNisabRateDisplay(){
            try {
                var type = $('#donation-zakat-type').val() || 'cash';
                var nisab = '';
                var rate = '';
                if ( window.donationZakat && donationZakat.types ) {
                    var t = donationZakat.types;
                    // options stored as cash_nisab, cash_rate, etc.
                    if ( typeof t[type + '_nisab'] !== 'undefined' ) nisab = t[type + '_nisab'];
                    if ( typeof t[type + '_rate'] !== 'undefined' ) rate = t[type + '_rate'];
                }

                $('#donation-zakat-nisab').text(nisab === '' ? '—' : nisab);
                $('#donation-zakat-rate').text(rate === '' ? '—' : rate);
            } catch (e){}
        }
        // init display
        updateNisabRateDisplay();

        // initialize type buttons (if present) and wire interactions
        function initTypeButtons(){
            var $buttons = $('.dz-type-btn');
            if ( !$buttons.length ) return;
            var current = $('#donation-zakat-type').val() || 'cash';
            $buttons.removeClass('active').attr('aria-pressed','false');
            $buttons.filter('[data-type="' + current + '"]').addClass('active').attr('aria-pressed','true');

            $buttons.on('click', function(){
                var t = $(this).data('type');
                $('#donation-zakat-type').val(t).trigger('change');
                $buttons.removeClass('active').attr('aria-pressed','false');
                $(this).addClass('active').attr('aria-pressed','true');
            });
        }
        initTypeButtons();

        // update when type changes (from select or buttons)
        $('#donation-zakat-type').on('change', function(){ updateNisabRateDisplay(); initTypeButtons(); });

        // Handle direct pay button — ask for confirmation, then redirect or emit event
        $('#donation-zakat-pay-btn').on('click', function(e){
            e.preventDefault();
            var raw = $('#donation-zakat-pay-amount').val() || '0';
            var amount = parseFloat( (''+raw).replace(/,/g,'') ) || 0;

            var confirmMsg = 'Are you sure you want to pay ' + amount + ' now?';
            try {
                if ( donationZakat && donationZakat.i18n && donationZakat.i18n.confirm_pay_direct ) {
                    confirmMsg = donationZakat.i18n.confirm_pay_direct.replace('%s', amount);
                }
            } catch ( err ) {}

            // Create and show modal
            showDonationConfirmModal(confirmMsg, function(){
                // confirmed
                $(document).trigger('donation_app_zakat_pay_confirmed', { amount: amount, type: 'direct' });
                // Add zakat to cart via AJAX, then redirect to checkout
                $.post(donationZakat.ajax_url, {
                    action: 'donation_app_add_zakat_to_cart',
                    nonce: donationZakat.nonce,
                    amount: amount
                }).done(function(res){
                    if ( res && res.success && res.data && res.data.checkout_url ) {
                        window.location.href = res.data.checkout_url;
                        return;
                    }
                    // fallback: emit pay event
                    $(document).trigger('donation_app_zakat_pay', { amount: amount, type: 'direct' });
                }).fail(function(){
                    $(document).trigger('donation_app_zakat_pay', { amount: amount, type: 'direct' });
                });
            }, function(){
                // cancelled
                $(document).trigger('donation_app_zakat_pay_cancelled', { amount: amount, type: 'direct' });
            });
        });

        // Calculate button (AJAX) — UI only, calculation on server
        $form.on('submit', function(e){
            e.preventDefault();
            var type = $('#donation-zakat-type').val() || 'cash';
            var raw = $('#donation-zakat-amount').val() || '0';
            var amount = (''+raw).replace(/,/g,'');

            $('#donation-zakat-result').text('...');
            // clear previous calculated value while processing
            $('#donation-zakat-calculated').val('');

            $.post(donationZakat.ajax_url, {
                action: 'donation_app_calculate_zakat',
                nonce: donationZakat.nonce,
                type: type,
                amount: amount
            }).done(function(res){
                if ( res && res.success && res.data ) {
                    var d = res.data;
                    if ( d.due ) {
                        // show formatted zakat and populate readonly input
                        $('#donation-zakat-result').html('<strong>' + d.zakat + '</strong>');
                        try { $('#donation-zakat-calculated').val(d.zakat); } catch(e){}
                        $('#donation-zakat-pay-calc-btn').show().data('amount', d.zakat_raw).data('type', type);
                    } else {
                        var nisab = (typeof d.nisab !== 'undefined') ? d.nisab : '';
                        var rate = (typeof d.rate !== 'undefined') ? d.rate : '';
                        // Arabic fallback message when not reached
                        var notReached = (donationZakat && donationZakat.i18n && donationZakat.i18n.not_reached) ? donationZakat.i18n.not_reached : 'المبلغ لم يبلغ النصاب بعد';

                        // populate result area and readonly input with message
                        var parts = [];
                        parts.push('<span class="dz-not-reached">' + notReached + '</span>');
                        parts.push('<span class="dz-nisab">' + (donationZakat && donationZakat.i18n && donationZakat.i18n.label_nisab ? donationZakat.i18n.label_nisab : 'النصاب') + ': ' + nisab + '</span>');
                        parts.push('<span class="dz-rate">' + (donationZakat && donationZakat.i18n && donationZakat.i18n.label_rate ? donationZakat.i18n.label_rate : 'النسبة') + ': ' + rate + '</span>');

                        $('#donation-zakat-result').html(parts.join(' — '));
                        try { $('#donation-zakat-calculated').val(notReached); } catch(e){}
                        $('#donation-zakat-pay-calc-btn').hide();
                    }
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Error';
                    $('#donation-zakat-result').text(msg);
                    $('#donation-zakat-pay-calc-btn').hide();
                }
            }).fail(function(){
                $('#donation-zakat-result').text('Request failed');
                $('#donation-zakat-calculated').val('');
                $('#donation-zakat-pay-calc-btn').hide();
            });
        });

        // Modal helper (created outside DOM ready block scope)
        function showDonationConfirmModal( message, onConfirm, onCancel ) {
            // remove existing
            $('.donation-zakat-modal-overlay').remove();
            var title = (donationZakat && donationZakat.i18n && donationZakat.i18n.modal_title) ? donationZakat.i18n.modal_title : 'Confirm Payment';
            var confirmText = (donationZakat && donationZakat.i18n && donationZakat.i18n.modal_confirm) ? donationZakat.i18n.modal_confirm : 'Confirm';
            var cancelText = (donationZakat && donationZakat.i18n && donationZakat.i18n.modal_cancel) ? donationZakat.i18n.modal_cancel : 'Cancel';

            var $overlay = $('<div class="donation-zakat-modal-overlay" role="dialog" aria-modal="true">');
            var $modal = $('<div class="donation-zakat-modal">');
            $modal.append('<h2>' + title + '</h2>');
            $modal.append('<p>' + message + '</p>');
            var $btns = $('<div class="dz-buttons">');
            var $cancel = $('<button type="button" class="dz-btn dz-btn-cancel">' + cancelText + '</button>');
            var $confirm = $('<button type="button" class="dz-btn dz-btn-confirm">' + confirmText + '</button>');
            $btns.append($cancel).append($confirm);
            $modal.append($btns);
            $overlay.append($modal);
            $('body').append($overlay);

            // focus
            $confirm.focus();

            $cancel.on('click', function(){
                $overlay.remove();
                if ( typeof onCancel === 'function' ) onCancel();
            });
            $confirm.on('click', function(){
                $overlay.remove();
                if ( typeof onConfirm === 'function' ) onConfirm();
            });

            // close on ESC
            $(document).on('keydown.donationZakatModal', function(e){
                if ( e.key === 'Escape' || e.keyCode === 27 ) {
                    $overlay.remove();
                    $(document).off('keydown.donationZakatModal');
                    if ( typeof onCancel === 'function' ) onCancel();
                }
            });

            return $overlay;
        }
        // Pay the calculated amount — confirm then add to cart and redirect to checkout (same flow as direct pay)
        $('#donation-zakat-pay-calc-btn').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var amt = $btn.data('amount') || 0;
            var type = $btn.data('type') || '';

            var confirmMsg = 'Are you sure you want to pay ' + amt + ' now?';
            try {
                if ( donationZakat && donationZakat.i18n && donationZakat.i18n.confirm_pay_direct ) {
                    confirmMsg = donationZakat.i18n.confirm_pay_direct.replace('%s', amt);
                }
            } catch ( err ) {}

            showDonationConfirmModal(confirmMsg, function(){
                // confirmed — add to cart via AJAX then redirect
                $.post(donationZakat.ajax_url, {
                    action: 'donation_app_add_zakat_to_cart',
                    nonce: donationZakat.nonce,
                    amount: amt
                }).done(function(res){
                    if ( res && res.success && res.data && res.data.checkout_url ) {
                        window.location.href = res.data.checkout_url;
                        return;
                    }
                    // fallback: emit pay event
                    $(document).trigger('donation_app_zakat_pay', { amount: amt, type: type });
                }).fail(function(){
                    $(document).trigger('donation_app_zakat_pay', { amount: amt, type: type });
                });
            }, function(){
                // cancelled
                $(document).trigger('donation_app_zakat_pay_cancelled', { amount: amt, type: type });
            });
        });

        function donationZakatMessagesNotDue(){
            try {
                if ( donationZakat && donationZakat.i18n && donationZakat.i18n.not_due ) {
                    return donationZakat.i18n.not_due;
                }
            } catch ( err ) {}
            return 'Not due according to nisab.';
        }

    });
})(jQuery);
