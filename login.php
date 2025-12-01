<?php
session_start();
require 'db_connect.php';

$note = "";

// Kung logged in na, dumiretso na sa dashboard
if (isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $email    = trim($_POST['email'] ?? "");
  $password = $_POST['password'] ?? "";

  if ($email === "" || $password === "") {
    $note = '<div class="msg error">Please enter email and password.</div>';
  } else {
    // Hanapin user by email
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
      // Success: set session, go to dashboard
      $_SESSION['user_id']  = $user['id'];
      $_SESSION['username'] = $user['username'];
      header("Location: index.php");
      exit;
    } else {
      $note = '<div class="msg error">Invalid email or password.</div>';
    }
    $stmt->close();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Login • FirstProject</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=14">
</head>
<body class="auth-page">
  <form class="auth-card" method="post" autocomplete="off">
    <h2>Login</h2>
    <?= $note ?>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <button type="submit">Login</button>

    <div class="small">No account? <a href="register.php">Register</a></div>
  </form>
</body>
</html>
