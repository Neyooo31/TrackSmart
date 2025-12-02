<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$transactions = $conn->query("
    SELECT date, type, description, category, amount, notes
    FROM transactions
    WHERE user_id = $user_id
    ORDER BY date ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TrackSmart – Printable Report</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h1 { margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px 10px; font-size: 13px; }
        th { background: #f3f2f7; text-align: left; }
    </style>
</head>
<body>
<h1>TrackSmart Transactions Report</h1>
<p>Print this page and choose "Save as PDF" if you want a PDF file.</p>

<table>
    <thead>
    <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Description</th>
        <th>Category</th>
        <th>Amount</th>
        <th>Notes</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($t = $transactions->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($t['date']) ?></td>
            <td><?= htmlspecialchars(ucfirst($t['type'])) ?></td>
            <td><?= htmlspecialchars($t['description']) ?></td>
            <td><?= htmlspecialchars($t['category']) ?></td>
            <td>₱<?= number_format($t['amount'], 2) ?></td>
            <td><?= htmlspecialchars($t['notes']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
