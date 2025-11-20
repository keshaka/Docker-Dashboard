<?php
// compose.php – Docker Compose Dashboard (fixed)

// ==== CONFIG ====
$enableActions = true; // set to false for read-only
$COMPOSE_DIR   = "/var/www/html/compose-projects"; // where projects live

if (!is_dir($COMPOSE_DIR)) {
    mkdir($COMPOSE_DIR, 0775, true);
}

// ---- helpers ----
function compose_project_path(string $name, string $baseDir): string {
    return rtrim($baseDir, "/") . "/" . $name;
}

function is_valid_project_name(string $name): bool {
    // allow letters, numbers, -, _
    return (bool)preg_match('/^[a-zA-Z0-9_\-]+$/', $name);
}

// ==== API HANDLER ====
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    $api     = $_GET['api'];
    $project = $_GET['project'] ?? null;

    // LIST PROJECTS
    if ($api === 'list') {
        $projects = [];
        $entries = scandir($COMPOSE_DIR);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $full = $COMPOSE_DIR . "/" . $entry;
            if (!is_dir($full)) continue;

            $hasCompose = file_exists($full . "/docker-compose.yml") || file_exists($full . "/docker-compose.yaml");
            $projects[] = [
                'name' => $entry,
                'hasCompose' => $hasCompose,
            ];
        }
        echo json_encode(['projects' => $projects]);
        exit;
    }

    // CREATE PROJECT (folder only)
    if ($api === 'create_project' && $enableActions) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];
        $name = $data['name'] ?? '';

        if (!$name || !is_valid_project_name($name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid project name. Use letters, numbers, - or _.']);
            exit;
        }

        $path = compose_project_path($name, $COMPOSE_DIR);
        if (!is_dir($path)) {
            if (!mkdir($path, 0775, true)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create project directory.']);
                exit;
            }
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    // UPLOAD COMPOSE FILE
    if ($api === 'upload' && $enableActions) {
        if (!isset($_POST['project']) || !is_valid_project_name($_POST['project'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid project name for upload.']);
            exit;
        }
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded.']);
            exit;
        }

        $name     = $_POST['project'];
        $projPath = compose_project_path($name, $COMPOSE_DIR);

        if (!is_dir($projPath) && !mkdir($projPath, 0775, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create project directory.']);
            exit;
        }

        $target = $projPath . "/docker-compose.yml"; // normalize name
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file.']);
            exit;
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    // helper to ensure compose file exists
    function ensure_compose_exists(string $project, string $baseDir) {
        $path = compose_project_path($project, $baseDir);
        $yml  = $path . "/docker-compose.yml";
        $yaml = $path . "/docker-compose.yaml";
        if (file_exists($yml)) return [$path, $yml];
        if (file_exists($yaml)) return [$path, $yaml];
        return [null, null];
    }

    // LOGS
    if ($api === 'logs' && $project) {
        [$path, $composeFile] = ensure_compose_exists($project, $COMPOSE_DIR);
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'No docker-compose.yml found for this project.']);
            exit;
        }

        $cmd = sprintf('cd %s && docker compose -f %s logs --no-color --tail=200 2>&1',
            escapeshellarg($path),
            escapeshellarg($composeFile)
        );
        $out = shell_exec($cmd);
        echo json_encode(['logs' => $out]);
        exit;
    }

    // UP / STOP / DOWN
    if (in_array($api, ['up', 'stop', 'down'], true) && $enableActions && $project) {
        [$path, $composeFile] = ensure_compose_exists($project, $COMPOSE_DIR);
        if (!$path) {
            http_response_code(400);
            echo json_encode(['error' => 'No docker-compose.yml found for this project.']);
            exit;
        }

        $subcmd = $api === 'up' ? 'up -d' : $api;
        $cmd = sprintf('cd %s && docker compose -f %s %s 2>&1',
            escapeshellarg($path),
            escapeshellarg($composeFile),
            $subcmd
        );
        $out = shell_exec($cmd);
        echo json_encode(['ok' => true, 'output' => $out]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Unknown API']);
    exit;
}

// ==== PAGE RENDER ====
$activePage = 'compose';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Compose</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .compose-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .compose-card {
            background: radial-gradient(circle at top left, rgba(56,189,248,0.18), transparent 55%),
                        rgba(15,23,42,0.98);
            border: 1px solid rgba(56,189,248,0.4);
            border-radius: 18px;
            padding: 14px 14px 12px;
        }
        .compose-card h3 {
            margin: 0 0 4px;
        }
        .compose-card small {
            color: var(--muted);
            font-size: 0.75rem;
        }
        .compose-status {
            margin-top: 6px;
            font-size: 0.78rem;
            color: var(--muted);
        }
        .compose-status span {
            border-radius: 999px;
            padding: 2px 7px;
            border: 1px solid rgba(148,163,184,0.5);
        }
        .compose-actions {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .compose-btn {
            padding: 6px 11px;
            border-radius: 999px;
            border: 1px solid rgba(56,189,248,0.7);
            background: var(--accent-soft);
            color: var(--accent);
            cursor: pointer;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .compose-btn.secondary {
            border-color: rgba(75,85,99,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--muted);
        }
        .compose-btn.danger {
            border-color: rgba(248,113,113,0.8);
            color: #fecaca;
        }
        .compose-btn:disabled {
            opacity: .5;
            cursor: default;
        }
        .compose-input {
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid rgba(55,65,81,0.9);
            background: rgba(15,23,42,0.96);
            color: var(--text);
            font-size: 0.82rem;
        }
        .compose-error {
            display:none;
            margin: 8px 0;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(127,29,29,0.3);
            border: 1px solid rgba(248,113,113,0.9);
            color: #fecaca;
            font-size: 0.78rem;
        }

        .log-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            display:none;
            justify-content:center;
            align-items:center;
            padding:20px;
            z-index: 50;
        }
        .log-box {
            background:#020617;
            border:1px solid #1f2937;
            padding:16px;
            width:90%;
            max-width:900px;
            height:80vh;
            overflow:auto;
            border-radius:16px;
            white-space:pre;
            color:#e5e7eb;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.78rem;
        }
        .log-close {
            text-align:right;
            margin-bottom:6px;
            font-size:0.78rem;
            color:var(--muted);
            cursor:pointer;
        }
    </style>
</head>
<body>
<?php include 'nav.php'; ?>

<main class="main">
    <header class="main-header">
        <div class="main-title">
            <h1>Compose projects</h1>
            <p>Manage multi-container stacks powered by Docker Compose.</p>
        </div>
        <button class="btn-ghost" onclick="location.href='index.php'">← Back to Overview</button>
    </header>

    <section class="page-section">
        <h2>Compose dashboard</h2>
        <p>Create projects, upload <code>docker-compose.yml</code>, and control stacks.</p>

        <div id="composeError" class="compose-error"></div>

        <div style="margin-bottom:14px;">
            <h3>Create new project</h3>
            <input id="newProjectName"
                   class="compose-input"
                   style="min-width:220px;"
                   placeholder="project-name (letters, numbers, - and _)">
            <button id="createBtn" class="compose-btn">＋ Create</button>
        </div>

        <div style="margin-bottom:16px;">
            <h3>Upload docker-compose.yml</h3>
            <input id="uploadProjectName"
                   class="compose-input"
                   style="min-width:180px;"
                   placeholder="existing or new project-name">
            <input type="file" id="composeFile" accept=".yml,.yaml"
                   style="margin-left:6px;color:#e5e7eb;">
            <button id="uploadBtn" class="compose-btn">⬆ Upload</button>
        </div>

        <h3>Projects</h3>
        <div id="projectList">Loading…</div>

        <div class="page-note" style="margin-top:10px;">
            Projects live under <code><?php echo htmlspecialchars($COMPOSE_DIR, ENT_QUOTES); ?></code>.
            Each card corresponds to a folder; actions require a valid <code>docker-compose.yml</code>.
        </div>
    </section>
</main>

<div id="logModal" class="log-modal">
    <div class="log-box">
        <div class="log-close" id="logClose">Close ×</div>
        <pre id="logContent"></pre>
    </div>
</div>

<script>
const errorBox       = document.getElementById('composeError');
const projectList    = document.getElementById('projectList');
const newProjectName = document.getElementById('newProjectName');
const createBtn      = document.getElementById('createBtn');
const uploadProject  = document.getElementById('uploadProjectName');
const composeFile    = document.getElementById('composeFile');
const uploadBtn      = document.getElementById('uploadBtn');
const logModal       = document.getElementById('logModal');
const logClose       = document.getElementById('logClose');
const logContent     = document.getElementById('logContent');

function setError(msg) {
    if (!msg) {
        errorBox.style.display = 'none';
        errorBox.textContent = '';
    } else {
        errorBox.style.display = 'block';
        errorBox.textContent = msg;
    }
}

async function loadProjects() {
    setError('');
    projectList.textContent = 'Loading…';
    try {
        const res = await fetch('compose.php?api=list');
        const data = await res.json();
        if (!res.ok || data.error) throw new Error(data.error || ('HTTP ' + res.status));

        const projects = data.projects || [];
        if (!projects.length) {
            projectList.innerHTML = '<p>No compose projects yet. Create one above.</p>';
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'compose-grid';

        projects.forEach(p => {
            const div = document.createElement('div');
            div.className = 'compose-card';

            const hasCompose = !!p.hasCompose;
            const statusText = hasCompose
                ? 'Compose file detected.'
                : 'No docker-compose.yml yet. Upload one to enable actions.';

            div.innerHTML = `
                <h3>${p.name}</h3>
                <small>${p.name}/docker-compose.yml</small>
                <div class="compose-status">
                    <span>${statusText}</span>
                </div>
                <div class="compose-actions">
                    <button class="compose-btn" data-action="up"   ${hasCompose ? '' : 'disabled'}>Start</button>
                    <button class="compose-btn secondary" data-action="stop" ${hasCompose ? '' : 'disabled'}>Stop</button>
                    <button class="compose-btn danger" data-action="down" ${hasCompose ? '' : 'disabled'}>Down</button>
                    <button class="compose-btn" data-action="logs" ${hasCompose ? '' : 'disabled'}>Logs</button>
                </div>
            `;

            const btns = div.querySelectorAll('.compose-actions button');
            btns.forEach(btn => {
                const action = btn.dataset.action;
                if (!action || btn.disabled) return;

                if (action === 'logs') {
                    btn.addEventListener('click', () => showLogs(p.name));
                } else {
                    btn.addEventListener('click', () => doAction(p.name, action));
                }
            });

            grid.appendChild(div);
        });

        projectList.innerHTML = '';
        projectList.appendChild(grid);
    } catch (e) {
        setError('Failed to load projects: ' + e.message);
        projectList.innerHTML = '<p>Error loading projects.</p>';
    }
}

async function createProject() {
    const name = (newProjectName.value || '').trim();
    if (!name) {
        alert('Enter a project name.');
        return;
    }

    setError('');
    createBtn.disabled = true;
    createBtn.textContent = 'Creating…';

    try {
        const res = await fetch('compose.php?api=create_project', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            throw new Error(data.error || ('HTTP ' + res.status));
        }
        newProjectName.value = '';
        await loadProjects();
    } catch (e) {
        setError('Failed to create project: ' + e.message);
    } finally {
        createBtn.disabled = false;
        createBtn.textContent = '＋ Create';
    }
}

async function uploadCompose() {
    const name = (uploadProject.value || '').trim();
    const file = composeFile.files[0];

    if (!name) {
        alert('Enter a project name to upload into.');
        return;
    }
    if (!file) {
        alert('Select a docker-compose file.');
        return;
    }

    setError('');
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading…';

    try {
        const form = new FormData();
        form.append('project', name);
        form.append('file', file);

        const res = await fetch('compose.php?api=upload', {
            method: 'POST',
            body: form
        });
        const data = await res.json();
        if (!res.ok || data.error) {
            throw new Error(data.error || ('HTTP ' + res.status));
        }
        uploadProject.value = '';
        composeFile.value = '';
        await loadProjects();
    } catch (e) {
        setError('Failed to upload compose file: ' + e.message);
    } finally {
        uploadBtn.disabled = false;
        uploadBtn.textContent = '⬆ Upload';
    }
}

async function doAction(project, action) {
    if (!confirm(`Run "${action}" on project "${project}"?`)) return;
    setError('');

    try {
        const res = await fetch(`compose.php?api=${encodeURIComponent(action)}&project=${encodeURIComponent(project)}`);
        const data = await res.json();
        if (!res.ok || data.error) {
            throw new Error(data.error || ('HTTP ' + res.status));
        }
        await loadProjects();
    } catch (e) {
        setError(`Failed to run "${action}" on ${project}: ` + e.message);
    }
}

async function showLogs(project) {
    setError('');
    logContent.textContent = 'Loading logs…';
    logModal.style.display = 'flex';

    try {
        const res = await fetch('compose.php?api=logs&project=' + encodeURIComponent(project));
        const data = await res.json();
        if (!res.ok || data.error) {
            throw new Error(data.error || ('HTTP ' + res.status));
        }
        logContent.textContent = data.logs || '(no logs)';
    } catch (e) {
        logContent.textContent = 'Failed to load logs: ' + e.message;
    }
}

createBtn.addEventListener('click', createProject);
newProjectName.addEventListener('keydown', e => {
    if (e.key === 'Enter') createProject();
});
uploadBtn.addEventListener('click', uploadCompose);

logClose.addEventListener('click', () => logModal.style.display = 'none');
logModal.addEventListener('click', e => {
    if (e.target === logModal) logModal.style.display = 'none';
});

loadProjects();
</script>
</body>
</html>
