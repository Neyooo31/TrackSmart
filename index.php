<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// TOTAL INCOME
$incomeQuery = $conn->query("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id=$user_id AND type='income'");
$total_income = $incomeQuery->fetch_assoc()['total_income'] ?? 0;

// TOTAL EXPENSES
$expenseQuery = $conn->query("SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id=$user_id AND type='expense'");
$total_expenses = $expenseQuery->fetch_assoc()['total_expenses'] ?? 0;

// BALANCE
$balance = $total_income - $total_expenses;

// RECENT TRANSACTIONS
$recent = $conn->query("SELECT * FROM transactions WHERE user_id=$user_id ORDER BY date DESC LIMIT 5");
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard • TrackSmart</title>
    <link rel="stylesheet" href="assets/css/style.css?v=14">

    <!-- Chart.js (for bar chart) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Dark Mode Script -->
    <script src="assets/js/darkmode.js"></script>
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="topbar">
        <h1>Dashboard</h1>
        <span class="welcome">Welcome back, <?= $_SESSION['username']; ?>!</span>
    </div>

    <!-- DASHBOARD CARDS -->
    <div class="dashboard-cards">
        <div class="card purple">
            <h3>Total Balance</h3>
            <p>₱<?= number_format($balance, 2); ?></p>
        </div>

        <div class="card green">
            <h3>Total Income</h3>
            <p>₱<?= number_format($total_income, 2); ?></p>
        </div>

        <div class="card red">
            <h3>Total Expenses</h3>
            <p>₱<?= number_format($total_expenses, 2); ?></p>
        </div>
    </div>

    <!-- CHART SECTION -->
    <div class="chart-card">
        <h2>Monthly Overview</h2>
        <canvas id="overviewChart"></canvas>
    </div>

    <!-- RECENT TRANSACTIONS -->
    <div class="recent-card">
        <h2>Recent Transactions</h2>

        <?php while ($row = $recent->fetch_assoc()): ?>
            <div class="recent-item">
                <div>
                    <strong><?= $row['description']; ?></strong><br>
                    <small><?= $row['date']; ?> • <?= ucfirst($row['type']); ?></small>
                </div>

                <div class="<?= $row['type'] == 'income' ? 'income-text' : 'expense-text' ?>">
                    ₱<?= number_format($row['amount'], 2); ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

</div>

<!-- CHART SCRIPT -->
<script>
const ctx = document.getElementById('overviewChart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Amount',
            data: [40000, 48000, 45000, 60000, 52000, 58000],
            backgroundColor: '#6a0dad'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>
