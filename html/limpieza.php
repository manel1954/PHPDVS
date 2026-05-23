<?php
// =========================================
//           LIMPIEZA DEL SISTEMA
//              LIGHT EDITION
// =========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$paths = [
    'mmdvm' => '/home/pi/MMDVMHost/*.log',
    'logs'  => '/var/log/*.gz',
    'logs2' => '/var/log/*.1'
];

function consola($msg) {
    return "<div class='linea'>" . htmlspecialchars($msg) . "</div>";
}

function borrar($pattern) {

    $files = glob($pattern);
    $deleted = 0;
    $out = "";

    $out .= consola("Escaneando: $pattern");

    if (!$files) {
        $out .= consola("Sin archivos");
        return [$deleted, $out];
    }

    foreach ($files as $f) {

        if (is_file($f)) {

            if (@unlink($f)) {
                $out .= consola("✔ Eliminado: $f");
                $deleted++;
            } else {
                $out .= consola("✖ Error permisos: $f");
            }
        }
    }

    return [$deleted, $out];
}

function ejecutar_comando($cmd) {

    $output = [];
    $return = 0;

    exec($cmd . " 2>&1", $output, $return);

    return [
        'ok' => ($return === 0),
        'out' => implode("\n", $output)
    ];
}

function limpiar_historial() {

    $out = "";

    $file = '/home/pi/.bash_history';

    if (file_exists($file)) {

        if (@file_put_contents($file, "")) {
            $out .= consola("✔ Historial limpiado");
        } else {
            $out .= consola("✖ Sin permisos historial");
        }
    }

    return $out;
}

function info_sistema() {

    $df = shell_exec("df -h / | awk 'NR==2'");
    $inode = shell_exec("df -i / | awk 'NR==2'");
    $uptime = shell_exec("uptime -p");

    $mem = shell_exec("free -m | awk 'NR==2{printf \"Usado: %sMB / Total: %sMB\", $3,$2}'");

    return [
        'disco'  => trim($df),
        'inode'  => trim($inode),
        'uptime' => trim($uptime),
        'ram'    => trim($mem)
    ];
}

// ==================
// ENGINE
// ==================

function ejecutar($opt) {

    global $paths;

    $report = [];
    $console = "";

    // =========================
    // MMDVM LOGS
    // =========================
    if (!empty($opt['mmdvm'])) {

        list($c, $log) = borrar($paths['mmdvm']);

        $report['MMDVM'] = $c;
        $console .= $log;
    }

    // =========================
    // LOGS SISTEMA
    // =========================
    if (!empty($opt['logs'])) {

        list($c1, $l1) = borrar($paths['logs']);
        list($c2, $l2) = borrar($paths['logs2']);

        $report['Logs'] = $c1 + $c2;
        $console .= $l1 . $l2;
    }

    // =========================
    // APT CLEAN (SEGURO)
    // =========================
    if (!empty($opt['apt'])) {

        ejecutar_comando("apt clean");
        ejecutar_comando("apt autoclean");
        ejecutar_comando("apt autoremove -y");

        $report['APT'] = "OK";
        $console .= consola("✔ APT limpiado");
    }

    // =========================
    // JOURNALCTL
    // =========================
    if (!empty($opt['journal'])) {

        ejecutar_comando("journalctl --vacuum-time=3d");
        ejecutar_comando("journalctl --vacuum-size=100M");

        $report['Journal'] = "OK";
        $console .= consola("✔ Journal limpiado");
    }

    // =========================
    // CACHE USUARIO
    // =========================
    if (!empty($opt['cache'])) {

        ejecutar_comando("rm -rf /home/pi/.cache/*");

        $report['Cache'] = "OK";
        $console .= consola("✔ Cache usuario limpiada");
    }

    // =========================
    // THUMBNAILS
    // =========================
    if (!empty($opt['thumb'])) {

        ejecutar_comando("rm -rf /home/pi/.thumbnails/*");
        ejecutar_comando("rm -rf /home/pi/.cache/thumbnails/*");

        $report['Thumbnails'] = "OK";
        $console .= consola("✔ Thumbnails limpiados");
    }

    return [$report, $console];
}

$report = null;
$console = "";
$sys = info_sistema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $opt = [
        'mmdvm'   => isset($_POST['mmdvm']),
        'logs'    => isset($_POST['logs']),
        'apt'     => isset($_POST['apt']),
        'journal' => isset($_POST['journal']),
        'cache'   => isset($_POST['cache']),
        'thumb'   => isset($_POST['thumb'])
    ];

    list($report, $console) = ejecutar($opt);

    $sys = info_sistema();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Limpieza del sistema</title>

<style>

body{
    margin:0;
    font-family:Arial;
    background:#0d1117;
    color:#e6edf3;
}

.contenedor{
    max-width:1150px;
    margin:30px auto;
    background:#161b22;
    padding:20px;
    border-radius:12px;
}

/* HEADER */
.top{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

h1{
    color:#c9d1d9;
}

.home{
    background:#21262d;
    color:#fff;
    padding:8px 12px;
    border-radius:8px;
    text-decoration:none;
}
.home:hover{background:#2a313c;}

/* GRID */
.grid-opciones{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
    gap:10px;
    margin-top:15px;
}

.opcion{
    background:#1c212a;
    border:1px solid #2a313c;
    padding:8px 12px;
    border-radius:8px;
    display:flex;
    align-items:center;
    gap:8px;
    cursor:pointer;
}

.opcion input{
    accent-color:#4c8bf5;
    transform:scale(1.1);
}

.opcion span{
    font-size:13px;
    color:#c9d1d9;
}

/* BOTÓN */
button{
    width:100%;
    margin-top:15px;
    padding:14px;
    border:none;
    border-radius:8px;
    background:#1f6feb;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#2f81f7;
}

/* CONSOLA */
.consola{
    margin-top:15px;
    background:#0b0f14;
    padding:10px;
    height:110px;
    overflow:auto;
    font-family:monospace;
    font-size:12px;
    border-radius:8px;
    border:1px solid #222;
}

.linea{
    color:#8ab4f8;
}

/* RESULTADOS */
.card{
    display:flex;
    justify-content:space-between;
    background:#1c212a;
    padding:10px;
    margin:6px 0;
    border-radius:8px;
    border:1px solid #2a313c;
}

.badge{
    background:#30363d;
    color:#fff;
    padding:3px 8px;
    border-radius:6px;
}

/* SISTEMA */
.sysgrid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:10px;
    margin-top:20px;
}

.syscard{
    background:#161b22;
    border:1px solid #2a313c;
    padding:15px;
    border-radius:10px;
}

.syscard h3{
    margin:0 0 10px 0;
    font-size:14px;
    color:#8ab4f8;
}

.sysvalue{
    font-size:11px;
    white-space:pre-wrap;
}

</style>
</head>

<body>

<div class="contenedor">

<div class="top">
<h1>🧹 Limpieza del sistema</h1>
<a class="home" href="mmdvm.php">🏠 Panel PHPPLUS</a>
</div>

<form method="post">

<div class="grid-opciones">

<label class="opcion">
<input type="checkbox" name="mmdvm" checked>
<span>MMDVM logs</span>
</label>

<label class="opcion">
<input type="checkbox" name="logs">
<span>Logs sistema</span>
</label>

<label class="opcion">
<input type="checkbox" name="apt">
<span>APT cache</span>
</label>

<label class="opcion">
<input type="checkbox" name="journal">
<span>Journal</span>
</label>

<label class="opcion">
<input type="checkbox" name="cache">
<span>Cache usuario</span>
</label>

<label class="opcion">
<input type="checkbox" name="thumb">
<span>Thumbnails</span>
</label>

</div>

<button type="submit">🚀 Ejecutar limpieza</button>

</form>

<?php if ($report): ?>
<div class="mt-3">
<?php foreach ($report as $k => $v): ?>
<div class="card">
<span><?= htmlspecialchars($k) ?></span>
<span class="badge"><?= htmlspecialchars($v) ?></span>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($console): ?>
<div class="consola"><?= $console ?></div>
<?php endif; ?>

<div class="sysgrid">

<div class="syscard">
<h3>💾 Disco</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['disco']) ?></div>
</div>

<div class="syscard">
<h3>🧩 Inodos</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['inode']) ?></div>
</div>

<div class="syscard">
<h3>🧠 RAM</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['ram']) ?></div>
</div>

<div class="syscard">
<h3>⏱️ Uptime</h3>
<div class="sysvalue"><?= htmlspecialchars($sys['uptime']) ?></div>
</div>

</div>

</div>

</body>
</html>
