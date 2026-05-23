<?php

function runCmd($cmd) {
    return shell_exec($cmd . " 2>&1");
}

$output = "";

/* =========================
   ACCIONES
========================= */
if (isset($_GET['action'])) {

    $action = $_GET['action'];

    $output .= "==================================================\n";
    $output .= "🧹 ACCIÓN: $action\n";
    $output .= "==================================================\n\n";

    if ($action == "start") {

        $output .= "▶ START OpenWebRX\n\n";
        $output .= runCmd("docker update --restart=no openwebrx");
        $output .= runCmd("docker start openwebrx");
    }

    if ($action == "stop") {

        $output .= "⏹ STOP OpenWebRX (NO AUTO RESTART)\n\n";
        $output .= runCmd("docker update --restart=no openwebrx");
        $output .= runCmd("docker stop -t 10 openwebrx");
    }

    if ($action == "restart") {

        $output .= "🔄 RESTART OpenWebRX\n\n";
        $output .= runCmd("docker update --restart=no openwebrx");
        $output .= runCmd("docker restart openwebrx");
    }

    if ($action == "lock") {

        $output .= "🔒 FULL LOCK (STOP + NO AUTO START)\n\n";
        $output .= runCmd("docker update --restart=no openwebrx");
        $output .= runCmd("docker stop -t 10 openwebrx");
    }

    $output .= "\n==================================================\n";
    $output .= "✔ ACCIÓN TERMINADA\n";
    $output .= "==================================================\n\n";
}

/* =========================
   STATUS
========================= */
$status = trim(runCmd("docker ps -q -f name=openwebrx"));
$isRunning = ($status != "");

$autostart = trim(runCmd("docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' openwebrx"));

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>OpenWebRX Control</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#0e1117;
    color:#fff;
}

.panel {
    background:#161b22;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
}

.topbar {
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

.title {
    font-weight:bold;
    font-size:18px;
}

.btn {
    font-size:0.75rem;
}

.terminal {
    background:#000;
    color:#00ff66;
    padding:15px;
    height:70vh;
    overflow-y:auto;
    font-family: monospace;
    font-size: 13px;
    border-radius:10px;
    white-space: pre-wrap;
}

.status-ok { color: lime; font-weight:bold; }
.status-bad { color: red; font-weight:bold; }
</style>
</head>

<body>

<div class="container py-4">

    <!-- PANEL SUPERIOR -->
    <div class="panel">
        <div class="topbar">

            <div class="title">📡 OpenWebRX CONTROL</div>

            <span>
                Docker:
                <?php if ($isRunning): ?>
                    <span class="status-ok">🟢 RUNNING</span>
                <?php else: ?>
                    <span class="status-bad">🔴 STOPPED</span>
                <?php endif; ?>
            </span>

            <span>
                Restart: <b><?= htmlspecialchars($autostart) ?></b>
            </span>

            <div style="flex:1"></div>

            <!-- BOTONES CONTROL -->
            <a class="btn btn-success" href="?action=start">▶ START</a>
            <a class="btn btn-danger" href="?action=stop">⏹ STOP</a>
            <a class="btn btn-warning" href="?action=restart">🔄 RESTART</a>
            <a class="btn btn-dark" href="?action=lock">🔒 LOCK</a>

            <!-- ACCESO WEB -->
            <a class="btn btn-info" target="_blank"
               href="http://<?= $_SERVER['SERVER_ADDR'] ?>:8073">
               🌐 OPENWEBRX
            </a>

            <!-- TU PANEL ORIGINAL -->
            <a class="btn btn-outline-light"
               href="mmdvm.php">
               🏠 PANEL PHPPLUS
            </a>

        </div>
    </div>

    <!-- TERMINAL -->
    <div class="panel">
        <h5>📟 STATUS / LOGS</h5>

        <div class="terminal">
<?php

if ($output != "") {
    echo $output;
}

echo "\n================ DOCKER STATUS ================\n";
echo runCmd("docker ps -a --filter name=openwebrx");

echo "\n================ CONTAINER INFO ================\n";
echo runCmd("docker inspect openwebrx --format 'Estado: {{.State.Status}} | Restart: {{.HostConfig.RestartPolicy.Name}}' 2>/dev/null");

echo "\n================ LOGS (LAST 100) ================\n";
echo runCmd("docker logs --tail 100 openwebrx 2>&1");

?>
        </div>
    </div>

</div>

</body>
</html>
