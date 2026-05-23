<?php
$config_file = "/etc/rbfeeder.ini";
$message = "";

/*
🧠 ESTADO ÚNICO DE VISTA:
none | terminal | editor
*/
$view = isset($_POST['view']) ? $_POST['view'] : "none";

/* 🔁 LOGS AJAX */
if (isset($_GET['logs'])) {
    echo shell_exec("journalctl -u rbfeeder.service -n 50 --no-pager 2>&1");
    exit;
}

/* ⚙️ ACCIONES SERVICIO */
if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        case 'start':
            shell_exec("sudo systemctl start rbfeeder.service");
            $message = "Radarbox arrancado";
            break;

        case 'stop':
            shell_exec("sudo systemctl stop rbfeeder.service");
            $message = "Radarbox parado";
            break;

        case 'restart':
            shell_exec("sudo systemctl restart rbfeeder.service");
            $message = "Radarbox reiniciado";
            break;

        case 'enable':
            shell_exec("sudo systemctl enable rbfeeder.service");
            $message = "Arranque automático ACTIVADO";
            break;

        case 'disable':
            shell_exec("sudo systemctl disable rbfeeder.service");
            $message = "Arranque automático DESACTIVADO";
            break;
    }
}

/* 💾 GUARDAR CONFIG */
if (isset($_POST['save_config'])) {
    file_put_contents($config_file, $_POST['config_content']);
    $message = "Configuración guardada";
    $view = "editor";
}

/* 📊 ESTADO SERVICIO */
$status = trim(shell_exec("systemctl is-active rbfeeder.service"));

/* 🔌 ESTADO AUTOARRANQUE */
$enabled = trim(shell_exec("systemctl is-enabled rbfeeder.service")) === "enabled";

/* 📄 CONFIG */
$config_content = file_exists($config_file) ? file_get_contents($config_file) : "";
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<title>Radarbox</title>

<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📡</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    background:#0f172a;
    color:#e2e8f0;
    font-family:Arial;
    padding:20px;
}

.box {
    background:#1e293b;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
}

button {
    padding:10px 18px;
    margin:5px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    background:#334155;
    color:white;
}

button:hover { background:#475569; }

.start   { background:#16a34a; }
.stop    { background:#dc2626; }
.restart { background:#ca8a04; }

/* Toggle autoarranque */
.autoarranque-on  { background:#2563eb; }
.autoarranque-off { background:#7c3aed; }
.autoarranque-on:hover  { background:#1d4ed8; }
.autoarranque-off:hover { background:#6d28d9; }

textarea {
    width:100%;
    height:400px;
    background:#020617;
    color:#e2e8f0;
    border:1px solid #334155;
    border-radius:8px;
    padding:10px;
    font-family:monospace;
}

.terminal {
    background:#020617;
    color:#22c55e;
    padding:15px;
    font-family:monospace;
    white-space:pre-wrap;
    height:400px;
    overflow-y:auto;
    border-radius:8px;
}

.active   { background:#16a34a; }
.inactive { background:#dc2626; }
</style>

<script>
function actualizarTerminal() {
    fetch("?logs=1")
    .then(res => res.text())
    .then(data => {
        let term = document.getElementById("terminal");
        if (term) {
            term.innerText = data;
            term.scrollTop = term.scrollHeight;
        }
    });
}

setInterval(() => {
    if (document.getElementById("terminal")) {
        actualizarTerminal();
    }
}, 2000);
</script>

</head>

<body>

<div class="box">
<h2>📡 Control Radarbox (rbfeeder)</h2>

<form method="post">

<button class="start"   name="action" value="start">▶️ Arrancar</button>
<button class="stop"    name="action" value="stop">⏹️ Parar</button>
<button class="restart" name="action" value="restart">🔄 Reiniciar</button>




<!-- 🔁 BOTÓN TOGGLE AUTOARRANQUE -->
<?php if ($enabled): ?>
    <button class="autoarranque-on" name="action" value="disable">
        ⚡ Autoarranque ON
    </button>
<?php else: ?>
    <button class="autoarranque-off" name="action" value="enable">
        ❌ Autoarranque OFF
    </button>
<?php endif; ?>

<!-- 📟 TERMINAL -->
<button type="submit" name="view" value="<?php echo ($view=='terminal') ? 'none' : 'terminal'; ?>">
📟 <?php echo ($view=='terminal') ? "Ocultar estado" : "Ver estado"; ?>
</button>

<!-- 📝 EDITOR -->
<button type="submit" name="view" value="<?php echo ($view=='editor') ? 'none' : 'editor'; ?>">
📝 <?php echo ($view=='editor') ? "Cerrar config" : "Editar config"; ?>
</button>



<a href="mmdvm.php">
    <button type="button">
        <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
    </button>
</a>


</form>

<p>
Estado:
<span class="<?php echo ($status=='active') ? 'active' : 'inactive'; ?>">
<?php echo $status; ?>
</span>
</p>

<p><?php echo $message; ?></p>
</div>

<!-- 📟 TERMINAL -->
<?php if ($view == "terminal"): ?>
<div class="box">
<h2>📟 Logs rbfeeder</h2>
<div id="terminal" class="terminal">Cargando logs...</div>
</div>
<?php endif; ?>

<!-- 📝 EDITOR -->
<?php if ($view == "editor"): ?>
<div class="box">
<h2>📝 rbfeeder.ini</h2>

<form method="post">
<textarea name="config_content"><?php echo htmlspecialchars($config_content); ?></textarea>
<br><br>

<button name="save_config">💾 Guardar</button>

<button type="submit" name="view" value="none" style="background:#dc2626;">
🚪 Cerrar sin guardar
</button>

</form>

</div>
<?php endif; ?>

</body>
</html>
