<?php
// editor_enlaces.php  –  Editor CRUD con posicionado fila/columna
// Mismo directorio que enlaces.json y mis_enlaces.php

define('JSON_FILE', '/home/pi/.local/enlaces.json');
define('CAM_INI',  '/home/pi/.local/camara.ini');

function leerEnlaces(): array {
    if (!file_exists(JSON_FILE)) file_put_contents(JSON_FILE, '[]');
    return json_decode(file_get_contents(JSON_FILE), true) ?? [];
}

function guardarEnlaces(array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents(JSON_FILE, $json) !== false;
}

function sanitizar(array $d): array {
    return [
        'nombre'  => trim($d['nombre']  ?? ''),
        'url'     => trim($d['url']     ?? ''),
        'bg'      => preg_match('/^#[0-9a-fA-F]{3,6}$/', $d['bg'] ?? '') ? $d['bg'] : '#333333',
        'fg'      => preg_match('/^#[0-9a-fA-F]{3,6}$/', $d['fg'] ?? '') ? $d['fg'] : '#ffffff',
        'fila'    => max(1, (int)($d['fila']    ?? 1)),
        'columna' => max(1, (int)($d['columna'] ?? 1)),
        'local'   => !empty($d['local']),
    ];
}

// ── Funciones camara.ini ────────────────────────────────────────
function leerCamaras(): array {
    if (!file_exists(CAM_INI)) return [];
    return parse_ini_file(CAM_INI, true) ?: [];
}

function guardarCamaras(array $cams): bool {
    $txt = '';
    foreach ($cams as $key => $data) {
        $txt .= "[{$key}]\n";
        foreach ($data as $k => $v) {
            $txt .= "{$k} = " . $v . "\n";
        }
        $txt .= "\n";
    }
    return file_put_contents(CAM_INI, $txt) !== false;
}

function nombreAClave(string $nombre): string {
    $clave = strtolower($nombre);
    $clave = preg_replace('/[^a-z0-9]+/', '_', $clave);
    return trim($clave, '_');
}

function respJSON(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── API AJAX ─────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$isAPI  = isset($_GET['api']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

if ($isAPI) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
        case 'list':
            respJSON(['ok' => true, 'data' => leerEnlaces()]);

        case 'add':
            $nuevo = sanitizar($body);
            if ($nuevo['nombre'] === '') respJSON(['ok'=>false,'msg'=>'Nombre requerido'], 400);
            $lista = leerEnlaces();
            $lista[] = $nuevo;
            guardarEnlaces($lista);
            respJSON(['ok'=>true, 'msg'=>'Enlace añadido', 'data'=>$lista]);

        case 'update':
            $idx = (int)($body['index'] ?? -1);
            $lista = leerEnlaces();
            if ($idx < 0 || $idx >= count($lista)) respJSON(['ok'=>false,'msg'=>'Índice inválido'], 400);
            $lista[$idx] = sanitizar($body);
            guardarEnlaces($lista);
            respJSON(['ok'=>true, 'msg'=>'Enlace actualizado', 'data'=>$lista]);

        case 'delete':
            $idx = (int)($body['index'] ?? -1);
            $lista = leerEnlaces();
            if ($idx < 0 || $idx >= count($lista)) respJSON(['ok'=>false,'msg'=>'Índice inválido'], 400);
            $nom = $lista[$idx]['nombre'];
            array_splice($lista, $idx, 1);
            guardarEnlaces($lista);
            respJSON(['ok'=>true, 'msg'=>"\"$nom\" eliminado", 'data'=>$lista]);

        case 'reorder':
            $order = $body['order'] ?? [];
            $lista = leerEnlaces();
            $nueva = [];
            foreach ($order as $i) {
                if (isset($lista[(int)$i])) $nueva[] = $lista[(int)$i];
            }
            guardarEnlaces($nueva);
            respJSON(['ok'=>true, 'msg'=>'Orden guardado']);

        case 'autoplace':
            // Reasignar coordenadas secuenciales según el orden de la lista
            $cols  = max(1, (int)($body['cols'] ?? 3));
            $lista = leerEnlaces();
            foreach ($lista as $i => &$e) {
                $e['fila']    = (int)floor($i / $cols) + 1;
                $e['columna'] = ($i % $cols) + 1;
            }
            unset($e);
            guardarEnlaces($lista);
            respJSON(['ok'=>true, 'msg'=>'Posiciones reasignadas', 'data'=>$lista]);

        // POST ?action=cam_save  body: {key, nombre, rtsp}
        case 'cam_save':
            $key    = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($body['key'] ?? '')));
            $nombre = trim($body['nombre'] ?? '');
            $rtsp   = trim($body['rtsp']   ?? '');
            if ($key === '' || $rtsp === '') respJSON(['ok'=>false,'msg'=>'Clave y RTSP requeridos'], 400);
            $cams = leerCamaras();
            $cams[$key] = ['nombre' => $nombre, 'rtsp' => $rtsp];
            guardarCamaras($cams);
            respJSON(['ok'=>true, 'msg'=>"Cámara '$key' guardada", 'key'=>$key, 'data'=>$cams]);

        // POST ?action=cam_delete  body: {key}
        case 'cam_delete':
            $key  = trim($body['key'] ?? '');
            $cams = leerCamaras();
            if (!isset($cams[$key])) respJSON(['ok'=>false,'msg'=>'Cámara no encontrada'], 404);
            unset($cams[$key]);
            guardarCamaras($cams);
            respJSON(['ok'=>true, 'msg'=>"Cámara '$key' eliminada", 'data'=>$cams]);

        // GET ?action=cam_list
        case 'cam_list':
            respJSON(['ok'=>true, 'data'=>leerCamaras()]);

        default:
            respJSON(['ok'=>false,'msg'=>'Acción desconocida'], 400);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor de Enlaces</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&family=Rajdhani:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#181818;--surface:#222;--card:#262626;--border:#363636;
    --text:#ddd;--dim:#666;
    --cyan:#00e5ff;--amber:#ffb300;--red:#e53935;--green:#43a047;
    --r:4px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:'Rajdhani',sans-serif;height:100vh;overflow:hidden;display:flex;flex-direction:column}

/* HEADER */
header{
    background:linear-gradient(135deg,#111,#1a1a1a);
    border-bottom:2px solid #333;padding:12px 22px;
    display:flex;align-items:center;gap:12px;flex-shrink:0;
}
.badge{font-family:'Orbitron',sans-serif;font-size:9px;font-weight:700;color:var(--cyan);background:rgba(0,229,255,.08);border:1px solid rgba(0,229,255,.28);border-radius:3px;padding:3px 9px;letter-spacing:2px}
header h1{font-family:'Orbitron',sans-serif;font-size:16px;font-weight:900;letter-spacing:5px;color:#fff}
.a-volver{margin-left:auto;font-family:'Orbitron',sans-serif;font-size:9px;letter-spacing:2px;padding:5px 13px;border-radius:var(--r);border:1px solid #444;background:#252525;color:#888;text-decoration:none;transition:all .2s}

.a-volver-ader{margin-left:auto;font-family:'Orbitron',sans-serif;font-size:9px;letter-spacing:2px;padding:5px 13px;border-radius:var(--r);border:1px solid #444;background:#f09809;color:#000;text-decoration:none;transition:all .2s}
.a-volver:hover{background:#333;color:#fff;border-color:#555}

/* LAYOUT PRINCIPAL */
.main{display:grid;grid-template-columns:1fr 350px;flex:1;overflow:hidden}

/* COLUMNA IZQUIERDA: toolbar + tabla */
.col-left{display:flex;flex-direction:column;overflow:hidden;border-right:1px solid var(--border)}
.toolbar{background:var(--surface);border-bottom:1px solid var(--border);padding:8px 14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex-shrink:0}
.tb-input{flex:1;min-width:150px;background:#181818;border:1px solid #444;border-radius:var(--r);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:12px;padding:6px 11px;outline:none;transition:border-color .2s}
.tb-input:focus{border-color:rgba(0,229,255,.45)}
.btn{font-family:'Orbitron',sans-serif;font-size:9px;letter-spacing:1px;padding:6px 13px;border-radius:var(--r);border:none;cursor:pointer;transition:all .15s;white-space:nowrap}
.btn-add{background:var(--green);color:#fff}.btn-add:hover{background:#2e7d32}
.btn-auto{background:#1a3a5c;color:var(--cyan);border:1px solid rgba(0,229,255,.25)}.btn-auto:hover{background:#1e4570}
.btn-del{background:none;border:none;color:#c44;font-size:14px;cursor:pointer;padding:2px 5px;border-radius:3px;transition:all .15s}.btn-del:hover{background:#3d1111;color:#f66}
.btn-edit{background:none;border:none;color:#55f;font-size:14px;cursor:pointer;padding:2px 5px;border-radius:3px;transition:all .15s}.btn-edit:hover{background:#1a3060;color:#7ab}
.count{font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--dim);margin-left:auto;white-space:nowrap}

/* TABLA */
.tbl-wrap{flex:1;overflow-y:auto}
table{width:100%;border-collapse:collapse}
thead th{position:sticky;top:0;z-index:5;background:#1c1c1c;border-bottom:2px solid var(--border);font-family:'Orbitron',sans-serif;font-size:8px;letter-spacing:2px;color:var(--dim);padding:9px 9px;text-align:left;font-weight:400}
tbody tr{border-bottom:1px solid #282828;cursor:grab;transition:background .1s}
tbody tr:hover{background:#2c2c2c}
tbody tr.sel{background:#0e2535!important}
tbody tr.dragging{opacity:.35}
tbody tr.drag-over{border-top:2px solid var(--cyan)}
td{padding:6px 9px;vertical-align:middle}
.td-grip{width:16px;color:#3a3a3a;font-size:15px;text-align:center;padding-left:5px}
.td-col{width:28px}
.dot{width:20px;height:20px;border-radius:3px;display:inline-block;border:1px solid rgba(255,255,255,.12)}
.td-nom{font-family:'Share Tech Mono',monospace;font-size:11px;color:#ccc;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.td-pos{font-family:'Share Tech Mono',monospace;font-size:11px;color:var(--cyan);width:58px;white-space:nowrap;text-align:center}
.td-url{font-size:11px;color:#555;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.td-loc{width:32px;text-align:center;font-size:11px;color:var(--amber)}
.td-act{width:62px;text-align:right;padding-right:6px}

/* COLUMNA DERECHA */
.col-right{display:flex;flex-direction:column;background:var(--card);overflow-y:auto}
.form-hdr{background:#1c1c1c;border-bottom:1px solid var(--border);padding:12px 18px;font-family:'Orbitron',sans-serif;font-size:10px;letter-spacing:3px;color:var(--cyan);flex-shrink:0}
.form-body{padding:16px 18px;display:flex;flex-direction:column;gap:12px}

.field{display:flex;flex-direction:column;gap:4px}
.field label{font-family:'Orbitron',sans-serif;font-size:8px;letter-spacing:2px;color:var(--dim);text-transform:uppercase}
.fi{background:#181818;border:1px solid #444;border-radius:var(--r);color:var(--text);font-family:'Share Tech Mono',monospace;font-size:13px;padding:7px 11px;outline:none;transition:border-color .2s;width:100%}
.fi:focus{border-color:rgba(0,229,255,.5)}
.fi.err{border-color:var(--red)!important}

/* Fila / Columna side by side */
.pos-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.fi-num{background:#181818;border:1px solid #444;border-radius:var(--r);color:var(--cyan);font-family:'Orbitron',monospace;font-size:16px;padding:7px 10px;outline:none;transition:border-color .2s;width:100%;text-align:center;font-weight:700}
.fi-num:focus{border-color:rgba(0,229,255,.6)}
.fi-num.conflict{border-color:var(--amber)!important;color:var(--amber)!important}

/* Conflicto aviso */
.conflict-warn{display:none;background:rgba(255,179,0,.08);border:1px solid rgba(255,179,0,.35);border-radius:var(--r);padding:7px 11px;font-size:12px;color:var(--amber);font-family:'Share Tech Mono',monospace;line-height:1.5}
.conflict-warn.show{display:block}

/* Colores */
.colors-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.color-row{display:flex;align-items:center;gap:7px}
input[type=color]{width:36px;height:32px;border:1px solid #444;border-radius:3px;background:#181818;cursor:pointer;padding:1px}
.preview-btn{height:38px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;font-family:'Share Tech Mono',monospace;font-size:12px;border:1px solid rgba(255,255,255,.1);transition:all .2s;letter-spacing:1px;overflow:hidden;white-space:nowrap;padding:0 10px}

.check-row{display:flex;align-items:center;gap:9px}
.check-row input{width:15px;height:15px;accent-color:var(--amber);cursor:pointer}
.check-row span{font-size:13px;color:var(--amber);cursor:pointer}

.form-btns{display:flex;flex-direction:column;gap:7px}
.btn-lg{padding:9px 14px;font-size:9px;letter-spacing:2px;width:100%}
.btn-save{background:var(--cyan);color:#000}.btn-save:hover{filter:brightness(1.15)}
.btn-sec{background:#2a2a2a;color:#888;border:1px solid #3a3a3a}.btn-sec:hover{background:#333;color:#fff}
.btn-danger-full{background:var(--red);color:#fff}.btn-danger-full:hover{background:#b71c1c}

/* GRID MAP */
.map-hdr{background:#1c1c1c;border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:8px 18px;font-family:'Orbitron',sans-serif;font-size:8px;letter-spacing:3px;color:#555;display:flex;align-items:center;justify-content:space-between}
.map-hdr span{font-size:10px;color:#444}
#gridMap{padding:10px 18px 16px;display:grid;gap:2px}
.map-cell{border-radius:2px;height:16px;font-size:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;padding:0 3px;transition:all .2s;cursor:default;border:1px solid transparent}
.map-cell.empty{background:#1e1e1e;border:1px dashed #2a2a2a}
.map-cell.occupied{border-color:rgba(255,255,255,.08)}
.map-cell.editing{border:1px solid var(--cyan)!important;box-shadow:0 0 6px rgba(0,229,255,.4)}
.map-cell.conflict-cell{border:1px solid var(--amber)!important;box-shadow:0 0 6px rgba(255,179,0,.4)}

/* TOAST */
#toast{position:fixed;bottom:20px;right:20px;z-index:9999;background:#222;border:1px solid #444;border-radius:5px;font-family:'Share Tech Mono',monospace;font-size:12px;padding:9px 18px;color:#bbb;transform:translateY(16px);opacity:0;transition:all .3s;pointer-events:none}
#toast.show{transform:translateY(0);opacity:1}
#toast.ok{border-color:var(--green);color:var(--green)}
#toast.err{border-color:var(--red);color:var(--red)}

/* MODAL */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center}
.overlay.show{display:flex}
.modal{background:#222;border:1px solid #444;border-radius:7px;padding:26px 30px;max-width:360px;width:90%;text-align:center}
.modal h3{font-family:'Orbitron',sans-serif;font-size:13px;color:var(--red);margin-bottom:10px;letter-spacing:2px}
.modal p{font-size:13px;color:#999;margin-bottom:20px;line-height:1.6}
.modal-btns{display:flex;gap:9px;justify-content:center}

/* ── Panel Cámaras ── */
.cam-panel{background:#0e1a1a;border:1px solid rgba(0,229,255,.2);border-radius:var(--r);padding:14px 16px;margin-top:4px}
.cam-panel-title{font-family:'Orbitron',sans-serif;font-size:9px;letter-spacing:3px;color:var(--cyan);margin-bottom:10px;display:flex;align-items:center;gap:8px}
.cam-list{display:flex;flex-direction:column;gap:5px;margin-bottom:10px;max-height:130px;overflow-y:auto}
.cam-item{display:flex;align-items:center;gap:8px;background:#111;border-radius:3px;padding:6px 10px;font-size:11px;font-family:'Share Tech Mono',monospace}
.cam-item .cam-key{color:var(--cyan);min-width:90px}
.cam-item .cam-nom{color:#aaa;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cam-item .cam-use{font-family:'Orbitron',sans-serif;font-size:8px;padding:3px 8px;border-radius:3px;border:none;cursor:pointer;background:rgba(0,229,255,.15);color:var(--cyan);transition:all .15s}
.cam-item .cam-use:hover{background:rgba(0,229,255,.3)}
.cam-item .cam-del{font-size:12px;background:none;border:none;cursor:pointer;color:#633;padding:2px 5px;transition:all .15s;border-radius:3px}
.cam-item .cam-del:hover{background:#3d1111;color:#f66}
.cam-detect{display:none;background:rgba(0,229,255,.06);border:1px solid rgba(0,229,255,.25);border-radius:var(--r);padding:8px 12px;font-size:12px;color:var(--cyan);font-family:'Share Tech Mono',monospace;margin-top:6px;line-height:1.6}
.cam-detect.show{display:block}
.cam-detect b{color:#fff}

/* Scrollbar */
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-track{background:#181818}
::-webkit-scrollbar-thumb{background:#3a3a3a;border-radius:3px}
::-webkit-scrollbar-thumb:hover{background:#555}

@media(max-width:820px){.main{grid-template-columns:1fr;grid-template-rows:1fr 45vh}.col-right{border-top:1px solid var(--border)}}
</style>
</head>
<body>

<header>
    <div class="badge">EDITOR</div>
    <h1>ENLACES</h1>
        <a href="mmdvm.php" class="a-volver-ader" target="_blank">VOLVER AL PANEL ADER</a>
    <a href="mis_enlaces.php" class="a-volver" target="_blank">↗ VER PANEL ENLACES</a>
</header>

<div class="main">

    <!-- ── TABLA ── -->
    <div class="col-left">
        <div class="toolbar">
            <input class="tb-input" id="buscar" placeholder="🔍  Filtrar..." oninput="filtrar(this.value)">
            <button class="btn btn-add" onclick="nuevoEnlace()">＋ AÑADIR</button>
            <button class="btn btn-auto" onclick="pedirAutoplace()" title="Reasignar posiciones automáticamente">⚡ AUTO-POSICIONAR</button>
            <span class="count" id="countBadge">—</span>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr>
                    <th></th>
                    <th>CLR</th>
                    <th>NOMBRE</th>
                    <th style="text-align:center">F · C</th>
                    <th>URL</th>
                    <th>LOC</th>
                    <th></th>
                </tr></thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- ── FORMULARIO + MAPA ── -->
    <div class="col-right">
        <div class="form-hdr" id="formTitle">NUEVO ENLACE</div>
        <div class="form-body">
            <input type="hidden" id="editIdx" value="-1">

            <div class="field">
                <label>Nombre</label>
                <input class="fi" id="fNom" placeholder="BM Monitor" maxlength="60" oninput="preview()">
            </div>

            <div class="field">
                <label>URL</label>
                <input class="fi" id="fUrl" placeholder="https://..." maxlength="500">
            </div>

            <!-- Posición en el grid -->
            <div class="pos-row">
                <div class="field">
                    <label>Fila</label>
                    <input class="fi-num" type="number" id="fFila" value="1" min="1" max="99" oninput="onPosChange()">
                </div>
                <div class="field">
                    <label>Columna</label>
                    <input class="fi-num" type="number" id="fCol"  value="1" min="1" max="20" oninput="onPosChange()">
                </div>
            </div>
            <div class="conflict-warn" id="conflictWarn"></div>

            <!-- Colores -->
            <div class="colors-grid">
                <div class="field">
                    <label>Fondo</label>
                    <div class="color-row">
                        <input type="color" id="fBgP" value="#1a5490" oninput="syncColor('bg')">
                        <input class="fi" id="fBg" value="#1a5490" maxlength="7" placeholder="#1a5490" oninput="syncColorTxt('bg')">
                    </div>
                </div>
                <div class="field">
                    <label>Texto</label>
                    <div class="color-row">
                        <input type="color" id="fFgP" value="#ffffff" oninput="syncColor('fg')">
                        <input class="fi" id="fFg" value="#ffffff" maxlength="7" placeholder="#ffffff" oninput="syncColorTxt('fg')">
                    </div>
                </div>
            </div>

            <div class="preview-btn" id="prevBtn">NOMBRE DEL ENLACE</div>

            <div class="check-row">
                <input type="checkbox" id="fLocal">
                <span onclick="document.getElementById('fLocal').click()">⚠ Enlace local (sin URL)</span>
            </div>

            <!-- DETECTOR RTSP -->
            <div class="cam-detect" id="camDetect">
                📷 URL RTSP detectada — se guardará en <b>camara.ini</b><br>
                Clave: <b id="camClavePreview">—</b><br>
                <small style="color:#555;font-size:10px">El enlace quedará como <b>camara.php?cam=CLAVE</b></small>
            </div>

            <div class="form-btns">
                <button class="btn btn-save btn-lg" onclick="guardar()">💾 GUARDAR</button>
                <button class="btn btn-sec  btn-lg" onclick="cancelar()">✕ CANCELAR</button>
            </div>

            <!-- PANEL CÁMARAS -->
            <div class="cam-panel">
                <div class="cam-panel-title">
                    📷 CÁMARAS EN CAMARA.INI
                    <span style="color:#555;font-size:10px;margin-left:auto" id="camCount">—</span>
                </div>
                <div class="cam-list" id="camList">—</div>
            </div>
        </div>

        <!-- MAPA VISUAL DEL GRID -->
        <div class="map-hdr">
            <span style="font-family:'Orbitron',sans-serif;font-size:8px;letter-spacing:2px;color:#555">MAPA DEL PANEL</span>
            <span id="mapInfo">—</span>
        </div>
        <div id="gridMap"></div>
    </div>
</div>

<!-- Modal confirmación -->
<div class="overlay" id="overlay">
    <div class="modal">
        <h3 id="modalTitle">⚠ CONFIRMAR</h3>
        <p id="modalMsg">¿Continuar?</p>
        <div class="modal-btns">
            <button class="btn btn-danger-full" id="btnConfirm">CONFIRMAR</button>
            <button class="btn btn-sec" onclick="cerrarModal()">CANCELAR</button>
        </div>
    </div>
</div>

<div id="toast"></div>

<script>
// ═══════════════════════════════════════════════════════════
//  Estado
// ═══════════════════════════════════════════════════════════
let enlaces = [];
let filtro  = '';
let dragSrc = null;
let pendingAction = null;

// ═══════════════════════════════════════════════════════════
//  API
// ═══════════════════════════════════════════════════════════
async function api(action, body = null) {
    const r = await fetch(`?action=${action}&api=1`, {
        method: body ? 'POST' : 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined
    });
    return r.json();
}

// ═══════════════════════════════════════════════════════════
//  Carga
// ═══════════════════════════════════════════════════════════
async function cargar() {
    const res = await api('list');
    if (res.ok) { enlaces = res.data; renderTabla(); renderMapa(); }
    else toast('Error al cargar', 'err');
}

// ═══════════════════════════════════════════════════════════
//  TABLA
// ═══════════════════════════════════════════════════════════
function renderTabla() {
    const tbody = document.getElementById('tbody');
    const q     = filtro.toLowerCase();
    tbody.innerHTML = '';
    let vis = 0;

    enlaces.forEach((e, i) => {
        if (q && !e.nombre.toLowerCase().includes(q) && !(e.url||'').toLowerCase().includes(q)) return;
        vis++;
        const tr = document.createElement('tr');
        tr.dataset.index = i;
        tr.draggable = true;
        tr.innerHTML = `
            <td class="td-grip">⠿</td>
            <td class="td-col"><span class="dot" style="background:${e.bg}"></span></td>
            <td class="td-nom" title="${esc(e.nombre)}">${esc(e.nombre)}</td>
            <td class="td-pos">${e.fila}·${e.columna}</td>
            <td class="td-url" title="${esc(e.url||'')}">${e.url ? esc(e.url) : '<em style="color:#3a3a3a">—</em>'}</td>
            <td class="td-loc">${e.local ? '⚠' : ''}</td>
            <td class="td-act">
                <button class="btn-edit" onclick="editarEnlace(${i})" title="Editar">✏</button>
                <button class="btn-del"  onclick="pedirBorrar(${i})"  title="Eliminar">🗑</button>
            </td>`;

        // Highlight si es el que editamos
        if (parseInt(document.getElementById('editIdx').value) === i) tr.classList.add('sel');

        // Drag & Drop
        tr.addEventListener('dragstart', () => { dragSrc = tr; tr.classList.add('dragging'); });
        tr.addEventListener('dragend',   () => { tr.classList.remove('dragging'); limpiarDrag(); });
        tr.addEventListener('dragover',  ev => { ev.preventDefault(); limpiarDrag(); tr.classList.add('drag-over'); });
        tr.addEventListener('drop',      ev => { ev.preventDefault(); moverFila(+dragSrc.dataset.index, +tr.dataset.index); });

        tbody.appendChild(tr);
    });

    document.getElementById('countBadge').textContent =
        (q ? `${vis} de ` : '') + `${enlaces.length} enlaces`;
}

function limpiarDrag() {
    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
}
function filtrar(v) { filtro = v; renderTabla(); }

// Drag reorder → actualiza JSON order (no fila/columna)
async function moverFila(de, a) {
    if (de === a) return;
    const it = enlaces.splice(de, 1)[0];
    enlaces.splice(a, 0, it);
    // Ajustar editIdx si aplica
    const eid = parseInt(document.getElementById('editIdx').value);
    if (eid === de) document.getElementById('editIdx').value = a;
    renderTabla(); renderMapa();
    await api('reorder', { order: enlaces.map((_,i) => i) });
    const res = await api('list');
    if (res.ok) { enlaces = res.data; renderTabla(); renderMapa(); }
    toast('Orden guardado', 'ok');
}

// ═══════════════════════════════════════════════════════════
//  MAPA VISUAL
// ═══════════════════════════════════════════════════════════
function renderMapa() {
    const maxC = Math.max(3, ...enlaces.map(e => e.columna || 1));
    const maxF = Math.max(1, ...enlaces.map(e => e.fila    || 1));

    document.getElementById('gridMap').style.gridTemplateColumns = `repeat(${maxC}, 1fr)`;
    document.getElementById('mapInfo').textContent = `${maxF} × ${maxC}`;

    // Índice de posiciones
    const mapa = {}; // "f,c" -> enlace
    enlaces.forEach(e => { mapa[`${e.fila},${e.columna}`] = e; });

    const editIdx  = parseInt(document.getElementById('editIdx').value);
    const editF    = parseInt(document.getElementById('fFila').value) || 0;
    const editC    = parseInt(document.getElementById('fCol').value)  || 0;
    const conflKey = `${editF},${editC}`;

    const grid = document.getElementById('gridMap');
    grid.innerHTML = '';

    for (let f = 1; f <= maxF; f++) {
        for (let c = 1; c <= maxC; c++) {
            const key = `${f},${c}`;
            const e   = mapa[key];
            const cell = document.createElement('div');
            cell.className = 'map-cell';
            cell.title = e ? `${e.nombre}\nF${f}·C${c}` : `Vacía F${f}·C${c}`;

            // Es la celda que se está editando (posición destino)
            const isEditing = (f === editF && c === editC && editIdx >= 0);
            // Conflicto: celda ocupada por otro enlace en la posición destino
            const enlaceCelda = enlaces.findIndex(x => x.fila === f && x.columna === c);
            const isConflict  = isEditing && enlaceCelda >= 0 && enlaceCelda !== editIdx;

            if (e) {
                cell.classList.add('occupied');
                cell.style.background = e.bg;
                cell.style.color = e.fg;
                cell.textContent = e.nombre;
                // Click para editar
                const idx = enlaces.indexOf(e);
                cell.style.cursor = 'pointer';
                cell.onclick = () => editarEnlace(idx);
            } else {
                cell.classList.add('empty');
                if (isEditing) cell.style.background = 'rgba(0,229,255,.07)';
            }

            if (isEditing)  cell.classList.add('editing');
            if (isConflict) cell.classList.add('conflict-cell');

            grid.appendChild(cell);
        }
    }
}

// ═══════════════════════════════════════════════════════════
//  FORMULARIO
// ═══════════════════════════════════════════════════════════
function setForm(e, idx) {
    document.getElementById('editIdx').value = idx;
    document.getElementById('formTitle').textContent = idx >= 0 ? 'EDITAR ENLACE' : 'NUEVO ENLACE';
    document.getElementById('fNom').value   = e.nombre  || '';
    document.getElementById('fUrl').value   = e.url     || '';
    document.getElementById('fFila').value  = e.fila    || 1;
    document.getElementById('fCol').value   = e.columna || 1;
    const bg = e.bg || '#333333', fg = e.fg || '#ffffff';
    document.getElementById('fBg').value  = bg;  document.getElementById('fBgP').value = bg;
    document.getElementById('fFg').value  = fg;  document.getElementById('fFgP').value = fg;
    document.getElementById('fLocal').checked = !!e.local;
    preview(); onPosChange(); renderTabla(); renderMapa();
    document.getElementById('fNom').focus();
}

function nuevoEnlace() {
    // Calcular primera celda libre
    const ocupadas = new Set(enlaces.map(e => `${e.fila},${e.columna}`));
    const maxC = Math.max(3, ...enlaces.map(e => e.columna||1));
    let nf = 1, nc = 1;
    outer: for (let f = 1; f <= 999; f++) {
        for (let c = 1; c <= maxC; c++) {
            if (!ocupadas.has(`${f},${c}`)) { nf = f; nc = c; break outer; }
        }
    }
    setForm({ nombre:'', url:'', bg:'#1a5490', fg:'#ffffff', fila: nf, columna: nc }, -1);
}

function editarEnlace(i) {
    setForm(enlaces[i], i);
}

function cancelar() {
    document.getElementById('editIdx').value = -1;
    document.getElementById('formTitle').textContent = 'NUEVO ENLACE';
    onPosChange(); renderTabla(); renderMapa();
}

function preview() {
    const btn  = document.getElementById('prevBtn');
    btn.style.background = document.getElementById('fBg').value || '#333';
    btn.style.color      = document.getElementById('fFg').value || '#fff';
    btn.textContent      = document.getElementById('fNom').value || 'NOMBRE DEL ENLACE';
}

function onPosChange() {
    const f   = parseInt(document.getElementById('fFila').value) || 0;
    const c   = parseInt(document.getElementById('fCol').value)  || 0;
    const idx = parseInt(document.getElementById('editIdx').value);

    // Detectar conflicto
    const conflictIdx = enlaces.findIndex((e, i) => e.fila === f && e.columna === c && i !== idx);
    const warnEl = document.getElementById('conflictWarn');
    const fiEl   = document.getElementById('fFila');
    const fcEl   = document.getElementById('fCol');

    if (conflictIdx >= 0) {
        const c2 = enlaces[conflictIdx];
        warnEl.textContent = `⚠ Posición F${f}·C${c} ocupada por "${c2.nombre}"`;
        warnEl.classList.add('show');
        fiEl.classList.add('conflict');
        fcEl.classList.add('conflict');
    } else {
        warnEl.classList.remove('show');
        fiEl.classList.remove('conflict');
        fcEl.classList.remove('conflict');
    }
    renderMapa();
}

function syncColor(w) {
    const p = document.getElementById(w==='bg'?'fBgP':'fFgP');
    const t = document.getElementById(w==='bg'?'fBg':'fFg');
    t.value = p.value; preview();
}
function syncColorTxt(w) {
    const t = document.getElementById(w==='bg'?'fBg':'fFg');
    const p = document.getElementById(w==='bg'?'fBgP':'fFgP');
    if (/^#[0-9a-fA-F]{6}$/.test(t.value)) p.value = t.value;
    preview();
}

async function guardar() {
    const nombre = document.getElementById('fNom').value.trim();
    if (!nombre) { toast('El nombre es obligatorio', 'err'); document.getElementById('fNom').classList.add('err'); return; }
    document.getElementById('fNom').classList.remove('err');

    const payload = {
        nombre,
        url:     document.getElementById('fUrl').value.trim(),
        bg:      document.getElementById('fBg').value.trim()  || '#333333',
        fg:      document.getElementById('fFg').value.trim()  || '#ffffff',
        fila:    parseInt(document.getElementById('fFila').value) || 1,
        columna: parseInt(document.getElementById('fCol').value)  || 1,
        local:   document.getElementById('fLocal').checked,
    };

    const idx = parseInt(document.getElementById('editIdx').value);
    if (idx >= 0) payload.index = idx;

    const res = await api(idx >= 0 ? 'update' : 'add', payload);
    if (res.ok) {
        enlaces = res.data;
        if (idx < 0) document.getElementById('editIdx').value = -1;
        renderTabla(); renderMapa();
        toast(res.msg, 'ok');
    } else {
        toast('Error: ' + res.msg, 'err');
    }
}

// ═══════════════════════════════════════════════════════════
//  BORRAR
// ═══════════════════════════════════════════════════════════
function pedirBorrar(i) {
    document.getElementById('modalTitle').textContent = '⚠ ELIMINAR ENLACE';
    document.getElementById('modalMsg').textContent   = `¿Eliminar "${enlaces[i].nombre}"? No se puede deshacer.`;
    pendingAction = async () => {
        const res = await api('delete', { index: i });
        if (res.ok) {
            enlaces = res.data;
            if (parseInt(document.getElementById('editIdx').value) === i) cancelar();
            renderTabla(); renderMapa(); toast(res.msg, 'ok');
        } else { toast('Error: ' + res.msg, 'err'); }
    };
    abrirModal();
}

// ═══════════════════════════════════════════════════════════
//  AUTO-POSICIONAR
// ═══════════════════════════════════════════════════════════
function pedirAutoplace() {
    document.getElementById('modalTitle').textContent = '⚡ AUTO-POSICIONAR';
    document.getElementById('modalMsg').textContent   =
        'Se reasignarán fila y columna a todos los enlaces de forma secuencial (3 columnas), según el orden actual de la lista. ¿Continuar?';
    pendingAction = async () => {
        const res = await api('autoplace', { cols: 3 });
        if (res.ok) { enlaces = res.data; cancelar(); renderTabla(); renderMapa(); toast(res.msg, 'ok'); }
        else { toast('Error: ' + res.msg, 'err'); }
    };
    abrirModal();
}

// ═══════════════════════════════════════════════════════════
//  MODAL
// ═══════════════════════════════════════════════════════════
function abrirModal() { document.getElementById('overlay').classList.add('show'); }
function cerrarModal() { document.getElementById('overlay').classList.remove('show'); pendingAction = null; }
document.getElementById('btnConfirm').onclick = () => { const fn = pendingAction; cerrarModal(); if (fn) fn(); };
document.getElementById('overlay').addEventListener('click', e => { if (e.target === e.currentTarget) cerrarModal(); });

// ═══════════════════════════════════════════════════════════
//  TOAST
// ═══════════════════════════════════════════════════════════
let toastT;
function toast(msg, type = '') {
    const el = document.getElementById('toast');
    el.textContent = msg; el.className = 'show ' + type;
    clearTimeout(toastT);
    toastT = setTimeout(() => el.className = '', 3000);
}

// ═══════════════════════════════════════════════════════════
//  UTILS
// ═══════════════════════════════════════════════════════════
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ═══════════════════════════════════════════════════════════
//  CÁMARAS (camara.ini)
// ═══════════════════════════════════════════════════════════
let camaras = {};

async function cargarCamaras() {
    const res = await api('cam_list');
    if (res.ok) { camaras = res.data || {}; renderCamaras(); }
}

function renderCamaras() {
    const list  = document.getElementById('camList');
    const count = document.getElementById('camCount');
    const keys  = Object.keys(camaras);
    count.textContent = keys.length + ' cámara' + (keys.length !== 1 ? 's' : '');
    if (keys.length === 0) {
        list.innerHTML = '<span style="color:#333;font-size:11px">No hay cámaras configuradas</span>';
        return;
    }
    list.innerHTML = keys.map(k => `
        <div class="cam-item">
            <span class="cam-key">${esc(k)}</span>
            <span class="cam-nom" title="${esc(camaras[k].rtsp || '')}">${esc(camaras[k].nombre || k)}</span>
            <button class="cam-use" onclick="usarCamara('${esc(k)}')" title="Usar en enlace activo">↑ USAR</button>
            <button class="cam-del" onclick="borrarCamara('${esc(k)}')" title="Eliminar">🗑</button>
        </div>`).join('');
}

// Detectar RTSP al escribir la URL
document.getElementById('fUrl').addEventListener('input', function() {
    const val = this.value.trim();
    const detect = document.getElementById('camDetect');
    const prev   = document.getElementById('camClavePreview');
    if (val.startsWith('rtsp://') || val.startsWith('rtsps://')) {
        const nom   = document.getElementById('fNom').value.trim();
        const clave = nombreAClave(nom || 'camara');
        prev.textContent = clave;
        detect.classList.add('show');
    } else {
        detect.classList.remove('show');
    }
});

document.getElementById('fNom').addEventListener('input', function() {
    const url = document.getElementById('fUrl').value.trim();
    if (url.startsWith('rtsp://') || url.startsWith('rtsps://')) {
        const clave = nombreAClave(this.value.trim() || 'camara');
        document.getElementById('camClavePreview').textContent = clave;
    }
});

function nombreAClave(nombre) {
    return nombre.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        || 'camara';
}

// Al guardar: si URL es RTSP, guardar en camara.ini y reemplazar URL
const _guardarOriginal = guardar;
guardar = async function() {
    const urlVal = document.getElementById('fUrl').value.trim();
    if (urlVal.startsWith('rtsp://') || urlVal.startsWith('rtsps://')) {
        const nom   = document.getElementById('fNom').value.trim();
        const clave = nombreAClave(nom || 'camara');
        // Guardar en camara.ini
        const res = await api('cam_save', { key: clave, nombre: nom, rtsp: urlVal });
        if (res.ok) {
            camaras = res.data;
            renderCamaras();
            // Reemplazar URL con camara.php?cam=CLAVE
            document.getElementById('fUrl').value = 'http://raspberry.local/camara.php?cam=' + clave;
            document.getElementById('camDetect').classList.remove('show');
            toast('📷 Cámara guardada en camara.ini como "' + clave + '"', 'ok');
        } else {
            toast('Error guardando cámara: ' + res.msg, 'err');
            return;
        }
    }
    await _guardarOriginal();
};

async function usarCamara(key) {
    document.getElementById('fUrl').value = 'http://raspberry.local/camara.php?cam=' + key;
    document.getElementById('camDetect').classList.remove('show');
    preview();
    toast('URL actualizada con cámara "' + key + '"', 'ok');
}

async function borrarCamara(key) {
    if (!confirm('¿Eliminar cámara "' + key + '" de camara.ini?')) return;
    const res = await api('cam_delete', { key });
    if (res.ok) { camaras = res.data; renderCamaras(); toast(res.msg, 'ok'); }
    else toast('Error: ' + res.msg, 'err');
}

// ═══════════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════════
cargar();
cargarCamaras();
preview();
</script>
</body>
</html>
