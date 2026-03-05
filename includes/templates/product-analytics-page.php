<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap donation-analytics-page">
    <h1>Product Orders Analytics</h1>

    <div class="donation-analytics-filters">
        <!-- Hidden date inputs: kept for JS compatibility, not visible to user -->
        <input type="hidden" id="da-start" />
        <input type="hidden" id="da-end" />
        <button class="button" data-range="today">Today</button>
        <button class="button" data-range="7">Last 7 days</button>
        <button class="button" data-range="30">Last 30 days</button>
        <button class="button" data-range="year">This year</button>
        <label style="margin-left:12px;">Status:
            <select id="da-product-status">
                <option value="">All</option>
                <option value="completed">Completed</option>
                <option value="processing">Processing</option>
                <option value="cancelled">Cancelled</option>
                <option value="failed">Failed</option>
                <option value="refunded">Refunded</option>
            </select>
        </label>
        <button id="da-refresh" class="button button-primary">Apply</button>
    </div>

    <div id="da-message" style="margin-bottom:12px;"></div>
    <div class="donation-analytics-summary" id="da-summary-cards">
        <!-- Summary cards inserted by JS -->
    </div>

    <!-- diagnostics button removed from UI -->

    <h2>Products</h2>
    <table id="donation-analytics-products" class="widefat" style="width:100%">
        <thead>
            <tr>
                <th>Product</th>
                <th>Revenue</th>
                <th>Qty Sold</th>
                <th>Completed</th>
                <th>Processing</th>
                <th>Failed</th>
                <th>Cancelled</th>
                <th>Refunded</th>
                <th>Actions</th>
            </tr>
        </thead>
    </table>

    <!-- Orders modal -->
    <div id="donation-analytics-orders-modal" style="display:none;">
        <div class="donation-analytics-modal-inner">
            <button class="donation-analytics-modal-close">Close</button>
            <h2 id="da-modal-title">Orders</h2>
            <div class="donation-analytics-orders-filters">
                <label>Status:
                    <select id="da-order-status">
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="processing">Processing</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </label>
            </div>
            <table id="donation-analytics-orders-table" class="widefat" style="width:100%">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <input type="hidden" id="donation-analytics-nonce" value="<?php echo esc_attr(wp_create_nonce('donation_product_analytics_nonce')); ?>" />
</div>
