<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";

/*
    ==========================================
    ADD NEW BUDGET
    ==========================================
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_budget'])) {

    $category   = trim($_POST['category']);
    $limit      = (float) $_POST['limit_amount'];
    $start_date = $_POST['start_date'] ?: date('Y-m-01');

    if ($category === "" || $limit <= 0) {
        $msg = '<div class="msg error">Please enter a valid category and limit.</div>';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO budgets (user_id, category, amount, start_date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isds", $user_id, $category, $limit, $start_date);
        $stmt->execute();
        $stmt->close();

        header("Location: budgets.php?added=1");
        exit;
    }
}

/*
    ==========================================
    EDIT BUDGET
    ==========================================
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_budget'])) {

    $id         = (int) $_POST['id'];
    $category   = trim($_POST['category']);
    $limit      = (float) $_POST['limit_amount'];
    $start_date = $_POST['start_date'] ?: date('Y-m-01');

    if ($category === "" || $limit <= 0) {
        $msg = '<div class="msg error">Please enter a valid category and limit.</div>';
    } else {
        $stmt = $conn->prepare("
            UPDATE budgets
            SET category = ?, amount = ?, start_date = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("sdsii", $category, $limit, $start_date, $id, $user_id);
        $stmt->execute();
        $stmt->close();

        header("Location: budgets.php?updated=1");
        exit;
    }
}

/*
    ==========================================
    DELETE BUDGET
    ==========================================
*/
if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];
    $conn->query("DELETE FROM budgets WHERE id = $delete_id AND user_id = $user_id");
    header("Location: budgets.php?deleted=1");
    exit;
}

/*
    ==========================================
    FETCH BUDGETS + MONTHLY SPEND
    For each budget category, we calculate:
      - limit (amount)
      - spent this month (expense transactions only)
      - remaining
      - percent used
    ==========================================
*/
$budgetRows = [];
$total_limit = 0;
$total_spent = 0;

$budgetResult = $conn->query("
    SELECT * FROM budgets
    WHERE user_id = $user_id
    ORDER BY category ASC
");

while ($b = $budgetResult->fetch_assoc()) {

    $category = $b['category'];
    $limit    = (float) $b['amount'];

    // sum of EXPENSES for this category in the CURRENT MONTH
    $catEsc = $conn->real_escape_string($category);
    $spentRes = $conn->query("
        SELECT COALESCE(SUM(amount),0) AS spent
        FROM transactions
        WHERE user_id = $user_id
          AND type = 'expense'
          AND category = '$catEsc'
          AND MONTH(date) = MONTH(CURDATE())
          AND YEAR(date)  = YEAR(CURDATE())
    ");
    $spentRow = $spentRes->fetch_assoc();
    $spent    = (float) ($spentRow['spent'] ?? 0);

    $remaining = max($limit - $spent, 0);
    $percent   = $limit > 0 ? round(($spent / $limit) * 100) : 0;
    if ($percent > 100) $percent = 100;

    $budgetRows[] = [
        'id'         => $b['id'],
        'category'   => $category,
        'limit'      => $limit,
        'spent'      => $spent,
        'remaining'  => $remaining,
        'percent'    => $percent,
        'start_date' => $b['start_date'],
    ];

    $total_limit += $limit;
    $total_spent += $spent;
}

$total_remaining = max($total_limit - $total_spent, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgets • TrackSmart</title>
    <link rel="stylesheet" href="assets/css/style.css?v=21">
    <!-- Dark Mode Script -->
    <script src="assets/js/darkmode.js"></script>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
<div class="budgets-wrapper">

    <!-- HEADER -->
    <div class="budgets-header">
        <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</div>
        <div>
            <h1>Budgets</h1>
            <p class="subtext">Manage your spending limits</p>
        </div>
        <button class="add-btn" onclick="openAddBudget()">+ Add Budget</button>
    </div>

    <?php
    if (isset($_GET['added']))   echo '<div class="msg success">Budget added successfully.</div>';
    if (isset($_GET['updated'])) echo '<div class="msg success">Budget updated successfully.</div>';
    if (isset($_GET['deleted'])) echo '<div class="msg success">Budget deleted successfully.</div>';
    echo $msg;
    ?>

    <!-- BUDGET OVERVIEW TITLE -->
    <div class="section-header">
        <h2>Budget Overview</h2>
        <p class="subtext">See how your spending compares to your limits this month.</p>
    </div>

    <!-- BUDGET CARDS GRID -->
    <div class="budget-grid">
        <?php if (empty($budgetRows)): ?>
            <p class="empty">No budgets yet. Click “Add Budget” to create one.</p>
        <?php else: ?>
            <?php foreach ($budgetRows as $b): ?>
                <div class="budget-card">
                    <div class="budget-card-header">
                        <h3><?= htmlspecialchars($b['category']) ?></h3>
                        <span class="budget-percent"><?= $b['percent'] ?>%</span>
                    </div>

                    <div class="budget-meta">
                        <div>
                            <small>Spent</small>
                            <p>₱<?= number_format($b['spent'], 2) ?></p>
                        </div>
                        <div>
                            <small>Limit</small>
                            <p>₱<?= number_format($b['limit'], 2) ?></p>
                        </div>
                    </div>

                    <div class="budget-bar">
                        <div class="budget-bar-fill" style="width: <?= $b['percent']; ?>%"></div>
                    </div>

                    <p class="budget-remaining">
                        ₱<?= number_format($b['remaining'], 2) ?> remaining
                    </p>

                    <div class="budget-actions">
                        <button
                            class="edit-btn"
                            onclick="openEditBudget(
                                '<?= $b['id'] ?>',
                                '<?= htmlspecialchars($b['category'], ENT_QUOTES) ?>',
                                '<?= $b['limit'] ?>',
                                '<?= $b['start_date'] ?>'
                            )"
                        >
                            ✏️ Edit
                        </button>

                        <button class="delete-btn" onclick="deleteBudget(<?= $b['id'] ?>)">🗑 Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SUMMARY CARD -->
    <div class="budget-summary-card">
        <h2>Budget Summary</h2>
        <div class="summary-row">
            <div>
                <small>Total Budget</small>
                <p>₱<?= number_format($total_limit, 2) ?></p>
            </div>
            <div>
                <small>Total Spent</small>
                <p>₱<?= number_format($total_spent, 2) ?></p>
            </div>
            <div>
                <small>Total Remaining</small>
                <p>₱<?= number_format($total_remaining, 2) ?></p>
            </div>
        </div>
    </div>

</div>
</div>

<!-- ===========================
     ADD BUDGET MODAL
=========================== -->
<div id="addBudgetModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Add Budget</h2>
            <span class="close-btn" onclick="closeAddBudget()">✕</span>
        </div>

        <form method="POST">
            <input type="hidden" name="save_budget" value="1">

            <label>Category</label>
            <input type="text" name="category" class="input" required
                   placeholder="e.g. Food, Transportation, Shopping">

            <label>Monthly Limit (₱)</label>
            <input type="number" name="limit_amount" class="input" step="0.01" min="0" required>

            <label>Start Date</label>
            <input type="date" name="start_date" class="input"
                   value="<?= date('Y-m-01'); ?>">

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeAddBudget()">Cancel</button>
                <button type="submit" class="save-btn">Save Budget</button>
            </div>
        </form>
    </div>
</div>

<!-- ===========================
     EDIT BUDGET MODAL
=========================== -->
<div id="editBudgetModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Budget</h2>
            <span class="close-btn" onclick="closeEditBudget()">✕</span>
        </div>

        <form method="POST">
            <input type="hidden" name="edit_budget" value="1">
            <input type="hidden" name="id" id="edit_id">

            <label>Category</label>
            <input type="text" name="category" id="edit_category" class="input" required>

            <label>Monthly Limit (₱)</label>
            <input type="number" name="limit_amount" id="edit_limit" class="input" step="0.01" min="0" required>

            <label>Start Date</label>
            <input type="date" name="start_date" id="edit_start_date" class="input">

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeEditBudget()">Cancel</button>
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddBudget() {
    document.getElementById('addBudgetModal').style.display = 'flex';
}
function closeAddBudget() {
    document.getElementById('addBudgetModal').style.display = 'none';
}

function openEditBudget(id, category, limit, startDate) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_limit').value = limit;
    document.getElementById('edit_start_date').value = startDate;

    document.getElementById('editBudgetModal').style.display = 'flex';
}
function closeEditBudget() {
    document.getElementById('editBudgetModal').style.display = 'none';
}

function deleteBudget(id) {
    if (confirm("Are you sure you want to delete this budget?")) {
        window.location = "budgets.php?delete=" + id;
    }
}
</script>

</body>
</html>
