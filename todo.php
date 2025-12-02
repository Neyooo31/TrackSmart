<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// -------------------------
// ADD NEW TASK
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $task = trim($_POST['task'] ?? '');
    $due_date = $_POST['due_date'] ?? null;

    if ($task !== '') {
        $stmt = $conn->prepare("
            INSERT INTO todos (user_id, task, due_date, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iss", $user_id, $task, $due_date);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: todo.php");
    exit;
}

// -------------------------
// EDIT TASK
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $task     = trim($_POST['task'] ?? '');
    $due_date = $_POST['due_date'] ?? null;

    if ($id > 0 && $task !== '') {
        $stmt = $conn->prepare("
            UPDATE todos
               SET task = ?, due_date = ?
             WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("ssii", $task, $due_date, $id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: todo.php");
    exit;
}

// -------------------------
// TOGGLE STATUS (checkbox)
// -------------------------
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];

    // Get current status
    $stmt = $conn->prepare("
        SELECT status FROM todos
         WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->bind_result($status);
    $stmt->fetch();
    $stmt->close();

    if ($status === 'pending' || $status === 'done') {
        $newStatus = ($status === 'pending') ? 'done' : 'pending';

        $stmt = $conn->prepare("
            UPDATE todos
               SET status = ?
             WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("sii", $newStatus, $id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: todo.php");
    exit;
}

// -------------------------
// DELETE TASK
// -------------------------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM todos
         WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: todo.php");
    exit;
}

// -------------------------
// FETCH TASKS
// -------------------------
$tasks = $conn->prepare("
    SELECT id, task, due_date, status
      FROM todos
     WHERE user_id = ?
  ORDER BY status = 'done',        -- pending first
           (due_date IS NULL),     -- tasks with dates first
           due_date ASC,
           id DESC
");
$tasks->bind_param("i", $user_id);
$tasks->execute();
$result = $tasks->get_result();

// Count pending/completed
$pending = 0;
$completed = 0;
while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'done') $completed++;
    else $pending++;
    $rows[] = $row; // store in array for later rendering
}
$tasks->close();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List • TrackSmart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css?v=30">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="todo-wrapper">

        <!-- HEADER -->
        <div class="todo-header">
            <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                ☰
            </div>
            <div>
                <h1>To-Do List</h1>
                <p class="subtext">Manage your financial tasks and reminders</p>
            </div>
        </div>

        <!-- TOP BAR (input + button) -->
        <div class="todo-topbar">
            <input
                type="text"
                id="quickTaskInput"
                class="task-input"
                placeholder="Add new task..."
            >
            <button class="add-btn" onclick="openAddModal()">
                + Add Task
            </button>
        </div>

        <!-- TABLE CARD -->
        <div class="todo-card">
            <table class="todo-table">
                <thead>
                    <tr>
                        <th class="status-col">Status</th>
                        <th>Task</th>
                        <th class="duedate-col">Due Date</th>
                        <th class="actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="4" class="empty">No tasks yet. Add your first task above.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $t): ?>
                        <tr class="<?= $t['status'] === 'done' ? 'row-done' : '' ?>">
                            <td class="status-cell">
                                <input
                                    type="checkbox"
                                    class="status-checkbox"
                                    <?= $t['status'] === 'done' ? 'checked' : '' ?>
                                    onchange="toggleStatus(<?= (int)$t['id'] ?>)"
                                >
                            </td>
                            <td class="task-cell">
                                <?= htmlspecialchars($t['task']) ?>
                            </td>
                            <td class="duedate-cell">
                                <?= $t['due_date'] ? htmlspecialchars($t['due_date']) : '—' ?>
                            </td>
                            <td class="actions">
                                <button
                                    class="icon-btn"
                                    onclick="openEditModal(
                                        '<?= (int)$t['id'] ?>',
                                        '<?= htmlspecialchars($t['task'], ENT_QUOTES) ?>',
                                        '<?= $t['due_date'] ?>'
                                    )"
                                >✏</button>

                                <button
                                    class="icon-btn delete"
                                    onclick="deleteTask(<?= (int)$t['id'] ?>)"
                                >🗑</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="todo-footer">
                <span><?= $pending ?> pending tasks</span>
                <span><?= $completed ?> completed</span>
            </div>
        </div>

    </div>
</div>

<!-- ADD TASK MODAL -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Add Task</h2>
            <span class="close-btn" onclick="closeAddModal()">✕</span>
        </div>

        <form method="post">
            <input type="hidden" name="add_task" value="1">

            <label>Task</label>
            <input type="text" id="add_task" name="task" class="input" required>

            <label>Due Date</label>
            <input type="date" id="add_due_date" name="due_date" class="input">

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Task</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT TASK MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Task</h2>
            <span class="close-btn" onclick="closeEditModal()">✕</span>
        </div>

        <form method="post">
            <input type="hidden" name="edit_task" value="1">
            <input type="hidden" id="edit_id" name="id">

            <label>Task</label>
            <input type="text" id="edit_task" name="task" class="input" required>

            <label>Due Date</label>
            <input type="date" id="edit_due_date" name="due_date" class="input">

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="save-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// --------- MODALS ----------
function openAddModal() {
    const quick = document.getElementById('quickTaskInput').value.trim();
    document.getElementById('add_task').value = quick;
    document.getElementById('addModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(id, task, due_date) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_task').value = task;
    document.getElementById('edit_due_date').value = due_date || '';

    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// --------- ACTIONS ----------
function toggleStatus(id) {
    window.location = 'todo.php?toggle=' + id;
}

function deleteTask(id) {
    if (confirm('Delete this task?')) {
        window.location = 'todo.php?delete=' + id;
    }
}
</script>

</body>
</html>
