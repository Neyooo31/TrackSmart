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
        <!-- <form class="todo-input-card" id="addForm">
            <input type="text" name="task" class="todo-input" placeholder="Add new task...">
            <input type="date"  name="due_date" class="todo-date">
            <button class="add-btn" id="addTaskBtn">+ Add Task</button>
        </form> -->

        <form class="todo-input-card" id="addForm" method="POST">
            <input type="text" name="task" class="todo-input" placeholder="Add new task..." required>
        
            <select name="category" class="todo-input"  placeholder="Choose Category..."> 
                <!-- todo-category -->
                <option value="">Choose Category</option>
                <option value="Work">Work</option>
                <option value="School">School</option>
                <option value="Bills">Bills</option>
                <option value="Miscellaneous">Miscellaneous</option>
                <option value="Payments">Payments</option>
                <option value="Personal">Personal</option>
                <option value="Urgent">Urgent</option>
            </select>
        
            <input type="date" name="due_date" class="todo-date">
        
            <button class="add-btn" id="addTaskBtn">+ Add Task</button>
        </form>

         <!-- TASK TABLE -->
        <div class="todo-card">

            <table class="todo-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Category</th>
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
            
            <td>
                <?php if (!empty($row['category'])): ?>
                    <span class="tag tag-<?= strtolower($row['category']) ?>">
                        <?= $row['category'] ?>
                    </span>
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>


            <td class="<?= $row['is_done'] ? 'done-text-task' : '' ?>">
                <?= htmlspecialchars($row['task']) ?>
            </td>
                
            <?php 
                $today = strtotime('today');
                $dueDate = !empty($row['due_date']) ? strtotime($row['due_date']) : null;

                $isOverdue = $dueDate && $dueDate < $today && !$row['is_done'];
                $isToday = $dueDate && $dueDate == $today && !$row['is_done'];
                $isThisWeek = $dueDate && $dueDate > $today && $dueDate <= strtotime('+7 days') && !$row['is_done'];
                // $isOverdue = 
                //     (!empty($row['due_date']) && 
                //     strtotime($row['due_date']) < strtotime('today') && 
                //     !$row['is_done']);
            ?>

            <td class="
                <?= $isOverdue ? 'overdue' : '' ?>
                <?= $isToday ? 'due-today' : '' ?>
                <?= $isThisWeek ? 'due-week' : '' ?>
            ">
                <?= !empty($row['due_date']) ? 
                date("m/d/Y", strtotime($row['due_date'])) : "-" ?>
            </td>

                <td class="actions-col">
                    <button class="todo-edit" data-id="<?= $row['id'] ?>">✎ᝰ</button>
                    <button class="todo-delete" data-id="<?= $row['id'] ?>">🗑️</button>
                </td>
            </tr>  <!--icon options: ✎ 🖊️🖊🗑️✎𓂃 𓂃🖊🖋✏️✒️✎ᝰ. ⌦-->
            
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
<!-- DELETE CONFIRMATION MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box small">
        <div class="modal-header">
            <h2>Delete Task?</h2>
            <span class="close-btn" id="closeDelete">×</span>
        </div>

        <p>Are you sure you want to delete this task?</p>

        <div class="modal-actions">
            <button type="button" class="cancel-btn" id="cancelDelete">Cancel</button>
            <button type="button" class="delete-confirm-btn" id="confirmDelete">Delete</button>
        </div>
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
<script>
// GLOBAL DELETE ID HOLDER
let taskToDelete = null;

// When clicking delete button → open modal
document.addEventListener("click", function(e){
    if (e.target.classList.contains("todo-delete")) {
        taskToDelete = e.target.dataset.id;
        document.getElementById("deleteModal").classList.add("active");
    }
});

// Close modal buttons
document.getElementById("closeDelete").onclick = 
document.getElementById("cancelDelete").onclick = function () {
    document.getElementById("deleteModal").classList.remove("active");
    taskToDelete = null;
};

// Confirm deletion
document.getElementById("confirmDelete").onclick = function() {
    if (!taskToDelete) return;

    fetch("delete_task.php", {
        method: "POST",
        body: new URLSearchParams({ id: taskToDelete })
    }).then(() => {
        location.reload();
    });
};
</script>
</body>
</html>