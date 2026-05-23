<?php
// camara.php  –  Visor de cámara RTSP en el navegador vía HLS

define('HLS_DIR',    '/var/www/html/hls');
define('HLS_URL',    '/hls');
define('PID_FILE',   '/tmp/camara_ffmpeg.pid');
define('LOG_FILE',   '/tmp/camara_ffmpeg.log');
define('FFMPEG',     '/usr/bin/ffmpeg');
// define('CAM_INI',    '/home/pi/.local/camara.ini');
set_time_limit(60);

// Leer desde camara.ini si viene ?cam=CLAVE
$camKey = trim($_GET['cam'] ?? '');
if ($camKey !== '' && file_exists(CAM_INI)) {
    $cams = parse_ini_file(CAM_INI, true);
    if (isset($cams[$camKey])) {
        $rtsp   = trim($cams[$camKey]['rtsp']   ?? '');
        $nombre = trim($cams[$camKey]['nombre'] ?? $camKey);
    } else {
        $rtsp   = trim($_GET['rtsp']   ?? '');
        $nombre = trim($_GET['nombre'] ?? 'Cámara');
    }
} else {
    $rtsp   = trim($_GET['rtsp']   ?? '');
    $nombre = trim($_GET['nombre'] ?? 'Cámara');
}
if ($rtsp === '') die('Error: URL RTSP no configurada. Revisa camara.ini');

// ── API AJAX ─────────────────────────────────────────────
$action = $_GET['action'] ?? '';
if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {

        case 'start':
            // Matar proceso anterior si existe
            if (file_exists(PID_FILE)) {
                $oldPid = (int)file_get_contents(PID_FILE);
                if ($oldPid > 0) @posix_kill($oldPid, 9);
                @unlink(PID_FILE);
            }
            // Crear directorio HLS
            if (!is_dir(HLS_DIR)) mkdir(HLS_DIR, 0755, true);
            // Limpiar segmentos anteriores
            array_map('unlink', glob(HLS_DIR . '/*.ts'));
            array_map('unlink', glob(HLS_DIR . '/*.m3u8'));

            $rtspSafe = escapeshellarg($rtsp);
            $m3u8     = HLS_DIR . '/stream.m3u8';
            $segPat   = HLS_DIR . '/seg%03d.ts';

            // Comando ffmpeg: RTSP → HLS de baja latencia, sin audio
            $cmd = FFMPEG
                 . ' -rtsp_transport tcp'
                 . ' -i ' . $rtspSafe
                 . ' -an'                          // sin audio (ALSA no disponible)
                 . ' -c:v libx264'                 // recodificar HEVC→H264 (compatible navegadores)
                 . ' -preset ultrafast'             // máxima velocidad en Pi
                 . ' -tune zerolatency'             // mínima latencia
                 . ' -crf 28'                       // calidad/tamaño equilibrado
                 . ' -vf scale=800:448'             // mantener resolución original
                 . ' -f hls'
                 . ' -hls_time 1'                  // segmentos de 1s
                 . ' -hls_list_size 3'             // solo 3 segmentos en lista
                 . ' -hls_flags delete_segments+omit_endlist'
                 . ' -hls_segment_filename ' . escapeshellarg($segPat)
                 . ' ' . escapeshellarg($m3u8)
                 . ' > ' . LOG_FILE . ' 2>&1 &';

            exec($cmd, $out, $ret);

            // Capturar PID del último proceso ffmpeg
            usleep(2000000); // esperar 2s a que arranque
            exec("pgrep -n ffmpeg", $pidOut);
            $pid = (int)($pidOut[0] ?? 0);
            if ($pid > 0) file_put_contents(PID_FILE, $pid);

            // Esperar hasta 4s a que aparezca el .m3u8
            $ok = false;
            for ($i = 0; $i < 40; $i++) {
                usleep(500000);
                if (file_exists($m3u8) && filesize($m3u8) > 0) { $ok = true; break; }
            }

            echo json_encode([
                'ok'  => $ok,
                'pid' => $pid,
                'msg' => $ok ? 'Stream iniciado' : 'Timeout esperando stream – revisa la URL RTSP',
                'log' => file_exists(LOG_FILE) ? implode("\n", array_slice(file(LOG_FILE), -6)) : ''
            ]);
            break;

        case 'stop':
            $stopped = false;
            if (file_exists(PID_FILE)) {
                $pid = (int)file_get_contents(PID_FILE);
                if ($pid > 0) { posix_kill($pid, 15); usleep(300000); posix_kill($pid, 9); }
                unlink(PID_FILE);
                $stopped = true;
            }
            // Matar cualquier ffmpeg residual apuntando a esta URL
            exec("pkill -f " . escapeshellarg(parse_url($rtsp, PHP_URL_HOST)));
            // Limpiar segmentos
            array_map('unlink', glob(HLS_DIR . '/*.ts'));
            array_map('unlink', glob(HLS_DIR . '/*.m3u8'));
            echo json_encode(['ok' => true, 'msg' => 'Stream detenido']);
            break;

        case 'status':
            $running = false;
            if (file_exists(PID_FILE)) {
                $pid = (int)file_get_contents(PID_FILE);
                $running = ($pid > 0 && file_exists("/proc/$pid"));
            }
            $m3u8exists = file_exists(HLS_DIR . '/stream.m3u8');
            $log = '';
            if (file_exists(LOG_FILE)) {
                $lines = file(LOG_FILE);
                $log   = implode('', array_slice($lines, -4));
            }
            echo json_encode([
                'ok'      => true,
                'running' => $running,
                'ready'   => $m3u8exists,
                'log'     => trim($log)
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($nombre) ?> · Visor</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<!-- hls.js para reproducción HLS en el navegador -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js"></script>
<style>
:root {
    --bg: #0e0e0e; --surface: #161616; --border: #2a2a2a;
    --cyan: #00e5ff; --amber: #ffb300; --red: #e53935; --green: #43a047;
    --text: #ddd; --dim: #555;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: 'Share Tech Mono', monospace; min-height: 100vh; display: flex; flex-direction: column; }

/* HEADER */
header {
    background: linear-gradient(135deg, #0a0a0a, #141414);
    border-bottom: 2px solid #222;
    padding: 14px 24px;
    display: flex; align-items: center; gap: 14px;
}
.badge { font-family: 'Orbitron', sans-serif; font-size: 9px; font-weight: 700; color: var(--cyan); background: rgba(0,229,255,.07); border: 1px solid rgba(0,229,255,.25); border-radius: 3px; padding: 3px 9px; letter-spacing: 2px; }
header h1 { font-family: 'Orbitron', sans-serif; font-size: 16px; font-weight: 900; letter-spacing: 4px; color: #fff; }
.a-back { margin-left: auto; font-family: 'Orbitron', sans-serif; font-size: 9px; letter-spacing: 2px; padding: 6px 14px; border-radius: 4px; border: 1px solid #333; background: #1a1a1a; color: #777; text-decoration: none; transition: all .2s; }
.a-back:hover { background: #222; color: #fff; border-color: #555; }

/* MAIN */
main { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 24px 16px; gap: 16px; }

/* VIDEO WRAPPER */
.video-wrap {
    width: 100%; max-width: 860px;
    background: #000;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
    position: relative;
    aspect-ratio: 16/9;
}
#video { width: 100%; height: 100%; display: block; object-fit: contain; }

/* OVERLAY de estado */
.overlay-msg {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px;
    background: rgba(0,0,0,.75);
    font-family: 'Orbitron', sans-serif;
}
.overlay-msg.hidden { display: none; }
.spinner {
    width: 40px; height: 40px;
    border: 3px solid #222;
    border-top-color: var(--cyan);
    border-radius: 50%;
    animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.overlay-msg p { font-size: 12px; letter-spacing: 2px; color: var(--cyan); }
.overlay-msg .err-msg { color: var(--red); font-size: 11px; letter-spacing: 1px; text-align: center; max-width: 300px; line-height: 1.6; }

/* CONTROLES */
.controls {
    width: 100%; max-width: 860px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 14px 18px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.btn {
    font-family: 'Orbitron', sans-serif; font-size: 9px; letter-spacing: 2px;
    padding: 8px 18px; border-radius: 4px; border: none; cursor: pointer;
    transition: all .15s; white-space: nowrap;
}
.btn-start { background: var(--green); color: #fff; }
.btn-start:hover { background: #2e7d32; }
.btn-stop  { background: var(--red);   color: #fff; }
.btn-stop:hover  { background: #b71c1c; }
.btn:disabled { opacity: .4; cursor: not-allowed; }

.status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.status-dot.off     { background: #444; }
.status-dot.loading { background: var(--amber); animation: pulse 1s infinite; }
.status-dot.on      { background: var(--green); box-shadow: 0 0 8px var(--green); }
.status-dot.error   { background: var(--red); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

.status-txt { font-size: 12px; color: var(--dim); }
.rtsp-lbl   { margin-left: auto; font-size: 11px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; }

/* LOG BOX */
.log-wrap {
    width: 100%; max-width: 860px;
    background: #0a0a0a;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 14px;
}
.log-wrap summary { font-family: 'Orbitron', sans-serif; font-size: 9px; letter-spacing: 2px; color: var(--dim); cursor: pointer; }
.log-wrap summary:hover { color: #888; }
#logBox { margin-top: 8px; font-size: 11px; color: #555; line-height: 1.7; white-space: pre-wrap; word-break: break-all; max-height: 120px; overflow-y: auto; }

/* Scrollbar */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: #0a0a0a; }
::-webkit-scrollbar-thumb { background: #333; border-radius: 2px; }
</style>
</head>
<body>

<header>
    <div class="badge">CAM</div>
    <h1><?= htmlspecialchars(strtoupper($nombre)) ?></h1>
    <a href="mis_enlaces.php" class="a-back">← PANEL</a>
</header>

<main>
    <!-- VIDEO -->
    <div class="video-wrap">
        <video id="video" autoplay muted playsinline></video>
        <div class="overlay-msg" id="overlay">
            <div class="spinner"></div>
            <p id="overlayTxt">INICIANDO STREAM…</p>
            <span class="err-msg" id="overlayErr"></span>
        </div>
    </div>

    <!-- CONTROLES -->
    <div class="controls">
        <div class="status-dot off" id="dot"></div>
        <span class="status-txt" id="statusTxt">Detenido</span>
        <button class="btn btn-start" id="btnStart" onclick="startStream()">▶ INICIAR</button>
        <button class="btn btn-stop"  id="btnStop"  onclick="stopStream()" disabled>■ DETENER</button>
        <span class="rtsp-lbl" title="<?= htmlspecialchars($rtsp) ?>"><?= htmlspecialchars($rtsp) ?></span>
    </div>

    <!-- LOG -->
    <details class="log-wrap">
        <summary>LOG FFMPEG</summary>
        <div id="logBox">—</div>
    </details>
</main>

<script>
const RTSP   = <?= json_encode($rtsp) ?>;
const NOMBRE = <?= json_encode($nombre) ?>;
const HLS_M3U8 = '<?= HLS_URL ?>/stream.m3u8';
const BASE_URL = location.pathname + '?rtsp=' + encodeURIComponent(RTSP) + '&nombre=' + encodeURIComponent(NOMBRE);

let hls       = null;
let statusInt = null;

// ── Estado visual ─────────────────────────────────────────
function setStatus(state, msg) {
    const dot = document.getElementById('dot');
    const txt = document.getElementById('statusTxt');
    dot.className = 'status-dot ' + state;
    txt.textContent = msg;
}

function showOverlay(visible, msg = '', err = '') {
    const el  = document.getElementById('overlay');
    const txt = document.getElementById('overlayTxt');
    const er  = document.getElementById('overlayErr');
    el.classList.toggle('hidden', !visible);
    txt.textContent = msg;
    er.textContent  = err;
}

function setLog(txt) {
    document.getElementById('logBox').textContent = txt || '—';
}

// ── HLS player ────────────────────────────────────────────
function iniciarPlayer() {
    const video = document.getElementById('video');
    if (hls) { hls.destroy(); hls = null; }

    if (Hls.isSupported()) {
        hls = new Hls({
            liveSyncDurationCount: 2,
            liveMaxLatencyDurationCount: 4,
            lowLatencyMode: true,
        });
        hls.loadSource(HLS_M3U8 + '?t=' + Date.now());
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play();
            showOverlay(false);
            setStatus('on', 'En directo');
        });
        hls.on(Hls.Events.ERROR, (e, data) => {
            if (data.fatal) {
                setStatus('error', 'Error de reproducción');
                showOverlay(true, 'ERROR', data.details);
            }
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari nativo
        video.src = HLS_M3U8 + '?t=' + Date.now();
        video.play();
        showOverlay(false);
        setStatus('on', 'En directo');
    } else {
        showOverlay(true, 'NAVEGADOR NO COMPATIBLE', 'Usa Chrome o Firefox');
        setStatus('error', 'Navegador no compatible');
    }
}

// ── Iniciar stream ────────────────────────────────────────
async function startStream() {
    document.getElementById('btnStart').disabled = true;
    document.getElementById('btnStop').disabled  = false;
    setStatus('loading', 'Iniciando ffmpeg…');
    showOverlay(true, 'INICIANDO STREAM…');

    try {
        const r = await fetch(BASE_URL + '&action=start');
        const d = await r.json();
        setLog(d.log || '');
        if (d.ok) {
            setStatus('loading', 'Conectando player…');
            showOverlay(true, 'CONECTANDO…');
            iniciarPlayer();
            startStatusPoll();
        } else {
            setStatus('error', 'Error');
            showOverlay(true, 'ERROR AL INICIAR', d.msg);
            document.getElementById('btnStart').disabled = false;
            document.getElementById('btnStop').disabled  = true;
        }
    } catch(e) {
        setStatus('error', 'Sin respuesta');
        showOverlay(true, 'ERROR', e.message);
        document.getElementById('btnStart').disabled = false;
    }
}

// ── Detener stream ────────────────────────────────────────
async function stopStream() {
    stopStatusPoll();
    if (hls) { hls.destroy(); hls = null; }
    document.getElementById('video').src = '';
    document.getElementById('btnStop').disabled  = true;
    document.getElementById('btnStart').disabled = false;
    showOverlay(true, 'DETENIENDO…');
    setStatus('loading', 'Deteniendo…');
    try {
        const r = await fetch(BASE_URL + '&action=stop');
        const d = await r.json();
        setLog('');
    } catch(e) {}
    setStatus('off', 'Detenido');
    showOverlay(true, 'STREAM DETENIDO', '', '');
    document.getElementById('overlay').querySelector('.spinner').style.display = 'none';
    document.getElementById('overlayTxt').textContent = 'Pulsa INICIAR para conectar';
}

// ── Polling de estado ─────────────────────────────────────
function startStatusPoll() {
    stopStatusPoll();
    statusInt = setInterval(async () => {
        try {
            const r = await fetch(BASE_URL + '&action=status');
            const d = await r.json();
            setLog(d.log || '');
            if (!d.running) {
                setStatus('error', 'ffmpeg se detuvo');
                showOverlay(true, 'STREAM INTERRUMPIDO', '', 'ffmpeg finalizó inesperadamente');
                stopStatusPoll();
                document.getElementById('btnStart').disabled = false;
                document.getElementById('btnStop').disabled  = true;
            }
        } catch(e) {}
    }, 5000);
}

function stopStatusPoll() {
    if (statusInt) { clearInterval(statusInt); statusInt = null; }
}

// Mostrar overlay inicial
showOverlay(true, 'STREAM DETENIDO', '', '');
document.getElementById('overlay').querySelector('.spinner').style.display = 'none';
document.getElementById('overlayTxt').textContent = 'Pulsa INICIAR para conectar';

// Auto-iniciar
startStream();
</script>
</body>
</html>
