<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');

$CONFIG_FILE = '/home/pi/dump1090-fa/dump1090.args';
$action = $_GET['action'] ?? '';

// ── Leer configuración ───────────────────────────────────────────────────────
if ($action === 'config-read') {
    header('Content-Type: application/json');
    if (!file_exists($CONFIG_FILE)) {
        echo json_encode(['ok'=>false, 'error'=>'Archivo de configuración no encontrado']);
        exit;
    }
    echo json_encode(['ok'=>true, 'content'=>htmlspecialchars(file_get_contents($CONFIG_FILE))]);
    exit;
}

// ── Guardar configuración ────────────────────────────────────────────────────
if ($action === 'config-save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $newContent = $_POST['content'] ?? '';
    if (preg_match('/[;|&`$()<>]/', $newContent)) {
        echo json_encode(['ok'=>false, 'error'=>'Caracteres no permitidos']);
        exit;
    }
    if (!is_writable($CONFIG_FILE)) {
        echo json_encode(['ok'=>false, 'error'=>'Sin permisos. Ejecuta: sudo chown www-www-data '.$CONFIG_FILE]);
        exit;
    }
    echo json_encode(file_put_contents($CONFIG_FILE, $newContent) !== false
        ? ['ok'=>true, 'msg'=>'✅ Configuración guardada. Reinicia el servicio.']
        : ['ok'=>false, 'error'=>'Error al guardar']);
    exit;
}

// ── Iniciar servicio ─────────────────────────────────────────────────────────
if ($action === 'dump1090-start') {
    shell_exec('sudo systemctl start dump1090-fa 2>/dev/null');
    $st = 'activating';
    for ($i = 0; $i < 15; $i++) {
        sleep(1);
        $st = trim(shell_exec('systemctl is-active dump1090-fa 2>/dev/null'));
        if ($st !== 'activating') break;
    }
    header('Content-Type: application/json');
    echo json_encode($st === 'active'
        ? ['ok'=>true, 'output'=>'dump1090-fa iniciado correctamente']
        : ['ok'=>false, 'error'=>'No arrancó (estado: '.$st.')']);
    exit;
}

// ── Parar servicio ───────────────────────────────────────────────────────────
if ($action === 'dump1090-stop') {
    shell_exec('sudo systemctl stop dump1090-fa 2>/dev/null');
    sleep(1);
    $st = trim(shell_exec('systemctl is-active dump1090-fa 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode($st !== 'active'
        ? ['ok'=>true, 'msg'=>'dump1090-fa detenido correctamente']
        : ['ok'=>false, 'msg'=>'No se pudo detener (estado: '.$st.')']);
    exit;
}

// ── Estado servicio + autoarranque ───────────────────────────────────────────
if ($action === 'dump1090-status') {
    $st  = trim(shell_exec('systemctl is-active dump1090-fa 2>/dev/null'));
    $pid = trim(shell_exec('systemctl show dump1090-fa --property=MainPID --value 2>/dev/null'));
    $en  = trim(shell_exec('systemctl is-enabled dump1090-fa 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode([
        'active'  => $st === 'active',
        'status'  => $st,
        'pid'     => $pid,
        'enabled' => ($en === 'enabled')
    ]);
    exit;
}

// ── Enable autoarranque ──────────────────────────────────────────────────────
if ($action === 'dump1090-enable') {
    shell_exec('sudo systemctl enable dump1090-fa 2>/dev/null');
    sleep(1);
    header('Content-Type: application/json');
    echo json_encode(trim(shell_exec('systemctl is-enabled dump1090-fa 2>/dev/null')) === 'enabled'
        ? ['ok'=>true, 'msg'=>'✅ Autostart activado']
        : ['ok'=>false, 'error'=>'No se pudo activar']);
    exit;
}

// ── Disable autoarranque ─────────────────────────────────────────────────────
if ($action === 'dump1090-disable') {
    shell_exec('sudo systemctl disable dump1090-fa 2>/dev/null');
    sleep(1);
    header('Content-Type: application/json');
    echo json_encode(trim(shell_exec('systemctl is-enabled dump1090-fa 2>/dev/null')) !== 'enabled'
        ? ['ok'=>true, 'msg'=>'✅ Autostart desactivado']
        : ['ok'=>false, 'error'=>'No se pudo desactivar']);
    exit;
}

// ── Log ──────────────────────────────────────────────────────────────────────
if ($action === 'dump1090-log') {
    header('Content-Type: text/plain');
    $log = shell_exec('sudo journalctl -u dump1090-fa -n 80 --no-pager --output=short 2>/dev/null');
    echo !empty(trim($log)) ? $log : '(sin log disponible)';
    exit;
}

// ── Terminal ─────────────────────────────────────────────────────────────────
if ($action === 'terminal') {
    $cmd = trim($_POST['cmd'] ?? '');
    if (preg_match('/^\s*(vim|vi|less|more|top|htop|su)\s*/i', $cmd)) {
        header('Content-Type: application/json');
        echo json_encode(['output'=>'Comando interactivo no soportado.']); exit;
    }
    if (preg_match('/(rm\s+-rf|shutdown|mkfs|dd\s+if=|nano\s+|vi\s+|vim\s+)/i', $cmd)) {
        header('Content-Type: application/json');
        echo json_encode(['output'=>'❌ Comando bloqueado por seguridad']); exit;
    }
    $out = $cmd !== '' ? (shell_exec('/usr/bin/sudo -n -u pi -H bash -c '.escapeshellarg($cmd).' 2>&1') ?? '') : '';
    header('Content-Type: application/json');
    echo json_encode(['output'=>htmlspecialchars($out)]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>✈ dump1090-fa · Control</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0a0e14; --surface: #111720; --border: #1e2d3d;
    --green: #00ff9f; --red: #ff4560; --cyan: #00d4ff; --amber: #ffb300;
    --text: #a8b9cc; --text-dim: #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-ui: 'Rajdhani', sans-serif;
    --font-orb: 'Orbitron', monospace;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); height: 100vh; display: flex; flex-direction: column; overflow: hidden; }

.ex-header { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1.4rem; background: var(--surface); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.ex-title { font-family: var(--font-orb); font-size: 1rem; font-weight: 700; color: var(--cyan); letter-spacing: .1em; text-transform: uppercase; }
.ex-subtitle { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); letter-spacing: .1em; margin-top: .15rem; }
.ex-btns { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; }

.btn-ex { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; border-radius: 4px; padding: .28rem .85rem; cursor: pointer; transition: background .2s, opacity .2s; border: 1px solid; background: transparent; }
.btn-ex:disabled { opacity: .4; cursor: not-allowed; }
.btn-cyan:hover:not(:disabled)  { background: rgba(0,212,255,.1); }
.btn-green:hover:not(:disabled) { background: rgba(0,255,159,.1); }
.btn-red:hover:not(:disabled)   { background: rgba(255,69,96,.15); }
.btn-amber:hover:not(:disabled) { background: rgba(255,179,0,.15); }
.btn-cyan  { color: var(--cyan);  border-color: var(--cyan);  }
.btn-green { color: var(--green); border-color: var(--green); }
.btn-red   { color: var(--red);   border-color: var(--red);   }
.btn-amber { color: var(--amber); border-color: var(--amber); }
.btn-dim   { color: var(--text-dim); border-color: #1e2d3d; }
.btn-active { background: rgba(0,212,255,.15) !important; border-color: var(--cyan) !important; color: var(--cyan) !important; }

.sw { position: relative; width: 56px; height: 28px; flex-shrink: 0; cursor: pointer; }
.sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid var(--red); transition: background .3s, border-color .3s; }
.sw-knob  { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: var(--red); transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s, box-shadow .3s; }
.sw input:checked ~ .sw-track { border-color: var(--green); }
.sw input:checked ~ .sw-knob  { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw-busy-dot { display: none; position: absolute; top: 50%; right: -20px; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--amber); border-top-color: transparent; animation: spin .7s linear infinite; }
.sw.busy .sw-busy-dot { display: block; }
@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

.auto-bar { display: flex; align-items: center; gap: .8rem; padding: .5rem 1.4rem; background: rgba(0,0,0,.2); border-bottom: 1px solid var(--border); flex-shrink: 0; }
.auto-label { font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim); letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
.auto-status { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .05em; min-width: 70px; transition: color .3s; text-align: right; }
.auto-status.on  { color: var(--green); }
.auto-status.off { color: var(--red); }

.ex-status { display: flex; align-items: center; gap: 1.2rem; padding: .45rem 1.4rem; background: rgba(0,0,0,.3); border-bottom: 1px solid var(--border); flex-shrink: 0; font-family: var(--font-mono); font-size: .72rem; flex-wrap: wrap; }
.dot-status { width: 8px; height: 8px; border-radius: 50%; background: var(--text-dim); display: inline-block; margin-right: .4rem; transition: background .4s, box-shadow .4s; }
.dot-status.on  { background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 2s infinite; }
.dot-status.err { background: var(--red);   box-shadow: 0 0 6px var(--red); }
.dot-status.activating { background: var(--amber); box-shadow: 0 0 6px var(--amber); animation: pulse-amber 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
@keyframes pulse-amber { 0%,100%{opacity:1;box-shadow:0 0 6px var(--amber)} 50%{opacity:.6;box-shadow:0 0 2px var(--amber)} }
.sep { color: var(--border); }

.ex-tabs { display: flex; gap: .4rem; padding: .6rem 1.4rem; background: var(--surface); border-bottom: 1px solid var(--border); flex-shrink: 0; }

.ex-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.tab-pane { flex: 1; display: none; flex-direction: column; overflow: hidden; }
.tab-pane.active { display: flex; }

.xterm-out { font-family: var(--font-mono); font-size: .75rem; color: #7a9ab5; background: #060c10; padding: 1rem 1.4rem; flex: 1; overflow-y: auto; white-space: pre-wrap; word-break: break-all; line-height: 1.55; }
.xterm-out::-webkit-scrollbar { width: 4px; }
.xterm-out::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.xterm-row { display: flex; align-items: center; gap: .5rem; background: #060c10; border-top: 1px solid var(--border); padding: .5rem 1.4rem; flex-shrink: 0; }
.xterm-pr  { font-family: var(--font-mono); font-size: .78rem; color: #00ff9f; white-space: nowrap; }
.xterm-inp { flex: 1; background: transparent; border: none; outline: none; font-family: var(--font-mono); font-size: .78rem; color: #c9d1d9; caret-color: #00ff9f; }
.xt-cmd { color: #c9d1d9; }
.xt-out { color: #7a9ab5; }
.xt-err { color: #f85149; }
.xt-ok  { color: var(--green); }

.config-wrap { flex: 1; display: flex; flex-direction: column; padding: 1rem 1.4rem; overflow: hidden; }
.config-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: .8rem; }
.config-path { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); }
.config-actions { display: flex; gap: .5rem; }
.config-editor { flex: 1; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; font-family: var(--font-mono); font-size: .75rem; color: #c9d1d9; resize: none; outline: none; line-height: 1.6; }
.config-editor:focus { border-color: var(--cyan); box-shadow: 0 0 0 2px rgba(0,212,255,.2); }
.config-hint { font-size: .7rem; color: var(--text-dim); margin-top: .5rem; }

.launch-card { margin: 2rem auto; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 2rem 2.5rem; max-width: 480px; text-align: center; }
.launch-icon  { font-size: 3rem; margin-bottom: 1rem; }
.launch-title { font-family: var(--font-orb); font-size: 1.1rem; color: var(--cyan); letter-spacing: .08em; margin-bottom: .6rem; }
.launch-desc  { font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim); line-height: 1.6; margin-bottom: 1.5rem; }
.launch-params { text-align: left; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem 1rem; margin-bottom: 1.5rem; font-family: var(--font-mono); font-size: .72rem; color: var(--amber); line-height: 1.7; }
.launch-params a { color: var(--cyan); text-decoration: none; border-bottom: 1px dashed var(--cyan); cursor: pointer; }
.launch-params a:hover { opacity: .85; }
.status-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .6rem; background: #060c10; border: 1px solid var(--border); border-radius: 4px; font-family: var(--font-mono); font-size: .7rem; margin: .3rem 0; }
.status-dot { width: 6px; height: 6px; border-radius: 50%; }
.status-dot.on  { background: var(--green); box-shadow: 0 0 4px var(--green); }
.status-dot.off { background: var(--red); }
.status-dot.activating { background: var(--amber); box-shadow: 0 0 4px var(--amber); animation: pulse-amber 1s infinite; }
</style>
</head>
<body>

<header class="ex-header">
    <div>
        <div class="ex-title">✈ dump1090-fa · Control</div>
        <div class="ex-subtitle">SDR · FlightAware · dump1090-fa.service</div>
    </div>
    <div class="ex-btns">
        <label class="sw" id="swDump" title="Iniciar / Parar dump1090-fa.service">
            <input type="checkbox" id="chkDump" onchange="toggleDump1090(this)">
            <span class="sw-track"></span>
            <span class="sw-knob"></span>
            <span class="sw-busy-dot"></span>
        </label>
        <span id="swLabel" style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);letter-spacing:.08em;text-transform:uppercase;min-width:2rem;">OFF</span>
        <button class="btn-ex btn-green" onclick="fetchDump1090Log()">⟳ Log</button>
        <a href="/mmdvm.php" class="btn-ex btn-red" style="text-decoration:none;">✖ Cerrar</a>
    </div>
</header>

<div class="auto-bar">
    <span class="auto-label">🔌 Autostart</span>
    <label class="sw" id="swAuto">
        <input type="checkbox" id="chkAuto" onchange="toggleAutoStart(this)">
        <span class="sw-track"></span>
        <span class="sw-knob"></span>
        <span class="sw-busy-dot"></span>
    </label>
    <span class="auto-status off" id="autoStatus">OFF</span>
</div>

<div class="ex-status">
    <span><span class="dot-status" id="dotStatus"></span><span id="statusTxt">Comprobando servicio…</span></span>
    <span class="sep">|</span>
    <span style="color:var(--text-dim)">Servicio: <span id="svcStatus" style="color:var(--amber)">—</span></span>
    <span class="sep">|</span>
    <span style="color:var(--text-dim)">PID: <span id="svcPid" style="color:var(--cyan)">—</span></span>
</div>

<div class="ex-tabs">
    <button id="tabBtnLog" class="btn-ex btn-active" onclick="switchTab('log')">📋 Terminal</button>
    <button id="tabBtnMap" class="btn-ex btn-dim"    onclick="openMapPage()">✈ Mapa</button>
    <button id="tabBtnCfg" class="btn-ex btn-dim"    onclick="switchTab('cfg')">⚙ Config</button>
</div>

<div class="ex-content">
    <div id="paneLog" class="tab-pane active">
        <div id="launchCard" class="launch-card">
            <div class="launch-icon">✈</div>
            <div class="launch-title">dump1090-fa · ADS-B</div>
            <div class="launch-desc">Activa el toggle superior para arrancar el servicio con tus parámetros configurados.</div>
            
            <div style="margin: 1rem 0; display: flex; flex-direction: column; gap: .4rem; align-items: center;">
                <div class="status-badge">
                    <span class="status-dot" id="cardSvcDot"></span>
                    <span id="cardSvcText">Servicio: —</span>
                </div>
                <div class="status-badge">
                    <span class="status-dot" id="cardAutoDot"></span>
                    <span id="cardAutoText">Autostart: —</span>
                </div>
            </div>
            
            <div class="launch-params">
                ⚙ Servicio: dump1090-fa.service<br>
                📝 Log:     journalctl -u dump1090-fa<br>
                ⚙ Config:  /home/pi/dump1090-fa/dump1090.args<br>
                🔗 <strong>Mapa:</strong> <a href="dump1090monitor.php" target="_blank" rel="noopener">dump1090monitor.php</a>
            </div>
        </div>
        <div id="terminalWrap" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
            <div class="xterm-out" id="xtOut">pi@raspberry:~$ Terminal lista
</div>
            <div class="xterm-row">
                <span class="xterm-pr" id="xtPr">pi@raspberry:~$</span>
                <input id="xtInp" class="xterm-inp" autocomplete="off" spellcheck="false" placeholder="escribe un comando…">
            </div>
        </div>
    </div>

    <div id="paneMap" class="tab-pane" style="align-items:center;justify-content:center;text-align:center;padding:2rem;">
        <div class="launch-card">
            <div class="launch-icon">✈</div>
            <div class="launch-title">Mapa de aeronaves</div>
            <div class="launch-desc">Abre el visor de tráfico aéreo en tiempo real con dump1090.</div>
            <button class="btn-ex btn-cyan" onclick="openMapPage()" style="font-size:.85rem;padding:.5rem 1.5rem;">
                Abrir → dump1090monitor.php
            </button>
            <div class="launch-params" style="margin-top:1rem;font-size:.68rem;">
                💡 Se abrirá en pestaña nueva para evitar conflictos de interfaz.
            </div>
        </div>
    </div>

    <div id="paneCfg" class="tab-pane">
        <div class="config-wrap">
            <div class="config-header">
                <span class="config-path">📄 /home/pi/dump1090-fa/dump1090.args</span>
                <div class="config-actions">
                    <button class="btn-ex btn-cyan" onclick="loadConfig()">⟳ Recargar</button>
                    <button class="btn-ex btn-green" onclick="saveConfig()">💾 Guardar</button>
                </div>
            </div>
            <textarea id="configEditor" class="config-editor" spellcheck="false" placeholder="Cargando configuración…"></textarea>
            <div class="config-hint">
                ⚠️ Edita los parámetros de lanzamiento. Los cambios requieren reiniciar el servicio para aplicar.
            </div>
        </div>
    </div>
</div>

<script>
const mapUrl = 'dump1090monitor.php';
let logPollInterval = null;

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// 🔽 ÚNICA FUENTE DE VERDAD PARA EL ESTADO
function updateStatusBar(d) {
    const st = (d.status || '').toLowerCase();
    let dotState = '', statusTxt = '', svcTxt = '', svcColor = '';

    if (st === 'active') {
        dotState = 'on'; statusTxt = 'dump1090-fa.service activo'; svcTxt = 'ACTIVO'; svcColor = 'var(--green)'; setSwitch(true);
    } else if (st === 'inactive' || st === 'unknown' || st === 'disabled') {
        dotState = ''; statusTxt = 'dump1090-fa.service inactivo'; svcTxt = 'DETENIDO'; svcColor = 'var(--red)'; setSwitch(false);
    } else if (st === 'activating') {
        dotState = 'activating'; statusTxt = 'dump1090-fa.service iniciando…'; svcTxt = 'INICIANDO…'; svcColor = 'var(--amber)';
    } else if (st === 'failed') {
        dotState = 'err'; statusTxt = 'dump1090-fa.service error'; svcTxt = 'ERROR'; svcColor = 'var(--red)';
    } else {
        dotState = ''; statusTxt = 'Estado: ' + st; svcTxt = st.toUpperCase(); svcColor = 'var(--amber)';
    }

    // Actualizar barra superior
    document.getElementById('dotStatus').className = 'dot-status ' + dotState;
    document.getElementById('statusTxt').textContent = statusTxt;
    document.getElementById('svcStatus').textContent = svcTxt;
    document.getElementById('svcStatus').style.color = svcColor;
    document.getElementById('svcPid').textContent = (d.pid && d.pid !== '0') ? d.pid : '—';

    // Sincronizar tarjeta de lanzamiento
    const cardDot = document.getElementById('cardSvcDot');
    const cardTxt = document.getElementById('cardSvcText');
    cardDot.className = 'status-dot ' + dotState;
    cardTxt.textContent = 'Servicio: ' + svcTxt;

    if (d.enabled !== undefined) updateAutoState(d.enabled);
}

function setSwitch(on) {
    document.getElementById('chkDump').checked = on;
    const lbl = document.getElementById('swLabel');
    lbl.textContent = on ? 'ON' : 'OFF';
    lbl.style.color  = on ? 'var(--green)' : 'var(--text-dim)';
}

function updateAutoState(enabled) {
    const chk = document.getElementById('chkAuto');
    const lbl = document.getElementById('autoStatus');
    chk.checked = enabled;
    lbl.textContent = enabled ? 'ON' : 'OFF';
    lbl.className = 'auto-status ' + (enabled ? 'on' : 'off');
    
    document.getElementById('cardAutoDot').className = 'status-dot ' + (enabled ? 'on' : 'off');
    document.getElementById('cardAutoText').textContent = 'Autostart: ' + (enabled ? 'ON' : 'OFF');
}

function cerrarVentana() {
    window.close();
    setTimeout(() => {
        if (!window.closed) {
            if (window.history.length > 1) window.history.back();
            else document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:\'Share Tech Mono\',monospace;color:#00d4ff;font-size:1rem;">Puedes cerrar esta pestaña manualmente.</div>';
        }
    }, 300);
}

function switchTab(tab) {
    ['log','map','cfg'].forEach(t => {
        const pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
        const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        if (pane) pane.classList.remove('active');
        if (btn) btn.className = 'btn-ex btn-dim';
    });
    document.getElementById('pane' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
    document.getElementById('tabBtn' + tab.charAt(0).toUpperCase() + tab.slice(1)).className = 'btn-ex btn-active';
    if (tab === 'cfg') loadConfig();
}

function openMapPage() { window.open(mapUrl, '_blank', 'noopener,noreferrer'); }

async function toggleAutoStart(chk) {
    const sw = document.getElementById('swAuto');
    const target = chk.checked;
    sw.classList.add('busy'); chk.disabled = true;
    try {
        const r = await fetch('?action=dump1090-' + (target ? 'enable' : 'disable'));
        const d = await r.json();
        if (d.ok) {
            updateAutoState(target);
            xtApp('<span class="xt-ok">✅ ' + esc(d.msg) + '</span>');
        } else {
            chk.checked = !target; updateAutoState(!target);
            xtApp('<span class="xt-err">❌ ' + esc(d.error) + '</span>');
        }
    } catch(e) {
        chk.checked = !target; updateAutoState(!target);
        xtApp('<span class="xt-err">❌ Error de red: ' + esc(e.message) + '</span>');
    } finally { sw.classList.remove('busy'); chk.disabled = false; }
}

async function toggleDump1090(chk) {
    const wasOn = !chk.checked;
    chk.checked = wasOn;
    const sw = document.getElementById('swDump');
    sw.classList.add('busy');
    document.getElementById('launchCard').style.display = 'none';
    document.getElementById('terminalWrap').style.display = 'flex';
    
    // Feedback inmediato visual
    const actionTxt = wasOn ? 'Deteniendo' : 'Iniciando';
    document.getElementById('statusTxt').textContent = actionTxt + ' dump1090-fa.service…';
    document.getElementById('dotStatus').className = wasOn ? 'dot-status' : 'dot-status activating';
    document.getElementById('svcStatus').textContent = wasOn ? 'DETENIENDO…' : 'INICIANDO…';
    document.getElementById('svcStatus').style.color = wasOn ? 'var(--text)' : 'var(--amber)';
    xtApp('<span class="xt-out">⏳ ' + actionTxt + ' dump1090-fa.service…</span>');
    
    try {
        const r = await fetch('?action=' + (wasOn ? 'dump1090-stop' : 'dump1090-start'));
        const d = await r.json();
        if (!d.ok) xtApp('<span class="xt-err">❌ ' + esc(d.error || d.msg) + '</span>');
        else xtApp('<span class="xt-ok">✅ ' + esc(d.output || d.msg) + '</span>');
    } catch(e) {
        xtApp('<span class="xt-err">❌ Error de red: ' + esc(e.message) + '</span>');
    } finally {
        sw.classList.remove('busy');
        checkServiceStatus(); // Restaura el estado REAL del sistema
    }
}

async function checkServiceStatus() {
    try {
        const r = await fetch('?action=dump1090-status');
        const d = await r.json();
        updateStatusBar(d); // ✅ Actualiza TODO coherentemente
        
        if (d.active) {
            document.getElementById('launchCard').style.display = 'none';
            document.getElementById('terminalWrap').style.display = 'flex';
            if (!logPollInterval) startLogPoll();
        } else {
            stopLogPoll();
        }
    } catch(e) {
        document.getElementById('dotStatus').className = 'dot-status err';
        document.getElementById('statusTxt').textContent = 'Error al comprobar servicio';
        document.getElementById('svcStatus').textContent = 'ERROR';
        document.getElementById('svcStatus').style.color = 'var(--red)';
    }
}

function startLogPoll() { stopLogPoll(); fetchDump1090Log(); logPollInterval = setInterval(fetchDump1090Log, 3000); }
function stopLogPoll()  { clearInterval(logPollInterval); logPollInterval = null; }
function fetchDump1090Log() {
    fetch('?action=dump1090-log&t=' + Date.now())
        .then(r => r.text())
        .then(text => { const o=document.getElementById('xtOut'); o.textContent=text; o.scrollTop=o.scrollHeight; });
}

async function loadConfig() {
    const editor = document.getElementById('configEditor');
    editor.value = '⏳ Cargando…'; editor.disabled = true;
    try {
        const r = await fetch('?action=config-read&t='+Date.now());
        const d = await r.json();
        editor.value = d.ok ? d.content : '⚠ Error: ' + d.error;
    } catch(e) { editor.value = '⚠ Error de red: ' + e.message; }
    editor.disabled = false;
}

async function saveConfig() {
    const editor = document.getElementById('configEditor');
    const content = editor.value;
    editor.disabled = true;
    try {
        const r = await fetch('?action=config-save', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'content=' + encodeURIComponent(content)
        });
        const d = await r.json();
        xtApp('<span class="xt-' + (d.ok?'ok':'err') + '">' + (d.ok?'✅':'❌') + ' ' + esc(d.msg||d.error) + '</span>');
    } catch(e) { xtApp('<span class="xt-err">❌ Error de red: ' + esc(e.message) + '</span>'); }
    editor.disabled = false;
}

let xtHist = [], xtHidx = -1, xtCwd = '/home/pi';
function xtPrStr() { return 'pi@raspberry:' + xtCwd.replace('/home/pi','~') + '$'; }
function xtApp(html) { const o=document.getElementById('xtOut'); o.innerHTML+=html+'\n'; o.scrollTop=o.scrollHeight; }

async function xtExec(cmd) {
    xtHist.unshift(cmd); xtHidx=-1;
    document.getElementById('xtInp').value='';
    xtApp('<span class="xt-cmd">'+esc(xtPrStr())+' '+esc(cmd)+'</span>');
    try {
        const resp = await fetch('?action=terminal',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'cmd='+encodeURIComponent('cd '+xtCwd+' && '+cmd)});
        const dat  = await resp.json();
        if (dat.output) xtApp('<span class="xt-out">'+dat.output+'</span>');
    } catch(err) { xtApp('<span class="xt-err">Error: '+esc(err.message)+'</span>'); }
    document.getElementById('xtPr').textContent = xtPrStr();
}

document.getElementById('xtInp').addEventListener('keydown', async function(e) {
    if (e.key==='ArrowUp')   { e.preventDefault(); if(xtHidx<xtHist.length-1) this.value=xtHist[++xtHidx]||''; return; }
    if (e.key==='ArrowDown') { e.preventDefault(); xtHidx>0?this.value=xtHist[--xtHidx]:(xtHidx=-1,this.value=''); return; }
    if (e.key!=='Enter') return;
    const cmd=this.value.trim(); if(!cmd) return;
    if (/^\s*clear\s*$/.test(cmd))  { document.getElementById('xtOut').innerHTML=''; this.value=''; return; }
    if (/^\s*(edit|nano)(\s+\S+)?\s*$/.test(cmd)) { xtApp('<span class="xt-err">Editor no disponible.</span>'); this.value=''; return; }
    if (/^\s*(sudo\s+su|su\s*$|top|htop|vim|vi|less|more)\s*/.test(cmd)) { xtApp('<span class="xt-err">Comando interactivo no soportado.</span>'); this.value=''; return; }
    if (/^\s*cd(\s|$)/.test(cmd)) {
        const t=cmd.replace(/^\s*cd\s*/,'').trim()||'~';
        if(t==='~'||t==='') xtCwd='/home/pi';
        else if(t.startsWith('/')) xtCwd=t;
        else if(t==='..'){const p=xtCwd.split('/').filter(Boolean);p.pop();xtCwd='/'+p.join('/')||'/';}
        else xtCwd=xtCwd.replace(/\/$/,'')+'/'+t;
        xtApp('<span class="xt-cmd">'+esc(xtPrStr())+' '+esc(cmd)+'</span>');
        xtHist.unshift(cmd); xtHidx=-1; this.value='';
        document.getElementById('xtPr').textContent=xtPrStr();
        return;
    }
    await xtExec(cmd);
});

checkServiceStatus();
setInterval(checkServiceStatus, 10000);
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('paneCfg').classList.contains('active')) loadConfig();
});
</script>
</body>
</html>
