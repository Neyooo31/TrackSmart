<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$note = "";

// ===========================
// SAVE SETTINGS
// ===========================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_settings'])) {

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $currency  = $_POST['currency'] ?? 'PHP';
    $timezone  = $_POST['timezone'] ?? 'Asia/Manila';
    $theme     = $_POST['theme'] ?? 'light';

    $notify_budget_alerts        = isset($_POST['notify_budget_alerts']) ? 1 : 0;
    $notify_transaction_reminders = isset($_POST['notify_transaction_reminders']) ? 1 : 0;
    $notify_monthly_reports      = isset($_POST['notify_monthly_reports']) ? 1 : 0;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $note = "<div class='msg error'>Please enter a valid email address.</div>";
    } else {
        $stmt = $conn->prepare("
            UPDATE users
            SET full_name = ?, email = ?, currency = ?, timezone = ?, theme = ?,
                notify_budget_alerts = ?, notify_transaction_reminders = ?, notify_monthly_reports = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssssssiii",
            $full_name,
            $email,
            $currency,
            $timezone,
            $theme,
            $notify_budget_alerts,
            $notify_transaction_reminders,
            $notify_monthly_reports,
            $user_id
        );

        $stmt->execute();
        $stmt->close();

        // 🔥 UPDATE SESSION THEME IMMEDIATELY
        $_SESSION['theme'] = $theme;

        $note = "<div class='msg success'>Settings saved successfully.</div>";
    }
}

// ===========================
// LOAD CURRENT SETTINGS
// ===========================
$stmt = $conn->prepare("
    SELECT username, full_name, email, currency, timezone, theme,
           notify_budget_alerts, notify_transaction_reminders, notify_monthly_reports
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result   = $stmt->get_result();
$settings = $result->fetch_assoc();
$stmt->close();

$full_name = $settings['full_name'] ?: $settings['username'];
$email     = $settings['email'];
$currency  = $settings['currency'] ?: 'PHP';
$timezone  = $settings['timezone'] ?: 'Asia/Manila';
$theme     = $settings['theme'] ?: 'light'; // load DB theme
$_SESSION['theme'] = $theme; // sync session

$notify_budget_alerts        = (int)$settings['notify_budget_alerts'];
$notify_transaction_reminders = (int)$settings['notify_transaction_reminders'];
$notify_monthly_reports      = (int)$settings['notify_monthly_reports'];

$currencyOptions = [
    'PHP' => 'Philippine Peso (₱)',
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)',
];

$timezoneOptions = [
    'Asia/Manila'        => 'Asia/Manila (GMT+8)',
    'America/New_York'   => 'America/New York (GMT-5)',
    'Europe/London'      => 'Europe/London (GMT+0)',
    'Asia/Tokyo'         => 'Asia/Tokyo (GMT+9)',
];

$themeOptions = [
    'light'  => 'Light',
    'dark'   => 'Dark',
    'system' => 'Auto (System)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings • TrackSmart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css?v=25">
</head>

<!-- 🔥 APPLY THEME HERE -->
<body class="<?= htmlspecialchars($theme) ?>">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="settings-wrapper">

        <div class="settings-header">
            <div class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">☰</div>
            <div>
                <h1>Settings</h1>
                <p class="subtext">Welcome back!</p>
            </div>
        </div>

        <?= $note ?>

        <form method="POST" class="settings-form">

            <div class="settings-card">
                <h2 class="settings-card-title">Profile Settings</h2>

                <label class="settings-label">Full Name</label>
                <input type="text" name="full_name" class="input"
                       value="<?= htmlspecialchars($full_name) ?>" required>

                <label class="settings-label">Email</label>
                <input type="email" name="email" class="input"
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="settings-card">
                <h2 class="settings-card-title">Regional Settings</h2>

                <label class="settings-label">Currency</label>
                <select name="currency" class="input">
                    <?php foreach ($currencyOptions as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $code === $currency ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="settings-label">Timezone</label>
                <select name="timezone" class="input">
                    <?php foreach ($timezoneOptions as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $code === $timezone ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="settings-card">
                <h2 class="settings-card-title">Appearance</h2>

                <label class="settings-label">Theme</label>
                <select name="theme" class="input">
                    <?php foreach ($themeOptions as $code => $label): ?>
                        <option value="<?= $code ?>" <?= $code === $theme ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="settings-card">
                <h2 class="settings-card-title">Notifications</h2>

                <label class="settings-checkbox">
                    <input type="checkbox" name="notify_budget_alerts" value="1"
                        <?= $notify_budget_alerts ? 'checked' : '' ?>>
                    <span>Budget alerts</span>
                </label>

                <label class="settings-checkbox">
                    <input type="checkbox" name="notify_transaction_reminders" value="1"
                        <?= $notify_transaction_reminders ? 'checked' : '' ?>>
                    <span>Transaction reminders</span>
                </label>

                <label class="settings-checkbox">
                    <input type="checkbox" name="notify_monthly_reports" value="1"
                        <?= $notify_monthly_reports ? 'checked' : '' ?>>
                    <span>Monthly reports</span>
                </label>
            </div>

            <div class="settings-card">
                <h2 class="settings-card-title">Data Management</h2>
                <p class="settings-help">Export your financial data for backup or analysis.</p>

                <div class="data-actions">
                    <a href="export_csv.php" class="small-btn primary">Export as CSV</a>
                    <a href="export_print.php" target="_blank" class="small-btn">Export as PDF</a>
                </div>
            </div>

            <div class="settings-actions">
                <button type="button" class="cancel-btn" onclick="window.location='index.php'">Cancel</button>

                <button type="submit" name="save_settings" class="save-btn large">Save Changes</button>
            </div>

        </form>
    </div>
</div>

</body>
</html>
