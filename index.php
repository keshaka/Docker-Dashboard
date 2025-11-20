<?php
$activePage = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Overview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
    <main class="main">
        <header class="main-header">
            <div class="main-title">
                <h1>Overview</h1>
                <p>Central hub to manage containers, images, volumes, networks and more on this VPS.</p>
            </div>
            <div>
                <span class="badge-pill">Mode: Self-hosted</span>
            </div>
        </header>

        <section class="page-section">
            <h2>Quick navigation</h2>
            <p>Select a section to manage different parts of your Docker environment.</p>

            <div class="card-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Containers</div>
                        <span class="card-chip">Core</span>
                    </div>
                    <div class="card-body">
                        List, filter and control containers (start, stop, restart, remove, logs, stats).
                    </div>
                    <div class="card-footer">
                        <a href="containers.php" class="card-link">Open Containers →</a>
                        <span class="card-meta">Like Docker Desktop containers tab.</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Images</div>
                        <span class="card-chip">Registry</span>
                    </div>
                    <div class="card-body">
                        View, pull, delete and build images directly from the web UI.
                    </div>
                    <div class="card-footer">
                        <a href="images.php" class="card-link">Open Images →</a>
                        <span class="card-meta">docker images, pull, rmi…</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Volumes</div>
                        <span class="card-chip">Data</span>
                    </div>
                    <div class="card-body">
                        Inspect persistent volumes, free up space and manage data containers.
                    </div>
                    <div class="card-footer">
                        <a href="volumes.php" class="card-link">Open Volumes →</a>
                        <span class="card-meta">docker volume ls…</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Networks</div>
                        <span class="card-chip">Connectivity</span>
                    </div>
                    <div class="card-body">
                        Manage Docker networks, see which containers are connected and adjust topology.
                    </div>
                    <div class="card-footer">
                        <a href="networks.php" class="card-link">Open Networks →</a>
                        <span class="card-meta">bridge, overlay, custom…</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Compose</div>
                        <span class="card-chip">Stacks</span>
                    </div>
                    <div class="card-body">
                        Launch and control app stacks using docker-compose files from the browser.
                    </div>
                    <div class="card-footer">
                        <a href="compose.php" class="card-link">Open Compose →</a>
                        <span class="card-meta">multi-container apps.</span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">System</div>
                        <span class="card-chip">Diagnostics</span>
                    </div>
                    <div class="card-body">
                        Global stats: CPU, memory, disk usage and Docker engine info at a glance.
                    </div>
                    <div class="card-footer">
                        <a href="system.php" class="card-link">Open System →</a>
                        <span class="card-meta">docker info, df…</span>
                    </div>
                </div>
            </div>

            <div class="page-note">
                Next steps: we’ll gradually implement each section (containers first, then images, volumes, networks…).
            </div>
        </section>
    </main>
</div> <!-- closes .app-shell from nav.php -->
</body>
</html>

