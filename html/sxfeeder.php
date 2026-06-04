<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');

/* ========== FUNCIÓN PARA EXTRAER PUERTO WEB DINÁMICO ========== */
function getWebPortFromConfig($configFile) {
    if (!file_exists($configFile) || filesize($configFile) === 0) {
        return 8080;
    }
    
    $content = file_get_contents($configFile);
    
    if (empty(trim($content))) {
        return 8080;
    }
    
    if (preg_match('/-N\s*(\d+)/', $content, $matches)) {
        return (int)$matches[1];
    }
    
    return 8080;
}

$SERVICES = [
    'ais' => [
        'name'    => 'AIS-catcher',
        'systemd' => 'ais-catcher.service',
        'config'  => '/etc/AIS-catcher/config.cmd',
        'webport' => 8080
    ],
    'sxfeeder' => [
        'name'    => 'SXFeeder',
        'systemd' => 'sxfeeder.service',
        'config'  => '/etc/sxfeeder.ini',
        'webport' => null
    ]
];

$serviceKey = $_GET['service'] ?? 'ais';
$action = $_GET['action'] ?? '';

if (!isset($SERVICES[$serviceKey])) die('Servicio inválido');

$SVC = $SERVICES[$serviceKey];
$SYSTEMD = $SVC['systemd'];
$CONFIG_FILE = $SVC['config'];

$WEB_PORT = ($serviceKey === 'ais') ? getWebPortFromConfig($CONFIG_FILE) : null;

/* ================= STATUS ================= */
if ($action === 'status') {
    $st  = trim(shell_exec("systemctl is-active $SYSTEMD 2>/dev/null"));
    $en  = trim(shell_exec("systemctl is-enabled $SYSTEMD 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode([
        'active'  => $st === 'active',
        'status'  => $st,
        'enabled' => $en === 'enabled'
    ]);
    exit;
}

/* ================= ON ================= */
if ($action === 'on') {
    shell_exec("sudo systemctl enable $SYSTEMD 2>/dev/null");
    shell_exec("sudo systemctl start $SYSTEMD 2>/dev/null");

    sleep(1);
    $st = trim(shell_exec("systemctl is-active $SYSTEMD 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode($st === 'active'
        ? ['ok'=>true,'msg'=>"$SVC[name] iniciado"]
        : ['ok'=>false,'error'=>'No arrancó']);
    exit;
}

/* ================= OFF ================= */
if ($action === 'off') {
    shell_exec("sudo systemctl stop $SYSTEMD 2>/dev/null");
    shell_exec("sudo systemctl disable $SYSTEMD 2>/dev/null");

    sleep(1);
    $st = trim(shell_exec("systemctl is-active $SYSTEMD 2>/dev/null"));

    header('Content-Type: application/json');
    echo json_encode($st !== 'active'
        ? ['ok'=>true,'msg'=>"$SVC[name] detenido"]
        : ['ok'=>false,'error'=>'No se pudo detener']);
    exit;
}

/* ================= LOG ================= */
if ($action === 'log') {
    header('Content-Type: text/plain');
    echo shell_exec("sudo journalctl -u $SYSTEMD -n 80 --no-pager 2>/dev/null");
    exit;
}

/* ================= CONFIG ================= */
if ($action === 'config-read') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'=>true,
        'content'=>file_get_contents($CONFIG_FILE)
    ]);
    exit;
}

if ($action === 'config-save') {
    header('Content-Type: application/json');
    file_put_contents($CONFIG_FILE, $_POST['content'] ?? '');
    echo json_encode(['ok'=>true]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $SVC['name'] ?> · Ship Control</title>

<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚢</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --bg-primary: #0b1220;
    --bg-secondary: #111827;
    --bg-tertiary: #1f2937;
    --border: #1f2937;
    --border-light: #374151;
    --text-primary: #f3f4f6;
    --text-secondary: #9ca3af;
    --accent: #3b82f6;
    --accent-hover: #2563eb;
    --success: #10b981;
    --success-hover: #059669;
    --danger: #ef4444;
    --warning: #f59e0b;
    --admin: #8b5cf6;
    --admin-hover: #7c3aed;
}

* { box-sizing: border-box; }

body {
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, sans-serif;
    min-height: 100vh;
    margin: 0;
    font-size: 14px;
    background-image:
        radial-gradient(circle at 15% 10%, rgba(59,130,246,0.08), transparent 40%),
        radial-gradient(circle at 85% 90%, rgba(139,92,246,0.06), transparent 40%);
    background-attachment: fixed;
}

/* TOPBAR */
.topbar {
    background: rgba(17, 24, 39, 0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 14px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 16px;
    letter-spacing: 0.3px;
}

.brand-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3b82f6, #06b6d4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
    transition: all 0.2s ease;
}

/* LOGO CLICKEABLE */
.brand-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    transition: opacity 0.2s ease;
}

.brand-link:hover {
    opacity: 0.85;
}

.brand-link:hover .brand-icon {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(59,130,246,0.3);
}

.brand-link:hover .brand > div:last-child {
    color: var(--accent);
    transition: color 0.2s ease;
}

.topbar-right {
    display: flex;
    align-items: center;
}

/* LAYOUT */
.container-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 28px;
}

/* CARDS */
.card-panel {
    background: rgba(17, 24, 39, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 22px;
    overflow: hidden;
    transition: border-color 0.2s;
}

.card-panel:hover {
    border-color: var(--border-light);
}

.card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(31, 41, 55, 0.3);
}

.card-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.card-header h3 i {
    color: var(--accent);
    font-size: 16px;
}

.card-body {
    padding: 22px;
}

/* SELECTOR DE SERVICIO (TABS) */
.service-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 22px;
    background: var(--bg-tertiary);
    padding: 6px;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.service-tab {
    flex: 1;
    padding: 10px 16px;
    text-align: center;
    border-radius: 7px;
    text-decoration: none;
    color: var(--text-secondary);
    font-size: 13px;
    font-weight: 500;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.service-tab:hover {
    color: var(--text-primary);
    background: rgba(255,255,255,0.03);
}

.service-tab.active {
    background: var(--accent);
    color: white;
    box-shadow: 0 2px 8px rgba(59,130,246,0.3);
}

/* BOTONES */
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: var(--bg-tertiary);
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}

.btn-action:hover {
    background: #374151;
    border-color: #4b5563;
    color: var(--text-primary);
    transform: translateY(-1px);
}

.btn-action i { font-size: 14px; }

.btn-primary-act {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}
.btn-primary-act:hover {
    background: var(--accent-hover);
    border-color: var(--accent-hover);
    color: white;
}

.btn-admin {
    background: var(--admin);
    border-color: var(--admin);
    color: white;
}
.btn-admin:hover {
    background: var(--admin-hover);
    border-color: var(--admin-hover);
    color: white;
}

.btn-config {
    background: rgba(107, 114, 128, 0.15);
    border-color: rgba(107, 114, 128, 0.35);
    color: #d1d5db;
}
.btn-config:hover {
    background: rgba(107, 114, 128, 0.25);
    border-color: #6b7280;
    color: white;
}

.btn-log {
    background: rgba(59, 130, 246, 0.12);
    border-color: rgba(59, 130, 246, 0.35);
    color: #93c5fd;
}
.btn-log:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: var(--accent);
    color: #bfdbfe;
}

.btn-phpplus {
    background: var(--admin);
    border-color: var(--admin);
    color: white;
}
.btn-phpplus:hover {
    background: var(--admin-hover);
    border-color: var(--admin-hover);
    color: white;
}

/* SWITCH MODERNO */
.switch-container {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 22px;
    background: var(--bg-tertiary);
    border-radius: 12px;
    border: 1px solid var(--border);
    margin-bottom: 22px;
}

.switch {
    position: relative;
    width: 56px;
    height: 30px;
    display: inline-block;
    flex-shrink: 0;
}

.switch input { display: none; }

.slider {
    position: absolute;
    inset: 0;
    background: #374151;
    cursor: pointer;
    transition: .3s;
    border-radius: 999px;
    border: 1px solid #4b5563;
}

.slider:before {
    content: '';
    position: absolute;
    height: 22px;
    width: 22px;
    left: 3px;
    top: 3px;
    background: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input:checked + .slider {
    background: var(--success);
    border-color: var(--success);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.switch-info {
    flex: 1;
}

.switch-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    margin-bottom: 4px;
    font-weight: 600;
}

.switch-status {
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-on { color: #34d399; }
.status-off { color: #f87171; }

/* INFO GRID */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.info-item {
    background: var(--bg-tertiary);
    padding: 14px 16px;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.info-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-secondary);
    margin-bottom: 6px;
    font-weight: 600;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* BUTTON GROUPS */
.btn-group-custom {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.section-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    margin-bottom: 10px;
    font-weight: 600;
}

/* TERMINAL */
.terminal-window {
    background: #0a0f1a;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    font-family: "SF Mono", "Monaco", "Menlo", "Consolas", monospace;
}

.terminal-header {
    background: #111827;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-secondary);
}

.terminal-dots {
    display: flex;
    gap: 6px;
}

.terminal-dots span {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #374151;
}
.terminal-dots span:nth-child(1) { background: #ef4444; }
.terminal-dots span:nth-child(2) { background: #f59e0b; }
.terminal-dots span:nth-child(3) { background: #10b981; }

.terminal-body {
    padding: 16px;
    color: #10b981;
    font-size: 12.5px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    height: 420px;
    overflow-y: auto;
    margin: 0;
}

.terminal-body::-webkit-scrollbar { width: 8px; }
.terminal-body::-webkit-scrollbar-track { background: #0a0f1a; }
.terminal-body::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }

/* EDITOR */
.config-editor {
    width: 100%;
    min-height: 300px;
    background: #0a0f1a;
    color: #e5e7eb;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    font-family: "SF Mono", "Monaco", "Menlo", monospace;
    font-size: 13px;
    line-height: 1.55;
    resize: vertical;
}

.config-editor:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.hidden { display: none !important; }

@media (max-width: 640px) {
    .container-main { padding: 16px; }
    .topbar { padding: 12px 16px; }
    .card-body { padding: 16px; }
    .service-tab { font-size: 12px; padding: 8px 10px; }
}
</style>
</head>

<body>

<!-- TOPBAR -->
<div class="topbar">
    <a href="https://www.shipxplorer.com/" target="_blank" rel="noopener" class="brand-link">
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-tsunami"></i></div>
            <div>
                Ship <span style="color: var(--text-secondary); font-weight: 400;">· AIS Tracker</span>
            </div>
        </div>
    </a>
    
    <div class="topbar-right">
        <a href="mmdvm.php" class="btn-action btn-phpplus" style="padding: 7px 14px; font-size: 12px;">
            <i class="bi bi-house-door-fill"></i> Panel PHPPLUS
        </a>
    </div>
</div>

<div class="container-main">

    <!-- SELECTOR DE SERVICIO -->
    <div class="service-tabs">
        <a href="?service=ais" class="service-tab <?= $serviceKey==='ais'?'active':'' ?>">
            <i class="bi bi-broadcast-pin"></i> AIS-catcher
        </a>
        <a href="?service=sxfeeder" class="service-tab <?= $serviceKey==='sxfeeder'?'active':'' ?>">
            <i class="bi bi-diagram-3"></i> SXFeeder
        </a>
    </div>

    <!-- PANEL PRINCIPAL -->
    <div class="card-panel">
        <div class="card-header">
            <h3><i class="bi bi-sliders"></i> <?= $SVC['name'] ?></h3>
            <span style="font-size: 12px; color: var(--text-secondary);">
                <i class="bi bi-clock"></i> <span id="clock"><?= date('d/m/Y H:i') ?></span>
            </span>
        </div>
        <div class="card-body">

            <!-- INFO ESTADO -->
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Servicio</div>
                    <div class="info-value" id="infoStatus">
                        <i class="bi bi-hourglass-split" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-secondary);">Consultando...</span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Autoarranque</div>
                    <div class="info-value" id="infoEnabled">
                        <i class="bi bi-hourglass-split" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-secondary);">Consultando...</span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Configuración</div>
                    <div class="info-value">
                        <i class="bi bi-file-earmark-code" style="color: var(--accent);"></i>
                        <span style="font-family: monospace; font-size: 12px;"><?= basename($CONFIG_FILE) ?></span>
                    </div>
                </div>
                <?php if ($serviceKey === 'ais'): ?>
                <div class="info-item">
                    <div class="info-label">Puerto Web</div>
                    <div class="info-value">
                        <i class="bi bi-ethernet" style="color: var(--accent);"></i>
                        <span style="font-family: monospace;"><?= $WEB_PORT ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- SWITCH ON/OFF -->
            <div class="switch-container">
                <label class="switch">
                    <input type="checkbox" id="sw" onchange="toggle()">
                    <span class="slider"></span>
                </label>
                <div class="switch-info">
                    <div class="switch-label">Estado del servicio</div>
                    <div class="switch-status" id="state">
                        <i class="bi bi-hourglass-split"></i> Consultando...
                    </div>
                </div>
            </div>

            <!-- BOTONES DE ACCIÓN -->
            <div class="section-label">Acciones</div>
            <div class="btn-group-custom" style="margin-bottom: 22px;">
                <button class="btn-action btn-config" onclick="toggleCfg()">
                    <i class="bi bi-gear-fill"></i> Configuración
                </button>
                <button class="btn-action btn-log" onclick="toggleLog()">
                    <i class="bi bi-terminal-fill"></i> Ver Logs
                </button>
                <?php if ($serviceKey === 'ais'): ?>
                <button class="btn-action btn-primary-act" onclick="openWeb()">
                    <i class="bi bi-broadcast-pin"></i> WEB AIS
                </button>
                <button class="btn-action btn-admin" onclick="openAdmin()">
                    <i class="bi bi-shield-lock-fill"></i> WEB ADMIN
                </button>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- PANEL CONFIGURACIÓN -->
    <div id="cfgPanel" class="card-panel hidden">
        <div class="card-header">
            <h3><i class="bi bi-file-earmark-code"></i> Editor · <?= basename($CONFIG_FILE) ?></h3>
            <span style="font-size: 11px; color: var(--text-secondary);">
                <i class="bi bi-pencil-square"></i> Editando
            </span>
        </div>
        <div class="card-body">
            <textarea id="cfgTxt" class="config-editor"></textarea>
            <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-action btn-primary-act" onclick="saveCfg()">
                    <i class="bi bi-check2-circle"></i> Guardar cambios
                </button>
                <button class="btn-action btn-config" onclick="toggleCfg()">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- PANEL LOGS -->
    <div id="logPanel" class="card-panel hidden">
        <div class="card-header">
            <h3><i class="bi bi-terminal-fill"></i> Terminal en vivo</h3>
            <span style="font-size: 11px; color: var(--text-secondary);">
                <i class="bi bi-journal-text"></i> journalctl · <?= $SYSTEMD ?>
            </span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="terminal-window" style="border-radius: 0; border: none;">
                <div class="terminal-header">
                    <div class="terminal-dots"><span></span><span></span><span></span></div>
                    <span style="margin-left: 8px;">Últimas 80 líneas</span>
                </div>
                <pre id="logContent" class="terminal-body">Cargando logs...</pre>
            </div>
        </div>
    </div>

    <div style="text-align: center; padding: 20px 0 10px; color: var(--text-secondary); font-size: 11px; letter-spacing: 0.5px;">
        SHIP TRACKER CONTROL PANEL · <?= date('Y') ?>
    </div>

</div>

<script>

const svc='<?= $serviceKey ?>';
const webPort = <?= ($WEB_PORT !== null) ? intval($WEB_PORT) : 'null' ?>;

/* API */
function api(a,p=null){
    return fetch('?service='+svc+'&action='+a,{
        method:p?'POST':'GET',
        headers:p?{'Content-Type':'application/x-www-form-urlencoded'}:{},
        body:p
    });
}

/* STATUS */
async function status(){
    const r=await api('status');
    const d=await r.json();

    document.getElementById('sw').checked=d.active;

    // Estado principal (switch)
    document.getElementById('state').innerHTML = d.active
        ? '<i class="bi bi-check-circle-fill"></i> <span class="status-on">ACTIVO</span>'
        : '<i class="bi bi-x-circle-fill"></i> <span class="status-off">'+d.status.toUpperCase()+'</span>';

    // Info grid - Servicio
    document.getElementById('infoStatus').innerHTML = d.active
        ? '<i class="bi bi-check-circle-fill" style="color:#34d399;"></i> <span style="color:#34d399;">Active</span>'
        : '<i class="bi bi-x-circle-fill" style="color:#f87171;"></i> <span style="color:#f87171;">'+d.status.charAt(0).toUpperCase()+d.status.slice(1)+'</span>';

    // Info grid - Autoarranque
    document.getElementById('infoEnabled').innerHTML = d.enabled
        ? '<i class="bi bi-lightning-charge-fill" style="color:#34d399;"></i> <span style="color:#34d399;">Enabled</span>'
        : '<i class="bi bi-lightning-charge" style="color:#9ca3af;"></i> <span style="color:#9ca3af;">Disabled</span>';
}

/* SWITCH */
async function toggle(){
    if(document.getElementById('sw').checked){
        await api('on');
    }else{
        await api('off');
    }
    status();
}

/* WEB AIS ONLY - Puerto Dinámico */
function openWeb(){
    if(svc!=='ais') return alert('SXFeeder no tiene web');
    const port = (webPort !== null && webPort > 0) ? webPort : 8080;
    window.open('http://'+location.hostname+':'+port,'_blank');
}

/* WEB ADMIN - Puerto Fijo 8110 */
function openAdmin(){
    if(svc!=='ais') return alert('SXFeeder no tiene panel Admin');
    window.open('http://'+location.hostname+':8110','_blank');
}

/* CONFIG TOGGLE */
let cfgOpen=false;
function toggleCfg(){
    cfgOpen=!cfgOpen;
    document.getElementById('cfgPanel').classList.toggle('hidden');
    if(cfgOpen) loadCfg();
}

async function loadCfg(){
    const r=await api('config-read');
    const d=await r.json();
    document.getElementById('cfgTxt').value=d.content;
}

/* GUARDAR CONFIG Y RECARGAR */
async function saveCfg(){
    await api('config-save','content='+encodeURIComponent(document.getElementById('cfgTxt').value));
    
    setTimeout(() => {
        location.reload();
    }, 500);
}

/* LOG TOGGLE */
let logOpen=false;
function toggleLog(){
    logOpen=!logOpen;
    document.getElementById('logPanel').classList.toggle('hidden');
    if(logOpen) loadLog();
}

async function loadLog(){
    const r=await api('log');
    document.getElementById('logContent').textContent=await r.text();
}

/* INIT */
setInterval(status,3000);
status();

</script>

</body>
</html>
