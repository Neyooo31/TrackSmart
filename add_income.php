<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id']; 
$note = "";

/* ==========================================
   FETCH BUDGET CATEGORIES (DYNAMIC)
========================================== */
$categoryQuery = $conn->query("
    SELECT * FROM categories
    WHERE type='income'
");

$budgetCategories = [];
while ($row = $categoryQuery->fetch_assoc()) {
    $budgetCategories[] = [$row['id'], $row['name']];
}

/* ==========================================
   ADD NEW INCOME
========================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_income'])) {

    $amount = $_POST['amount'];
    $category_id = $_POST['category_id'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $notes = $_POST['notes'] ?? "";

    $stmt = $conn->prepare("
        INSERT INTO transactions (user_id, type, description, amount, category_id, date, notes)
        VALUES (?, 'income', ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("isdiss", 
        $user_id, $description, $amount, $category_id, $date, $notes
    );

    $stmt->execute();
    $stmt->close();

    $note = '<div class="msg success">Income added successfully!</div>';
}

/* ==========================================
   EDIT INCOME
========================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_income'])) {

    $id = $_POST['id'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $category_id = $_POST['category_id'];
    $amount = $_POST['amount'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("
        UPDATE transactions 
        SET date=?, description=?, category_id=?, amount=?, notes=?
        WHERE id=? AND user_id=? AND type='income'
    ");

    $stmt->bind_param("ssidsii", 
        $date, $description, $category_id, $amount, $notes, $id, $user_id
    );

    $stmt->execute();
    $stmt->close();

    $note = '<div class="msg success">Income updated successfully!</div>';
}

/* ==========================================
   DELETE INCOME
========================================== */
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM transactions WHERE id=$id AND user_id=$user_id AND type='income'");
    header("Location: add_income.php?deleted=1");
    exit;
}

/* ==========================================
   MONTHLY TOTAL
========================================== */
$sum = $conn->query("
    SELECT SUM(amount) AS total 
    FROM transactions 
    WHERE user_id=$user_id 
      AND type='income'
      AND MONTH(date)=MONTH(CURDATE())
      AND YEAR(date)=YEAR(CURDATE())
");
$row = $sum->fetch_assoc();
$monthTotal = $row['total'] ?? 0;

/* ==========================================
   RECENT INCOME LIST
========================================== */
$recent = $conn->query("
    SELECT t.*, c.name FROM transactions t, categories c
    WHERE t.user_id=$user_id AND t.type='income' AND t.category_id=c.id
    ORDER BY t.date DESC
");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Income • TrackSmart</title>
<link rel="stylesheet" href="assets/css/style.css?v=21">
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
<div class="add-income-wrapper">

    <div class="header-area">
        <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</div>
        <h1>Add Income</h1>
        <p class="subtext">Record your income transactions.</p>
    </div>

    <?= $note ?>

    <button class="add-btn" onclick="openAddModal()">+ Add Income</button>

    <!-- MONTH SUMMARY -->
    <div class="income-summary-card">
        <p class="summary-title">Total Income This Month</p>
        <h2 class="summary-amount">₱<?= number_format($monthTotal, 2) ?></h2>
    </div>

    <!-- INCOME LIST -->
    <div class="income-summary-card">
        <h3 class="recent-title">Income Records</h3>

        <?php if ($recent->num_rows === 0): ?>
            <p class="empty">No income recorded yet.</p>
        <?php else: ?>
            <?php while ($r = $recent->fetch_assoc()): ?>
                <div class="summary-item">

                    <div>
                        <strong><?= $r['description'] ?></strong><br>
                        <small><?= $r['date'] ?> • <?= $r['name'] ?></small>
                    </div>

                    <div class="income-amount">₱<?= number_format($r['amount'], 2) ?></div>

                    <div class="actions">
                        <button class="edit-btn" onclick="openEditModal(
                            '<?= $r['id'] ?>',
                            '<?= $r['date'] ?>',
                            '<?= htmlspecialchars($r['description']) ?>',
                            '<?= htmlspecialchars($r['category_id']) ?>',
                            '<?= $r['amount'] ?>',
                            `<?= htmlspecialchars($r['notes']) ?>`
                        )">✏️</button>

                        <button class="delete-btn" onclick="deleteIncome(<?= $r['id'] ?>)">🗑</button>
                    </div>

                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

</div>
</div>


<!-- ADD INCOME MODAL -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Add Income</h2>
            <span class="close-btn" onclick="closeAddModal()">✕</span>
        </div>

        <form method="POST">
            <input type="hidden" name="save_income" value="1">

            <label>Date</label>
            <input type="date" name="date" class="input" required>

            <label>Description</label>
            <input type="text" name="description" class="input" required>

            <label>Category</label>
            <select name="category_id" class="input" required>
                <option value="">Select category</option>
                <?php foreach ($budgetCategories as $cat): ?>
                    <option value="<?= $cat[0] ?>"><?= $cat[1] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Amount</label>
            <input type="number" name="amount" class="input" step="0.01" required>

            <label>Notes</label>
            <textarea name="notes" class="input"></textarea>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Income</button>
            </div>
        </form>
    </div>
</div>


<!-- EDIT INCOME MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Income</h2>
            <span class="close-btn" onclick="closeEditModal()">✕</span>
        </div>

        <form method="POST">
            <input type="hidden" name="edit_income" value="1">
            <input type="hidden" name="id" id="edit_id">

            <label>Date</label>
            <input type="date" name="date" id="edit_date" class="input" required>

            <label>Description</label>
            <input type="text" name="description" id="edit_description" class="input" required>

            <label>Category</label>
            <select name="category_id" id="edit_category" class="input" required>
                <?php foreach ($budgetCategories as $cat): ?>
                    <option value="<?= $cat[0] ?>"><?= $cat[1] ?></option>
                <?php endforeach; ?>
            </select>

            <label>Amount</label>
            <input type="number" name="amount" id="edit_amount" class="input" step="0.01" required>

            <label>Notes</label>
            <textarea name="notes" id="edit_notes" class="input"></textarea>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>


<script>
function openAddModal() {
    document.getElementById("addModal").style.display = "flex";
}
function closeAddModal() {
    document.getElementById("addModal").style.display = "none";
}

function openEditModal(id, date, desc, category, amount, notes) {
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_date").value = date;
    document.getElementById("edit_description").value = desc;
    document.getElementById("edit_category").value = category;
    document.getElementById("edit_amount").value = amount;
    document.getElementById("edit_notes").value = notes;

    document.getElementById("editModal").style.display = "flex";
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
}

function deleteIncome(id) {
    if (confirm("Delete this income?")) {
        window.location = "add_income.php?delete=" + id;
    }
}
</script>

</body>
</html>
