<?php
// 🎛️ SvxLink Control Panel - AJAX Edition
session_start();
header('X-Content-Type-Options: nosniff');

$SERVICE = "svxlink";
$action = $_GET['action'] ?? '';

if ($action === 'start') {
    shell_exec("sudo systemctl start $SERVICE 2>/dev/null");
    $st = 'activating';
    for($i=0;$i<15;$i++){ usleep(200000); $st=trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null")); if($st!=='activating') break; }
    header('Content-Type: application/json');
    echo json_encode($st==='active' ? ['ok'=>true,'output'=>'✅ Iniciado'] : ['ok'=>false,'error'=>'No arrancó: '.$st]);
    exit;
}
if ($action === 'stop') {
    shell_exec("sudo systemctl stop $SERVICE 2>/dev/null"); usleep(300000);
    header('Content-Type: application/json');
    echo json_encode(trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"))!=='active' ? ['ok'=>true,'msg'=>'✅ Detenido'] : ['ok'=>false,'msg'=>'No se pudo detener']);
    exit;
}
if ($action === 'enable') {
    shell_exec("sudo systemctl enable $SERVICE 2>/dev/null"); usleep(200000);
    header('Content-Type: application/json');
    echo json_encode(trim(shell_exec("systemctl is-enabled $SERVICE 2>/dev/null"))==='enabled' ? ['ok'=>true,'msg'=>'✅ Autostart ON'] : ['ok'=>false,'error'=>'Error']);
    exit;
}
if ($action === 'disable') {
    shell_exec("sudo systemctl disable $SERVICE 2>/dev/null"); usleep(200000);
    header('Content-Type: application/json');
    echo json_encode(trim(shell_exec("systemctl is-enabled $SERVICE 2>/dev/null"))!=='enabled' ? ['ok'=>true,'msg'=>'✅ Autostart OFF'] : ['ok'=>false,'error'=>'Error']);
    exit;
}
if ($action === 'status') {
    $st=trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));
    $pid=trim(shell_exec("systemctl show $SERVICE --property=MainPID --value 2>/dev/null"));
    $en=trim(shell_exec("systemctl is-enabled $SERVICE 2>/dev/null"));
    header('Content-Type: application/json');
    echo json_encode(['active'=>$st==='active','status'=>$st,'pid'=>$pid?:'—','enabled'=>$en==='enabled']);
    exit;
}
if ($action === 'log') {
    header('Content-Type: text/plain');
    echo trim(shell_exec("sudo journalctl -u $SERVICE -n 100 --no-pager --output=short 2>/dev/null")) ?: '(sin registros)';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🎛️ svxlink · Control</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0e14;--surface:#111720;--border:#1e2d3d;--green:#00ff9f;--red:#ff4560;--cyan:#00d4ff;--amber:#ffb300;--text:#a8b9cc;--text-dim:#4a5568;--font-mono:'Share Tech Mono',monospace;--font-ui:'Rajdhani',sans-serif;--font-orb:'Orbitron',monospace}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:var(--font-ui);height:100vh;display:flex;flex-direction:column;overflow:hidden}
.ex-header{display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.4rem;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0}
.ex-title{font-family:var(--font-orb);font-size:1rem;font-weight:700;color:var(--cyan);letter-spacing:.1em;text-transform:uppercase}
.ex-subtitle{font-family:var(--font-mono);font-size:.7rem;color:var(--text-dim);letter-spacing:.1em;margin-top:.15rem}
.ex-btns{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
.btn-ex{font-family:var(--font-mono);font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;border-radius:4px;padding:.28rem .85rem;cursor:pointer;transition:background .2s,opacity .2s;border:1px solid;background:transparent}
.btn-ex:disabled{opacity:.4;cursor:not-allowed}
.btn-cyan:hover:not(:disabled){background:rgba(0,212,255,.1)}
.btn-green:hover:not(:disabled){background:rgba(0,255,159,.1)}
.btn-red:hover:not(:disabled){background:rgba(255,69,96,.15)}
.btn-amber:hover:not(:disabled){background:rgba(255,179,0,.15)}
.btn-cyan{color:var(--cyan);border-color:var(--cyan)}
.btn-green{color:var(--green);border-color:var(--green)}
.btn-red{color:var(--red);border-color:var(--red)}
.btn-amber{color:var(--amber);border-color:var(--amber)}
.btn-dim{color:var(--text-dim);border-color:#1e2d3d}
.btn-active{background:rgba(0,212,255,.15)!important;border-color:var(--cyan)!important;color:var(--cyan)!important}
.sw{position:relative;width:56px;height:28px;flex-shrink:0;cursor:pointer}
.sw input{opacity:0;width:0;height:0;position:absolute}
.sw-track{position:absolute;inset:0;border-radius:2px;background:#1a2535;border:2px solid var(--red);transition:background .3s,border-color .3s}
.sw-knob{position:absolute;top:3px;left:3px;width:20px;height:20px;background:var(--red);transition:transform .3s cubic-bezier(.4,0,.2,1),background .3s,box-shadow .3s}
.sw input:checked~.sw-track{border-color:var(--green)}
.sw input:checked~.sw-knob{transform:translateX(28px);background:var(--green);box-shadow:0 0 8px rgba(0,255,159,.6)}
.sw-busy-dot{display:none;position:absolute;top:50%;right:-20px;transform:translateY(-50%);width:8px;height:8px;border-radius:50%;border:2px solid var(--amber);border-top-color:transparent;animation:spin .7s linear infinite}
.sw.busy .sw-busy-dot{display:block}
@keyframes spin{to{transform:translateY(-50%) rotate(360deg)}}
.auto-bar{display:flex;align-items:center;gap:.8rem;padding:.5rem 1.4rem;background:rgba(0,0,0,.2);border-bottom:1px solid var(--border);flex-shrink:0}
.auto-label{font-family:var(--font-mono);font-size:.75rem;color:var(--text-dim);letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
.auto-status{font-family:var(--font-mono);font-size:.7rem;letter-spacing:.05em;min-width:70px;transition:color .3s}
.auto-status.on{color:var(--green)}
.auto-status.off{color:var(--red)}
.ex-status{display:flex;align-items:center;gap:1.2rem;padding:.45rem 1.4rem;background:rgba(0,0,0,.3);border-bottom:1px solid var(--border);flex-shrink:0;font-family:var(--font-mono);font-size:.72rem;flex-wrap:wrap}
.dot-status{width:8px;height:8px;border-radius:50%;background:var(--text-dim);display:inline-block;margin-right:.4rem;transition:background .4s,box-shadow .4s}
.dot-status.on{background:var(--green);box-shadow:0 0 6px var(--green);animation:pulse 2s infinite}
.dot-status.err{background:var(--red);box-shadow:0 0 6px var(--red)}
.dot-status.activating{background:var(--amber);box-shadow:0 0 6px var(--amber);animation:pulse-amber 1s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
@keyframes pulse-amber{0%,100%{opacity:1;box-shadow:0 0 6px var(--amber)}50%{opacity:.6;box-shadow:0 0 2px var(--amber)}}
.sep{color:var(--border)}
/* ── TABS ── */
.ex-tabs{display:flex;gap:.4rem;padding:.6rem 1.4rem;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0}
/* ── CONTENIDO ── */
.ex-content{flex:1;display:flex;flex-direction:column;overflow:hidden}
.tab-pane{flex:1;display:none;flex-direction:column;overflow:hidden}
.tab-pane.active{display:flex}
/* ── TERMINAL ── */
.xterm-out{font-family:var(--font-mono);font-size:.75rem;color:#7a9ab5;background:#060c10;padding:1rem 1.4rem;flex:1;overflow-y:auto;white-space:pre-wrap;word-break:break-all;line-height:1.55}
.xterm-out::-webkit-scrollbar{width:4px}
.xterm-out::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}
/* ── LAUNCH CARD ── */
.launch-card{margin:2rem auto;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:2rem 2.5rem;max-width:480px;text-align:center}
.launch-icon{font-size:3rem;margin-bottom:1rem}
.launch-title{font-family:var(--font-orb);font-size:1.1rem;color:var(--cyan);letter-spacing:.08em;margin-bottom:.6rem}
.launch-desc{font-family:var(--font-mono);font-size:.75rem;color:var(--text-dim);line-height:1.6;margin-bottom:1.5rem}
.launch-params{text-align:left;background:#060c10;border:1px solid var(--border);border-radius:4px;padding:.8rem 1rem;margin-bottom:1.5rem;font-family:var(--font-mono);font-size:.72rem;color:var(--amber);line-height:1.7}
.status-badge{display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .6rem;background:#060c10;border:1px solid var(--border);border-radius:4px;font-family:var(--font-mono);font-size:.7rem;margin:.3rem 0}
.status-dot{width:6px;height:6px;border-radius:50%}
.status-dot.on{background:var(--green);box-shadow:0 0 4px var(--green)}
.status-dot.off{background:var(--red)}
.status-dot.activating{background:var(--amber);box-shadow:0 0 4px var(--amber);animation:pulse-amber 1s infinite}
/* ── IFRAME EDITOR ── */
#editorFrame{width:100%;flex:1;border:none;background:var(--bg)}
</style>
</head>
<body>

<header class="ex-header">
    <div>
        <div class="ex-title">🎛️ svxlink · Control</div>
        <div class="ex-subtitle">Repetidor · EchoLink · svxlink.service</div>
    </div>
    <div class="ex-btns">
        <label class="sw" id="swSvc" title="Iniciar / Parar svxlink.service">
            <input type="checkbox" id="chkSvc" onchange="toggleService(this)">
            <span class="sw-track"></span>
            <span class="sw-knob"></span>
            <span class="sw-busy-dot"></span>
        </label>
        <span id="svcLabel" style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);letter-spacing:.08em;text-transform:uppercase;min-width:2rem;">OFF</span>
        <!-- <button class="btn-ex btn-red" onclick="cerrarVentana()">✖ Cerrar</button> -->
        <button class="btn-ex btn-red" onclick="window.location.href='mmdvm.php'">✖ Cerrar</button>
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

<!-- ── TABS ── -->
<div class="ex-tabs">
    <button id="tabBtnTerminal" class="btn-ex btn-active" onclick="switchTab('terminal')">📋 Terminal</button>
    <button id="tabBtnEditor"   class="btn-ex btn-dim"    onclick="switchTab('editor')">✏️ Editor</button>
</div>

<div class="ex-content">

    <!-- ── PANEL TERMINAL ── -->
    <div id="paneTerminal" class="tab-pane active">
        <div id="launchCard" class="launch-card">
            <div class="launch-icon">🎛️</div>
            <div class="launch-title">svxlink · Repetidor</div>
            <div class="launch-desc">Activa el toggle superior para arrancar el servicio con tu configuración.</div>
            <div style="margin:1rem 0;display:flex;flex-direction:column;gap:.4rem;align-items:center;">
                <div class="status-badge"><span class="status-dot" id="cardSvcDot"></span><span id="cardSvcText">Servicio: —</span></div>
                <div class="status-badge"><span class="status-dot" id="cardAutoDot"></span><span id="cardAutoText">Autostart: —</span></div>
            </div>
            <div class="launch-params">
                ⚙ Servicio: svxlink.service<br>
                📝 Log:     journalctl -u svxlink<br>
                ⚙ Config:  /usr/local/etc/svxlink/<br>
                📄 Archivos: svxlink.conf, ModuleEchoLink.conf
            </div>
            <button class="btn-ex btn-green" style="margin-top:.5rem" onclick="mostrarTerminal()">📋 Cargar logs</button>
        </div>
        <div id="terminalWrap" style="display:none;flex:1;flex-direction:column;overflow:hidden;">
            <div class="xterm-out" id="xtOut">Cargando logs…</div>
        </div>
    </div>

    <!-- ── PANEL EDITOR (iframe) ── -->
    <div id="paneEditor" class="tab-pane">
        <iframe id="editorFrame" src="" title="Editor SvxLink"></iframe>
    </div>

</div><!-- /ex-content -->

<script>
let logPoll = null;
let logLoaded = false;

// ── Mostrar terminal y arrancar polling ──
function mostrarTerminal() {
    document.getElementById('launchCard').style.display = 'none';
    document.getElementById('terminalWrap').style.display = 'flex';
    fetchLog();
    startLogPoll();
    logLoaded = true;
}

function startLogPoll() { stopLogPoll(); logPoll = setInterval(fetchLog, 3000); }
function stopLogPoll()  { if(logPoll) clearInterval(logPoll); logPoll = null; }

function fetchLog() {
    fetch('?action=log&t=' + Date.now())
        .then(r => r.text())
        .then(text => {
            const o = document.getElementById('xtOut');
            o.textContent = text;
            o.scrollTop = o.scrollHeight;
        });
}

// ── Cambio de tab ──
function switchTab(tab) {
    ['terminal','editor'].forEach(t => {
        document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1)).classList.remove('active');
        document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1)).className = 'btn-ex btn-dim';
    });
    document.getElementById('pane' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
    document.getElementById('tabBtn' + tab.charAt(0).toUpperCase() + tab.slice(1)).className = 'btn-ex btn-active';

    if (tab === 'editor') {
        // Parar servicio antes de abrir el editor
        const chk = document.getElementById('chkSvc');
        if (chk.checked) {
            mostrarAviso('⏹️ Parando svxlink antes de editar…', 'amber');
            fetch('?action=stop').then(() => {
                checkServiceStatus();
                mostrarAviso('✅ svxlink parado. Edita y guarda la configuración.', 'green');
            });
        }
        // Cargar iframe solo la primera vez
        const frame = document.getElementById('editorFrame');
        if (!frame.src || frame.src === window.location.href) {
            frame.src = 'svxlink_editor.php';
        }
        stopLogPoll();
    } else if (tab === 'terminal') {
        mostrarAviso('ℹ️ Recuerda arrancar svxlink con el toggle si lo paró el editor.', 'cyan');
        if (logLoaded) startLogPoll();
    }
}

// ── Aviso temporal en la barra de estado ──
function mostrarAviso(msg, color) {
    const el = document.getElementById('statusTxt');
    const colors = { amber: 'var(--amber)', green: 'var(--green)', cyan: 'var(--cyan)', red: 'var(--red)' };
    const prev = el.textContent;
    const prevColor = el.style.color;
    el.textContent = msg;
    el.style.color = colors[color] || 'var(--text)';
    setTimeout(() => { el.textContent = prev; el.style.color = prevColor; }, 4000);
}

// ── Llamado desde el iframe cuando pulsa X o SALIR ──
function cerrarEditorTab() {
    switchTab('terminal');
}

// ── Interceptar window.close() del iframe (mismo origen) ──
document.getElementById('editorFrame').addEventListener('load', function() {
    try {
        // Sobreescribir window.close dentro del iframe para que vuelva al tab Terminal
        this.contentWindow.close = function() {
            cerrarEditorTab();
        };
        // También cubrir la llamada directa al padre si existe
        this.contentWindow.cerrarEditor = function() {
            cerrarEditorTab();
        };
    } catch(e) {
        console.warn('No se pudo interceptar iframe:', e);
    }
});

// ── Status bar ──
function updateStatusBar(d) {
    const st = (d.status || '').toLowerCase();
    let dotState = '', statusTxt = '', svcTxt = '', svcColor = '';
    if (st === 'active') {
        dotState = 'on'; statusTxt = 'svxlink.service activo'; svcTxt = 'ACTIVO'; svcColor = 'var(--green)'; setServiceSwitch(true);
    } else if (st === 'inactive' || st === 'unknown' || st === 'deactivating') {
        dotState = ''; statusTxt = 'svxlink.service inactivo'; svcTxt = 'DETENIDO'; svcColor = 'var(--red)'; setServiceSwitch(false);
    } else if (st === 'activating') {
        dotState = 'activating'; statusTxt = 'svxlink.service iniciando…'; svcTxt = 'INICIANDO…'; svcColor = 'var(--amber)';
    } else if (st === 'failed') {
        dotState = 'err'; statusTxt = 'svxlink.service error'; svcTxt = 'ERROR'; svcColor = 'var(--red)';
    } else {
        dotState = ''; statusTxt = 'Estado: ' + st; svcTxt = st.toUpperCase(); svcColor = 'var(--amber)';
    }
    document.getElementById('dotStatus').className = 'dot-status ' + dotState;
    document.getElementById('statusTxt').textContent = statusTxt;
    document.getElementById('svcStatus').textContent = svcTxt;
    document.getElementById('svcStatus').style.color = svcColor;
    document.getElementById('svcPid').textContent = (d.pid && d.pid !== '0') ? d.pid : '—';
    document.getElementById('cardSvcDot').className = 'status-dot ' + dotState;
    document.getElementById('cardSvcText').textContent = 'Servicio: ' + svcTxt;
    if (d.enabled !== undefined) updateAutoState(d.enabled);
}

function setServiceSwitch(on) {
    document.getElementById('chkSvc').checked = on;
    const lbl = document.getElementById('svcLabel');
    lbl.textContent = on ? 'ON' : 'OFF';
    lbl.style.color = on ? 'var(--green)' : 'var(--text-dim)';
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

// function cerrarVentana() {
//     document.body.innerHTML = `
//         <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;
//             background:#0a0e14;color:#a8b9cc;font-family:'Share Tech Mono',monospace;text-align:center;padding:20px;">
//             <div style="font-size:5rem;font-weight:900;color:#00d4ff;margin-bottom:10px;" id="countdownNum">5</div>
//             <div style="font-size:1.2rem;margin-bottom:30px;color:#7a9ab5;">Cerrando panel de control...</div>
//             <button onclick="window.close()" style="padding:12px 24px;background:#ff4560;color:white;border:none;
//                 border-radius:4px;cursor:pointer;font-family:inherit;font-size:1rem;">Cerrar ahora</button>
//         </div>`;
//     let count = 5;
//     const iv = setInterval(() => {
//         count--;
//         const el = document.getElementById('countdownNum');
//         if (el) el.textContent = count;
//         if (count <= 0) { clearInterval(iv); window.close(); }
//     }, 1000);
// }

async function toggleService(chk) {
    const wasOn = !chk.checked;
    chk.checked = wasOn;
    const sw = document.getElementById('swSvc');
    sw.classList.add('busy');
    document.getElementById('statusTxt').textContent = (wasOn?'Deteniendo ':'Iniciando ')+'svxlink.service…';
    document.getElementById('dotStatus').className = wasOn?'dot-status':'dot-status activating';
    document.getElementById('svcStatus').textContent = wasOn?'DETENIENDO…':'INICIANDO…';
    document.getElementById('svcStatus').style.color = wasOn?'var(--text)':'var(--amber)';
    try {
        const r = await fetch('?action='+(wasOn?'stop':'start'));
        const d = await r.json();
        if(d.ok) {
            const out = document.getElementById('xtOut');
            out.textContent += (d.output||d.msg) + '\n';
            out.scrollTop = out.scrollHeight;
        }
    } catch(e) { console.error(e); } finally {
        sw.classList.remove('busy');
        checkServiceStatus();
    }
}

async function toggleAutoStart(chk) {
    const sw = document.getElementById('swAuto');
    const target = chk.checked;
    sw.classList.add('busy'); chk.disabled = true;
    try {
        const r = await fetch('?action='+(target?'enable':'disable'));
        const d = await r.json();
        if(d.ok) updateAutoState(target);
        else { chk.checked = !target; updateAutoState(!target); }
    } catch(e) { chk.checked = !target; updateAutoState(!target); } finally {
        sw.classList.remove('busy'); chk.disabled = false;
    }
}

async function checkServiceStatus() {
    try {
        const r = await fetch('?action=status&t='+Date.now());
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const d = await r.json();
        updateStatusBar(d);
    } catch(e) {
        document.getElementById('dotStatus').className='dot-status err';
        document.getElementById('statusTxt').textContent='Error al comprobar servicio';
        document.getElementById('svcStatus').textContent='ERROR';
        document.getElementById('svcStatus').style.color='var(--red)';
    }
}

checkServiceStatus();
setInterval(checkServiceStatus, 10000);
</script>
</body>
</html>
