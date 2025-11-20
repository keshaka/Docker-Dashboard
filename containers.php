<?php
// containers.php – full containers dashboard

// ==== CONFIG ====
$enableActions = true; // set false for read-only mode

// ==== API HANDLER (JSON) ====
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    // OPTIONAL EXTRA SECURITY: only allow local access (commented out)
    /*
    if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    */

    // List containers
    if ($_GET['api'] === 'containers') {
        // -a = all containers, --size includes size info, JSON per line
        $cmd = "docker ps -a --size --format '{{json .}}' 2>/dev/null";
        $output = shell_exec($cmd);

        if ($output === null) {
            echo json_encode(['error' => 'Failed to run docker. Make sure PHP user can access docker.']);
            exit;
        }

        $lines = explode("\n", trim($output));
        $containers = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $obj = json_decode($line, true);
            if ($obj) $containers[] = $obj;
        }

        echo json_encode(['containers' => $containers]);
        exit;
    }

    // Actions on containers
    if ($_GET['api'] === 'action' && $enableActions) {
        $data   = json_decode(file_get_contents('php://input'), true);
        $id     = $data['id']     ?? null;
        $action = $data['action'] ?? null;

        if (!$id || !$action) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing id or action']);
            exit;
        }

        $allowedActions = [
            'start'   => 'docker start %s 2>&1',
            'stop'    => 'docker stop %s 2>&1',
            'restart' => 'docker restart %s 2>&1',
            'remove'  => 'docker rm %s 2>&1',
        ];

        if (!isset($allowedActions[$action])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
        }

        $cmd = sprintf($allowedActions[$action], escapeshellarg($id));
        $output = shell_exec($cmd);

        echo json_encode(['ok' => true, 'output' => $output]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown API endpoint']);
    exit;
}

// ==== PAGE RENDER ====
$activePage = 'containers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Containers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Containers page specific styling */

        .containers-header-note {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 3px;
        }

        .cont-summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .cont-chip {
            background: radial-gradient(circle at top left, rgba(56,189,248,0.08), transparent 60%),
                        rgba(15,23,42,0.98);
            border-radius: 999px;
            border: 1px solid rgba(30,64,175,0.7);
            padding: 6px 11px;
            font-size: 0.8rem;
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .cont-chip strong {
            color: var(--text);
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
        }

        .dot-running {
            background: #22c55e;
            box-shadow: 0 0 10px rgba(34,197,94,0.9);
        }

        .dot-stopped {
            background: #f97373;
            box-shadow: 0 0 10px rgba(248,113,113,0.9);
        }

        .cont-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .cont-search-wrap {
            flex: 1 1 260px;
            position: relative;
        }

        .cont-search-wrap input {
            width: 100%;
            padding: 8px 10px 8px 28px;
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--text);
            font-size: 0.85rem;
        }

        .cont-search-wrap span.icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.9rem;
        }

        .cont-select {
            background: rgba(15,23,42,0.96);
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            color: var(--muted);
            padding: 7px 11px;
            font-size: 0.8rem;
        }

        .cont-refresh-btn {
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(56,189,248,0.8);
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.82rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .cont-refresh-btn:disabled {
            opacity: .5;
            cursor: default;
        }

        .cont-table-wrap {
            overflow: auto;
            border-radius: var(--radius-md);
            border: 1px solid rgba(30,64,175,0.7);
            background: radial-gradient(circle at top left, rgba(30,64,175,0.3), transparent 55%),
                        rgba(15,23,42,0.98);
            max-height: 70vh;
        }

        table.cont-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .cont-table thead {
            position: sticky;
            top: 0;
            background: rgba(15,23,42,0.98);
            z-index: 2;
        }

        .cont-table th,
        .cont-table td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(31,41,55,0.9);
            text-align: left;
            white-space: nowrap;
        }

        .cont-table th {
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--muted);
        }

        .cont-table tbody tr:hover {
            background: rgba(30,64,175,0.45);
        }

        .status-pill {
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }

        .status-running {
            background: rgba(22,163,74,0.18);
            color: #4ade80;
            border: 1px solid rgba(22,163,74,0.55);
        }

        .status-exited {
            background: rgba(127,29,29,0.25);
            color: #fecaca;
            border: 1px solid rgba(127,29,29,0.6);
        }

        .status-other {
            background: rgba(30,64,175,0.35);
            color: #bfdbfe;
            border: 1px solid rgba(30,64,175,0.75);
        }

        .cont-badge {
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 0.7rem;
            background: rgba(15,23,42,0.95);
            border: 1px solid rgba(75,85,99,0.9);
            color: var(--muted);
        }

        .cont-tag {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 0.7rem;
            background: rgba(15,23,42,0.98);
            border: 1px solid rgba(55,65,81,0.9);
            color: #e5e7eb;
        }

        .logline {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.74rem;
            white-space: pre;
            color: var(--muted);
        }

        .cont-actions button {
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            font-size: 0.7rem;
            padding: 4px 9px;
            margin-right: 4px;
            cursor: pointer;
            color: var(--muted);
        }

        .cont-actions button.primary {
            border-color: rgba(56,189,248,0.8);
            color: var(--accent);
        }

        .cont-actions button.danger {
            border-color: rgba(248,113,113,0.7);
            color: #fecaca;
        }

        .cont-error {
            margin-bottom: 10px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.78rem;
            background: rgba(127,29,29,0.3);
            border: 1px solid rgba(248,113,113,0.9);
            color: #fecaca;
        }

        .cont-footer-note {
            margin-top: 10px;
            font-size: 0.75rem;
            color: var(--muted);
            opacity: .8;
        }

        @media (max-width: 800px) {
            .cont-table-wrap {
                max-height: 60vh;
            }
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>
    <main class="main">
        <header class="main-header">
            <div class="main-title">
                <h1>Containers</h1>
                <p>View and manage all Docker containers running on this VPS.</p>
                <div class="containers-header-note">
                    Actions call <code>docker ps/start/stop/restart/rm</code> under the hood.
                </div>
            </div>
            <button class="btn-ghost" onclick="location.href='index.php'">← Back to Overview</button>
        </header>

        <section class="page-section">
            <h2>Containers dashboard</h2>
            <p>Real-time overview of containers with filters, actions and auto-refresh.</p>

            <div id="contError" class="cont-error" style="display:none;"></div>

            <div class="cont-summary-row">
                <div class="cont-chip">
                    <span class="status-dot dot-running"></span>
                    Running: <strong id="runningCount">0</strong>
                </div>
                <div class="cont-chip">
                    <span class="status-dot dot-stopped"></span>
                    Stopped: <strong id="stoppedCount">0</strong>
                </div>
                <div class="cont-chip">
                    Total: <strong id="totalCount">0</strong>
                </div>
                <div class="cont-chip">
                    Filtered view: <strong id="filteredCount">0</strong>
                </div>
            </div>

            <div class="cont-toolbar">
                <div class="cont-search-wrap">
                    <span class="icon">🔍</span>
                    <input id="searchInput" type="text" placeholder="Filter by name, image, status, port, id…">
                </div>

                <select id="statusFilter" class="cont-select">
                    <option value="">All statuses</option>
                    <option value="running">Running only</option>
                    <option value="exited">Exited / stopped</option>
                </select>

                <select id="autoRefresh" class="cont-select">
                    <option value="5000">Auto refresh: 5s</option>
                    <option value="10000">Auto refresh: 10s</option>
                    <option value="30000" selected>Auto refresh: 30s</option>
                    <option value="0">Auto refresh: Off</option>
                </select>

                <button id="refreshBtn" class="cont-refresh-btn">
                    ⟳ Refresh now
                </button>
            </div>

            <div class="cont-table-wrap">
                <table class="cont-table">
                    <thead>
                    <tr>
                        <th>Container</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Ports</th>
                        <th>Uptime</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="containersBody">
                    <tr><td colspan="7">Loading containers…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="cont-footer-note">
                Security tip: protect this page (VPN / SSH tunnel / HTTP auth) before exposing it to the internet.
            </div>
        </section>
    </main>
</div> <!-- closes .app-shell from nav.php -->

<script>
    const containersBody = document.getElementById('containersBody');
    const runningCount   = document.getElementById('runningCount');
    const stoppedCount   = document.getElementById('stoppedCount');
    const totalCount     = document.getElementById('totalCount');
    const filteredCount  = document.getElementById('filteredCount');
    const contError      = document.getElementById('contError');
    const searchInput    = document.getElementById('searchInput');
    const statusFilter   = document.getElementById('statusFilter');
    const autoRefresh    = document.getElementById('autoRefresh');
    const refreshBtn     = document.getElementById('refreshBtn');

    let containers = [];
    let timer = null;

    function setError(msg) {
        if (!msg) {
            contError.style.display = 'none';
            contError.textContent = '';
        } else {
            contError.style.display = 'block';
            contError.textContent = msg;
        }
    }

    async function fetchContainers() {
        setError('');
        refreshBtn.disabled = true;
        refreshBtn.textContent = '⟳ Refreshing…';

        try {
            const res = await fetch('containers.php?api=containers');
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            const data = await res.json();
            if (data.error) {
                throw new Error(data.error);
            }
            containers = data.containers || [];
            renderContainers();
        } catch (e) {
            setError('Failed to load containers: ' + e.message + ' (Is Docker accessible from PHP?)');
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '⟳ Refresh now';
        }
    }

    function normalizeStatus(status) {
        const s = (status || '').toLowerCase();
        if (s.startsWith('up')) return 'running';
        if (s.startsWith('exited')) return 'exited';
        return 'other';
    }

    function renderContainers() {
        const query = (searchInput.value || '').toLowerCase();
        const filterStatus = statusFilter.value;

        let running = 0, stopped = 0;
        containers.forEach(c => {
            const st = normalizeStatus(c.Status);
            if (st === 'running') running++;
            else if (st === 'exited') stopped++;
        });

        runningCount.textContent = running;
        stoppedCount.textContent = stopped;
        totalCount.textContent = containers.length;

        const filtered = containers.filter(c => {
            const st = normalizeStatus(c.Status);
            if (filterStatus && st !== filterStatus) return false;

            if (!query) return true;

            const haystack = [
                c.Names, c.Image, c.Status, c.Ports, c.ID
            ].join(' ').toLowerCase();

            return haystack.includes(query);
        });

        filteredCount.textContent = filtered.length;

        if (!filtered.length) {
            containersBody.innerHTML = '<tr><td colspan="7">No containers match your filters.</td></tr>';
            return;
        }

        containersBody.innerHTML = '';

        filtered.forEach(c => {
            const tr = document.createElement('tr');
            const stKind = normalizeStatus(c.Status);

            let pillClass = 'status-other';
            let pillLabel = 'Other';
            if (stKind === 'running') {
                pillClass = 'status-running';
                pillLabel = 'Running';
            } else if (stKind === 'exited') {
                pillClass = 'status-exited';
                pillLabel = 'Exited';
            }

            tr.innerHTML = `
                <td>
                    <div style="display:flex;flex-direction:column;gap:3px;">
                        <span style="font-weight:500;">${c.Names}</span>
                        <span class="cont-badge">ID: ${c.ID}</span>
                    </div>
                </td>
                <td><span class="cont-tag">${c.Image}</span></td>
                <td>
                    <span class="status-pill ${pillClass}">
                        <span class="status-dot ${stKind === 'running' ? 'dot-running' : 'dot-stopped'}"></span>
                        ${pillLabel}
                    </span>
                    <div class="logline">${c.Status}</div>
                </td>
                <td><span class="logline">${c.Ports || '—'}</span></td>
                <td><span class="logline">${c.RunningFor || '—'}</span></td>
                <td><span class="cont-badge">${c.Size || '—'}</span></td>
                <td class="cont-actions">
                    <?php if ($enableActions): ?>
                        ${renderActionButtonsTemplate()}
                    <?php else: ?>
                        <span class="logline" style="opacity:0.75;">Actions disabled (read-only mode).</span>
                    <?php endif; ?>
                </td>
            `;

            <?php if ($enableActions): ?>
            const actionsCell = tr.querySelector('.cont-actions');
            const btnStart   = actionsCell.querySelector('[data-action="start"]');
            const btnStop    = actionsCell.querySelector('[data-action="stop"]');
            const btnRestart = actionsCell.querySelector('[data-action="restart"]');
            const btnRemove  = actionsCell.querySelector('[data-action="remove"]');

            const isRunning = stKind === 'running';
            btnStart.disabled   = isRunning;
            btnStop.disabled    = !isRunning;
            btnRestart.disabled = !isRunning;

            [btnStart, btnStop, btnRestart, btnRemove].forEach(btn => {
                btn.addEventListener('click', () => doAction(c.ID, c.Names, btn.dataset.action));
            });
            <?php endif; ?>

            containersBody.appendChild(tr);
        });
    }

    <?php if ($enableActions): ?>
    function renderActionButtonsTemplate() {
        return `
            <button class="primary" data-action="start">Start</button>
            <button data-action="stop">Stop</button>
            <button data-action="restart">Restart</button>
            <button class="danger" data-action="remove">Remove</button>
        `;
    }

    async function doAction(id, name, action) {
        let confirmText = '';
        if (action === 'remove') {
            confirmText = `Remove container "${name}" (${id})? This cannot be undone.`;
        } else if (action === 'stop') {
            confirmText = `Stop container "${name}"?`;
        } else if (action === 'restart') {
            confirmText = `Restart container "${name}"?`;
        } else if (action === 'start') {
            confirmText = `Start container "${name}"?`;
        }

        if (confirmText && !confirm(confirmText)) return;

        setError('');
        refreshBtn.disabled = true;
        refreshBtn.textContent = 'Running action…';

        try {
            const res = await fetch('containers.php?api=action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, action })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            await fetchContainers();
        } catch (e) {
            setError(`Action failed (${action} on ${name}): ${e.message}`);
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '⟳ Refresh now';
        }
    }
    <?php else: ?>
    function renderActionButtonsTemplate() { return ''; }
    <?php endif; ?>

    function setupAutoRefresh() {
        if (timer) clearInterval(timer);
        const ms = parseInt(autoRefresh.value, 10);
        if (ms > 0) {
            timer = setInterval(fetchContainers, ms);
        }
    }

    refreshBtn.addEventListener('click', fetchContainers);
    searchInput.addEventListener('input', renderContainers);
    statusFilter.addEventListener('change', renderContainers);
    autoRefresh.addEventListener('change', setupAutoRefresh);

    fetchContainers();
    setupAutoRefresh();
</script>
</body>
</html>
