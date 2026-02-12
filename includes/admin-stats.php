<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin UI for Donation App store statistics
 * - Adds menu item "إحصائيات المنصة"
 * - Renders a simple page with period filters and charts
 */

function donation_app_admin_menu() {
    $capability = 'manage_woocommerce';
    if (!current_user_can($capability)) $capability = 'manage_options';

    add_menu_page(
        'إحصائيات المنصة',
        'إحصائيات المنصة',
        $capability,
        'donation-app-stats',
        'donation_app_stats_page_render',
        'dashicons-chart-area',
        56
    );
}
add_action('admin_menu', 'donation_app_admin_menu');

function donation_app_stats_page_render() {
    if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }

    // Basic page: selectors and containers for charts and tables
    ?>
    <div class="wrap">
        <h1>إحصائيات المنصة</h1>
        <p>عرض إحصائيات المنصة: الزيارات، الزوار الفريدين،زيارات صفحة الدفع، الطلبات المكتملة، وطلبات مهجورة.</p>

        <label for="donation-stats-period">الفترة:</label>
        <select id="donation-stats-period">
            <option value="daily">يومي</option>
            <option value="weekly">اسبوعي</option>
            <option value="monthly" selected>شهري</option>
            <option value="yearly">سنوي</option>
        </select>

        <button id="donation-stats-refresh" class="button">تحديث</button>

        <div id="donation-stats-summary" style="margin-top:18px;display:flex;gap:12px;flex-wrap:wrap;"></div>

        <h2>المخطط</h2>
        <div style="width:800px; max-width:100%; height:300px;">
          <canvas id="donation-stats-chart" style="width:100%; height:100%;"></canvas>
        </div>

        <h2>التفاصيل</h2>
        <table class="widefat fixed" id="donation-stats-table">
            <thead><tr><th>مفتاح</th><th>قيمة</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <?php
}

function donation_app_stats_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_donation-app-stats') return;

    // Chart.js from CDN (admin only)
    wp_enqueue_script('donation-app-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

    // Local admin JS
    $js = "
    (function(){
      const ajax = function(period){
        const data = { action: 'donation_app_get_stats', period: period };
        return fetch(ajaxurl + '?action=donation_app_get_stats&period=' + period, { credentials: 'same-origin' })
          .then(r=>r.json());
      };

      let statsChart = null;

      const render = function(d){
        const container = document.getElementById('donation-stats-summary');
        container.innerHTML = '';
        const items = [
          ['الزيارات', d.total_visits || 0],
          ['الزوار الفريدين', d.unique_visits || 0],
          ['زيارات صفحة الدفع', d.checkout_visits || 0],
          ['زوار الدفع الفريدين', d.checkout_unique_visits || 0],
          ['الطلبات المكتملة', d.orders || 0],
          ['الطلبات المهجورة', d.abandoned_checkouts || 0]
        ];
        items.forEach(i=>{
          const el = document.createElement('div');
          el.style.padding='12px'; el.style.background='#fff'; el.style.border='1px solid #e5e5e5'; el.style.borderRadius='8px'; el.style.minWidth='160px';
          el.innerHTML = '<strong>'+i[0] + '</strong><div style=\'font-size:20px\'>'+i[1]+'</div>';
          container.appendChild(el);
        });

        // Chart: render metrics as a simple bar chart
        try {
          const ctx = document.getElementById('donation-stats-chart').getContext('2d');
          const chartLabels = items.map(i=>i[0]);
          const chartData = items.map(i=>Number(i[1] || 0));

          if (statsChart) {
            statsChart.data.labels = chartLabels;
            statsChart.data.datasets[0].data = chartData;
            statsChart.update();
          } else {
            statsChart = new Chart(ctx, {
              type: 'bar',
              data: {
                labels: chartLabels,
                datasets: [{
                  label: 'احصائيات المنصة',
                  data: chartData,
                  backgroundColor: 'rgba(54, 162, 235, 0.6)'
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  y: { beginAtZero: true }
                }
              }
            });
          }
        } catch (e) {
          // fail silently if Chart.js isn't available
          console.warn('Chart render failed', e);
        }

        // table
        const tbody = document.querySelector('#donation-stats-table tbody');
        tbody.innerHTML = '';
        Object.keys(d).forEach(k=>{
          const tr = document.createElement('tr');
          const td1 = document.createElement('td'); td1.textContent = k;
          const td2 = document.createElement('td'); td2.textContent = d[k];
          tr.appendChild(td1); tr.appendChild(td2); tbody.appendChild(tr);
        });
      };

      document.getElementById('donation-stats-refresh').addEventListener('click', function(){
        const period = document.getElementById('donation-stats-period').value;
        ajax(period).then(resp=>{ if (resp.success) render(resp.data); else alert('خطأ في تحميل البيانات'); });
      });

      // auto refresh on load
      document.addEventListener('DOMContentLoaded', function(){
        document.getElementById('donation-stats-refresh').click();
      });
    })();
    ";

    wp_add_inline_script('donation-app-chartjs', $js);
}
add_action('admin_enqueue_scripts', 'donation_app_stats_enqueue_admin_assets');
