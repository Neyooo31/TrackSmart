<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("SESSION MISSING");
}

$id = $_POST['id'];
$task = $_POST['task'];
// $due = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

$stmt = $conn->prepare("UPDATE todos SET task=? WHERE id=?");
$stmt->bind_param("si", $task, $id);
$stmt->execute();

echo "ok";
?>