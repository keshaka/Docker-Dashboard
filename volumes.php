<?php
// volumes.php – Docker Volumes dashboard

// ==== CONFIG ====
$enableActions = true; // set false for read-only mode

// ==== API HANDLER ====
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    // OPTIONAL IP restriction (commented out)
    /*
    if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    */

    // List volumes
    if ($_GET['api'] === 'volumes') {
    $cmd = "docker volume ls --format '{{json .}}' 2>/dev/null";
    $output = shell_exec($cmd);

    // If docker didn’t return anything, just treat it as "no volumes"
    if ($output === null || trim($output) === '') {
        echo json_encode(['volumes' => []]);
        exit;
    }

    $lines   = explode("\n", trim($output));
    $volumes = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $obj = json_decode($line, true);
        if ($obj) $volumes[] = $obj;
    }

    echo json_encode(['volumes' => $volumes]);
    exit;
}


    // Inspect volume (single)
    if ($_GET['api'] === 'inspect') {
        $name = $_GET['name'] ?? null;
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing volume name']);
            exit;
        }

        $cmd = sprintf("docker volume inspect %s --format '{{json .}}' 2>/dev/null", escapeshellarg($name));
        $output = shell_exec($cmd);

        if ($output === null || trim($output) === '') {
            http_response_code(404);
            echo json_encode(['error' => 'Volume not found or inspect failed']);
            exit;
        }

        $obj = json_decode(trim($output), true);
        echo json_encode(['inspect' => $obj]);
        exit;
    }

    // Actions: create / remove / prune
    if ($_GET['api'] === 'action' && $enableActions) {
        $data   = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? null;

        if ($action === 'create') {
            $name   = $data['name'] ?? null;
            $driver = $data['driver'] ?? '';

            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing volume name']);
                exit;
            }

            // docker volume create [--driver foo] name
            $cmd = 'docker volume create ';
            if ($driver !== '') {
                $cmd .= '--driver ' . escapeshellarg($driver) . ' ';
            }
            $cmd .= escapeshellarg($name) . ' 2>&1';

            $output = shell_exec($cmd);
            echo json_encode(['ok' => true, 'output' => $output]);
            exit;
        }

        if ($action === 'remove') {
            $name = $data['name'] ?? null;
            if (!$name) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing volume name']);
                exit;
            }

            $cmd = sprintf('docker volume rm %s 2>&1', escapeshellarg($name));
            $output = shell_exec($cmd);
            echo json_encode(['ok' => true, 'output' => $output]);
            exit;
        }

        if ($action === 'prune') {
            $cmd = 'docker volume prune -f 2>&1';
            $output = shell_exec($cmd);
            echo json_encode(['ok' => true, 'output' => $output]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown API endpoint']);
    exit;
}

// ==== PAGE RENDER ====
$activePage = 'volumes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Volumes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Volumes page specific */

        .vol-header-note {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 3px;
        }

        .vol-summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .vol-chip {
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
        .vol-chip strong {
            color: var(--text);
        }

        .vol-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .vol-search-wrap {
            flex: 1 1 260px;
            position: relative;
        }

        .vol-search-wrap input {
            width: 100%;
            padding: 8px 10px 8px 28px;
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--text);
            font-size: 0.85rem;
        }

        .vol-search-wrap span.icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.9rem;
        }

        .vol-select {
            background: rgba(15,23,42,0.96);
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            color: var(--muted);
            padding: 7px 11px;
            font-size: 0.8rem;
        }

        .vol-btn {
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(56,189,248,0.8);
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .vol-btn.secondary {
            border-color: rgba(75,85,99,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--muted);
        }
        .vol-btn.danger {
            border-color: rgba(248,113,113,0.8);
            color: #fecaca;
        }
        .vol-btn:disabled {
            opacity: 0.5;
            cursor: default;
        }

        .vol-create-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
            flex: 1 1 260px;
        }

        .vol-input {
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--text);
            font-size: 0.8rem;
        }

        .vol-input.name {
            flex: 1 1 180px;
        }
        .vol-input.driver {
            width: 140px;
        }

        .vol-table-wrap {
            overflow: auto;
            border-radius: var(--radius-md);
            border: 1px solid rgba(30,64,175,0.7);
            background: radial-gradient(circle at top left, rgba(30,64,175,0.3), transparent 55%),
                        rgba(15,23,42,0.98);
            max-height: 70vh;
        }

        table.vol-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .vol-table thead {
            position: sticky;
            top: 0;
            background: rgba(15,23,42,0.98);
            z-index: 2;
        }

        .vol-table th,
        .vol-table td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(31,41,55,0.9);
            text-align: left;
            white-space: nowrap;
        }

        .vol-table th {
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--muted);
        }

        .vol-table tbody tr:hover {
            background: rgba(30,64,175,0.45);
        }

        .vol-tag {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 0.7rem;
            background: rgba(15,23,42,0.98);
            border: 1px solid rgba(55,65,81,0.9);
            color: #e5e7eb;
        }

        .vol-badge {
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 0.7rem;
            background: rgba(15,23,42,0.95);
            border: 1px solid rgba(75,85,99,0.9);
            color: var(--muted);
        }

        .vol-error {
            margin-bottom: 10px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.78rem;
            background: rgba(127,29,29,0.3);
            border: 1px solid rgba(248,113,113,0.9);
            color: #fecaca;
        }

        .vol-actions button {
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            font-size: 0.7rem;
            padding: 4px 9px;
            margin-right: 4px;
            cursor: pointer;
            color: var(--muted);
        }

        .vol-actions button.danger {
            border-color: rgba(248,113,113,0.8);
            color: #fecaca;
        }

        .vol-footer-note {
            margin-top: 10px;
            font-size: 0.75rem;
            color: var(--muted);
            opacity: .8;
        }

        @media (max-width: 800px) {
            .vol-table-wrap {
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
                <h1>Volumes</h1>
                <p>Inspect and manage Docker volumes for persistent data on this VPS.</p>
                <div class="vol-header-note">
                    Uses <code>docker volume ls/create/rm/prune</code> behind the scenes.
                </div>
            </div>
            <button class="btn-ghost" onclick="location.href='index.php'">← Back to Overview</button>
        </header>

        <section class="page-section">
            <h2>Volumes dashboard</h2>
            <p>Browse volumes, filter by driver, and clean up unused ones.</p>

            <div id="volError" class="vol-error" style="display:none;"></div>

            <div class="vol-summary-row">
                <div class="vol-chip">Total volumes: <strong id="totalVolumes">0</strong></div>
                <div class="vol-chip">Local driver: <strong id="localVolumes">0</strong></div>
                <div class="vol-chip">Other drivers: <strong id="otherVolumes">0</strong></div>
                <div class="vol-chip">Filtered view: <strong id="filteredVolumes">0</strong></div>
            </div>

            <div class="vol-toolbar">
                <div class="vol-search-wrap">
                    <span class="icon">🔍</span>
                    <input id="searchInput" type="text" placeholder="Filter by name, driver, scope, mountpoint…">
                </div>

                <select id="driverFilter" class="vol-select">
                    <option value="">All drivers</option>
                    <option value="local">local only</option>
                    <option value="nonlocal">non-local</option>
                </select>

                <select id="autoRefresh" class="vol-select">
                    <option value="30000" selected>Auto refresh: 30s</option>
                    <option value="10000">Auto refresh: 10s</option>
                    <option value="60000">Auto refresh: 60s</option>
                    <option value="0">Auto refresh: Off</option>
                </select>

                <button id="refreshBtn" class="vol-btn">
                    ⟳ Refresh now
                </button>
            </div>

            <?php if ($enableActions): ?>
            <div class="vol-toolbar" style="margin-top:4px;">
                <div class="vol-create-wrap">
                    <input id="createName" class="vol-input name" type="text"
                           placeholder="New volume name (e.g. app_data)">
                    <input id="createDriver" class="vol-input driver" type="text"
                           placeholder="Driver (optional, e.g. local)">
                    <button id="createBtn" class="vol-btn">＋ Create</button>
                </div>
                <button id="pruneBtn" class="vol-btn danger">🧹 Prune unused</button>
            </div>
            <?php endif; ?>

            <div class="vol-table-wrap">
                <table class="vol-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Driver</th>
                        <th>Scope</th>
                        <th>Mountpoint</th>
                        <th>Labels</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="volumesBody">
                    <tr><td colspan="6">Loading volumes…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="vol-footer-note">
                Note: Docker may refuse to remove a volume that is still in use by a container.
            </div>
        </section>
    </main>
</div> <!-- closes .app-shell from nav.php -->

<script>
    const volumesBody     = document.getElementById('volumesBody');
    const volError        = document.getElementById('volError');
    const totalVolumes    = document.getElementById('totalVolumes');
    const localVolumes    = document.getElementById('localVolumes');
    const otherVolumes    = document.getElementById('otherVolumes');
    const filteredVolumes = document.getElementById('filteredVolumes');
    const searchInput     = document.getElementById('searchInput');
    const driverFilter    = document.getElementById('driverFilter');
    const autoRefresh     = document.getElementById('autoRefresh');
    const refreshBtn      = document.getElementById('refreshBtn');
    const createName      = document.getElementById('createName');
    const createDriver    = document.getElementById('createDriver');
    const createBtn       = document.getElementById('createBtn');
    const pruneBtn        = document.getElementById('pruneBtn');

    let volumes = [];
    let timer   = null;

    function setError(msg) {
        if (!msg) {
            volError.style.display = 'none';
            volError.textContent = '';
        } else {
            volError.style.display = 'block';
            volError.textContent = msg;
        }
    }

    async function fetchVolumes() {
        setError('');
        refreshBtn.disabled = true;
        refreshBtn.textContent = '⟳ Refreshing…';

        try {
            const res = await fetch('volumes.php?api=volumes');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            volumes = data.volumes || [];
            renderVolumes();
        } catch (e) {
            setError('Failed to load volumes: ' + e.message);
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '⟳ Refresh now';
        }
    }

    function renderVolumes() {
        const query  = (searchInput.value || '').toLowerCase();
        const filter = driverFilter.value;

        let local = 0, other = 0;
        volumes.forEach(v => {
            if ((v.Driver || '').toLowerCase() === 'local') local++;
            else other++;
        });

        totalVolumes.textContent = volumes.length;
        localVolumes.textContent = local;
        otherVolumes.textContent = other;

        const filtered = volumes.filter(v => {
            const driver = (v.Driver || '').toLowerCase();

            if (filter === 'local' && driver !== 'local') return false;
            if (filter === 'nonlocal' && driver === 'local') return false;

            if (!query) return true;

            const haystack = [
                v.Name,
                v.Driver,
                v.Scope,
                v.Mountpoint,
                v.Labels
            ].join(' ').toLowerCase();

            return haystack.includes(query);
        });

        filteredVolumes.textContent = filtered.length;

        if (!filtered.length) {
            volumesBody.innerHTML = '<tr><td colspan="6">No volumes match your filters.</td></tr>';
            return;
        }

        volumesBody.innerHTML = '';

        filtered.forEach(v => {
            const tr = document.createElement('tr');
            const labels = v.Labels && v.Labels !== '<none>' ? v.Labels : '—';

            tr.innerHTML = `
                <td><span class="vol-tag">${v.Name}</span></td>
                <td><span class="vol-badge">${v.Driver || '—'}</span></td>
                <td><span class="vol-badge">${v.Scope || '—'}</span></td>
                <td><span class="vol-badge" style="max-width:260px;display:inline-block;overflow:hidden;text-overflow:ellipsis;">${v.Mountpoint || '—'}</span></td>
                <td><span class="vol-badge" style="max-width:260px;display:inline-block;overflow:hidden;text-overflow:ellipsis;">${labels}</span></td>
                <td class="vol-actions">
                    <?php if ($enableActions): ?>
                        ${renderVolumeActionsTemplate()}
                    <?php else: ?>
                        <span class="vol-badge">Read-only</span>
                    <?php endif; ?>
                </td>
            `;

            <?php if ($enableActions): ?>
            const actionsCell = tr.querySelector('.vol-actions');
            const btnRemove   = actionsCell.querySelector('[data-action="remove"]');
            btnRemove.addEventListener('click', () => doRemoveVolume(v.Name));
            <?php endif; ?>

            volumesBody.appendChild(tr);
        });
    }

    <?php if ($enableActions): ?>
    function renderVolumeActionsTemplate() {
        return `<button class="danger" data-action="remove">Remove</button>`;
    }

    async function doRemoveVolume(name) {
        if (!confirm(`Remove volume "${name}"?\n\nNote: this will fail if the volume is still in use.`)) return;

        setError('');
        refreshBtn.disabled = true;
        refreshBtn.textContent = 'Removing…';

        try {
            const res = await fetch('volumes.php?api=action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'remove', name })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            await fetchVolumes();
        } catch (e) {
            setError('Failed to remove volume: ' + e.message);
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '⟳ Refresh now';
        }
    }

    async function doCreateVolume() {
        const name   = (createName.value || '').trim();
        const driver = (createDriver.value || '').trim();

        if (!name) {
            alert('Enter a name for the new volume.');
            return;
        }

        if (!confirm(`Create volume "${name}"${driver ? ' with driver "' + driver + '"' : ''}?`)) return;

        setError('');
        createBtn.disabled = true;
        createBtn.textContent = 'Creating…';

        try {
            const res = await fetch('volumes.php?api=action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'create', name, driver })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            createName.value = '';
            createDriver.value = '';
            await fetchVolumes();
        } catch (e) {
            setError('Failed to create volume: ' + e.message);
        } finally {
            createBtn.disabled = false;
            createBtn.textContent = '＋ Create';
        }
    }

    async function doPruneVolumes() {
        if (!confirm('Prune unused volumes? This will remove all volumes not referenced by any containers.')) return;

        setError('');
        pruneBtn.disabled = true;
        pruneBtn.textContent = 'Pruning…';

        try {
            const res = await fetch('volumes.php?api=action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'prune' })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            await fetchVolumes();
        } catch (e) {
            setError('Failed to prune volumes: ' + e.message);
        } finally {
            pruneBtn.disabled = false;
            pruneBtn.textContent = '🧹 Prune unused';
        }
    }
    <?php else: ?>
    function renderVolumeActionsTemplate() { return ''; }
    function doCreateVolume() {}
    function doPruneVolumes() {}
    <?php endif; ?>

    function setupAutoRefresh() {
        if (timer) clearInterval(timer);
        const ms = parseInt(autoRefresh.value, 10);
        if (ms > 0) {
            timer = setInterval(fetchVolumes, ms);
        }
    }

    refreshBtn.addEventListener('click', fetchVolumes);
    searchInput.addEventListener('input', renderVolumes);
    driverFilter.addEventListener('change', renderVolumes);
    autoRefresh.addEventListener('change', setupAutoRefresh);

    <?php if ($enableActions): ?>
    createBtn.addEventListener('click', doCreateVolume);
    createName && createName.addEventListener('keydown', e => {
        if (e.key === 'Enter') doCreateVolume();
    });
    createDriver && createDriver.addEventListener('keydown', e => {
        if (e.key === 'Enter') doCreateVolume();
    });
    pruneBtn.addEventListener('click', doPruneVolumes);
    <?php endif; ?>

    fetchVolumes();
    setupAutoRefresh();
</script>
</body>
</html>
