<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    die("SESSION MISSING");
}

$user_id = $_SESSION['user_id'];

/* --- TOTAL INCOME --- */
$income = $conn->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND type='income'");
$income->bind_param("i", $user_id);
$income->execute();
$total_income = $income->get_result()->fetch_row()[0] ?? 0;

/* --- TOTAL EXPENSES --- */
$expense = $conn->prepare("SELECT SUM(amount) FROM transactions WHERE user_id=? AND type='expense'");
$expense->bind_param("i", $user_id);
$expense->execute();
$total_expenses = $expense->get_result()->fetch_row()[0] ?? 0;

$net_savings = $total_income - $total_expenses;

/* --- CATEGORY BREAKDOWN --- */
$cat = $conn->prepare("
    SELECT c.name, SUM(t.amount) AS total
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id=? AND t.type='expense'
    GROUP BY c.name
");
$cat->bind_param("i", $user_id);
$cat->execute();
$category_data = $cat->get_result();

/* --- INCOME VS EXPENSE BY MONTH --- */
$monthly = $conn->prepare("
    SELECT 
        DATE_FORMAT(date, '%b') AS month,
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) AS income_total,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS expense_total
    FROM transactions
    WHERE user_id=?
    GROUP BY MONTH(date)
    ORDER BY MONTH(date)
");
$monthly->bind_param("i", $user_id);
$monthly->execute();
$monthly_data = $monthly->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports • TrackSmart</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<?php include "sidebar.php"; ?>

<div class="main-content">

    <div class="header-area">
        <h1>Reports</h1>
        <p class="subtext">Welcome back!</p>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="dashboard-cards">
        <div class="card green">
            <h3>Total Income</h3>
            <p>₱<?= number_format($total_income,2) ?></p>
        </div>

        <div class="card red">
            <h3>Total Expenses</h3>
            <p>₱<?= number_format($total_expenses,2) ?></p>
        </div>

        <div class="card purple">
            <h3>Net Savings</h3>
            <p>₱<?= number_format($net_savings,2) ?></p>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="chart-card">
        <h3>Expense Breakdown</h3>
        <canvas id="pieChart"></canvas>
    </div>

    <div class="chart-card">
        <h3>Income vs Expenses</h3>
        <canvas id="barChart"></canvas>
    </div>

</div>

<script>
/* --- PIE CHART DATA --- */
const pieLabels = [
    <?php while($r = $category_data->fetch_assoc()) echo "'".$r['name']."'," ?>
];

const pieValues = [
    <?php 
        $cat->execute();
        $category_data = $cat->get_result();
        while($r = $category_data->fetch_assoc()) echo $r['total']."," 
    ?>
];

new Chart(document.getElementById("pieChart"), {
    type: 'pie',
    data: {
        labels: pieLabels,
        datasets: [{
            data: pieValues
        }]
    }
});

/* --- BAR CHART DATA --- */
const months = [
    <?php while($m = $monthly_data->fetch_assoc()) echo "'".$m['month']."'," ?>
];

const incomeValues = [
    <?php 
        $monthly->execute(); 
        $monthly_data = $monthly->get_result();
        while($m = $monthly_data->fetch_assoc()) echo $m['income_total']."," 
    ?>
];

const expenseValues = [
    <?php 
        $monthly->execute(); 
        $monthly_data = $monthly->get_result();
        while($m = $monthly_data->fetch_assoc()) echo $m['expense_total']."," 
    ?>
];

new Chart(document.getElementById("barChart"), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            { label: "Income", data: incomeValues },
            { label: "Expenses", data: expenseValues }
        ]
    }
});
</script>

</body>
</html>
