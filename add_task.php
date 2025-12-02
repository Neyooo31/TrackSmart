<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("SESSION MISSING");
}

// $user_id = $_SESSION['user_id'];
// $task = trim($_POST['task']);
// $due = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

// // Use correct SQL
// $stmt = $conn->prepare("
//     INSERT INTO todos (user_id, task, due_date) 
//     VALUES (?, ?, ?)
// ");

// // If due_date is NULL → bind_param needs "s" type, still OK
// $stmt->bind_param("iss", $user_id, $task, $due);

// if (!$stmt->execute()) {
//     die("SQL ERROR: " . $stmt->error);
// }

// echo "ok";

$user_id = $_SESSION['user_id'];
$task = $_POST['task'];
$due = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$category = !empty($_POST['category']) ? $_POST['category'] : null;

$stmt = $conn->prepare("
    INSERT INTO todos (user_id, task, due_date, category) 
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("isss", $user_id, $task, $due, $category);
$stmt->execute();

echo "ok";
?>