(function($){
    var productsTable, ordersTable;

    function initProducts() {
        productsTable = $('#donation-analytics-products').DataTable({
            serverSide: true,
            processing: true,
            ajax: function(data, callback){
                var params = {
                    action: 'donation_product_analytics_list',
                    nonce: donation_analytics_params.nonce,
                    draw: data.draw,
                    start: data.start,
                    length: data.length,
                    search: data.search,
                    order: data.order,
                    start_date: $('#da-start').val(),
                    end_date: $('#da-end').val()
                };
                $.post(donation_analytics_params.ajax_url, params, function(resp){
                    if (!resp || !resp.success) {
                        console.error('Analytics AJAX error', resp);
                        $('#da-message').html('<div class="notice notice-error">Error loading analytics data. Check console for details.</div>');
                        return callback({draw: data.draw, recordsTotal:0, recordsFiltered:0, data: []});
                    }
                    // clear any previous message
                    $('#da-message').html('');
                    // show hint when zero rows
                    if (resp.data && parseInt(resp.data.recordsTotal,10) === 0) {
                        $('#da-message').html('<div class="notice notice-warning">No analytics data found for the selected range. Ensure WooCommerce Analytics tables are populated.</div>');
                    }
                    callback(resp.data);
                }, 'json').fail(function(jqxhr, status, err){
                    console.error('AJAX failed', status, err, jqxhr.responseText);
                    $('#da-message').html('<div class="notice notice-error">AJAX request failed. See browser console for details.</div>');
                    callback({draw: data.draw, recordsTotal:0, recordsFiltered:0, data: []});
                });
            },
            columns: [
                {data: 'product'},
                {data: 'revenue'},
                {data: 'qty'},
                {data: 'completed'},
                {data: 'processing'},
                {data: 'failed'},
                {data: 'cancelled'},
                {data: 'refunded'},
                {data: 'actions'}
            ]
        });

        $('#donation-analytics-products').on('click', '.donation-analytics-view-orders', function(){
            var pid = $(this).data('product-id');
            openOrdersModal(pid);
        });
    }

    function openOrdersModal(productId) {
        $('#da-modal-title').text('Orders for product #' + productId);
        $('#donation-analytics-orders-modal').show();
        if ($.fn.DataTable.isDataTable('#donation-analytics-orders-table')) {
            ordersTable.destroy();
            $('#donation-analytics-orders-table').empty();
        }
        ordersTable = $('#donation-analytics-orders-table').DataTable({
            serverSide: true,
            processing: true,
            ajax: function(data, callback){
                var params = {
                    action: 'donation_product_orders_list',
                    nonce: donation_analytics_params.nonce,
                    product_id: productId,
                    draw: data.draw,
                    start: data.start,
                    length: data.length,
                    status: $('#da-order-status').val(),
                    start_date: $('#da-start').val(),
                    end_date: $('#da-end').val()
                };
                $.post(donation_analytics_params.ajax_url, params, function(resp){
                    if (!resp || !resp.success) {
                        console.error('Orders AJAX error', resp);
                        $('#da-message').html('<div class="notice notice-error">Error loading orders. Check console for details.</div>');
                        return callback({draw: data.draw, recordsTotal:0, recordsFiltered:0, data: []});
                    }
                    $('#da-message').html('');
                    callback(resp.data);
                }, 'json').fail(function(jqxhr, status, err){
                    console.error('Orders AJAX failed', status, err, jqxhr.responseText);
                    $('#da-message').html('<div class="notice notice-error">AJAX request failed. See browser console for details.</div>');
                    callback({draw: data.draw, recordsTotal:0, recordsFiltered:0, data: []});
                });
            },
            columns: [
                {data:'order'},
                {data:'customer'},
                {data:'qty'},
                {data:'subtotal'},
                {data:'total'},
                {data:'payment'},
                {data:'date'},
                {data:'action'}
            ]
        });

    }

    $(document).ready(function(){
        // load DataTables library if not present (assume admin has it)
        if (!$.fn.DataTable) {
            console.warn('DataTables not available in admin; please ensure it is loaded');
            return;
        }
        // set default date range to last 30 days if empty
        if (!$('#da-start').val() && !$('#da-end').val()) {
            var end = new Date();
            var start = new Date(); start.setDate(end.getDate()-29);
            $('#da-start').val(start.toISOString().slice(0,10));
            $('#da-end').val(end.toISOString().slice(0,10));
        }
        initProducts();

        // diagnostics
        $('#da-run-diag').on('click', function(){
            $('#da-message').html('<div class="notice">Running diagnostics…</div>');
            $.post(donation_analytics_params.ajax_url, { action: 'donation_product_analytics_diag', nonce: donation_analytics_params.nonce }, function(resp){
                if (!resp || !resp.success) {
                    $('#da-message').html('<div class="notice notice-error">Diagnostics failed. Check console.</div>');
                    console.error('Diag failed', resp);
                    return;
                }
                var d = resp.data;
                var html = '<div class="notice notice-success"><strong>Diagnostics</strong><ul>';
                if (d.tables) {
                    for (var t in d.tables) {
                        html += '<li>' + t + ': ' + d.tables[t] + ' (rows: ' + (d.counts && d.counts[t] ? d.counts[t] : 0) + ')</li>';
                    }
                }
                if (d.earliest) html += '<li>Earliest order date: ' + d.earliest + '</li>';
                if (d.latest) html += '<li>Latest order date: ' + d.latest + '</li>';
                html += '</ul></div>';
                $('#da-message').html(html);
                console.log('Diagnostics result', d);
            }, 'json').fail(function(jqxhr, status, err){
                $('#da-message').html('<div class="notice notice-error">Diagnostics AJAX failed. See console.</div>');
                console.error('Diag AJAX error', status, err, jqxhr.responseText);
            });
        });

        $('#da-refresh').on('click', function(){
            productsTable.ajax.reload();
        });

        $('[data-range]').on('click', function(){
            var v = $(this).data('range');
            var end = new Date();
            var start = new Date();
            if (v === 'today') { start = end; }
            else if (v === '7') { start.setDate(end.getDate()-6); }
            else if (v === '30') { start.setDate(end.getDate()-29); }
            else if (v === 'year') { start = new Date(end.getFullYear(),0,1); }
            $('#da-start').val(start.toISOString().slice(0,10));
            $('#da-end').val(end.toISOString().slice(0,10));
            $('#da-refresh').click();
        });

        $('.donation-analytics-modal-close').on('click', function(){
            $('#donation-analytics-orders-modal').hide();
        });
    });
})(jQuery);
