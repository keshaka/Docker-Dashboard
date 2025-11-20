<?php
// settings.php – Dashboard settings page

session_start();

// --- CONFIG ---
$SETTINGS_FILE = "/var/www/html/dashboard-settings.json"; // persistent settings

// Load saved settings
$saved = [
    "password_enabled" => false,
    "password_hash" => null
];
if (file_exists($SETTINGS_FILE)) {
    $saved = json_decode(file_get_contents($SETTINGS_FILE), true);
}

// Handle password form:
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["set_password"])) {
    $newPass = trim($_POST["new_password"] ?? "");
    if ($newPass !== "") {
        $saved["password_enabled"] = true;
        $saved["password_hash"] = password_hash($newPass, PASSWORD_DEFAULT);
        file_put_contents($SETTINGS_FILE, json_encode($saved));
        $msg = "Password updated!";
    } else {
        $msg = "Password cannot be empty.";
    }
}

// Handle disabling protection:
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["disable_password"])) {
    $saved["password_enabled"] = false;
    $saved["password_hash"] = null;
    file_put_contents($SETTINGS_FILE, json_encode($saved));
    $msg = "Dashboard authentication disabled.";
}

$activePage = "settings";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Docker VPS UI – Settings</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/style.css">

<style>
.settings-card {
    background: radial-gradient(circle at top left, rgba(56,189,248,0.18), transparent 55%),
                rgba(15,23,42,0.98);
    padding: 18px;
    border-radius: 18px;
    border: 1px solid rgba(56,189,248,0.4);
    margin-bottom: 16px;
}

.settings-card h3 {
    margin-top: 0;
}

.settings-input {
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid rgba(75,85,99,0.7);
    background: rgba(15,23,42,0.9);
    color: #e5e7eb;
    width: 260px;
}

.settings-btn {
    padding: 8px 14px;
    border-radius: 999px;
    background: var(--accent-soft);
    border: 1px solid rgba(56,189,248,0.7);
    color: var(--accent);
    cursor: pointer;
    margin-top: 6px;
}

.settings-btn.danger {
    border-color: rgba(248,113,113,0.7);
    color: #fecaca;
}

.settings-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
}

.message-bar {
    background: rgba(22,163,74,0.25);
    color: #4ade80;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid rgba(22,163,74,0.5);
    margin-bottom: 12px;
}
</style>
</head>
<body>

<?php include "nav.php"; ?>

<main class="main">
<header class="main-header">
    <div class="main-title">
        <h1>Settings</h1>
        <p>Customize the behavior and security of the Docker VPS UI.</p>
    </div>
</header>

<section class="page-section">

<?php if (isset($msg)): ?>
<div class="message-bar"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- THEME / UI -->
<div class="settings-card">
    <h3>Appearance & UI</h3>
    <div class="settings-toggle">
        <label><strong>Theme:</strong></label>
        <select id="themeSelect" class="settings-input" style="width:150px;">
            <option value="dark">Dark (default)</option>
            <option value="light">Light</option>
        </select>
    </div>
    <p style="font-size:0.8rem; color:var(--muted); margin-top:6px;">
        Theme is client-side only and stored in your browser.
    </p>

    <button class="settings-btn secondary" onclick="resetLayout()">Reset dashboard layout</button>
</div>

<!-- SECURITY -->
<div class="settings-card">
    <h3>Security</h3>

    <?php if ($saved["password_enabled"]): ?>
        <p style="color:#4ade80;">🔒 Password protection is enabled</p>
    <?php else: ?>
        <p style="color:#fca5a5;">🔓 Dashboard is not password protected</p>
    <?php endif; ?>

    <form method="POST" style="margin-top:10px;">
        <label><strong>Set / Change Password</strong></label><br>
        <input class="settings-input" type="password" name="new_password" placeholder="New password">
        <br>
        <button class="settings-btn" name="set_password">Save Password</button>
    </form>

    <form method="POST" style="margin-top:10px;">
        <button class="settings-btn danger" name="disable_password">Disable Password</button>
    </form>
</div>

<!-- ABOUT -->
<div class="settings-card">
    <h3>About</h3>
    <p><strong>Hostname:</strong> <?php echo htmlspecialchars(gethostname()); ?></p>
    <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
    <p><strong>Dashboard Version:</strong> 1.0.0</p>

    <p style="font-size:0.8rem; color:var(--muted); margin-top:10px;">
        Docker VPS UI is a self-hosted dashboard to manage containers, images, volumes, networks, and stacks.
    </p>
</div>

</section>
</main>

<script>
// ------------------- THEME SWITCH -------------------
document.getElementById("themeSelect").value =
    localStorage.getItem("theme") || "dark";

document.getElementById("themeSelect").addEventListener("change", (e) => {
    localStorage.setItem("theme", e.target.value);
    alert("Theme saved. (A full theme system can be implemented later)");
});

// ------------------- RESET LAYOUT -------------------
function resetLayout() {
    if (confirm("Reset UI layout & saved filters?")) {
        localStorage.clear();
        alert("Layout reset!");
    }
}
</script>

</body>
</html>
