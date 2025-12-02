<?php
session_start();
require 'db_connect.php';
require $_SERVER['DOCUMENT_ROOT'].'/TrackSmart/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /TrackSmart/login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

/* ADD OR UPDATE BUDGET */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category = trim($_POST['category']);
    $limit = floatval($_POST['limit_amount']);
    $budget_id = $_POST['budget_id'] ?? null;

    if ($budget_id) {
        $stmt = $conn->prepare(
            "UPDATE budgets SET category=?, limit_amount=? WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("sdii", $category, $limit, $budget_id, $user_id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO budgets (user_id, category, limit_amount) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("isd", $user_id, $category, $limit);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: /TrackSmart/budget.php?saved=1");
    exit;
}

/* DELETE BUDGET */
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM budgets WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $del_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: /TrackSmart/budget.php?deleted=1");
    exit;
}

/* FETCH BUDGETS */
$budgets = [];
$stmt = $conn->prepare(
    "SELECT id, category, limit_amount FROM budgets WHERE user_id=? ORDER BY id ASC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $budgets[] = $row;
}
$stmt->close();

/* FETCH SPENT TOTAL PER CATEGORY */
$spent_map = [];
$stmt = $conn->prepare("
    SELECT category, SUM(amount) AS spent
    FROM transactions
    WHERE user_id=? AND type='expense'
    GROUP BY category
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $spent_map[$r['category']] = floatval($r['spent']);
}
$stmt->close();

function money($n) {
    return "₱" . number_format($n, 2);
}

/* CALCULATE TOTALS */
$total_budget = 0;
$total_spent = 0;
foreach ($budgets as $b) {
    $total_budget += $b['limit_amount'];
    $spent = $spent_map[$b['category']] ?? 0;
    $total_spent += $spent;
}
$total_remaining = $total_budget - $total_spent;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budgets • TrackSmart</title>
<link rel="stylesheet" href="/TrackSmart/assets/css/style.css">

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">

    <div class="header">
        <div>
            <h1>Budget Overview</h1>
            <div class="subtext">Manage your spending limits</div>
        </div>
        <button class="add-btn" onclick="openModal()">+ Add Budget</button>
    </div>

    <div class="grid">
        <?php foreach ($budgets as $b):
            $cat = $b['category'];
            $limit = $b['limit_amount'];
            $spent = $spent_map[$cat] ?? 0;
            $remaining = $limit - $spent;
            $percent = $limit > 0 ? min(100, ($spent / $limit) * 100) : 0;
        ?>
        <div class="card">
            <div class="card-top">
                <div class="category-title"><?= htmlspecialchars($cat) ?></div>
                <div class="percent-pill"><?= round($percent) ?>%</div>
            </div>

            <button class="menu-btn" onclick="toggleMenu(<?= $b['id'] ?>)">⋮</button>
            <div class="menu-options" id="menu-<?= $b['id'] ?>">
                <button onclick="editBudget('<?= htmlspecialchars($cat) ?>', <?= $limit ?>, <?= $b['id'] ?>)">Edit</button>
                <button onclick="deleteBudget(<?= $b['id'] ?>)">Delete</button>
            </div>

            <div class="row">
                <div class="label">Spent</div>
                <div class="amount"><?= money($spent) ?></div>
            </div>

            <div class="row">
                <div class="label">Limit</div>
                <div class="amount"><?= money($limit) ?></div>
            </div>

            <div class="line"></div>

            <div class="progress-wrap">
                <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
            </div>

            <div class="remaining"><?= money($remaining) ?> Remaining</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="budget-summary-card">
        <h3>Budget Summary</h3>
        <div class="summary-row">
            <div class="summary-col">
                <div class="summary-label">Total Budget</div>
                <div class="summary-amount"><?= money($total_budget) ?></div>
            </div>
            <div class="summary-col center">
                <div class="summary-label">Total Spent</div>
                <div class="summary-amount"><?= money($total_spent) ?></div>
            </div>
            <div class="summary-col right">
                <div class="summary-label">Total Remaining</div>
                <div class="summary-amount remaining"><?= money($total_remaining) ?></div>
            </div>
        </div>
    </div>

</div>

<div id="budgetModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Add / Edit Budget</h3>
        <form method="POST">
            <input type="hidden" name="budget_id" id="budget_id">
            <label>Category</label>
            <select name="category" class="input" id="categoryField">
                <option>Food & Dining</option>
                <option>Transportation</option>
                <option>Utilities</option>
                <option>Entertainment</option>
                <option>Shopping</option>
                <option>Healthcare</option>
                <option>Business</option>
                <option>Other</option>
            </select>
            <label>Limit Amount</label>
            <input type="number" name="limit_amount" class="input" step="0.01" id="limitField">
            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="save-btn">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(){ document.getElementById('budgetModal').style.display='flex'; }
function closeModal(){ document.getElementById('budgetModal').style.display='none'; }

function toggleMenu(id){
    const menu = document.getElementById('menu-' + id);
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

function editBudget(category, limit, id){
    document.getElementById('categoryField').value = category;
    document.getElementById('limitField').value = limit;
    document.getElementById('budget_id').value = id;
    openModal();
}

function deleteBudget(id){
    if(confirm('Are you sure you want to delete this budget?')){
        window.location = '/TrackSmart/budget.php?delete=' + id;
    }
}

document.addEventListener('click', function(event){
    document.querySelectorAll('.menu-options').forEach(function(menu){
        if(!menu.contains(event.target) && !menu.previousElementSibling.contains(event.target)){
            menu.style.display = 'none';
        }
    });
});
</script>

</body>
</html>
