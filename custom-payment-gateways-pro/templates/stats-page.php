<?php
// File: templates/stats-page.php (Dashboard V2.4 - Timezone Fix)
defined('ABSPATH') || exit;

// Filter Data
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
$gateway_filter = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';

// 1. Data Query (Query Database Logic remains safe to fetch data)
$args = [
    'limit' => -1,
    'type' => 'shop_order',
    'status' => ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending'], 
    'date_created' => $start_date . '...' . $end_date, // Note: This queries based on rough UTC range logic in WC
];

if ($gateway_filter) {
    $args['payment_method'] = $gateway_filter;
} else {
    $custom_ids = [];
    foreach($gateways as $g) { $custom_ids[] = $g->gateway_id; }
    if(!empty($custom_ids)) {
         $args['payment_method'] = $custom_ids;
    }
}

$orders = wc_get_orders($args);

// 2. Metrics Calculation with Timezone Correction
$total_sales = 0;
$total_orders = 0; // Will count based on Loop
$sales_by_date = [];
$status_counts = ['wc-completed'=>0, 'wc-processing'=>0, 'wc-on-hold'=>0, 'wc-pending'=>0];

// Site Timezone (to ensure accurate daily bucketing)
$site_timezone = new DateTimeZone(wp_timezone_string());

foreach($orders as $order) {
    $total_orders++;
    $total_sales += $order->get_total();
    
    // --- [IMPORTANT] TIMEZONE FIX FOR CHARTS ---
    $date_obj = $order->get_date_created();
    if($date_obj){
        $date_obj->setTimezone($site_timezone); // Shift time to Local Site Time
        $date_key = $date_obj->format('Y-m-d'); // Group by Local Date
    } else {
        $date_key = 'Unknown';
    }

    if (!isset($sales_by_date[$date_key])) $sales_by_date[$date_key] = 0;
    $sales_by_date[$date_key] += $order->get_total();
    
    // Status
    $status = 'wc-' . $order->get_status();
    if(isset($status_counts[$status])) $status_counts[$status]++;
    else $status_counts['other'] = ($status_counts['other'] ?? 0) + 1;
}

// Prepare JS Data
ksort($sales_by_date); // Sort by date ascending
$chart_labels = json_encode(array_keys($sales_by_date));
$chart_data = json_encode(array_values($sales_by_date));

$avg_order = ($total_orders > 0) ? $total_sales / $total_orders : 0;
$export_url = admin_url('admin-ajax.php?action=cpg_export_csv&gateway='.$gateway_filter.'&start_date='.$start_date.'&end_date='.$end_date);
?>

<div class="wrap wc-custom-payment-wrap">
    <h1><?php _e('Gateway Analytics Dashboard', 'wc-custom-payment'); ?></h1>

    <div class="cpg-dashboard-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="cpg-gateway-stats" />
            <div class="filter-group">
                <select name="gateway" class="cpg-select">
                    <option value=""><?php _e('All Custom Gateways', 'wc-custom-payment'); ?></option>
                    <?php foreach ($gateways as $g): ?>
                        <option value="<?php echo esc_attr($g->gateway_id); ?>" <?php selected($gateway_filter, $g->gateway_id); ?>>
                            <?php echo esc_html($g->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="cpg-date">
                <span class="sep">-</span>
                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="cpg-date">
                <button type="submit" class="button button-primary"><?php _e('Update', 'wc-custom-payment'); ?></button>
            </div>
        </form>
        <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary download-csv">
            <span class="dashicons dashicons-download" style="line-height:28px"></span> Export CSV
        </a>
    </div>

    <div class="cpg-stats-grid">
        <div class="cpg-card">
            <div class="icon-wrap blue"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="meta">
                <span class="label">Total Revenue</span>
                <span class="value"><?php echo wc_price($total_sales); ?></span>
            </div>
        </div>
        <div class="cpg-card">
            <div class="icon-wrap green"><span class="dashicons dashicons-cart"></span></div>
            <div class="meta">
                <span class="label">Orders</span>
                <span class="value"><?php echo esc_html($total_orders); ?></span>
            </div>
        </div>
        <div class="cpg-card">
            <div class="icon-wrap purple"><span class="dashicons dashicons-chart-bar"></span></div>
            <div class="meta">
                <span class="label">Avg Order</span>
                <span class="value"><?php echo wc_price($avg_order); ?></span>
            </div>
        </div>
    </div>

    <div class="cpg-charts-row">
        <div class="cpg-chart-container main-chart">
            <h3>Revenue Trend (Daily)</h3>
            <div class="chart-wrapper"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="cpg-chart-container pie-chart">
            <h3>Status Overview</h3>
            <div class="chart-wrapper"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <div class="gateways-list-container">
        <h3>Recent Transactions (<?php echo esc_html($total_orders); ?>)</h3>
        <table class="wp-list-table widefat fixed striped stats-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Date (Local)</th>
                    <th>Customer</th>
                    <th>Gateway</th>
                    <th>Status</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_orders > 0): ?>
                    <?php 
                    $limit_show = 25; 
                    foreach(array_reverse($orders) as $order): 
                    if($limit_show-- <= 0) break;
                    
                    // Display Local Date
                    $d = $order->get_date_created();
                    if($d) $d->setTimezone($site_timezone);
                    $local_display = $d ? $d->format('Y-m-d H:i') : '-';
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo $order->get_order_number(); ?></a></td>
                        <td><?php echo $local_display; ?></td>
                        <td><?php echo $order->get_formatted_billing_full_name() ?: 'Guest'; ?></td>
                        <td><?php echo esc_html($order->get_payment_method_title()); ?></td>
                        <td><span class="status-badge <?php echo $order->get_status(); ?>"><?php echo wc_get_order_status_name($order->get_status()); ?></span></td>
                        <td style="text-align:right;"><strong><?php echo $order->get_formatted_order_total(); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding: 20px;">No transactions found in this date range.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx1 = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: <?php echo $chart_labels; ?>,
            datasets: [{
                label: 'Sales',
                data: <?php echo $chart_data; ?>,
                borderColor: '#007cba',
                backgroundColor: 'rgba(0, 124, 186, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: {display:false} },
            scales: { y: { beginAtZero: true } }
        }
    });

    const ctx2 = document.getElementById('statusChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Processing', 'On Hold'],
            datasets: [{
                data: [
                    <?php echo $status_counts['wc-completed']; ?>, 
                    <?php echo $status_counts['wc-processing']; ?>, 
                    <?php echo $status_counts['wc-on-hold']; ?>
                ],
                backgroundColor: ['#4ab694', '#f0b849', '#96a4ab'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });
});
</script>