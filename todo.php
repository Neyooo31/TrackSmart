<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("User not logged in. SESSION ID missing.");
}

$user_id = $_SESSION['user_id']; // must already be set at login
require 'db_connect.php';
var_dump($_SESSION['user_id']);

// Fetch tasks
$stmt = $conn->prepare("SELECT * FROM todos WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tasks = $stmt->get_result();
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>To-Do-List • TrackSmart</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=14">
</head>

<body>
<?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="todo-wrapper">
            <div class="header-area">
                <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</div>
                <h1>To-Do List</h1>
                <p class="subtext">Manage your financial tasks and reminders</p>
            </div>

        <!-- ADD TASK INPUTS -->
        <form class="todo-input-card" form id="addForm">
            <input type="text" name="task" class="todo-input" placeholder="Add new task...">
            <input type="date"  name="due_date" class="todo-date">
            <button class="add-btn" id="addTaskBtn">+ Add Task</button>
        </form>

         <!-- TASK TABLE -->
        <div class="todo-card">

            <table class="todo-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Task</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="todoList">
                    <?php while($row = $tasks->fetch_assoc()): ?>
                    <tr data-id="<?= $row['id'] ?>">
    <td>
        <input type="checkbox" class="todo-check" <?= $row['is_done'] ? 'checked' : '' ?>>
    </td>

    <td class="<?= $row['is_done'] ? 'done-text' : '' ?>">
        <?= htmlspecialchars($row['task']) ?>
    </td>

    <?php 
$isOverdue = (!empty($row['due_date']) && strtotime($row['due_date']) < strtotime('today') && !$row['is_done']);
?>
<td class="<?= $isOverdue ? 'overdue' : '' ?>">
    <?= !empty($row['due_date']) ? date("m/d/Y", strtotime($row['due_date'])) : "-" ?>
</td>

    <td class="actions-col">
        <button class="todo-edit" data-id="<?= $row['id'] ?>">✏️</button>
        <button class="todo-delete" data-id="<?= $row['id'] ?>">🗑️</button>
    </td>
</tr>

                    <?php endwhile; ?>
                    
                </tbody>
            </table>

                <div class="todo-footer">
                    <span id="pendingCount">0 pending</span>
                    <span id="completedCount">0 completed</span>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Task</h2>
            <span class="close-btn" id="closeEdit">×</span>
        </div>
        <form id="editForm">
            <input type="hidden" name="id" id="editId">
            <input type="text" name="task" id="editTask" class="input" required>
            <div class="modal-actions">
                <button type="button" class="cancel-btn" id="cancelEdit">Cancel</button>
                <button type="submit" class="save-btn">Save</button>
            </div>
        </form>
    </div>
</div>


<script>
    let todoList = document.getElementById("todoList");
    let pendingCount = document.getElementById("pendingCount");
    let completedCount = document.getElementById("completedCount");
    let addTaskBtn = document.getElementById("addTaskBtn");

    function updateCounts() {
        let checks = document.querySelectorAll(".todo-check");
        let done = 0;
        checks.forEach(c => { if (c.checked) done++; });

        completedCount.textContent = done + " completed";
        pendingCount.textContent = (checks.length - done) + " pending";
    }

    document.addEventListener("click", (e) => {
        // Delete
        if (e.target.classList.contains("todo-delete")) {
            e.target.closest("tr").remove();
            updateCounts();
        }

        // Status check
        if (e.target.classList.contains("todo-check")) {
            updateCounts();
        }
    });
</script>

<script>
// COUNT
function updateCounts() {
    let total = document.querySelectorAll(".todo-check").length;
    let done = document.querySelectorAll(".todo-check:checked").length;

    document.getElementById("pendingCount").innerText = (total - done) + " pending";
    document.getElementById("completedCount").innerText = done + " completed";
}

// ADD
document.getElementById("addForm").addEventListener("submit", function(e){
    e.preventDefault();

    let formData = new FormData(this);

    fetch("add_task.php", { method: "POST", body: formData })
    .then(() => location.reload());
});

// DELETE
document.addEventListener("click", function(e){
    if (e.target.classList.contains("todo-delete")) {
        let id = e.target.dataset.id;

        fetch("delete_task.php", {
            method: "POST",
            body: new URLSearchParams({id})
        }).then(() => location.reload());
    }
});

// TOGGLE DONE
document.addEventListener("change", function(e){
    if (e.target.classList.contains("todo-check")) {
        let id = e.target.closest("tr").dataset.id;
        let is_done = e.target.checked ? 1 : 0;

        fetch("toggle_task.php", {
            method: "POST",
            body: new URLSearchParams({id, is_done})
        });

        updateCounts();
    }
});

// EDIT — OPEN MODAL
document.addEventListener("click", function(e){
    if (e.target.classList.contains("todo-edit")) {
        let row = e.target.closest("tr");
        let id = row.dataset.id;
        let task = row.children[1].innerText;

        document.getElementById("editId").value = id;
        document.getElementById("editTask").value = task;

        document.getElementById("editModal").classList.add("active");
    }
});

// EDIT — SAVE
document.getElementById("editForm").addEventListener("submit", function(e){
    e.preventDefault();

    fetch("update_task.php", {
        method: "POST",
        body: new FormData(this)
    }).then(() => location.reload());
});

// CLOSE MODAL
document.getElementById("cancelEdit").onclick =
document.getElementById("closeEdit").onclick = () => {
    document.getElementById("editModal").classList.remove("active");
};

updateCounts();
</script>

</body>
</html>