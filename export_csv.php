<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=tracksmart-data.csv');

$output = fopen('php://output', 'w');

// header row
fputcsv($output, ['Date', 'Type', 'Description', 'Category', 'Amount', 'Notes']);

$stmt = $conn->prepare("
    SELECT date, type, description, category, amount, notes
    FROM transactions
    WHERE user_id = ?
    ORDER BY date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit;
