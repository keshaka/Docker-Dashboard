<?php
// index.php – New Overview dashboard
// Shows: Docker version, system usage, Docker usage, About

function run_cmd($cmd) {
    return shell_exec($cmd . ' 2>&1');
}

// ---- Docker version (parsed) ----
$dockerVersionRaw = run_cmd("docker version --format '{{json .}}'");
$dockerVersionArr = json_decode($dockerVersionRaw, true);
$dockerEngineVersion = 'Unknown';
$dockerApiVersion    = 'Unknown';

if (is_array($dockerVersionArr) && isset($dockerVersionArr['Server'])) {
    $dockerEngineVersion = $dockerVersionArr['Server']['Version'] ?? 'Unknown';
    $dockerApiVersion    = $dockerVersionArr['Server']['ApiVersion'] ?? 'Unknown';
} else {
    // fallback: plain text
    $dockerVersionText = trim(run_cmd("docker version"));
}

// ---- Docker info (counts) ----
$dockerInfoRaw = run_cmd("docker info --format '{{json .}}'");
$dockerInfoArr = json_decode($dockerInfoRaw, true);

$containersTotal   = $dockerInfoArr['Containers']        ?? 0;
$containersRunning = $dockerInfoArr['ContainersRunning'] ?? 0;
$containersPaused  = $dockerInfoArr['ContainersPaused']  ?? 0;
$containersStopped = $dockerInfoArr['ContainersStopped'] ?? 0;
$imagesCount       = $dockerInfoArr['Images']            ?? 0;

// ---- Docker disk usage (system df) ----
$dockerDfJsonLines = run_cmd("docker system df --format '{{json .}}'");
$dockerDfSummary   = [
    'Images'     => null,
    'Containers' => null,
    'Local Volumes' => null,
    'Build Cache'   => null,
];

if (trim($dockerDfJsonLines) !== '') {
    $lines = explode("\n", trim($dockerDfJsonLines));
    foreach ($lines as $line) {
        $o = json_decode($line, true);
        if (!$o || !isset($o['Type'])) continue;
        $type = $o['Type'];
        $dockerDfSummary[$type] = [
            'TotalCount'  => $o['TotalCount']  ?? null,
            'Active'      => $o['Active']      ?? null,
            'Size'        => $o['Size']        ?? null,
            'Reclaimable' => $o['Reclaimable'] ?? null,
        ];
    }
} else {
    // fallback to human text
    $dockerDfText = trim(run_cmd("docker system df"));
}

// ---- Host system usage ----
$cpuCores = trim(run_cmd("nproc")) ?: 'N/A';
$loadAvg  = trim(run_cmd("cat /proc/loadavg")) ?: 'N/A';
$memInfo  = run_cmd("free -h") ?: '';
$diskInfo = run_cmd("df -h /") ?: '';

$hostname   = gethostname();
$phpVersion = PHP_VERSION;
$osString   = php_uname('s') . ' ' . php_uname('r');

$activePage = 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Overview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .ov-card {
            background: radial-gradient(circle at top left, rgba(56,189,248,0.18), transparent 60%),
                        rgba(15,23,42,0.98);
            border-radius: 18px;
            border: 1px solid rgba(56,189,248,0.45);
            padding: 16px;
        }
        .ov-card h3 {
            margin-top: 0;
            margin-bottom: 6px;
        }
        .ov-metric {
            font-size: 1.6rem;
            font-weight: 600;
        }
        .ov-sub {
            font-size: 0.8rem;
            color: var(--muted);
        }
        .ov-tag {
            display: inline-block;
            margin-top: 6px;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 0.72rem;
            background: rgba(15,23,42,0.98);
            border: 1px solid rgba(55,65,81,0.9);
            color: #e5e7eb;
        }
        pre.ov-pre {
            background: #020617;
            border-radius: 12px;
            border: 1px solid #1e293b;
            padding: 10px 12px;
            font-size: 0.78rem;
            color: #cbd5e1;
            overflow-x: auto;
            white-space: pre-wrap;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<?php include 'nav.php'; ?>

    <main class="main">
        <header class="main-header">
            <div class="main-title">
                <h1>Overview</h1>
                <p>Quick glance at Docker version, host usage and Docker disk usage.</p>
            </div>
        </header>

        <section class="page-section">
            <h2>Docker & host summary</h2>

            <div class="overview-grid">
                <!-- Docker version card -->
                <div class="ov-card">
                    <h3>Docker version</h3>
                    <div class="ov-metric">
                        <?php echo htmlspecialchars($dockerEngineVersion); ?>
                    </div>
                    <div class="ov-sub">
                        Engine API: <?php echo htmlspecialchars($dockerApiVersion); ?>
                    </div>
                    <?php if (!empty($dockerVersionText ?? '')): ?>
                        <span class="ov-tag">Using plain-text version output</span>
                    <?php else: ?>
                        <span class="ov-tag">Parsed from <code>docker version --format json</code></span>
                    <?php endif; ?>
                </div>

                <!-- Containers card -->
                <div class="ov-card">
                    <h3>Containers</h3>
                    <div class="ov-metric">
                        <?php echo (int)$containersRunning; ?> / <?php echo (int)$containersTotal; ?>
                    </div>
                    <div class="ov-sub">
                        Running / Total
                    </div>
                    <div class="ov-sub" style="margin-top:6px;">
                        Paused: <?php echo (int)$containersPaused; ?> · Stopped: <?php echo (int)$containersStopped; ?>
                    </div>
                    <span class="ov-tag">Source: <code>docker info</code></span>
                </div>

                <!-- Images card -->
                <div class="ov-card">
                    <h3>Images</h3>
                    <div class="ov-metric">
                        <?php echo (int)$imagesCount; ?>
                    </div>
                    <div class="ov-sub">Total local images</div>
                    <span class="ov-tag">Source: <code>docker info</code></span>
                </div>

                <!-- Host CPU / load -->
                <div class="ov-card">
                    <h3>Host CPU</h3>
                    <div class="ov-metric">
                        <?php echo htmlspecialchars($cpuCores); ?>
                    </div>
                    <div class="ov-sub">CPU cores</div>
                    <div class="ov-sub" style="margin-top:6px;">
                        Load average: <?php echo htmlspecialchars($loadAvg); ?>
                    </div>
                    <span class="ov-tag">Source: <code>nproc</code>, <code>/proc/loadavg</code></span>
                </div>
            </div>
        </section>

        <section class="page-section">
            <h2>System usage</h2>
            <p>Memory and root filesystem usage on this VPS.</p>

            <div class="overview-grid">
                <div class="ov-card">
                    <h3>Memory (free -h)</h3>
                    <pre class="ov-pre"><?php echo htmlspecialchars($memInfo ?: 'N/A'); ?></pre>
                </div>
                <div class="ov-card">
                    <h3>Disk (/)</h3>
                    <pre class="ov-pre"><?php echo htmlspecialchars($diskInfo ?: 'N/A'); ?></pre>
                </div>
            </div>
        </section>

        <section class="page-section">
            <h2>Docker usage</h2>
            <p>Space used by Docker objects on this host.</p>

            <div class="overview-grid">
                <?php if (!empty($dockerDfText ?? '')): ?>
                    <div class="ov-card">
                        <h3>docker system df</h3>
                        <pre class="ov-pre"><?php echo htmlspecialchars($dockerDfText); ?></pre>
                    </div>
                <?php else: ?>
                    <?php foreach ($dockerDfSummary as $type => $info): ?>
                        <?php if (!$info) continue; ?>
                        <div class="ov-card">
                            <h3><?php echo htmlspecialchars($type); ?></h3>
                            <div class="ov-metric">
                                <?php echo htmlspecialchars($info['Size'] ?? 'N/A'); ?>
                            </div>
                            <div class="ov-sub">
                                Total: <?php echo htmlspecialchars($info['TotalCount'] ?? 'N/A'); ?> ·
                                Active: <?php echo htmlspecialchars($info['Active'] ?? 'N/A'); ?>
                            </div>
                            <div class="ov-sub" style="margin-top:6px;">
                                Reclaimable: <?php echo htmlspecialchars($info['Reclaimable'] ?? 'N/A'); ?>
                            </div>
                            <span class="ov-tag">Source: <code>docker system df --format json</code></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="page-section">
            <h2>About this dashboard</h2>

            <div class="overview-grid">
                <div class="ov-card">
                    <h3>Instance</h3>
                    <p><strong>Hostname:</strong> <?php echo htmlspecialchars($hostname); ?></p>
                    <p><strong>OS:</strong> <?php echo htmlspecialchars($osString); ?></p>
                    <p><strong>PHP:</strong> <?php echo htmlspecialchars($phpVersion); ?></p>
                    <span class="ov-tag">Docker VPS UI – Self-hosted</span>
                </div>

                <div class="ov-card">
                    <h3>What you can do</h3>
                    <p style="font-size:0.85rem; color:var(--muted);">
                        Use the left navigation to manage containers, images, volumes,
                        networks, compose stacks and system stats. This overview is
                        read-only and designed to give you a quick health snapshot.
                    </p>
                </div>
            </div>
        </section>
    </main>
</div> <!-- closes .app-shell from nav.php -->

</body>
</html>
