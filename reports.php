<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// =========================
// TOTALS (INCOME / EXPENSE)
// =========================
$totalIncome = 0;
$totalExpense = 0;

$totals = $conn->query("
    SELECT 
        SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END) AS total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expense
    FROM transactions
    WHERE user_id = $user_id
");

if ($row = $totals->fetch_assoc()) {
    $totalIncome  = $row['total_income']  ?? 0;
    $totalExpense = $row['total_expense'] ?? 0;
}

$netSavings = $totalIncome - $totalExpense;

// =========================
// EXPENSES BY CATEGORY
// (for pie + category table)
// =========================
$catLabels = [];
$catTotals = [];

$catResult = $conn->query("
    SELECT category, SUM(amount) AS total
    FROM transactions
    WHERE user_id = $user_id AND type = 'expense'
    GROUP BY category
    ORDER BY total DESC
");

while ($row = $catResult->fetch_assoc()) {
    $label = $row['category'] ?: 'Uncategorized';
    $catLabels[] = $label;
    $catTotals[] = (float) $row['total'];
}

$totalExpenseForPercent = array_sum($catTotals);

// =========================
// MONTHLY INCOME vs EXPENSE
// =========================
$monthLabels = [];
$incomeSeries = [];
$expenseSeries = [];

$monthResult = $conn->query("
    SELECT 
        DATE_FORMAT(date, '%b') AS month_label,
        DATE_FORMAT(date, '%Y-%m') AS ym,
        SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END) AS income_total,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense_total
    FROM transactions
    WHERE user_id = $user_id
    GROUP BY ym, month_label
    ORDER BY ym
");

while ($row = $monthResult->fetch_assoc()) {
    $monthLabels[]   = $row['month_label'];
    $incomeSeries[]  = (float) $row['income_total'];
    $expenseSeries[] = (float) $row['expense_total'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports • TrackSmart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css?v=30">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="reports-wrapper">

        <!-- HEADER -->
        <div class="reports-header">
            <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</div>
            <div>
                <h1>Reports</h1>
                <p class="subtext">Welcome back!</p>
            </div>
        </div>

        <!-- TOP SUMMARY CARDS -->
        <div class="reports-summary-row">
            <div class="summary-card">
                <p class="summary-label">Total Income</p>
                <p class="summary-value income-text">₱<?= number_format($totalIncome, 2) ?></p>
            </div>

            <div class="summary-card">
                <p class="summary-label">Total Expenses</p>
                <p class="summary-value expense-text">₱<?= number_format($totalExpense, 2) ?></p>
            </div>

            <div class="summary-card">
                <p class="summary-label">Net Savings</p>
                <p class="summary-value">
                    ₱<?= number_format($netSavings, 2) ?>
                </p>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="reports-chart-row">

            <!-- Expense Breakdown (Pie) -->
            <div class="chart-card">
                <h2>Expense Breakdown</h2>
                <canvas id="expensePie"></canvas>
            </div>

            <!-- Income vs Expenses (Bar) -->
            <div class="chart-card">
                <h2>Income vs Expenses</h2>
                <canvas id="incomeExpenseBar"></canvas>
            </div>
        </div>

        <!-- CATEGORY DETAILS -->
        <div class="category-card">
            <h2>Category Details</h2>

            <table class="category-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($totalExpenseForPercent <= 0): ?>
                    <tr>
                        <td colspan="3" class="empty">No expense data yet.</td>
                    </tr>
                <?php else: ?>
                    <?php for ($i = 0; $i < count($catLabels); $i++): 
                        $percent = ($catTotals[$i] / $totalExpenseForPercent) * 100;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($catLabels[$i]) ?></td>
                            <td>₱<?= number_format($catTotals[$i], 2) ?></td>
                            <td><?= number_format($percent, 1) ?>%</td>
                        </tr>
                    <?php endfor; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
// -------------------------
// DATA FROM PHP
// -------------------------
const pieLabels   = <?= json_encode($catLabels) ?>;
const pieData     = <?= json_encode($catTotals) ?>;
const monthLabels = <?= json_encode($monthLabels) ?>;
const incomeData  = <?= json_encode($incomeSeries) ?>;
const expenseData = <?= json_encode($expenseSeries) ?>;

// -------------------------
// EXPENSE PIE CHART
// -------------------------
const ctxPie = document.getElementById('expensePie').getContext('2d');
new Chart(ctxPie, {
    type: 'pie',
    data: {
        labels: pieLabels,
        datasets: [{
            data: pieData,
            backgroundColor: [
                '#6d28d9', '#f97316', '#22c55e', '#0ea5e9',
                '#facc15', '#ec4899', '#14b8a6', '#a855f7'
            ]
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// -------------------------
// INCOME vs EXPENSE BAR
// -------------------------
const ctxBar = document.getElementById('incomeExpenseBar').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [
            {
                label: 'Income',
                data: incomeData,
                backgroundColor: '#22c55e'
            },
            {
                label: 'Expense',
                data: expenseData,
                backgroundColor: '#8b5cf6'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: {
                stacked: false
            },
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>
