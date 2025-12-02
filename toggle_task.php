<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("SESSION MISSING");
}

$id = $_POST['id'];
$is_done = $_POST['is_done'];

$conn->query("UPDATE todos SET is_done=$is_done WHERE id=$id");

echo "ok";
