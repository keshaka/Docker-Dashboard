<?php
// images.php – Docker Images dashboard

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

    // List images
    if ($_GET['api'] === 'images') {
        $cmd = "docker images --all --digests --format '{{json .}}' 2>/dev/null";
        $output = shell_exec($cmd);

        if ($output === null) {
            echo json_encode(['error' => 'Failed to run docker. Make sure PHP user can access docker.']);
            exit;
        }

        $lines = explode("\n", trim($output));
        $images = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $obj = json_decode($line, true);
            if ($obj) $images[] = $obj;
        }

        echo json_encode(['images' => $images]);
        exit;
    }

    // Pull image
    if ($_GET['api'] === 'pull' && $enableActions) {
        $data  = json_decode(file_get_contents('php://input'), true);
        $image = $data['image'] ?? null;

        if (!$image) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing image name']);
            exit;
        }

        $cmd = sprintf('docker pull %s 2>&1', escapeshellarg($image));
        $output = shell_exec($cmd);

        echo json_encode(['ok' => true, 'output' => $output]);
        exit;
    }

    // Remove / prune
    if ($_GET['api'] === 'action' && $enableActions) {
        $data   = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? null;

        if ($action === 'remove') {
            $ref = $data['ref'] ?? null; // repository:tag or ID
            if (!$ref) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing image ref']);
                exit;
            }

            $cmd = sprintf('docker rmi %s 2>&1', escapeshellarg($ref));
            $output = shell_exec($cmd);
            echo json_encode(['ok' => true, 'output' => $output]);
            exit;
        }

        if ($action === 'prune') {
            $cmd = 'docker image prune -f 2>&1';
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
$activePage = 'images';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Images</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Images page specific */

        .images-header-note {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 3px;
        }

        .img-summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .img-chip {
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

        .img-chip strong {
            color: var(--text);
        }

        .img-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .img-search-wrap {
            flex: 1 1 260px;
            position: relative;
        }

        .img-search-wrap input {
            width: 100%;
            padding: 8px 10px 8px 28px;
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--text);
            font-size: 0.85rem;
        }

        .img-search-wrap span.icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 0.9rem;
        }

        .img-select {
            background: rgba(15,23,42,0.96);
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            color: var(--muted);
            padding: 7px 11px;
            font-size: 0.8rem;
        }

        .img-pull-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            flex: 1 1 230px;
        }

        .img-pull-input {
            flex: 1;
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--text);
            font-size: 0.8rem;
        }

        .img-btn {
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
        .img-btn.secondary {
            border-color: rgba(75,85,99,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--muted);
        }
        .img-btn.danger {
            border-color: rgba(248,113,113,0.8);
            color: #fecaca;
        }
        .img-btn:disabled {
            opacity: 0.5;
            cursor: default;
        }

        .img-table-wrap {
            overflow: auto;
            border-radius: var(--radius-md);
            border: 1px solid rgba(30,64,175,0.7);
            background: radial-gradient(circle at top left, rgba(30,64,175,0.3), transparent 55%),
                        rgba(15,23,42,0.98);
            max-height: 70vh;
        }

        table.img-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
        }

        .img-table thead {
            position: sticky;
            top: 0;
            background: rgba(15,23,42,0.98);
            z-index: 2;
        }

        .img-table th,
        .img-table td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(31,41,55,0.9);
            text-align: left;
            white-space: nowrap;
        }

        .img-table th {
            font-size: 0.76rem;
            font-weight: 500;
            color: var(--muted);
        }

        .img-table tbody tr:hover {
            background: rgba(30,64,175,0.45);
        }

        .img-tag {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 0.7rem;
            background: rgba(15,23,42,0.98);
            border: 1px solid rgba(55,65,81,0.9);
            color: #e5e7eb;
        }

        .img-badge {
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 0.7rem;
            background: rgba(15,23,42,0.95);
            border: 1px solid rgba(75,85,99,0.9);
            color: var(--muted);
        }

        .img-dangling {
            background: rgba(127,29,29,0.28);
            border-color: rgba(248,113,113,0.85);
            color: #fecaca;
        }

        .img-error {
            margin-bottom: 10px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.78rem;
            background: rgba(127,29,29,0.3);
            border: 1px solid rgba(248,113,113,0.9);
            color: #fecaca;
        }

        .img-actions button {
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            font-size: 0.7rem;
            padding: 4px 9px;
            margin-right: 4px;
            cursor: pointer;
            color: var(--muted);
        }

        .img-actions button.danger {
            border-color: rgba(248,113,113,0.8);
            color: #fecaca;
        }

        .img-footer-note {
            margin-top: 10px;
            font-size: 0.75rem;
            color: var(--muted);
            opacity: .8;
        }

        @media (max-width: 800px) {
            .img-table-wrap {
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
                <h1>Images</h1>
                <p>View, pull, delete and clean up Docker images on this VPS.</p>
                <div class="images-header-note">
                    Backed by <code>docker images/pull/rmi/image prune</code>.
                </div>
            </div>
            <button class="btn-ghost" onclick="location.href='index.php'">← Back to Overview</button>
        </header>

        <section class="page-section">
            <h2>Images dashboard</h2>
            <p>Browse local images, pull from registries and prune unused layers.</p>

            <div id="imgError" class="img-error" style="display:none;"></div>

            <div class="img-summary-row">
                <div class="img-chip">Total images: <strong id="totalImages">0</strong></div>
                <div class="img-chip">Tagged: <strong id="taggedImages">0</strong></div>
                <div class="img-chip">Dangling: <strong id="danglingImages">0</strong></div>
                <div class="img-chip">Filtered view: <strong id="filteredImages">0</strong></div>
            </div>

            <div class="img-toolbar">
                <div class="img-search-wrap">
                    <span class="icon">🔍</span>
                    <input id="searchInput" type="text" placeholder="Filter by repository, tag, ID, digest…">
                </div>

                <select id="danglingFilter" class="img-select">
                    <option value="">All images</option>
                    <option value="tagged">Tagged only</option>
                    <option value="dangling">Dangling only</option>
                </select>

                <select id="autoRefresh" class="img-select">
                    <option value="30000" selected>Auto refresh: 30s</option>
                    <option value="10000">Auto refresh: 10s</option>
                    <option value="60000">Auto refresh: 60s</option>
                    <option value="0">Auto refresh: Off</option>
                </select>

                <button id="refreshBtn" class="img-btn">
                    ⟳ Refresh now
                </button>
            </div>

            <?php if ($enableActions): ?>
            <div class="img-toolbar" style="margin-top:4px;">
                <div class="img-pull-wrap">
                    <input id="pullInput" class="img-pull-input" type="text"
                           placeholder="nginx:latest, ubuntu:22.04, ghcr.io/user/app:tag…">
                    <button id="pullBtn" class="img-btn">⬇ Pull image</button>
                </div>
                <button id="pruneBtn" class="img-btn danger">
                    🧹 Prune dangling
                </button>
            </div>
            <?php endif; ?>

            <div class="img-table-wrap">
                <table class="img-table">
                    <thead>
                    <tr>
                        <th>Repository</th>
                        <th>Tag</th>
                        <th>Image ID</th>
                        <th>Digest</th>
                        <th>Created</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="imagesBody">
                    <tr><td colspan="7">Loading images…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="img-footer-note">
                Tip: pulling large images over slow networks may take a while. You’ll see the raw Docker output after each pull/prune/remove.
            </div>
        </section>
    </main>
</div> <!-- closes .app-shell from nav.php -->

<script>
    const imagesBody      = document.getElementById('imagesBody');
    const imgError        = document.getElementById('imgError');
    const totalImages     = document.getElementById('totalImages');
    const taggedImages    = document.getElementById('taggedImages');
    const danglingImages  = document.getElementById('danglingImages');
    const filteredImages  = document.getElementById('filteredImages');
    const searchInput     = document.getElementById('searchInput');
    const danglingFilter  = document.getElementById('danglingFilter');
    const autoRefresh     = document.getElementById('autoRefresh');
    const refreshBtn      = document.getElementById('refreshBtn');
    const pullInput       = document.getElementById('pullInput');
    const pullBtn         = document.getElementById('pullBtn');
    const pruneBtn        = document.getElementById('pruneBtn');

    let images = [];
    let timer  = null;

    function setError(msg) {
        if (!msg) {
            imgError.style.display = 'none';
            imgError.textContent = '';
        } else {
            imgError.style.display = 'block';
            imgError.textContent = msg;
        }
    }

    async function fetchImages() {
        setError('');
        refreshBtn.disabled = true;
        refreshBtn.textContent = '⟳ Refreshing…';

        try {
            const res = await fetch('images.php?api=images');
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const data = await res.json();
            if (data.error) throw new Error(data.error);

            images = data.images || [];
            renderImages();
        } catch (e) {
            setError('Failed to load images: ' + e.message);
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '⟳ Refresh now';
        }
    }

    function isDangling(img) {
        return (img.Repository === '<none>' || img.Tag === '<none>');
    }

    function renderImages() {
        const query = (searchInput.value || '').toLowerCase();
        const filter = danglingFilter.value;

        let tagged = 0, dangling = 0;
        images.forEach(img => {
            if (isDangling(img)) dangling++;
            else tagged++;
        });

        totalImages.textContent    = images.length;
        taggedImages.textContent   = tagged;
        danglingImages.textContent = dangling;

        const filtered = images.filter(img => {
            const d = isDangling(img);

            if (filter === 'tagged' && d) return false;
            if (filter === 'dangling' && !d) return false;

            if (!query) return true;

            const haystack = [
                img.Repository,
                img.Tag,
                img.ID,
                img.Digest,
                img.CreatedSince,
                img.CreatedAt,
                img.Size
            ].join(' ').toLowerCase();

            return haystack.includes(query);
        });

        filteredImages.textContent = filtered.length;

        if (!filtered.length) {
            imagesBody.innerHTML = '<tr><td colspan="7">No images match your filters.</td></tr>';
            return;
        }

        imagesBody.innerHTML = '';

        filtered.forEach(img => {
            const tr = document.createElement('tr');
            const dangling = isDangling(img);
            const repoText = img.Repository || '<none>';
            const tagText  = img.Tag || '<none>';
            const ref      = dangling ? img.ID : `${repoText}:${tagText}`;

            tr.innerHTML = `
                <td>
                    <span class="img-tag ${dangling ? 'img-dangling' : ''}">
                        ${repoText}
                    </span>
                </td>
                <td>
                    <span class="img-tag ${dangling ? 'img-dangling' : ''}">
                        ${tagText}
                    </span>
                </td>
                <td><span class="img-badge">${img.ID}</span></td>
                <td><span class="img-badge" style="max-width:260px;display:inline-block;overflow:hidden;text-overflow:ellipsis;">${img.Digest || '—'}</span></td>
                <td><span class="img-badge">${img.CreatedSince || '—'}</span></td>
                <td><span class="img-badge">${img.Size || '—'}</span></td>
                <td class="img-actions">
                    <?php if ($enableActions): ?>
                        ${renderRemoveButtonTemplate()}
                    <?php else: ?>
                        <span class="img-badge">Read-only</span>
                    <?php endif; ?>
                </td>
            `;

            <?php if ($enableActions): ?>
            const actionsCell = tr.querySelector('.img-actions');
            const btnRemove = actionsCell.querySelector('[data-action="remove"]');
            btnRemove.addEventListener('click', () => doRemoveImage(ref, img.ID, repoText, tagText));
            <?php endif; ?>

            imagesBody.appendChild(tr);
        });
    }

    <?php if ($enableActions): ?>
    function renderRemoveButtonTemplate() {
        return `<button class="danger" data-action="remove">Remove</button>`;
    }

    async function doRemoveImage(ref, id, repo, tag) {
        const label = repo === '<none>' && tag === '<none>'
            ? id
            : `${repo}:${tag}`;

        if (!confirm(`Remove image ${label}?`)) return;

        setError('');
        refreshBtn.disabled = true;
        refreshBtn.textContent = 'Removing…';

        try {
            const res = await fetch('images.php?api=action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'remove', ref })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            await fetchImages();
        } catch (e) {
            setError('Failed to remove image: ' + e.message);
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.textContent = '⟳ Refresh now';
        }
    }

    async function doPullImage() {
        const image = (pullInput.value || '').trim();
        if (!image) {
            alert('Enter an image name to pull, e.g. nginx:latest');
            return;
        }

        if (!confirm(`Pull image "${image}"?`)) return;

        setError('');
        pullBtn.disabled = true;
        pullBtn.textContent = 'Pulling…';

        try {
            const res = await fetch('images.php?api=pull', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ image })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            await fetchImages();
        } catch (e) {
            setError('Failed to pull image: ' + e.message);
        } finally {
            pullBtn.disabled = false;
            pullBtn.textContent = '⬇ Pull image';
        }
    }

    async function doPruneImages() {
        if (!confirm('Prune dangling images? This will delete unused layers.')) return;

        setError('');
        pruneBtn.disabled = true;
        pruneBtn.textContent = 'Pruning…';

        try {
            const res = await fetch('images.php?api=action', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'prune' })
            });
            const data = await res.json();
            if (!res.ok || data.error) {
                throw new Error(data.error || ('HTTP ' + res.status));
            }
            await fetchImages();
        } catch (e) {
            setError('Failed to prune images: ' + e.message);
        } finally {
            pruneBtn.disabled = false;
            pruneBtn.textContent = '🧹 Prune dangling';
        }
    }
    <?php else: ?>
    function renderRemoveButtonTemplate() { return ''; }
    function doPullImage() {}
    function doPruneImages() {}
    <?php endif; ?>

    function setupAutoRefresh() {
        if (timer) clearInterval(timer);
        const ms = parseInt(autoRefresh.value, 10);
        if (ms > 0) {
            timer = setInterval(fetchImages, ms);
        }
    }

    refreshBtn.addEventListener('click', fetchImages);
    searchInput.addEventListener('input', renderImages);
    danglingFilter.addEventListener('change', renderImages);
    autoRefresh.addEventListener('change', setupAutoRefresh);

    <?php if ($enableActions): ?>
    pullBtn.addEventListener('click', doPullImage);
    pullInput && pullInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') doPullImage();
    });
    pruneBtn.addEventListener('click', doPruneImages);
    <?php endif; ?>

    fetchImages();
    setupAutoRefresh();
</script>
</body>
</html>
