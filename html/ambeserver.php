<?php

$baseDir = "/home/pi/AMBE_SERVER";

$binary  = $baseDir . "/AMBEserver";
$iniFile = $baseDir . "/AMBEserver.ini";
$logFile = $baseDir . "/ambe.log";
$pidFile = $baseDir . "/ambe.pid";

/* =========================================================
   CRON AUTOARRANQUE
   ========================================================= */

$cronCmd = "@reboot sleep 10 && cd $baseDir && nohup $binary "
    . "-s \$(grep '^velocidad=' $iniFile | cut -d= -f2) "
    . "-i \$(grep '^puerto=' $iniFile | cut -d= -f2) "
    . "-p \$(grep '^puertonet=' $iniFile | cut -d= -f2) "
    . ">> $logFile 2>&1 & ; echo \$(pgrep -f AMBEserver | head -n1) > $pidFile";

/* =========================================================
   FUNCIONES
   ========================================================= */

function loadConfig($iniFile)
{
    clearstatcache(true, $iniFile);
    $cfg = @parse_ini_file($iniFile) ?: [];

    return [
        'velocidad' => trim($cfg['velocidad'] ?? '460800'),
        'puerto'    => trim($cfg['puerto'] ?? '/dev/ttyUSB0'),
        'puertonet' => trim($cfg['puertonet'] ?? '3500'),
    ];
}

function logMsg($logFile, $msg)
{
    file_put_contents(
        $logFile,
        "[" . date("Y-m-d H:i:s") . "] $msg\n",
        FILE_APPEND | LOCK_EX
    );
}

function getAutoStatus()
{
    $cron = shell_exec("crontab -l 2>/dev/null");
    return strpos($cron, "AMBEserver") !== false;
}

function isRunning($pidFile)
{
    $pid = trim(@file_get_contents($pidFile));

    if ($pid && trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"))) {
        return true;
    }

    return trim(shell_exec("pgrep -x AMBEserver 2>/dev/null")) !== '';
}

/* =========================================================
   LIBERAR TTY / DVSTICK
   ========================================================= */

function releaseTTY($tty, $logFile)
{
    shell_exec("fuser -k $tty 2>/dev/null");
    usleep(500000);

    shell_exec("stty -F $tty sane 2>/dev/null");
    usleep(500000);

    shell_exec("sync");
    usleep(500000);

    logMsg($logFile, ">>> TTY liberado: $tty");
}

/* =========================================================
   PARAR AMBESERVER
   ========================================================= */

function stopAMBE($pidFile, $logFile, $tty)
{
    if (!file_exists($pidFile)) {
        releaseTTY($tty, $logFile);
        return true;
    }

    $pid = trim(file_get_contents($pidFile));
    if (!$pid) {
        @unlink($pidFile);
        releaseTTY($tty, $logFile);
        return true;
    }

    logMsg($logFile, ">>> Deteniendo PID $pid");

    shell_exec("kill -TERM $pid 2>/dev/null");

    for ($i = 0; $i < 10; $i++) {
        usleep(500000);
        $still = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));
        if (!$still) break;
    }

    $still = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));
    if ($still) {
        logMsg($logFile, ">>> Enviando SIGINT");
        shell_exec("kill -INT $pid 2>/dev/null");
        sleep(1);
    }

    @unlink($pidFile);
    releaseTTY($tty, $logFile);
    sleep(1);

    logMsg($logFile, ">>> AMBEserver detenido correctamente");
    return true;
}

/* =========================================================
   ARRANCAR AMBESERVER
   ========================================================= */

function startAMBE($binary, $config, $logFile, $pidFile)
{
    $speed = (int)$config['velocidad'];
    $tty   = trim($config['puerto']);
    $port  = (int)$config['puertonet'];

    releaseTTY($tty, $logFile);

    $cmd = sprintf(
        "nohup %s -s %d -i %s -p %d >> %s 2>&1 & echo $!",
        escapeshellcmd($binary),
        $speed,
        escapeshellarg($tty),
        $port,
        escapeshellarg($logFile)
    );

    logMsg($logFile, ">>> CMD: $cmd");

    $pid = trim(shell_exec($cmd));

    if (!$pid) {
        logMsg($logFile, ">>> ERROR obteniendo PID");
        return false;
    }

    file_put_contents($pidFile, $pid);
    sleep(2);

    $running = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));
    if (!$running) {
        @unlink($pidFile);
        logMsg($logFile, ">>> ERROR: proceso muerto tras iniciar");
        return false;
    }

    logMsg($logFile, ">>> AMBEserver iniciado PID $pid");
    return true;
}

/* =========================================================
   ACCIONES AJAX
   ========================================================= */

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'log') {
        header('Content-Type: text/plain');
        system("tail -n 50 " . escapeshellarg($logFile));
        exit;
    }

    if ($action === 'status') {
        header('Content-Type: application/json');
        echo json_encode([
            'running' => isRunning($pidFile),
            'auto'    => getAutoStatus(),
        ]);
        exit;
    }

    if ($action === 'start') {
        header('Content-Type: application/json');
        if (isRunning($pidFile)) {
            echo json_encode(['ok' => false, 'msg' => 'Ya está en ejecución']);
            exit;
        }
        $config = loadConfig($iniFile);
        logMsg($logFile, ">>> START solicitado");
        $ok = startAMBE($binary, $config, $logFile, $pidFile);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($action === 'stop') {
        header('Content-Type: application/json');
        $config = loadConfig($iniFile);
        logMsg($logFile, ">>> STOP solicitado");
        $ok = stopAMBE($pidFile, $logFile, $config['puerto']);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($action === 'restart') {
        header('Content-Type: application/json');
        $config = loadConfig($iniFile);
        logMsg($logFile, ">>> RESTART solicitado");
        stopAMBE($pidFile, $logFile, $config['puerto']);
        sleep(2);
        $ok = startAMBE($binary, $config, $logFile, $pidFile);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($action === 'clear') {
        header('Content-Type: application/json');
        file_put_contents($logFile, "");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'enable_auto') {
        header('Content-Type: application/json');
        shell_exec("(crontab -l 2>/dev/null; echo " . escapeshellarg($cronCmd) . ") | crontab -");
        logMsg($logFile, ">>> Autoarranque ACTIVADO");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'disable_auto') {
        header('Content-Type: application/json');
        shell_exec("crontab -l 2>/dev/null | grep -v 'AMBEserver' | crontab -");
        logMsg($logFile, ">>> Autoarranque DESACTIVADO");
        echo json_encode(['ok' => true]);
        exit;
    }
}

/* =========================================================
   GUARDAR INI
   ========================================================= */

if (isset($_POST['save'])) {
    $ini =
        "velocidad=" . trim($_POST['velocidad'] ?? '460800') . "\n" .
        "puerto="    . trim($_POST['puerto'] ?? '/dev/ttyUSB0') . "\n" .
        "puertonet=" . trim($_POST['puertonet'] ?? '3500') . "\n";

    file_put_contents($iniFile, $ini, LOCK_EX);
    clearstatcache(true, $iniFile);

    if (isRunning($pidFile)) {
        $config = loadConfig($iniFile);
        stopAMBE($pidFile, $logFile, $config['puerto']);
        sleep(2);
        startAMBE($binary, $config, $logFile, $pidFile);
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?saved=1");
    exit;
}

/* =========================================================
   DATOS HTML
   ========================================================= */

$config = loadConfig($iniFile);
$speed       = $config['velocidad'];
$tty         = $config['puerto'];
$net         = $config['puertonet'];
$running     = isRunning($pidFile);
$autoEnabled = getAutoStatus();
$saved       = $_GET['saved'] ?? null;

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AMBE Server</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background: #0d0d0d; }
.log-box {
    background: #000; color: #0f0; font-family: monospace; font-size: 13px;
    height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;
    border-radius: 6px; padding: 12px;
}
.form-control, .form-control:focus {
    background: #111; color: #0f0; border-color: #333;
}
.form-control:focus { box-shadow: none; border-color: #00ffcc; }
.form-label { color: #aaa; }
.alert-success { background: #0a3; border-color: #0c4; color: #fff; }
</style>
</head>
<body class="bg-dark text-white">
<div class="container py-4">

<?php if ($saved): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>Configuración guardada y aplicada.
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ESTADO Y CONTROLES -->
<div class="card bg-secondary bg-opacity-25 border-secondary mb-3">
<div class="card-body">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="card-title m-0 text-light">
    <i class="bi bi-cpu-fill me-2 text-warning"></i>
    AMBE Server
</h4>
    <a href="mmdvm.php" class="btn btn-outline-light">
        <i class="bi bi-house-door-fill me-1"></i>
        Panel PHPPLUS
    </a>
</div>

<div class="d-flex gap-4 mb-4">
<div>
<span class="text-light small">Estado</span><br>
<span id="statusLabel" class="fw-bold fs-5 <?= $running ? 'text-success' : 'text-danger' ?>">
<?= $running ? '● RUNNING' : '● STOPPED' ?>
</span>
</div>
<div>
<span class="text-light small">Autoarranque</span><br>
<span id="autoLabel" class="fw-bold fs-5 <?= $autoEnabled ? 'text-info' : 'text-warning' ?>">
<?= $autoEnabled ? 'ON' : 'OFF' ?>
</span>
</div>
</div>

<div class="d-flex flex-wrap gap-2">
<button class="btn btn-success" onclick="doAction('start')">
<i class="bi bi-play-fill me-1"></i>Start
</button>
<button class="btn btn-warning text-dark" onclick="doAction('restart')">
<i class="bi bi-arrow-clockwise me-1"></i>Restart
</button>
<button class="btn btn-danger" onclick="doAction('stop')">
<i class="bi bi-stop-fill me-1"></i>Stop
</button>
<button class="btn btn-secondary" onclick="doAction('clear')">
<i class="bi bi-trash me-1"></i>Clear Log
</button>
<button id="btnAuto" class="btn <?= $autoEnabled ? 'btn-outline-danger' : 'btn-outline-info' ?>" onclick="toggleAuto()">
<?= $autoEnabled 
    ? '<i class="bi bi-x-circle me-1"></i>Auto OFF' 
    : '<i class="bi bi-lightning-charge me-1"></i>Auto ON' ?>
</button>
</div>
</div>
</div>

<!-- CONFIGURACIÓN COMPACTA -->
<div class="card bg-secondary bg-opacity-25 border-secondary mb-3">
<div class="card-body py-3 px-3">
    <h5 class="card-title mb-3 text-light">
        <i class="bi bi-sliders me-2 text-info"></i>Configuración (AMBEserver.ini)
    </h5>
    <form method="post">
        <div class="row g-3 align-items-end">
            <div class="col-md-3 col-sm-4 col-6">
                <label class="form-label text-secondary small mb-1">Velocidad (bps)</label>
                <input type="number" class="form-control form-control-sm" name="velocidad" value="<?= htmlspecialchars($speed) ?>">
            </div>
            <div class="col-md-3 col-sm-4 col-6">
                <label class="form-label text-secondary small mb-1">Puerto NET</label>
                <input type="number" class="form-control form-control-sm" name="puertonet" value="<?= htmlspecialchars($net) ?>">
            </div>
            <div class="col-md-4 col-sm-4 col-12">
                <label class="form-label text-secondary small mb-1">Puerto Serie</label>
                <input type="text" class="form-control form-control-sm" name="puerto" value="<?= htmlspecialchars($tty) ?>" placeholder="/dev/ttyUSB0">
            </div>
            <div class="col-md-2 col-sm-12 text-md-end">
                <button class="btn btn-sm btn-primary w-100 w-md-auto" name="save">
                    <i class="bi bi-floppy me-1"></i>Guardar
                </button>
            </div>
        </div>
    </form>
</div>
</div>

<!-- LOG -->
<div class="card bg-secondary bg-opacity-25 border-secondary">
<div class="card-body">
<h5 class="card-title mb-3">
<i class="bi bi-terminal me-2 text-success"></i>
Log (últimas líneas)
</h5>
<div class="log-box" id="logbox"></div>
</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function refreshLog() {
    fetch('?action=log')
    .then(r => r.text())
    .then(text => {
        const box = document.getElementById('logbox');
        box.textContent = text || '[Sin logs]';
        box.scrollTop = box.scrollHeight;
    });
}

function refreshStatus() {
    fetch('?action=status')
    .then(r => r.json())
    .then(data => {
        const sl = document.getElementById('statusLabel');
        sl.textContent = data.running ? '● RUNNING' : '● STOPPED';
        sl.className = 'fw-bold fs-5 ' + (data.running ? 'text-success' : 'text-danger');
        
        const al = document.getElementById('autoLabel');
        al.textContent = data.auto ? 'ON' : 'OFF';
        al.className = 'fw-bold fs-5 ' + (data.auto ? 'text-info' : 'text-warning');
        
        const btn = document.getElementById('btnAuto');
        if (data.auto) {
            btn.className = 'btn btn-outline-danger';
            btn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Auto OFF';
        } else {
            btn.className = 'btn btn-outline-info';
            btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i>Auto ON';
        }
    });
}

function toggleAuto() {
    fetch('?action=status')
    .then(r => r.json())
    .then(data => {
        doAction(data.auto ? 'disable_auto' : 'enable_auto');
    });
}

function doAction(action) {
    document.querySelectorAll('button[onclick]').forEach(b => b.disabled = true);
    fetch('?action=' + action)
    .then(r => r.json())
    .then(() => {
        refreshLog();
        refreshStatus();
    })
    .catch(() => {})
    .finally(() => {
        document.querySelectorAll('button[onclick]').forEach(b => b.disabled = false);
    });
}

refreshLog();
refreshStatus();
setInterval(refreshLog, 2000);
setInterval(refreshStatus, 2000);
</script>
</body>
</html>
