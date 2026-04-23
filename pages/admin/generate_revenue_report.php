<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../../config/db.php';

// Get date filters from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['type']) ? $_GET['type'] : 'full';

$filename = 'revenue_report_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Build date condition (FIXED: using prepared statements to prevent SQL injection)
$summary = [];
$transactions = [];

if ($report_type == 'full' || $report_type == 'summary') {
    // Revenue summary with date filter - using prepared statements
    $sql = "
        SELECT 
            (COALESCE((SELECT SUM(total_amount) FROM bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0) +
             COALESCE((SELECT SUM(total_amount) FROM camping_bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0)
            ) as total_revenue,
            (COALESCE((SELECT COUNT(*) FROM bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0) +
             COALESCE((SELECT COUNT(*) FROM camping_bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0)
            ) as total_transactions,
            (COALESCE((SELECT SUM(total_amount * 0.386) FROM bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0) +
             COALESCE((SELECT SUM(total_amount * 0.386) FROM camping_bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0)
            ) as env_fees,
            (COALESCE((SELECT SUM(total_amount * 0.614) FROM bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0) +
             COALESCE((SELECT SUM(total_amount * 0.614) FROM camping_bookings WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?), 0)
            ) as reg_fees
    ";
    $stmt = $pdo->prepare($sql);
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    $stmt->execute([$start_datetime, $end_datetime, $start_datetime, $end_datetime, 
                    $start_datetime, $end_datetime, $start_datetime, $end_datetime,
                    $start_datetime, $end_datetime, $start_datetime, $end_datetime,
                    $start_datetime, $end_datetime, $start_datetime, $end_datetime]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($report_type == 'full' || $report_type == 'transactions') {
    // Transactions with date filter - FIXED: removed balance_amount
    $sql = "
        SELECT 
            'Day Hike' as booking_type,
            b.booking_number as transaction_code,
            u.name as hiker_name,
            u.email as hiker_email,
            m.name as mountain_name,
            b.total_amount,
            b.downpayment_amount,
            (b.total_amount - b.downpayment_amount) as balance_amount,
            b.payment_status,
            b.status,
            b.created_at,
            NULL as paid_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN mountains m ON b.mountain_id = m.id
        WHERE b.payment_status IN ('paid', 'pending', 'expired')
        AND b.created_at BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 
            'Camping' as booking_type,
            cb.booking_number as transaction_code,
            u.name as hiker_name,
            u.email as hiker_email,
            m.name as mountain_name,
            cb.total_amount,
            cb.downpayment_amount,
            (cb.total_amount - cb.downpayment_amount) as balance_amount,
            cb.payment_status,
            cb.status,
            cb.created_at,
            NULL as paid_at
        FROM camping_bookings cb
        JOIN users u ON cb.user_id = u.id
        JOIN mountains m ON cb.mountain_id = m.id
        WHERE cb.payment_status IN ('paid', 'pending', 'expired')
        AND cb.created_at BETWEEN ? AND ?
        
        ORDER BY created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    $stmt->execute([$start_datetime, $end_datetime, $start_datetime, $end_datetime]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Revenue Report</title>
<style>
    body { font-family: 'Calibri', 'Arial', sans-serif; margin: 20px; }
    h1 { color: #2c5f2d; border-bottom: 3px solid #2c5f2d; padding-bottom: 8px; }
    h2 { background-color: #e8f0e8; padding: 8px; color: #2c5f2d; border-left: 4px solid #2c5f2d; margin-top: 25px; }
    .report-header { margin-bottom: 20px; background: #f5f5f5; padding: 10px; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th { background-color: #2c5f2d; color: white; padding: 10px; text-align: left; font-weight: bold; border: 1px solid #ddd; }
    td { padding: 8px; border: 1px solid #ddd; }
    tr:hover { background-color: #f5f5f5; }
    .status-paid { color: #4a6741; font-weight: bold; }
    .status-pending { color: #b88a15; font-weight: bold; }
    .status-expired, .status-failed { color: #b94040; font-weight: bold; }
    .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; text-align: center; font-size: 10px; color: #999; }
    .total-row { background-color: #f0f0f0; font-weight: bold; }
</style>
</head>
<body>

<h1>🏔️ LAKBAY Revenue Report</h1>

<div class="report-header">
    <strong>Report Period:</strong> <?= date('F j, Y', strtotime($start_date)) ?> - <?= date('F j, Y', strtotime($end_date)) ?><br>
    <strong>Generated:</strong> <?= date('F j, Y g:i A') ?><br>
    <strong>Generated by:</strong> <?= htmlspecialchars($_SESSION['user_name']) ?>
</div>

<?php if ($summary && isset($summary['total_revenue'])): ?>
<h2>📊 Financial Summary</h2>
<table>
    <tr>
        <th>Metric</th>
        <th>Value</th>
    </tr>
    <tr>
        <td><strong>Total Revenue</strong></td>
        <td><strong>₱<?= number_format($summary['total_revenue'], 2) ?></strong></td>
    </tr>
    <tr>
        <td>Total Transactions</td>
        <td><?= number_format($summary['total_transactions']) ?></td>
    </tr>
    <tr>
        <td>Average Transaction Value</td>
        <td>₱<?= number_format($summary['total_transactions'] > 0 ? $summary['total_revenue'] / $summary['total_transactions'] : 0, 2) ?></td>
    </tr>
    <tr>
        <td>Environmental Fees (38.6%)</td>
        <td>₱<?= number_format($summary['env_fees'], 2) ?></td>
    </tr>
    <tr>
        <td>Registration Fees (61.4%)</td>
        <td>₱<?= number_format($summary['reg_fees'], 2) ?></td>
    </tr>
</table>
<?php endif; ?>

<?php if ($transactions): ?>
<h2>📋 Detailed Transaction List</h2>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Transaction Code</th>
            <th>Type</th>
            <th>Hiker Name</th>
            <th>Email</th>
            <th>Mountain</th>
            <th>Total Amount</th>
            <th>Downpayment</th>
            <th>Balance</th>
            <th>Payment Status</th>
            <th>Booking Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $totalAmount = 0;
        $totalDownpayment = 0;
        $totalBalance = 0;
        foreach ($transactions as $transaction): 
            $totalAmount += $transaction['total_amount'];
            $totalDownpayment += $transaction['downpayment_amount'];
            $totalBalance += $transaction['balance_amount'];
            $statusClass = 'status-' . $transaction['payment_status'];
        ?>
        <tr>
            <td><?= date('Y-m-d', strtotime($transaction['created_at'])) ?></td>
            <td><?= htmlspecialchars($transaction['transaction_code']) ?></td>
            <td><?= htmlspecialchars($transaction['booking_type']) ?></td>
            <td><?= htmlspecialchars($transaction['hiker_name']) ?></td>
            <td><?= htmlspecialchars($transaction['hiker_email']) ?></td>
            <td><?= htmlspecialchars($transaction['mountain_name']) ?></td>
            <td style="text-align:right">₱<?= number_format($transaction['total_amount'], 2) ?></td>
            <td style="text-align:right">₱<?= number_format($transaction['downpayment_amount'], 2) ?></td>
            <td style="text-align:right">₱<?= number_format($transaction['balance_amount'], 2) ?></td>
            <td class="<?= $statusClass ?>"><?= ucfirst($transaction['payment_status']) ?></td>
            <td><?= ucfirst($transaction['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="6" style="text-align:right"><strong>TOTALS:</strong></td>
            <td style="text-align:right"><strong>₱<?= number_format($totalAmount, 2) ?></strong></td>
            <td style="text-align:right"><strong>₱<?= number_format($totalDownpayment, 2) ?></strong></td>
            <td style="text-align:right"><strong>₱<?= number_format($totalBalance, 2) ?></strong></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<?php if (empty($transactions) && (empty($summary) || $summary['total_transactions'] == 0)): ?>
<p style="text-align:center; color:#999; padding:40px;">No transactions found for the selected period.</p>
<?php endif; ?>

<div class="footer">
    LAKBAY Wilderness Intelligence System - Official Revenue Report<br>
    This report includes all transactions recorded between <?= date('Y-m-d', strtotime($start_date)) ?> and <?= date('Y-m-d', strtotime($end_date)) ?>
</div>

</body>
</html>