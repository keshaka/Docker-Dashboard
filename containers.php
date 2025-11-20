<?php
$activePage = 'containers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Docker VPS UI – Containers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
    <main class="main">
        <header class="main-header">
            <div class="main-title">
                <h1>Containers</h1>
                <p>View and manage all Docker containers on this VPS.</p>
            </div>
            <button class="btn-ghost" onclick="location.href='index.php'">← Back to Overview</button>
        </header>

        <section class="page-section">
            <h2>Containers dashboard</h2>
            <p>
                This page will host the “Docker Desktop–like” containers UI:
                list, filters, actions, logs, stats, etc.
            </p>
            <div class="page-note">
                Next step: we’ll move your existing containers code here and hook actions into the API.
            </div>
        </section>
    </main>
</div>
</body>
</html>
