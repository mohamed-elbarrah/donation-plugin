(function($){
    var currentType = '';
    window.openZakatType = function(type, label){
        currentType = type;
        $('#input-fields').show();
        $('#current-title').text('حساب زكاة ' + (label || type));
        $('#zakat-result').hide();
        $('.zakat-tab-btn').removeClass('active');
        $('.zakat-tab-btn[data-type="'+type+'"]').addClass('active');
    };

    $(document).on('click', '#calc-trigger', function(){
        var val = $('#zakat-value').val();
        if(!val) return alert('يرجى إدخال القيمة');
        $.post(zakat_params.ajax_url, {
            action: 'calculate_zakat',
            amount: val,
            type: currentType
        }, function(response){
            if(!response || !response.data) return;
            var res = response.data;
            var $div = $('#zakat-result');
            $div.show().removeClass('res-success res-fail');
            $div.addClass(res.status === 'success' ? 'res-success' : 'res-fail');
            $div.html(res.message);
        });
    });
})(jQuery);
