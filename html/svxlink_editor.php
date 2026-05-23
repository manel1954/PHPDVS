<?php
// =====================================================
// EDITOR SVXLINK / ECHOLINK
// Edita: ModuleEchoLink.conf + svxlink.conf
// =====================================================

$message       = "";
$message_type  = "";

// ---- AUTO-DETECCIÓN DE RUTAS ----
$echolink_candidates = [
    "/usr/local/etc/svxlink/svxlink.d/ModuleEchoLink.conf",
    "/etc/svxlink/svxlink.d/ModuleEchoLink.conf",
    "/etc/svxlink.d/ModuleEchoLink.conf",
    "/usr/share/svxlink/ModuleEchoLink.conf",
    "/home/pi/svxlink/ModuleEchoLink.conf",
];
$svxlink_candidates = [
    "/usr/local/etc/svxlink/svxlink.conf",
    "/etc/svxlink/svxlink.conf",
    "/etc/svxlink.conf",
    "/home/pi/svxlink/svxlink.conf",
];

// Permitir override manual por GET o sesión
session_start();
if (isset($_POST['set_paths'])) {
    $_SESSION['echolink_conf'] = $_POST['custom_echolink'] ?? '';
    $_SESSION['svxlink_conf']  = $_POST['custom_svxlink']  ?? '';
}
if (isset($_SESSION['echolink_conf']) && file_exists($_SESSION['echolink_conf'])) {
    $echolink_conf = $_SESSION['echolink_conf'];
} else {
    $echolink_conf = '';
    foreach ($echolink_candidates as $c) { if (file_exists($c)) { $echolink_conf = $c; break; } }
}
if (isset($_SESSION['svxlink_conf']) && file_exists($_SESSION['svxlink_conf'])) {
    $svxlink_conf = $_SESSION['svxlink_conf'];
} else {
    $svxlink_conf = '';
    foreach ($svxlink_candidates as $c) { if (file_exists($c)) { $svxlink_conf = $c; break; } }
}

// Estado de ficheros para diagnóstico
$diag = [
    'echolink' => [
        'path'     => $echolink_conf ?: '(no encontrado)',
        'exists'   => $echolink_conf && file_exists($echolink_conf),
        'readable' => $echolink_conf && is_readable($echolink_conf),
    ],
    'svxlink' => [
        'path'     => $svxlink_conf ?: '(no encontrado)',
        'exists'   => $svxlink_conf && file_exists($svxlink_conf),
        'readable' => $svxlink_conf && is_readable($svxlink_conf),
    ],
];

// ---- AJAX: devolver contenido raw de fichero ----
if (isset($_GET['rawfile'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $f = $_GET['rawfile'] === 'echolink' ? $echolink_conf : $svxlink_conf;
    echo file_exists($f) ? file_get_contents($f) : "# Fichero no encontrado: $f";
    exit;
}

// ---- AJAX: guardar contenido raw de fichero ----
if (isset($_POST['rawsave'])) {
    $f = $_POST['rawsave'] === 'echolink' ? $echolink_conf : $svxlink_conf;
    file_put_contents($f, $_POST['rawcontent']);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ---- HELPERS ----

function read_conf(string $file): array {
    if (!file_exists($file)) return [];
    $data = [];
    $section = '__GLOBAL__';
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = rtrim($line);
        if (preg_match('/^\[(.+?)\]/', $line, $m)) {
            $section = $m[1];
        } elseif (preg_match('/^([A-Z_0-9]+)\s*=\s*(.*)/', $line, $m)) {
            $data[$section][$m[1]] = trim($m[2], '"\'');
        }
    }
    return $data;
}

function update_conf(string $file, string $section, array $updates): void {
    if (!file_exists($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $in_section = false;
    $done = [];
    $result = [];

    foreach ($lines as $line) {
        $trimmed = rtrim($line);

        if (preg_match('/^\[(.+?)\]/', $trimmed, $m)) {
            $in_section = ($m[1] === $section);
            $result[] = $trimmed;
            continue;
        }

        if ($in_section) {
            $replaced = false;
            foreach ($updates as $key => $value) {
                // activa o comentada
                if (preg_match('/^#?\s*' . preg_quote($key, '/') . '\s*=/', $trimmed)) {
                    $result[]    = $key . '=' . $value;
                    $done[$key]  = true;
                    $replaced    = true;
                    unset($updates[$key]);
                    break;
                }
            }
            if (!$replaced) $result[] = $trimmed;
        } else {
            $result[] = $trimmed;
        }
    }
    file_put_contents($file, implode("\n", $result) . "\n");
}

// ---- PROCESAR POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
        // ModuleEchoLink.conf
        update_conf($echolink_conf, 'ModuleEchoLink', [
            'CALLSIGN'            => $_POST['callsign']        ?? '',
            'PASSWORD'            => $_POST['password']        ?? '',
            'SYSOPNAME'           => $_POST['sysopname']       ?? '',
            'LOCATION'            => $_POST['location']        ?? '',
            'AUTOCON_ECHOLINK_ID' => $_POST['autocon_id']      ?? '',
            'PROXY_SERVER'        => $_POST['proxy_server']    ?? '',
            'PROXY_PORT'          => $_POST['proxy_port']      ?? '',
            'PROXY_PASSWORD'      => $_POST['proxy_password']  ?? '',
            'MAX_CONNECTIONS'     => $_POST['max_connections'] ?? '',
        ]);

        // svxlink.conf [SimplexLogic]
        update_conf($svxlink_conf, 'SimplexLogic', [
            'SHORT_IDENT_INTERVAL' => $_POST['short_ident']  ?? '30',
            'RGR_SOUND_DELAY'      => $_POST['rgr_delay']    ?? '0',
        ]);

        // svxlink.conf [Rx1]
        update_conf($svxlink_conf, 'Rx1', [
            'AUDIO_DEV' => $_POST['audio_rx']  ?? '',
            'SQL_DET'   => $_POST['sql_det']   ?? 'CTCSS',
            'CTCSS_FQ'  => $_POST['ctcss_fq']  ?? '',
        ]);

        // svxlink.conf [Tx1]
        update_conf($svxlink_conf, 'Tx1', [
            'AUDIO_DEV' => $_POST['audio_tx']  ?? '',
            'PTT_TYPE'  => $_POST['ptt_type']  ?? 'NONE',
            'PTT_PORT'  => $_POST['ptt_port']  ?? '',
        ]);

        $message      = "✅ Configuración guardada correctamente";
        $message_type = "success";
    }

    elseif ($action === 'toggle_sql') {
        $svx     = read_conf($svxlink_conf);
        $current = $svx['Rx1']['SQL_DET'] ?? 'CTCSS';
        $new     = ($current === 'CTCSS') ? 'VOX' : 'CTCSS';
        update_conf($svxlink_conf, 'Rx1', ['SQL_DET' => $new]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    elseif ($action === 'toggle_rgr') {
        $svx     = read_conf($svxlink_conf);
        $current = $svx['SimplexLogic']['RGR_SOUND_DELAY'] ?? '0';
        $new     = ($current === '0') ? '500' : '0';
        update_conf($svxlink_conf, 'SimplexLogic', ['RGR_SOUND_DELAY' => $new]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    elseif ($action === 'toggle_ptt') {
        $svx     = read_conf($svxlink_conf);
        $current = $svx['Tx1']['PTT_TYPE'] ?? 'NONE';
        $new     = ($current === 'NONE') ? 'SERIAL' : 'NONE';
        update_conf($svxlink_conf, 'Tx1', ['PTT_TYPE' => $new]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ---- LEER VALORES ACTUALES ----
$svx = read_conf($svxlink_conf);
$el  = read_conf($echolink_conf);

$callsign        = $el['ModuleEchoLink']['CALLSIGN']            ?? '';
$password        = $el['ModuleEchoLink']['PASSWORD']            ?? '';
$sysopname       = $el['ModuleEchoLink']['SYSOPNAME']           ?? '';
$location        = $el['ModuleEchoLink']['LOCATION']            ?? '';
$autocon_id      = $el['ModuleEchoLink']['AUTOCON_ECHOLINK_ID'] ?? '';
$proxy_server    = $el['ModuleEchoLink']['PROXY_SERVER']        ?? '';
$proxy_port      = $el['ModuleEchoLink']['PROXY_PORT']          ?? '';
$proxy_password  = $el['ModuleEchoLink']['PROXY_PASSWORD']      ?? '';
$max_connections = $el['ModuleEchoLink']['MAX_CONNECTIONS']     ?? '';

$short_ident     = $svx['SimplexLogic']['SHORT_IDENT_INTERVAL'] ?? '30';
$rgr_delay       = $svx['SimplexLogic']['RGR_SOUND_DELAY']      ?? '0';
$audio_rx        = $svx['Rx1']['AUDIO_DEV']                     ?? '';
$sql_det         = $svx['Rx1']['SQL_DET']                       ?? 'CTCSS';
$ctcss_fq        = $svx['Rx1']['CTCSS_FQ']                      ?? '';
$audio_tx        = $svx['Tx1']['AUDIO_DEV']                     ?? '';
$ptt_type        = $svx['Tx1']['PTT_TYPE']                      ?? 'NONE';
$ptt_port        = $svx['Tx1']['PTT_PORT']                      ?? '';

$roger_active    = ($rgr_delay !== '0');
$is_soundcard    = ($ptt_type  === 'NONE');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editor SvxLink</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📻</text></svg>">
<style>
:root {
    --bg:        #1a1f2e;
    --bg2:       #242b3d;
    --bg3:       #2d3651;
    --border:    #3a4460;
    --text:      #d0d8f0;
    --text-dim:  #7a8aaa;
    --blue:      #4a90d9;
    --blue-dark: #2563eb;
    --green:     #22c55e;
    --green-dark:#15803d;
    --orange:    #f59e0b;
    --orange-dk: #b45309;
    --cyan:      #22d3ee;
    --red:       #ef4444;
    --label-bg:  #1e2640;
    --val-bg:    #111827;
    --radius:    6px;
    --font-mono: 'Courier New', monospace;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: Arial, sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 20px;
}

/* ── TARJETA PRINCIPAL (aspecto modal) ── */
.editor-card {
    background: var(--bg2);
    border: 2px solid var(--border);
    border-radius: 10px;
    width: 100%;
    max-width: 780px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.6);
}

/* ── BARRA DE TÍTULO ── */
.title-bar {
    background: #2a3150;
    border-bottom: 2px solid var(--border);
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.title-bar h2 {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 1px;
    color: #e8eeff;
}
.btn-close-x {
    background: var(--red);
    border: none;
    border-radius: 50%;
    width: 22px; height: 22px;
    color: #fff;
    font-size: 12px;
    cursor: pointer;
    line-height: 22px;
    text-align: center;
    display: inline-block;
    text-decoration: none;
    font-weight: bold;
}
.btn-close-x:hover { background: #dc2626; }

/* ── CUERPO ── */
.editor-body { padding: 14px 16px; }

/* ── MENSAJE ── */
.msg {
    padding: 8px 14px;
    border-radius: var(--radius);
    margin-bottom: 12px;
    font-size: .88rem;
    font-weight: 600;
}
.msg-success { background: #14532d; border: 1px solid var(--green); color: #86efac; }
.msg-info    { background: #1e3a5f; border: 1px solid var(--blue);  color: #93c5fd; }

/* ── GRID DE CAMPOS ── */
.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5px;
    margin-bottom: 5px;
}
.field-grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 5px;
    margin-bottom: 5px;
}
.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5px;
    margin-bottom: 5px;
}
.field-full {
    display: flex;
    gap: 5px;
    margin-bottom: 5px;
}

/* ── CELDA ETIQUETA / VALOR ── */
.cell-label {
    background: var(--label-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 6px 10px;
    font-size: .78rem;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    white-space: nowrap;
}
.cell-input {
    background: var(--val-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 6px 10px;
    font-size: .82rem;
    color: var(--text);
    width: 100%;
    outline: none;
    font-family: var(--font-mono);
}
.cell-input:focus { border-color: var(--blue); }

/* Password wrapper with eye icon */
.pw-wrap {
    position: relative;
    width: 100%;
}
.pw-wrap input { padding-right: 32px; }
.pw-eye {
    position: absolute;
    right: 8px; top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--text-dim);
    font-size: .85rem;
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
}
.pw-eye:hover { color: var(--blue); }

/* Valor de solo lectura */
.cell-value {
    background: var(--val-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 6px 10px;
    font-size: .82rem;
    color: var(--cyan);
    font-family: var(--font-mono);
    display: flex;
    align-items: center;
}

/* ── BANNERS ── */
.banner {
    border-radius: var(--radius);
    padding: 8px 14px;
    text-align: center;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    width: 100%;
    letter-spacing: .5px;
    margin-bottom: 5px;
}
.banner-orange { background: var(--orange); color: #1a1000; }
.banner-orange:hover { background: var(--orange-dk); color: #fff; }

.banner-status {
    background: #0d1a10;
    border: 2px solid var(--green);
    color: var(--green);
    font-size: .85rem;
    cursor: default;
    margin-bottom: 5px;
}
.banner-status.inactive {
    border-color: var(--orange);
    color: var(--orange);
}

/* ── BOTONES ── */
.btn-row {
    display: flex;
    gap: 6px;
    margin-bottom: 5px;
}
.btn-action {
    flex: 1;
    padding: 8px 10px;
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    font-size: .82rem;
    font-weight: 600;
    color: #fff;
    letter-spacing: .3px;
}
.btn-blue      { background: var(--blue-dark); }
.btn-blue:hover { background: #1d4ed8; }
.btn-orange    { background: var(--orange); color: #1a1000; }
.btn-orange:hover { background: var(--orange-dk); color: #fff; }
.btn-gray      { background: #374151; }
.btn-gray:hover { background: #4b5563; }
.btn-cyan      { background: #0891b2; }
.btn-cyan:hover { background: #0e7490; }
.btn-violet    { background: #7c3aed; }
.btn-violet:hover { background: #6d28d9; }

/* Botón GUARDAR Y SALIR */
.btn-save {
    width: 100%;
    padding: 12px;
    background: var(--green-dark);
    color: #fff;
    border: none;
    border-radius: var(--radius);
    font-size: .95rem;
    font-weight: 800;
    letter-spacing: 1px;
    cursor: pointer;
    margin-top: 6px;
}
.btn-save:hover { background: #16a34a; }

/* Estado badge */
.state-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .5px;
    vertical-align: middle;
}
.badge-on  { background: var(--green-dark); color: #d1fae5; }
.badge-off { background: #7f1d1d;           color: #fca5a5; }

/* ── SEPARADOR ── */
.sep { border: none; border-top: 1px solid var(--border); margin: 8px 0; }

/* ── RAW FILE EDITOR (modal interno) ── */
.raw-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.75);
    z-index: 100;
    align-items: center;
    justify-content: center;
}
.raw-overlay.active { display: flex; }
.raw-modal {
    background: var(--bg2);
    border: 2px solid var(--border);
    border-radius: 10px;
    width: 90%;
    max-width: 700px;
    padding: 16px;
}
.raw-modal h3 {
    font-size: .9rem;
    margin-bottom: 10px;
    color: var(--orange);
}
.raw-textarea {
    width: 100%;
    height: 380px;
    background: #060d1a;
    color: #22c55e;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 10px;
    font-family: var(--font-mono);
    font-size: .78rem;
    resize: vertical;
}
.raw-btn-row {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

/* Input number pequeño */
input[type="number"].cell-input { -moz-appearance: textfield; }
input[type="number"].cell-input::-webkit-inner-spin-button { -webkit-appearance: none; }

/* Responsivo */
@media(max-width:600px) {
    .field-grid,
    .field-row   { grid-template-columns: 1fr; }
    .field-grid-3{ grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ╔══════════════════════════════════════╗ -->
<!-- ║        TARJETA PRINCIPAL             ║ -->
<!-- ╚══════════════════════════════════════╝ -->
<div class="editor-card">

    <!-- Barra de título -->
    <div class="title-bar">
        <h2>✏️ &nbsp;EDITOR SVXLINK</h2>
        <a class="btn-close-x" href="javascript:window.close()" title="Cerrar">✕</a>
    </div>

    <div class="editor-body">

        <!-- ── DIAGNÓSTICO DE FICHEROS ── -->
        <?php $all_ok = $diag['echolink']['readable'] && $diag['svxlink']['readable']; ?>
        <div style="background:<?= $all_ok ? '#0d1f10' : '#1f0d0d' ?>;
                    border:1px solid <?= $all_ok ? '#22c55e' : '#ef4444' ?>;
                    border-radius:6px; padding:8px 12px; margin-bottom:10px; font-size:.78rem;">
            <div style="font-weight:700;margin-bottom:4px;color:<?= $all_ok ? '#86efac' : '#fca5a5' ?>">
                <?= $all_ok ? '✅ Ficheros de configuración cargados correctamente' : '⚠️ Problema leyendo los ficheros' ?>
            </div>
            <?php foreach (['echolink'=>'ModuleEchoLink.conf','svxlink'=>'svxlink.conf'] as $k=>$label): ?>
            <div style="display:flex;gap:6px;align-items:center;margin-top:3px;flex-wrap:wrap">
                <span style="color:<?= $diag[$k]['readable'] ? '#22c55e' : '#ef4444' ?>">●</span>
                <span style="color:#7a8aaa"><?= $label ?>:</span>
                <span style="color:#d0d8f0;font-family:monospace;font-size:.75rem"><?= htmlspecialchars($diag[$k]['path']) ?></span>
                <?php if (!$diag[$k]['exists']): ?>
                    <span style="color:#fca5a5;font-weight:700">[NO ENCONTRADO]</span>
                <?php elseif (!$diag[$k]['readable']): ?>
                    <span style="color:#fbbf24;font-weight:700">[SIN PERMISO — ejecuta: sudo chmod a+r <?= htmlspecialchars($diag[$k]['path']) ?>]</span>
                <?php else: ?>
                    <span style="color:#22c55e">[OK]</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$all_ok): ?>
            <details style="margin-top:8px;">
                <summary style="cursor:pointer;color:#f59e0b;font-weight:600;">🔧 Especificar rutas manualmente</summary>
                <form method="post" style="margin-top:8px;">
                    <div style="display:grid;grid-template-columns:auto 1fr;gap:4px 8px;align-items:center;margin-bottom:6px;">
                        <span style="color:#7a8aaa;font-size:.75rem">ModuleEchoLink.conf:</span>
                        <input type="text" name="custom_echolink"
                               style="background:#111827;border:1px solid #3a4460;border-radius:4px;padding:4px 8px;color:#d0d8f0;font-family:monospace;font-size:.75rem;width:100%"
                               value="<?= htmlspecialchars($diag['echolink']['path'] !== '(no encontrado)' ? $diag['echolink']['path'] : '') ?>"
                               placeholder="/etc/svxlink/svxlink.d/ModuleEchoLink.conf">
                        <span style="color:#7a8aaa;font-size:.75rem">svxlink.conf:</span>
                        <input type="text" name="custom_svxlink"
                               style="background:#111827;border:1px solid #3a4460;border-radius:4px;padding:4px 8px;color:#d0d8f0;font-family:monospace;font-size:.75rem;width:100%"
                               value="<?= htmlspecialchars($diag['svxlink']['path'] !== '(no encontrado)' ? $diag['svxlink']['path'] : '') ?>"
                               placeholder="/etc/svxlink/svxlink.conf">
                    </div>
                    <button type="submit" name="set_paths" value="1"
                            style="background:#2563eb;color:#fff;border:none;border-radius:4px;padding:5px 14px;cursor:pointer;font-size:.78rem;">
                        💾 Aplicar rutas
                    </button>
                    <small style="color:#7a8aaa;margin-left:10px;">
                        Buscar en la Pi: <code style="color:#22d3ee">find / -name "svxlink.conf" 2>/dev/null</code>
                    </small>
                </form>
            </details>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
        <div class="msg msg-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post" id="mainForm">
        <input type="hidden" name="action" value="save">

        <!-- ── FILA 1: CALLSIGN / PASSWD ── -->
        <div class="field-row">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">CALLSIGN:</span>
                <input class="cell-input" type="text" name="callsign"
                       value="<?= htmlspecialchars($callsign) ?>" style="flex:1">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">PASSWD:</span>
                <div class="pw-wrap" style="flex:1">
                    <input class="cell-input" type="password" id="pw_main" name="password"
                           value="<?= htmlspecialchars($password) ?>">
                    <button type="button" class="pw-eye" onclick="togglePw('pw_main')">👁</button>
                </div>
            </div>
        </div>

        <!-- ── FILA 2: SYSOPNAME / LOCATION ── -->
        <div class="field-row">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">SYSOPNAME:</span>
                <input class="cell-input" type="text" name="sysopname"
                       value="<?= htmlspecialchars($sysopname) ?>" style="flex:1">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">LOCATION:</span>
                <input class="cell-input" type="text" name="location"
                       value="<?= htmlspecialchars($location) ?>" style="flex:1">
            </div>
        </div>

        <!-- ── FILA 3: AUTOCON ID ── -->
        <div style="display:flex;gap:5px;margin-bottom:5px;align-items:center;">
            <div class="cell-label" style="flex:1;background:#1a2a50;border-color:#3a5080;color:#93c5fd;">
                AUTOCON_ECHOLINK_ID &nbsp;<small style="color:var(--text-dim);">Conferencia</small>
            </div>
            <input class="cell-input" type="text" name="autocon_id"
                   value="<?= htmlspecialchars($autocon_id) ?>" style="width:140px;text-align:center;font-size:.95rem;">
        </div>

        <!-- ── FILA 4: PROXY_SERVER / PROXY_PASS ── -->
        <div class="field-row">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">PROXY_SERVER:</span>
                <input class="cell-input" type="text" name="proxy_server"
                       value="<?= htmlspecialchars($proxy_server) ?>" style="flex:1">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">PROXY_PASS:</span>
                <div class="pw-wrap" style="flex:1">
                    <input class="cell-input" type="password" id="pw_proxy" name="proxy_password"
                           value="<?= htmlspecialchars($proxy_password) ?>">
                    <button type="button" class="pw-eye" onclick="togglePw('pw_proxy')">👁</button>
                </div>
            </div>
        </div>

        <!-- ── FILA 5: PROXY_PORT / MAX_CONNECTIONS ── -->
        <div class="field-row" style="margin-bottom:8px;">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">PROXY_PORT:</span>
                <input class="cell-input" type="number" name="proxy_port"
                       value="<?= htmlspecialchars($proxy_port) ?>" style="flex:1">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">MAX_CONNECTIONS:</span>
                <input class="cell-input" type="number" name="max_connections"
                       value="<?= htmlspecialchars($max_connections) ?>" style="flex:1">
            </div>
        </div>

        <!-- ── BANNER: Abrir ModuleEchoLink.conf ── -->
        <button type="button" class="banner banner-orange"
                onclick="openRaw('echolink')">
            📂 Abrir fichero ModuleEchoLink.conf para hacer cualquier cambio
        </button>

        <hr class="sep">

        <!-- ── FILA 6: SHORT_IDENT / CTCSS_FQ / Alsamixer ── -->
        <div class="field-grid-3" style="margin-bottom:5px;">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label" style="font-size:.72rem;">SHORT_IDENT_INTERVAL:</span>
                <input class="cell-input" type="number" name="short_ident"
                       value="<?= htmlspecialchars($short_ident) ?>" style="width:60px;text-align:center;">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">CTCSS_FQ:</span>
                <input class="cell-input" type="text" name="ctcss_fq"
                       value="<?= htmlspecialchars($ctcss_fq) ?>" style="width:60px;text-align:center;">
            </div>
            <button type="button" class="btn-action btn-cyan"
                    onclick="alert('Abre una terminal SSH y ejecuta: alsamixer')">
                🎚️ Alsamixer
            </button>
        </div>

        <!-- ── FILA 7: AUDIO RX / AUDIO TX ── -->
        <div class="field-row">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label" style="white-space:nowrap">AUDIO RX</span>
                <input class="cell-input" type="text" name="audio_rx"
                       value="<?= htmlspecialchars($audio_rx) ?>" style="flex:1">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label" style="white-space:nowrap">AUDIO TX</span>
                <input class="cell-input" type="text" name="audio_tx"
                       value="<?= htmlspecialchars($audio_tx) ?>" style="flex:1">
            </div>
        </div>

        <!-- ── FILA 8: PTT_TYPE / PTT PORT ── -->
        <div class="field-row">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">PTT_TYPE</span>
                <input class="cell-input" type="text" name="ptt_type"
                       value="<?= htmlspecialchars($ptt_type) ?>" style="flex:1;font-weight:700;">
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label">PTT PORT</span>
                <input class="cell-input" type="text" name="ptt_port"
                       value="<?= htmlspecialchars($ptt_port) ?>" style="flex:1;">
            </div>
        </div>

        <!-- ── FILA 9: TONO/VOX / ROGER BEEP ── -->
        <div class="field-row" style="margin-bottom:6px;">
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label" style="white-space:nowrap">TONO ó VOX:</span>
                <span class="cell-value" style="flex:1;"><?= htmlspecialchars($sql_det) ?></span>
            </div>
            <div style="display:flex;gap:5px;align-items:center;">
                <span class="cell-label" style="white-space:nowrap">Roger Beep:</span>
                <span class="cell-value" style="flex:1;">
                    <?php if ($roger_active): ?>
                        <span class="state-badge badge-on">ACTIVADO</span>
                    <?php else: ?>
                        <span class="state-badge badge-off">DESACTIVADO</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- ── BOTONES TOGGLE SQL / ROGER ── -->
        <!-- (fuera del form para evitar submit) -->
        </form><!-- cierre form principal aquí -->

        <div class="btn-row">
            <form method="post" style="flex:1">
                <input type="hidden" name="action" value="toggle_sql">
                <button type="submit" class="btn-action btn-blue" style="width:100%">
                    <?= ($sql_det === 'CTCSS') ? '🔄 Cambiar a VOX' : '🔄 Cambiar a CTCSS' ?>
                </button>
            </form>
            <form method="post" style="flex:1">
                <input type="hidden" name="action" value="toggle_rgr">
                <button type="submit" class="btn-action <?= $roger_active ? 'btn-violet' : 'btn-gray' ?>"
                        style="width:100%">
                    <?= $roger_active ? '🔕 Desactivar Roger Beep' : '🔔 Activar Roger Beep' ?>
                </button>
            </form>
        </div>

        <hr class="sep">

        <!-- ── BANNER SISTEMA ACTIVO ── -->
        <div class="banner banner-status <?= $is_soundcard ? '' : 'inactive' ?>">
            SISTEMA ACTIVO:
            <?= $is_soundcard
                ? '🎵 modem Sound card o similar (PTT_TYPE=NONE)'
                : '🔌 modem con resistencia y transistor (PTT_TYPE=' . htmlspecialchars($ptt_type) . ')' ?>
        </div>

        <!-- ── BOTONES SISTEMA + ABRIR svxlink.conf ── -->
        <div class="btn-row">
            <form method="post" style="flex:1">
                <input type="hidden" name="action" value="toggle_ptt">
                <button type="submit" class="btn-action btn-gray" style="width:100%">
                    <?= $is_soundcard
                        ? '🔌 CAMBIAR a modem con resistencia y transistor'
                        : '🎵 CAMBIAR a modem Sound card (PTT_TYPE=NONE)' ?>
                </button>
            </form>
            <button type="button" class="btn-action btn-orange" style="flex:1"
                    onclick="openRaw('svxlink')">
                📂 Abrir fichero svxlink.conf
            </button>
        </div>

        <!-- ── BOTONES SALIR / GUARDAR ── -->
        <div style="display:flex;gap:6px;margin-top:6px;">
            <a href="javascript:window.close()"
               style="flex:1;display:block;padding:12px;background:#374151;color:#d0d8f0;
                      border-radius:6px;text-align:center;font-size:.95rem;font-weight:800;
                      letter-spacing:1px;text-decoration:none;">
                ✕ &nbsp;SALIR
            </a>
            <button type="button" class="btn-save" style="flex:2;margin-top:0"
                    onclick="document.getElementById('mainForm').submit()">
                💾 &nbsp;GUARDAR
            </button>
        </div>

    </div><!-- /editor-body -->
</div><!-- /editor-card -->

<!-- ══════════════════════════════════════════════ -->
<!-- RAW FILE EDITOR — ECHOLINK                     -->
<!-- ══════════════════════════════════════════════ -->
<div class="raw-overlay" id="overlay-echolink">
    <div class="raw-modal">
        <h3>📂 ModuleEchoLink.conf &nbsp;<small style="color:var(--text-dim);font-weight:400;">
            <?= htmlspecialchars($echolink_conf) ?></small></h3>
        <textarea class="raw-textarea" id="raw-echolink">Cargando...</textarea>
        <div class="raw-btn-row">
            <button class="btn-action btn-blue" onclick="saveRaw('echolink')">💾 Guardar</button>
            <button class="btn-action btn-gray" onclick="closeRaw('echolink')">✕ Cerrar sin guardar</button>
        </div>
        <div id="raw-echolink-msg" style="margin-top:8px;font-size:.8rem;color:var(--green);"></div>
    </div>
</div>

<!-- RAW FILE EDITOR — SVXLINK -->
<div class="raw-overlay" id="overlay-svxlink">
    <div class="raw-modal">
        <h3>📂 svxlink.conf &nbsp;<small style="color:var(--text-dim);font-weight:400;">
            <?= htmlspecialchars($svxlink_conf) ?></small></h3>
        <textarea class="raw-textarea" id="raw-svxlink">Cargando...</textarea>
        <div class="raw-btn-row">
            <button class="btn-action btn-blue" onclick="saveRaw('svxlink')">💾 Guardar</button>
            <button class="btn-action btn-gray" onclick="closeRaw('svxlink')">✕ Cerrar sin guardar</button>
        </div>
        <div id="raw-svxlink-msg" style="margin-top:8px;font-size:.8rem;color:var(--green);"></div>
    </div>
</div>

<script>
// ── Toggle visibilidad contraseña ──
function togglePw(id) {
    const el = document.getElementById(id);
    el.type = (el.type === 'password') ? 'text' : 'password';
}

// ── Raw file editor ──
async function openRaw(which) {
    const overlay  = document.getElementById('overlay-' + which);
    const textarea = document.getElementById('raw-' + which);
    overlay.classList.add('active');
    textarea.value = 'Cargando...';
    try {
        const r = await fetch('?rawfile=' + which);
        textarea.value = await r.text();
    } catch(e) {
        textarea.value = '# Error al cargar el fichero';
    }
}

function closeRaw(which) {
    document.getElementById('overlay-' + which).classList.remove('active');
}

async function saveRaw(which) {
    const textarea = document.getElementById('raw-' + which);
    const msg      = document.getElementById('raw-' + which + '-msg');
    const fd = new FormData();
    fd.append('rawsave',    which);
    fd.append('rawcontent', textarea.value);
    try {
        const r = await fetch('', { method:'POST', body:fd });
        const d = await r.json();
        msg.textContent = d.ok ? '✅ Guardado correctamente' : '❌ Error al guardar';
    } catch(e) {
        msg.textContent = '❌ Error de red';
    }
}

// Cerrar overlay al hacer clic fuera del modal
document.querySelectorAll('.raw-overlay').forEach(ov => {
    ov.addEventListener('click', e => {
        if (e.target === ov) ov.classList.remove('active');
    });
});

// Tecla Escape cierra overlays
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.raw-overlay.active').forEach(ov => {
            ov.classList.remove('active');
        });
    }
});
</script>
</body>
</html>
