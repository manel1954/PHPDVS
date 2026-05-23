<?php

require_once __DIR__ . '/auth.php';
// ── Ruta del fichero ──────────────────────────────────────────────────────────
$INI_PATH = '/home/pi/DMRGateway/DMRGateway.ini';

// ── Campos editables por sección ─────────────────────────────────────────────
$SECTIONS = [
    // 'General' => [
    //     ['key' => 'Callsign',    'label' => 'Indicativo',           'type' => 'str'],
    //     ['key' => 'Id',          'label' => 'DMR ID',               'type' => 'int'],
    //     ['key' => 'Timeout',     'label' => 'Timeout (s)',          'type' => 'int'],
    //     ['key' => 'Daemon',      'label' => 'Daemon (0/1)',         'type' => 'int'],
    // ],
    'Info' => [
        // ['key' => 'RXFrequency', 'label' => 'Frecuencia RX (Hz)',  'type' => 'int'],
        // ['key' => 'TXFrequency', 'label' => 'Frecuencia TX (Hz)',  'type' => 'int'],
        // ['key' => 'Power',       'label' => 'Potencia (W)',         'type' => 'int'],
        ['key' => 'Latitude',    'label' => 'Latitud',              'type' => 'str'],
        ['key' => 'Longitude',   'label' => 'Longitud',             'type' => 'str'],
        // ['key' => 'Height',      'label' => 'Altura (m)',           'type' => 'int'],
        ['key' => 'Location',    'label' => 'Localización',         'type' => 'str'],
        ['key' => 'Description', 'label' => 'Descripción',          'type' => 'str'],
        ['key' => 'URL',         'label' => 'URL',                  'type' => 'str'],
    ],
    'XLX Network' => [
        ['key' => 'Enabled',    'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'File',       'label' => 'Fichero hosts',         'type' => 'str'],
        ['key' => 'Port',       'label' => 'Puerto',                'type' => 'int'],
        ['key' => 'Password',   'label' => 'Password',              'type' => 'str'],
        ['key' => 'ReloadTime', 'label' => 'Reload Time (min)',     'type' => 'int'],
        ['key' => 'Slot',       'label' => 'Slot',                  'type' => 'int'],
        ['key' => 'TG',         'label' => 'Talk Group',            'type' => 'int'],
        ['key' => 'Base',       'label' => 'Base',                  'type' => 'int'],
        ['key' => 'Startup',    'label' => 'Reflector Startup',     'type' => 'int'],
        ['key' => 'Relink',     'label' => 'Relink Time (min)',     'type' => 'int'],
        ['key' => 'UserControl','label' => 'User Control (0/1)',    'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',           'type' => 'int'],
    ],
    'DMR Network 1' => [
        ['key' => 'Enabled',    'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'Name',       'label' => 'Nombre',                'type' => 'str'],
        ['key' => 'Address',    'label' => 'Dirección',             'type' => 'str'],
        ['key' => 'Port',       'label' => 'Puerto',                'type' => 'int'],
        ['key' => 'Password',   'label' => 'Password',              'type' => 'str'],
        ['key' => 'Location',   'label' => 'Location (0/1)',        'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',           'type' => 'int'],
    ],
    'DMR Network 2' => [
        ['key' => 'Enabled',    'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'Name',       'label' => 'Nombre',                'type' => 'str'],
        ['key' => 'Address',    'label' => 'Dirección',             'type' => 'str'],
        ['key' => 'Port',       'label' => 'Puerto',                'type' => 'int'],
        ['key' => 'Password',   'label' => 'Password',              'type' => 'str'],
        ['key' => 'Options',    'label' => 'Options',               'type' => 'str'],
        ['key' => 'Location',   'label' => 'Location (0/1)',        'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',           'type' => 'int'],
    ],
    'DMR Network 3' => [
        ['key' => 'Enabled',    'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'Name',       'label' => 'Nombre',                'type' => 'str'],
        ['key' => 'Address',    'label' => 'Dirección',             'type' => 'str'],
        ['key' => 'Port',       'label' => 'Puerto',                'type' => 'int'],
        ['key' => 'Password',   'label' => 'Password',              'type' => 'str'],
        ['key' => 'Location',   'label' => 'Location (0/1)',        'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',           'type' => 'int'],
    ],
    'DMR Network 4' => [
        ['key' => 'Enabled',    'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'Name',       'label' => 'Nombre',                'type' => 'str'],
        ['key' => 'Address',    'label' => 'Dirección',             'type' => 'str'],
        ['key' => 'Port',       'label' => 'Puerto',                'type' => 'int'],
        ['key' => 'Password',   'label' => 'Password',              'type' => 'str'],
        ['key' => 'Location',   'label' => 'Location (0/1)',        'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',           'type' => 'int'],
    ],
    'DMR Network 5' => [
        ['key' => 'Enabled',    'label' => 'Habilitado (0/1)',      'type' => 'int'],
        ['key' => 'Name',       'label' => 'Nombre',                'type' => 'str'],
        ['key' => 'Address',    'label' => 'Dirección',             'type' => 'str'],
        ['key' => 'Port',       'label' => 'Puerto',                'type' => 'int'],
        ['key' => 'Password',   'label' => 'Password',              'type' => 'str'],
        ['key' => 'Location',   'label' => 'Location (0/1)',        'type' => 'int'],
        ['key' => 'Debug',      'label' => 'Debug (0/1)',           'type' => 'int'],
    ],
];

// ── Colores para cada sección ─────────────────────────────────────────────────
$SEC_COLORS = [
    'General'       => '#a8b9cc',
    'Info'          => '#b57aff',
    'XLX Network'   => '#00d4ff',
    'DMR Network 1' => '#ffb300',
    'DMR Network 2' => '#00ff9f',
    'DMR Network 3' => '#ff7043',
    'DMR Network 4' => '#ab47bc',
    'DMR Network 5' => '#26c6da',
];

// ── Opciones desplegable Options ──────────────────────────────────────────────
$FIELD_OPTIONS = [
    'DMR Network 2' => [
      'Address' => [
            ['label' => '--- Selecciona servidor ---',      'value' => ''],
            ['label' => 'IPSC2-EA-Hotspot   (TG 4370)',    'value' => '212.237.3.141'],
            ['label' => 'IPSC2-EA4Master    (TG 4374)',    'value' => '80.211.226.37'],
            ['label' => 'IPSC2-Catalunya    (TG 4714)',    'value' => '217.61.2.11'],
            ['label' => 'IPSC2-Mexico       (TG 4382)',    'value' => '167.88.169.17'],
        ],
        'Options' => [
            ['label' => '--- Selecciona una opción ---',   'value' => ''],
            ['label' => 'IPSC2-EA4MASTER    (TG 4374)',    'value' => 'StartRef=4374;RelinkTime=15;UserLink=1;'],
            ['label' => 'IPSC2-EA-Hotspot   (TG 4370)',    'value' => 'StartRef=4370;RelinkTime=15;UserLink=1;'],
            ['label' => 'IPSC2-Catalunya    (TG 4714)',    'value' => 'StartRef=4714;RelinkTime=15;UserLink=1;'],
            ['label' => 'TS2_1=21465        (TG 21465)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=21465'],
            ['label' => 'TS1_1=37040        (TG 37040)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=37040'],
            ['label' => 'TS1_1=7480         (TG 7480)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=7480'],
            ['label' => 'TS1_1=74890        (TG 74890)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=74890'],
            ['label' => 'TS1_1=214000       (TG 21400)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=21400'],
            ['label' => 'TS1_1=33040        (TG 33040)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=33040'],
            ['label' => 'TS1_1=7080         (TG 7080)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=7080'],
            ['label' => 'TS1_1=7010         (TG 7010)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=7010'],
            ['label' => 'TS1_1=7060         (TG 7060)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=7060'],
            ['label' => 'TS1_1=3700         (TG 3700)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=3700'],
            ['label' => 'TS1_1=2140         (TG 2140)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=2140'],
            ['label' => 'TS1_1=33014        (TG 33014)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS1_1=33014'],
            ['label' => 'TS2_1=3301         (TG 3301)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=3301'],
            ['label' => 'TS2_1=31226        (TG 31226)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=31226'],
            ['label' => 'TS2_1=33015        (TG 33015)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=33015'],
            ['label' => 'TS2_1=21472        (TG 21472)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=21472'],
            ['label' => 'TS2_1=33041        (TG 33041)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=33041'],
            ['label' => 'TS2_1=31912        (TG 31912)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=31912'],
            ['label' => 'TS2_1=33001        (TG 33001)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=33001'],
            ['label' => 'TS2_1=2149         (TG 2149)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=2149'],
            ['label' => 'TS2_1=3302         (TG 3302)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=3302'],
            ['label' => 'TS2_1=2148         (TG 2148)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=2148'],
            ['label' => 'TS2_1=73440        (TG 73440)',   'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=73440'],
            ['label' => 'TS2_1=2143         (TG 2143)',    'value' => 'StartRef=4000;RelinkTime=15;UserLink=1;TS2_1=2143'],
        ],
    ],
];

// ── Leer valores actuales del INI ─────────────────────────────────────────────
function readIniValues($path, $sections) {
    $values = [];
    if (!file_exists($path)) return $values;
    $lines      = file($path);
    $currentSec = '';
    foreach ($lines as $line) {
        $stripped = trim($line);
        if (preg_match('/^\[(.+)\]/', $stripped, $m)) { $currentSec = $m[1]; continue; }
        if ($stripped === '' || $stripped[0] === '#' || $stripped[0] === ';') continue;
        if (strpos($stripped, '=') !== false) {
            [$k, $v] = explode('=', $stripped, 2);
            $k = trim($k); $v = trim($v);
            if (isset($sections[$currentSec])) {
                foreach ($sections[$currentSec] as $field) {
                    if (strtolower($field['key']) === strtolower($k)) {
                        $values[$currentSec][$field['key']] = $v;
                    }
                }
            }
        }
    }
    return $values;
}

// ── Escribir valores preservando comentarios ──────────────────────────────────
function writeIniValues($path, $sections, $newValues) {
    if (!file_exists($path)) return ['ok' => false, 'msg' => 'Fichero no encontrado'];
    $lines      = file($path);
    $currentSec = '';
    $result     = [];
    foreach ($lines as $line) {
        $stripped = trim($line);
        if (preg_match('/^\[(.+)\]/', $stripped, $m)) { $currentSec = $m[1]; $result[] = $line; continue; }
        if ($stripped === '' || $stripped[0] === '#' || $stripped[0] === ';') { $result[] = $line; continue; }
        if (strpos($stripped, '=') !== false && isset($sections[$currentSec])) {
            [$k] = explode('=', $stripped, 2);
            $k = trim($k);
            $matched = false;
            foreach ($sections[$currentSec] as $field) {
                if (strtolower($field['key']) === strtolower($k)) {
                    if (isset($newValues[$currentSec][$field['key']])) {
                        $result[] = $k . '=' . $newValues[$currentSec][$field['key']] . "\n";
                        $matched  = true;
                    }
                    break;
                }
            }
            if (!$matched) $result[] = $line;
        } else {
            $result[] = $line;
        }
    }
    file_put_contents($path, implode('', $result));
    return ['ok' => true, 'msg' => 'Configuración guardada correctamente'];
}

// ── Procesar POST ─────────────────────────────────────────────────────────────
$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $newValues = [];
    foreach ($SECTIONS as $sec => $fields) {
        foreach ($fields as $field) {
            $postKey = str_replace(' ', '_', $sec) . '_' . $field['key'];
            $val     = trim($_POST[$postKey] ?? '');
            if ($field['type'] === 'int' && $val !== '' && !is_numeric($val)) {
                $errors[] = "[{$sec}] '{$field['label']}' debe ser un número entero.";
            } else {
                $newValues[$sec][$field['key']] = $val;
            }
        }
    }
    if ($errors) { $message = implode('<br>', $errors); $msgType = 'error'; }
    else {
        $res = writeIniValues($INI_PATH, $SECTIONS, $newValues);
        $message = $res['msg']; $msgType = $res['ok'] ? 'success' : 'error';
    }
}

$values = readIniValues($INI_PATH, $SECTIONS);

function postKey($sec, $key) { return str_replace(' ', '_', $sec) . '_' . $key; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DMRGateway Config · EA3EIZ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0a0e14;
    --surface:   #111720;
    --border:    #1e2d3d;
    --green:     #00ff9f;
    --red:       #ff4560;
    --amber:     #ffb300;
    --cyan:      #00d4ff;
    --text:      #a8b9cc;
    --text-dim:  #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-ui:   'Rajdhani', sans-serif;
  }
  * { box-sizing: border-box; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }
  .ctrl-header {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem;
  }
  .ctrl-header img { height: 40px; width: auto; }
  .ctrl-header h1 {
    font-family: var(--font-ui); font-weight: 700; font-size: 1.4rem;
    letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase;
  }
  .page-body { padding: 2rem; max-width: 900px; margin: 0 auto; }
  .ini-path {
    font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim);
    background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px;
    padding: .4rem .8rem; margin-bottom: 1.5rem;
  }
  .ini-path span { color: var(--cyan); }
  .alert-custom {
    border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem;
    font-family: var(--font-mono); font-size: .85rem; border: 1px solid;
  }
  .alert-success { background: rgba(0,255,159,.08); border-color: var(--green); color: var(--green); }
  .alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }
  .tabs { display: flex; gap: .3rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
  .tab-btn {
    font-family: var(--font-mono); font-size: .75rem; letter-spacing: .08em;
    text-transform: uppercase; padding: .45rem 1rem; border-radius: 4px 4px 0 0;
    border: 1px solid var(--border); border-bottom: none;
    background: var(--surface); color: var(--text-dim);
    cursor: pointer; transition: background .2s, color .2s;
  }
  .tab-btn:hover { color: var(--text); }
  .tab-btn.active { background: #0d1e2a; color: var(--text); border-bottom: 2px solid; }
  .tab-panel { display: none; }
  .tab-panel.active { display: block; }
  .sec-card {
    background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 0 6px 6px 6px; padding: 1.5rem; margin-bottom: 1rem;
  }
  .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media (max-width: 600px) { .field-grid { grid-template-columns: 1fr; } }
  .field-wrap { margin-bottom: .2rem; }
  label { font-family: var(--font-ui); font-size: .95rem; color: var(--text); display: block; margin-bottom: .3rem; }
  input[type=text], input[type=number] {
    width: 100%; background: var(--surface); border: 1px solid var(--border);
    border-radius: 4px; color: var(--green); font-family: var(--font-mono);
    font-size: .9rem; padding: .5rem .8rem; outline: none; transition: border-color .2s;
  }
  input[type=text]:focus, input[type=number]:focus { border-color: var(--green); }
  .field-hint { font-family: var(--font-mono); font-size: .65rem; color: var(--text-dim); margin-top: .2rem; }
  .enabled-row { margin-bottom: 1.2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
  .enabled-select {
    background: var(--surface); border: 1px solid var(--border);
    color: var(--green); font-family: var(--font-mono);
    font-size: .9rem; padding: .5rem .8rem; border-radius: 4px;
    outline: none; width: 140px;
  }
  .custom-select-wrap { position: relative; width: 100%; }
  .custom-select-btn {
    width: 100%; background: var(--surface); border: 1px solid var(--border);
    border-radius: 4px; color: var(--green); font-family: var(--font-mono);
    font-size: .85rem; padding: .5rem .8rem; cursor: pointer;
    text-align: left; display: flex; justify-content: space-between; align-items: center;
    transition: border-color .2s;
  }
  .custom-select-btn:hover, .custom-select-btn.open { border-color: var(--green); }
  .custom-select-btn .arrow { font-size: .6rem; color: var(--text-dim); flex-shrink: 0; }
  .custom-select-btn .sel-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: .5rem; }
  .custom-select-list {
    display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
    background: #0d1e2a; border: 1px solid var(--green); border-radius: 4px;
    max-height: 240px; overflow-y: scroll; margin-top: 2px;
    scrollbar-width: thin; scrollbar-color: var(--border) transparent;
  }
  .custom-select-list::-webkit-scrollbar { width: 4px; }
  .custom-select-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
  .custom-select-list.open { display: block; }
  .custom-select-option {
    font-family: var(--font-mono); font-size: .82rem; color: var(--text);
    padding: .45rem .8rem; cursor: pointer; transition: background .15s; white-space: nowrap;
  }
  .custom-select-option:hover { background: rgba(0,255,159,.1); color: var(--green); }
  .custom-select-option.selected { background: rgba(0,255,159,.08); color: var(--green); font-weight: bold; }
  .btn-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 1.5rem; }
  .btn-save {
    background: #28a745; color: #fff; border: none; border-radius: 6px;
    font-family: var(--font-ui); font-weight: 700; font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .75rem 2.5rem; cursor: pointer; transition: background .2s;
  }
  .btn-save:hover { background: #218838; }
  .btn-back {
    background: #28a745; color: #fff; border: none; border-radius: 6px;
    font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em;
    text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none;
    margin-left: auto; transition: background .2s; display: inline-block;
  }
  .btn-back:hover { background: #218838; color: #fff; }
  .btn-reload {
    background: var(--surface); color: var(--text-dim); border: 1px solid var(--border);
    border-radius: 6px; font-family: var(--font-ui); font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .75rem 2rem; cursor: pointer; transition: background .2s, color .2s;
    text-decoration: none; display: inline-block;
  }
  .btn-reload:hover { background: #1e2d3d; color: var(--text); }
  .note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); }
</style>
</head>
<body>

<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <h1>DMRGateway Config Editor</h1>
  <a href="mmdvm.php" class="btn-back">← Volver a MMDVM Control</a>
</header>

<div class="page-body">

  <div class="ini-path">📄 Fichero: <span><?= htmlspecialchars($INI_PATH) ?></span></div>

  <?php if ($message): ?>
  <div class="alert-custom alert-<?= $msgType ?>">
    <?= $msgType === 'success' ? '✔ ' : '✖ ' ?><?= $message ?>
    <?php if ($msgType === 'success'): ?>
      <br><small>Reinicia DMRGateway para aplicar los cambios.</small>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!file_exists($INI_PATH)): ?>
  <div class="alert-custom alert-error">✖ Fichero no encontrado: <?= htmlspecialchars($INI_PATH) ?></div>
  <?php else: ?>

  <form method="POST">

    <!-- Tabs -->
    <div class="tabs">
      <?php $first = true; foreach ($SECTIONS as $sec => $fields):
        $color = $SEC_COLORS[$sec] ?? '#a8b9cc';
        $tabId = 'tab_' . str_replace(' ', '_', $sec);
      ?>
      <button type="button"
              class="tab-btn <?= $first ? 'active' : '' ?>"
              style="<?= $first ? "border-bottom-color:{$color};color:{$color}" : '' ?>"
              data-tab="<?= $tabId ?>"
              onclick="switchTab(this, '<?= $tabId ?>', '<?= $color ?>')">
        <?= htmlspecialchars($sec) ?>
      </button>
      <?php $first = false; endforeach; ?>
    </div>

    <!-- Tab panels -->
    <?php $first = true; foreach ($SECTIONS as $sec => $fields):
      $color = $SEC_COLORS[$sec] ?? '#a8b9cc';
      $tabId = 'tab_' . str_replace(' ', '_', $sec);
      $enabledField = null; $otherFields = [];
      foreach ($fields as $f) {
          if (strtolower($f['key']) === 'enabled') $enabledField = $f;
          else $otherFields[] = $f;
      }
    ?>
    <div class="tab-panel <?= $first ? 'active' : '' ?>" id="<?= $tabId ?>">
      <div class="sec-card" style="border-top: 3px solid <?= $color ?>;">

        <?php if ($enabledField): ?>
        <div class="enabled-row">
          <?php
            $pKey = postKey($sec, $enabledField['key']);
            $val  = $values[$sec][$enabledField['key']] ?? '0';
          ?>
          <label style="color:<?= $color ?>; font-size:1rem; font-weight:700; margin-bottom:.4rem;">
            <?= htmlspecialchars($enabledField['label']) ?>
          </label>
          <select name="<?= $pKey ?>" class="enabled-select" style="color:<?= $color ?>;">
            <option value="1" <?= $val === '1' ? 'selected' : '' ?>>✔ Activo</option>
            <option value="0" <?= $val === '0' ? 'selected' : '' ?>>✖ Inactivo</option>
          </select>
        </div>
        <?php endif; ?>

        <div class="field-grid">
          <?php foreach ($otherFields as $field):
            $pKey      = postKey($sec, $field['key']);
            $val       = $values[$sec][$field['key']] ?? '';
            $inputType = $field['type'] === 'int' ? 'number' : 'text';
            $hasOpts   = isset($FIELD_OPTIONS[$sec][$field['key']]);
          ?>
          <div class="field-wrap">
            <label for="<?= $pKey ?>"><?= htmlspecialchars($field['label']) ?></label>

            <?php if ($hasOpts): ?>
              <?php
                $opts     = $FIELD_OPTIONS[$sec][$field['key']];
                $selLabel = '--- Selecciona una opción ---';
                foreach ($opts as $opt) {
                    if ($opt['value'] === $val) { $selLabel = $opt['label']; break; }
                }
              ?>
              <input type="hidden" id="<?= $pKey ?>" name="<?= $pKey ?>" value="<?= htmlspecialchars($val) ?>">
              <div class="custom-select-wrap">
                <button type="button" class="custom-select-btn"
                        onclick="toggleDropdown(this)"
                        data-input="<?= $pKey ?>">
                  <span class="sel-label"><?= htmlspecialchars($selLabel) ?></span>
                  <span class="arrow">▼</span>
                </button>
                <div class="custom-select-list">
                  <?php foreach ($opts as $opt): ?>
                  <div class="custom-select-option <?= $opt['value'] === $val ? 'selected' : '' ?>"
                       data-value="<?= htmlspecialchars($opt['value']) ?>"
                       data-label="<?= htmlspecialchars($opt['label']) ?>"
                       onclick="selectOption(this)">
                    <?= htmlspecialchars($opt['label']) ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>

            <?php else: ?>
              <input
                type="<?= $inputType ?>"
                id="<?= $pKey ?>"
                name="<?= $pKey ?>"
                value="<?= htmlspecialchars($val) ?>"
                <?= $inputType === 'number' ? 'min="0"' : '' ?>
              >
            <?php endif; ?>

            <div class="field-hint"><?= htmlspecialchars($field['key']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
    <?php $first = false; endforeach; ?>

    <div class="btn-row">
      <button type="submit" class="btn-save">💾 Guardar</button>
      <a href="dmrgateway_config.php" class="btn-reload">🔄 Recargar</a>
      <span class="note">Los cambios requieren reiniciar DMRGateway</span>
    </div>

  </form>
  <?php endif; ?>
</div>

<script>
function switchTab(btn, tabId, color) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('active');
    b.style.borderBottomColor = '';
    b.style.color = '';
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  btn.style.borderBottomColor = color;
  btn.style.color = color;
  document.getElementById(tabId).classList.add('active');
}

function toggleDropdown(btn) {
  const list   = btn.nextElementSibling;
  const isOpen = list.classList.contains('open');
  document.querySelectorAll('.custom-select-list').forEach(l => l.classList.remove('open'));
  document.querySelectorAll('.custom-select-btn').forEach(b => b.classList.remove('open'));
  if (!isOpen) {
    list.classList.add('open');
    btn.classList.add('open');
    const sel = list.querySelector('.selected');
    if (sel) setTimeout(() => sel.scrollIntoView({ block: 'center' }), 10);
  }
}

function selectOption(opt) {
  const list  = opt.closest('.custom-select-list');
  const btn   = list.previousElementSibling;
  const input = document.getElementById(btn.dataset.input);
  list.querySelectorAll('.custom-select-option').forEach(o => o.classList.remove('selected'));
  opt.classList.add('selected');
  btn.querySelector('.sel-label').textContent = opt.dataset.label;
  input.value = opt.dataset.value;
  list.classList.remove('open');
  btn.classList.remove('open');
}

document.addEventListener('click', e => {
  if (!e.target.closest('.custom-select-wrap')) {
    document.querySelectorAll('.custom-select-list').forEach(l => l.classList.remove('open'));
    document.querySelectorAll('.custom-select-btn').forEach(b => b.classList.remove('open'));
  }
});
</script>

</body>
</html>
