<?php
session_start();
include 'db_connect.php';

$id = $_POST['id'];
$conn->query("DELETE FROM todos WHERE id=$id");

echo "ok";
