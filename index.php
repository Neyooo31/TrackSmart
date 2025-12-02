<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ===========================
   DASHBOARD TOTALS
   =========================== */

// TOTAL INCOME
$incomeQuery = $conn->query("
    SELECT SUM(amount) AS total_income 
    FROM transactions 
    WHERE user_id=$user_id AND type='income'
");
$total_income = $incomeQuery->fetch_assoc()['total_income'] ?? 0;

// TOTAL EXPENSES
$expenseQuery = $conn->query("
    SELECT SUM(amount) AS total_expenses 
    FROM transactions 
    WHERE user_id=$user_id AND type='expense'
");
$total_expenses = $expenseQuery->fetch_assoc()['total_expenses'] ?? 0;

// BALANCE
$balance = $total_income - $total_expenses;

// RECENT TRANSACTIONS
$recent = $conn->query("
    SELECT * FROM transactions 
    WHERE user_id=$user_id 
    ORDER BY date DESC LIMIT 5
");
/* MONTHLY CHART QUERY */
$monthly = $conn->query("
    SELECT
        MONTH(date) AS transaction_month,
    SUM(
        CASE
            WHEN type = 'income' THEN amount
        ELSE 0
            END
    ) AS income,
    SUM(
        CASE
            WHEN type = 'expense' THEN amount
        ELSE 0
            END
    ) AS expense
    FROM transactions WHERE
        YEAR(date) = YEAR(CURDATE()) AND
        user_id=$user_id
    GROUP BY transaction_month
    ORDER BY transaction_month;
");

// Prepare arrays for Chart.js
$days = [];
$incomeData = [];
$expenseData = [];

while ($r = $monthly->fetch_assoc()) {
    $timestamp = mktime(0, 0, 0, $r['transaction_month'], 1, date('Y'));
    $days[] = date("M", $timestamp);
    $incomeData[] = $r['income'] ?? 0;
    $expenseData[] = $r['expense'] ?? 0;
};

/* ===========================
   TODAY'S TASKS  (Feature #5)
   =========================== */

$today = date("Y-m-d");

$todayTasks = $conn->prepare("
    SELECT task, due_date 
    FROM todos 
    WHERE user_id=? AND due_date=? AND is_done=0
");
$todayTasks->bind_param("is", $user_id, $today);
$todayTasks->execute();
$today_results = $todayTasks->get_result();


/* ===========================
   NOTIFICATION BELL COUNTER
   =========================== */

$notifQuery = $conn->query("
    SELECT COUNT(*) AS overdue_count
    FROM todos 
    WHERE user_id=$user_id
    AND due_date < CURDATE()
    AND is_done = 0
");
$overdue_count = $notifQuery->fetch_assoc()['overdue_count'] ?? 0;

/* NOTIFICATION TASK LISTS */
$overdueTasks = $conn->query("
    SELECT task, due_date 
    FROM todos 
    WHERE user_id=$user_id AND due_date < CURDATE() AND is_done=0
");

$todayTasksNotif = $conn->query("
    SELECT task, due_date 
    FROM todos 
    WHERE user_id=$user_id AND due_date = CURDATE() AND is_done=0
");

$weekTasks = $conn->query("
    SELECT task, due_date 
    FROM todos
    WHERE user_id=$user_id 
    AND due_date > CURDATE() 
    AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND is_done=0
");
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

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Dashboard</h2>

        <div class="right-icons">

            <!-- 🔔 NOTIFICATION BELL -->
            <div class="notif-bell">
                <svg class="notif-bell-icon-svg" fill="#000000" width="26" height="26" viewBox="0 0 24 24" id="notification-bell" data-name="Flat Line" xmlns="http://www.w3.org/2000/svg" class="icon flat-line">
                    <path id="secondary" d="M19.38,14.38a2.12,2.12,0,0,1,.62,1.5h0A2.12,2.12,0,0,1,17.88,18H6.12A2.12,2.12,0,0,1,4,15.88H4a2.12,2.12,0,0,1,.62-1.5L6,13V9a6,6,0,0,1,6-6h0a6,6,0,0,1,6,6v4Z" fill=rgba(152, 42, 167, 1) stroke-width=2></path>
                    <path id="primary" d="M12,21h0a3,3,0,0,1-3-3h6A3,3,0,0,1,12,21Zm6-8V9a6,6,0,0,0-6-6h0A6,6,0,0,0,6,9v4L4.62,14.38A2.12,2.12,0,0,0,4,15.88H4A2.12,2.12,0,0,0,6.12,18H17.88A2.12,2.12,0,0,0,20,15.88h0a2.12,2.12,0,0,0-.62-1.5Z" stroke="#364153" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    
                <?php if ($overdue_count > 0): ?>
                    <span class="notif-badge"><?= $overdue_count ?></span>
                <?php endif; ?>
            </div>
            <span class="welcome">
                Welcome back, <?= htmlspecialchars($_SESSION['username']); ?>!
            </span>
        </div>
    </div>

    <!-- NOTIFICATION PANEL -->
    <div id="notifPanel" class="notif-panel">
        <div class="notif-close-btn" id="closeNotif">×</div>


        <h2>Notifications</h2>

        <!-- OVERDUE -->
        <h3 class="notif-section-title">Overdue Tasks</h3>
        <?php if ($overdueTasks->num_rows > 0): ?>
            <?php while($o = $overdueTasks->fetch_assoc()): ?>
                <div class="notif-item overdue-item">
                    <strong><?= htmlspecialchars($o['task']); ?></strong>
                    <small>Due: <?= $o['due_date'] ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-msg">No overdue tasks 🎉</p>
        <?php endif; ?>

        <!-- TODAY -->
        <h3 class="notif-section-title">Today</h3>
        <?php if ($todayTasksNotif->num_rows > 0): ?>
            <?php while($t = $todayTasksNotif->fetch_assoc()): ?>
                <div class="notif-item today-item">
                    <strong><?= htmlspecialchars($t['task']); ?></strong>
                    <small>Due Today</small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-msg">No tasks today 🎉</p>
        <?php endif; ?>

        <!-- THIS WEEK -->
        <h3 class="notif-section-title">Next 7 Days</h3>
        <?php if ($weekTasks->num_rows > 0): ?>
            <?php while($w = $weekTasks->fetch_assoc()): ?>
                <div class="notif-item week-item">
                    <strong><?= htmlspecialchars($w['task']); ?></strong>
                    <small>Due: <?= $w['due_date'] ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty-msg">Nothing coming up 👍</p>
        <?php endif; ?>

    </div>

    <!-- DASHBOARD CARDS -->
    <div class="dashboard-cards">
        <div class="card purple">
            <h3><img src="assets/images/totalbalanceIcon.png"  class="dashboard-icon" alt="Image here" ></img>&nbsp;Total Balance</h3>
            <p>₱<?= number_format($balance, 2); ?></p>
        </div>

        <div class="card green">
            <h3><img src="assets/images/totalincIcon.png" class="dashboard-icon" alt="Image here"></img>&nbsp;Total Income</h3>
            <p>₱<?= number_format($total_income, 2); ?></p>
        </div>

        <div class="card red">
            <h3><img src="assets/images/totalexpensesIcon.png" class="dashboard-icon" alt="Image here"></img>&nbsp;Total Expenses</h3>
            <p>₱<?= number_format($total_expenses, 2); ?></p>
        </div>
    </div>

    <!-- CHART SECTION -->
    <div class="chart-card">
        <h2>Monthly Overview</h2>
        <canvas id="overviewChart"></canvas>
    </div>

    <!-- TODAY'S TASKS (Feature #5) -->
    <div class="today-card">
        <h2>Today's Tasks</h2>

        <?php if ($today_results->num_rows > 0): ?>
            <ul class="today-list">
            <?php while ($t = $today_results->fetch_assoc()): ?>
                <li>• <?= htmlspecialchars($t['task']); ?></li>
            <?php endwhile; ?>
            </ul>

        <?php else: ?>
            <p>No tasks for today 🎉</p>
        <?php endif; ?>
    </div>

    <!-- RECENT TRANSACTIONS -->
    <div class="recent-card">
        <h2>Recent Transactions</h2>

        <?php while ($row = $recent->fetch_assoc()): ?>
            <div class="recent-item">
                <div>
                    <strong><?= htmlspecialchars($row['description']); ?></strong><br>
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
        labels: <?= json_encode($days) ?>,
        datasets: [
            {
                label: 'Income',
                data: <?= json_encode($incomeData) ?>,
                backgroundColor: '#22c55e'
            },
            {
                label: 'Expenses',
                data: <?= json_encode($expenseData) ?>,
                backgroundColor: '#e63946'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        },

        plugins: {
            legend: {
                display: false 
            }
        }
    }
});

const notifBell = document.querySelector(".notif-bell");
const notifPanel = document.getElementById("notifPanel");

notifBell.addEventListener("click", () => {
    notifPanel.classList.toggle("active");
});
</script>
<script>
document.getElementById("closeNotif").addEventListener("click", () => {
    document.getElementById("notifPanel").classList.remove("active");
});
</script>

</body>
</html>
