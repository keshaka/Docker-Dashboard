<?php
// nav.php - include on every page
if (!isset($activePage)) {
    $activePage = '';
}
?>
<div class="app-shell">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <div class="brand-logo">🐳</div>
                <div>
                    <div class="brand-name">Docker VPS UI</div>
                    <div class="brand-sub">Self-hosted dashboard</div>
                </div>
            </div>

            <div class="nav-section-label">Main</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo $activePage === 'home' ? 'active' : ''; ?>">
                        <span class="icon">🏠</span>
                        <span>Overview</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="containers.php" class="nav-link <?php echo $activePage === 'containers' ? 'active' : ''; ?>">
                        <span class="icon">📦</span>
                        <span>Containers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="images.php" class="nav-link <?php echo $activePage === 'images' ? 'active' : ''; ?>">
                        <span class="icon">🖼️</span>
                        <span>Images</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="volumes.php" class="nav-link <?php echo $activePage === 'volumes' ? 'active' : ''; ?>">
                        <span class="icon">💾</span>
                        <span>Volumes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="networks.php" class="nav-link <?php echo $activePage === 'networks' ? 'active' : ''; ?>">
                        <span class="icon">🌐</span>
                        <span>Networks</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="compose.php" class="nav-link <?php echo $activePage === 'compose' ? 'active' : ''; ?>">
                        <span class="icon">📜</span>
                        <span>Compose</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="system.php" class="nav-link <?php echo $activePage === 'system' ? 'active' : ''; ?>">
                        <span class="icon">📊</span>
                        <span>System</span>
                    </a>
                </li>
            </ul>

            <div class="nav-section-label">Other</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?php echo $activePage === 'settings' ? 'active' : ''; ?>">
                        <span class="icon">⚙️</span>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-footer">
            <div>Secured access recommended (VPN / SSH tunnel).</div>
        </div>
    </aside>

