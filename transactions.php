<?php  
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$note = "";

/* ========================================================
   DELETE TRANSACTION
======================================================== */
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM transactions WHERE id = $delete_id AND user_id = $user_id");
    header("Location: transactions.php?deleted=1");
    exit;
}

/* ========================================================
   SAVE NEW TRANSACTION
======================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_transaction'])) {

    $type = $_POST['type'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $date = $_POST['date'];
    $notes = $_POST['notes'] ?? "";

    $stmt = $conn->prepare("
        INSERT INTO transactions (user_id, type, description, amount, category, date, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issdsss",
        $user_id, $type, $description, $amount, $category, $date, $notes
    );

    $stmt->execute();
    $stmt->close();

    header("Location: transactions.php?added=1");
    exit;
}

/* ========================================================
   UPDATE TRANSACTION
======================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_transaction'])) {

    $id = $_POST['id'];
    $date = $_POST['date'];
    $type = $_POST['type'];
    $description = $_POST['description'];
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("
        UPDATE transactions 
        SET date=?, type=?, description=?, amount=?, category=?, notes=? 
        WHERE id=? AND user_id=?
    ");

    $stmt->bind_param("sssds sii",
        $date, $type, $description, $amount, $category, $notes, $id, $user_id
    );

    $stmt->execute();
    $stmt->close();

    header("Location: transactions.php?updated=1");
    exit;
}

/* ========================================================
   FETCH TRANSACTIONS
======================================================== */
$transactions = $conn->query("
    SELECT * FROM transactions 
    WHERE user_id = $user_id
    ORDER BY date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Transactions • TrackSmart</title>
    <link rel="stylesheet" href="assets/css/style.css?v=21" />
</head>

<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
<div class="transactions-wrapper">

    <div class="transactions-header">
        <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</div>
        <h1>Transactions</h1>
        <p class="subtext">Welcome back!</p>
    </div>

    <div class="transaction-topbar">
        <input type="text" placeholder="🔍 Search transactions..." class="search-input">
        <button class="add-btn" onclick="openModal()">+ Add Transaction</button>
        <button class="export-btn">⤓ Export</button>
    </div>

    <div class="transaction-card">
        <table class="transaction-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th class="amount-col">Amount</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php if ($transactions->num_rows == 0): ?>
                <tr>
                    <td colspan="5" class="empty">No transactions yet.</td>
                </tr>

            <?php else: ?>
                <?php while ($t = $transactions->fetch_assoc()): ?>
                <tr>
                    <td><?= $t['date'] ?></td>
                    <td><?= $t['description'] ?></td>
                    <td><span class="tag"><?= $t['category'] ?></span></td>

                    <td class="<?= $t['type'] === 'income' ? 'amount income' : 'amount expense' ?>">
                        ₱<?= number_format($t['amount'], 2) ?>
                    </td>

                    <td class="actions">
                        <button class="edit-btn" 
                            onclick="openEditModal(
                                '<?= $t['id'] ?>',
                                '<?= $t['date'] ?>',
                                '<?= $t['type'] ?>',
                                '<?= htmlspecialchars($t['description']) ?>',
                                '<?= htmlspecialchars($t['category']) ?>',
                                '<?= $t['amount'] ?>',
                                `<?= htmlspecialchars($t['notes']) ?>`
                            )">✏️</button>

                        <button class="delete-btn" onclick="deleteTransaction(<?= $t['id'] ?>)">🗑</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <p class="page-info">Showing <?= $transactions->num_rows ?> transactions</p>
    </div>

</div>
</div>




<!-- ADD TRANSACTION MODAL -->
<div id="transactionModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Add Transaction</h2>
            <span class="close-btn" onclick="closeModal()">✕</span>
        </div>

        <form method="POST">
            <input type="hidden" name="save_transaction" value="1">

            <label>Date</label>
            <input type="date" name="date" class="input" required>

            <label>Type</label>
            <div class="type-switch">
                <button type="button" class="type-btn active" id="expenseBtn" onclick="setType('expense')">Expense</button>
                <button type="button" class="type-btn" id="incomeBtn" onclick="setType('income')">Income</button>
            </div>
            <input type="hidden" name="type" id="typeField" value="expense">

            <label>Description</label>
            <input type="text" name="description" class="input" required>

            <label>Category</label>
            <select name="category" class="input" required>
                <option value="">Select category</option>
                <option>Food</option>
                <option>Utilities</option>
                <option>Transportation</option>
                <option>Entertainment</option>
                <option>Shopping</option>
                <option>Salary</option>
                <option>Freelance</option>
                <option>Business</option>
                <option>Other</option>
            </select>

            <label>Amount</label>
            <input type="number" name="amount" step="0.01" class="input" required>

            <label>Notes</label>
            <textarea name="notes" class="input"></textarea>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Transaction</button>
            </div>
        </form>
    </div>
</div>



<!-- EDIT TRANSACTION MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Transaction</h2>
            <span class="close-btn" onclick="closeEditModal()">✕</span>
        </div>

        <form method="POST">
            <input type="hidden" name="edit_transaction" value="1">
            <input type="hidden" id="edit_id" name="id">

            <label>Date</label>
            <input type="date" id="edit_date" name="date" class="input" required>

            <label>Type</label>
            <select id="edit_type" name="type" class="input">
                <option value="expense">Expense</option>
                <option value="income">Income</option>
            </select>

            <label>Description</label>
            <input type="text" id="edit_description" name="description" class="input" required>

            <label>Category</label>
            <select id="edit_category" name="category" class="input" required>
                <option>Food</option>
                <option>Utilities</option>
                <option>Transportation</option>
                <option>Entertainment</option>
                <option>Shopping</option>
                <option>Salary</option>
                <option>Freelance</option>
                <option>Business</option>
                <option>Other</option>
            </select>

            <label>Amount</label>
            <input type="number" id="edit_amount" name="amount" step="0.01" class="input" required>

            <label>Notes</label>
            <textarea id="edit_notes" name="notes" class="input"></textarea>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>



<script>
function openModal() {
    document.getElementById("transactionModal").style.display = "flex";
}
function closeModal() {
    document.getElementById("transactionModal").style.display = "none";
}

function openEditModal(id, date, type, desc, category, amount, notes) {
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_date").value = date;
    document.getElementById("edit_type").value = type;
    document.getElementById("edit_description").value = desc;
    document.getElementById("edit_category").value = category;
    document.getElementById("edit_amount").value = amount;
    document.getElementById("edit_notes").value = notes;
    
    document.getElementById("editModal").style.display = "flex";
}
function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
}

function deleteTransaction(id) {
    if (confirm("Are you sure you want to delete this transaction?")) {
        window.location = "transactions.php?delete=" + id;
    }
}

function setType(type) {
    document.getElementById("typeField").value = type;

    expenseBtn.classList.remove("active");
    incomeBtn.classList.remove("active");

    if (type === "expense") expenseBtn.classList.add("active");
    else incomeBtn.classList.add("active");
}
</script>

</body>
</html>
