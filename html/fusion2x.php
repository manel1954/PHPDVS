<?php
header('X-Content-Type-Options: nosniff');

$SERVICE = "fusion2x-web.service";

/* ───────────────────────── STATUS ───────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'status') {

    $st  = trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));
    $en  = trim(shell_exec("systemctl is-enabled $SERVICE 2>/dev/null"));
    $pid = trim(shell_exec("systemctl show $SERVICE --property=MainPID --value 2>/dev/null"));

    header('Content-Type: application/json');

    echo json_encode([
        "status"  => $st,
        "active"  => ($st === "active"),
        "enabled" => ($en === "enabled"),
        "pid"     => $pid
    ]);

    exit;
}

/* ───────────────────────── TOGGLE ───────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'toggle') {

    header('Content-Type: application/json');

    $st = trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));

    if ($st === "active") {

        shell_exec("sudo systemctl stop $SERVICE 2>/dev/null");
        shell_exec("sudo systemctl disable $SERVICE 2>/dev/null");

        sleep(1);

        echo json_encode([
            "ok"     => true,
            "active" => false
        ]);

    } else {

        shell_exec("sudo systemctl enable $SERVICE 2>/dev/null");
        shell_exec("sudo systemctl start $SERVICE 2>/dev/null");

        $active = false;

        for ($i = 0; $i < 12; $i++) {

            usleep(350000);

            $chk = trim(shell_exec("systemctl is-active $SERVICE 2>/dev/null"));

            if ($chk === "active") {
                $active = true;
                break;
            }
        }

        echo json_encode([
            "ok"     => true,
            "active" => $active
        ]);
    }

    exit;
}

/* ───────────────────────── LOG ───────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'log') {

    header('Content-Type: text/plain');

    $log = shell_exec(
        "sudo journalctl -u $SERVICE -n 80 --no-pager --output=short 2>/dev/null"
    );

    echo !empty(trim($log))
        ? $log
        : "(sin log disponible)";

    exit;
}

$ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>⚙ Fusion2X WEB · Control</title>

<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

<style>

:root{

    --bg:#0a0e14;
    --surface:#111720;
    --border:#1e2d3d;

    --cyan:#00d4ff;
    --green:#00ff9f;
    --red:#ff4560;
    --amber:#ffb300;

    --text:#a8b9cc;
    --text-dim:#4a5568;

    --font-mono:'Share Tech Mono', monospace;
    --font-ui:'Rajdhani', sans-serif;
    --font-orb:'Orbitron', monospace;
}

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    background:var(--bg);
    color:var(--text);
    font-family:var(--font-ui);
}

/* ───────────────── HEADER ───────────────── */

.ex-header{

    display:flex;
    justify-content:space-between;
    align-items:center;

    padding:.85rem 1.4rem;

    background:var(--surface);
    border-bottom:1px solid var(--border);
}

.ex-left{

    display:flex;
    align-items:center;
    gap:1.6rem;
}

.ex-title{

    font-family:var(--font-orb);
    color:var(--cyan);

    font-size:1rem;
    letter-spacing:.08em;
}

/* ───────────────── SWITCH ───────────────── */

.switch-wrap{

    display:flex;
    align-items:center;
    gap:.85rem;
}

.switch-label{

    font-family:var(--font-mono);
    font-size:.74rem;

    color:var(--text);

    text-transform:uppercase;
    letter-spacing:.08em;

    white-space:nowrap;
}

/* SWITCH FLAT */

.sw{

    position:relative;

    width:56px;
    height:28px;

    cursor:pointer;

    flex-shrink:0;
}

.sw input{

    opacity:0;
    width:0;
    height:0;

    position:absolute;
}

.sw-track{

    position:absolute;
    inset:0;

    border-radius:2px;

    background:#1a2535;

    border:2px solid var(--red);

    transition:
        background .25s,
        border-color .25s;
}

.sw-knob{

    position:absolute;

    top:3px;
    left:3px;

    width:18px;
    height:18px;

    background:var(--red);

    border-radius:1px;

    transition:
        transform .28s cubic-bezier(.4,0,.2,1),
        background .25s,
        box-shadow .25s;
}

/* ON */

.sw input:checked ~ .sw-track{

    border-color:var(--green);
}

.sw input:checked ~ .sw-knob{

    transform:translateX(28px);

    background:var(--green);

    box-shadow:0 0 4px rgba(0,255,159,.18);
}

/* ───────────────── PANEL BUTTON ───────────────── */

.btn-panel{

    font-family:var(--font-mono);

    font-size:.78rem;

    text-transform:uppercase;
    letter-spacing:.08em;

    padding:.55rem 1.15rem;

    border-radius:6px;

    border:1px solid #ffffff;

    background:transparent;

    color:#ffffff;

    text-decoration:none;

    display:flex;
    align-items:center;
    gap:.45rem;

    transition:.2s;
}

.btn-panel:hover{

    background:rgba(255,255,255,.08);
}

/* ───────────────── CONTAINER ───────────────── */

.container{

    max-width:760px;

    margin:40px auto;

    background:var(--surface);

    border:1px solid var(--border);
    border-radius:10px;

    padding:24px;
}

/* ───────────────── STATUS ───────────────── */

.status{

    margin:18px 0;

    font-family:var(--font-mono);
    font-size:.78rem;
}

.on{
    color:var(--green);
}

.off{
    color:var(--red);
}

/* ───────────────── FUSION BUTTON ───────────────── */

.fusion-btn{

    display:flex;
    align-items:center;
    justify-content:space-between;

    gap:12px;

    width:100%;

    margin-top:14px;

    padding:15px 18px;

    border:1px solid var(--cyan);
    border-radius:8px;

    background:
        linear-gradient(
            135deg,
            rgba(0,212,255,.08),
            rgba(0,0,0,0)
        );

    text-decoration:none;

    transition:.22s;
}

.fusion-btn:hover{

    background:rgba(0,212,255,.12);

    transform:translateY(-1px);

    box-shadow:0 0 12px rgba(0,212,255,.15);
}

.material-symbols-outlined{

    font-variation-settings:
    'FILL' 0,
    'wght' 400,
    'GRAD' 0,
    'opsz' 24;

    font-size:24px;
    color:var(--cyan);
}

.fusion-icon,
.fusion-signal{

    display:flex;
    align-items:center;
}

.fusion-text{

    flex:1;

    text-align:left;

    font-family:var(--font-mono);
}

.fusion-title{

    color:var(--cyan);

    font-size:.75rem;
    letter-spacing:.12em;
}

.fusion-sub{

    margin-top:2px;

    font-size:.65rem;

    color:var(--text-dim);
}

/* ───────────────── TERMINAL ───────────────── */

.term-wrap{

    margin-top:24px;

    border:1px solid var(--border);
    border-radius:8px;

    overflow:hidden;
}

.term-header{

    display:flex;
    justify-content:space-between;
    align-items:center;

    padding:.7rem 1rem;

    background:#0d141d;

    border-bottom:1px solid var(--border);

    font-family:var(--font-mono);
    font-size:.72rem;

    color:var(--cyan);

    letter-spacing:.08em;
}

.term-box{

    background:#060c10;

    color:#7fa2bf;

    font-family:var(--font-mono);

    font-size:.73rem;

    line-height:1.55;

    padding:1rem;

    height:360px;

    overflow-y:auto;

    white-space:pre-wrap;
}

.term-box::-webkit-scrollbar{

    width:4px;
}

.term-box::-webkit-scrollbar-thumb{

    background:var(--border);
}

</style>

</head>

<body>

<header class="ex-header">

    <div class="ex-left">

        <div class="ex-title">
            ⚙ Fusion2X WEB · Control
        </div>

        <!-- SWITCH -->
        <div class="switch-wrap">

            <label class="sw">

                <input
                    type="checkbox"
                    id="sw"
                    onchange="toggleService()">

                <span class="sw-track"></span>
                <span class="sw-knob"></span>

            </label>

            <span class="switch-label">
                OFF / ON
            </span>

        </div>

    </div>

    <!-- PANEL -->
    <a class="btn-panel" href="mmdvm.php">
        🏠 Panel PHPPLUS
    </a>

</header>

<div class="container">

    <h3 style="
        font-family:var(--font-orb);
        color:var(--cyan);
        margin-bottom:10px;
    ">
        Fusion 2X WEB SERVICE
    </h3>

    <div class="status">

        Estado:
        <span id="st" class="off">
            DESCONOCIDO
        </span>

    </div>

    <!-- WEB -->
    <a 
        class="fusion-btn"
        target="_blank"
        href="http://<?php echo $ip; ?>:8080">
          
        <div class="fusion-icon">
            <span class="material-symbols-outlined">
                radio
            </span>
        </div>

        <div class="fusion-text">

            <div class="fusion-title">
                FUSION 2X WEB Claves de entrada: Usuario admin · Contraseña admin
            </div>

            <div class="fusion-sub">
                Emisión / radio en tiempo real
            </div>

        </div>

        <div class="fusion-signal">
            <span class="material-symbols-outlined">
                signal_cellular_alt
            </span>
        </div>
    </a>

    <!-- TERMINAL -->
    <div class="term-wrap">

        <div class="term-header">

            <span>
                📡 journalctl · fusion2x-web.service
            </span>

            <span id="termState">
                LIVE
            </span>

        </div>

        <div class="term-box" id="term">
            Cargando logs...
        </div>

    </div>

</div>

<script>

let busy = false;

/* ───────────────── STATUS ───────────────── */

async function loadStatus(){

    try{

        const r = await fetch('?action=status&t=' + Date.now());

        const d = await r.json();

        const st = document.getElementById('st');
        const sw = document.getElementById('sw');

        sw.checked = d.active;

        if(d.active){

            st.textContent = "ACTIVO";
            st.className = "on";

        }else{

            st.textContent = "DETENIDO";
            st.className = "off";
        }

    }catch(e){

        console.error(e);
    }
}

/* ───────────────── TOGGLE ───────────────── */

async function toggleService(){

    if(busy) return;

    busy = true;

    const sw = document.getElementById('sw');

    sw.disabled = true;

    try{

        await fetch('?action=toggle');

        setTimeout(loadStatus, 700);

    }catch(e){

        console.error(e);

    }finally{

        setTimeout(() => {

            sw.disabled = false;
            busy = false;

        }, 900);
    }
}

/* ───────────────── LOGS ───────────────── */

async function loadLogs(){

    try{

        const r = await fetch('?action=log&t=' + Date.now());

        const txt = await r.text();

        const term = document.getElementById('term');

        term.textContent = txt;

        term.scrollTop = term.scrollHeight;

    }catch(e){

        console.error(e);
    }
}

/* ───────────────── INIT ───────────────── */

loadStatus();
loadLogs();

/* sincronización automática */
setInterval(loadStatus, 2000);

/* logs live */
setInterval(loadLogs, 2500);

</script>

</body>
</html>
