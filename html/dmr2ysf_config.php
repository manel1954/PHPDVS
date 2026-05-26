<?php
require_once __DIR__ . '/auth.php';

$INI_PATH = '/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF.ini';

$SECTIONS = [
    'YSF Network' => [
        ['key' => 'Callsign',       'label' => 'Indicativo YSF',         'type' => 'str'],
        ['key' => 'GatewayAddress', 'label' => 'Gateway Address',         'type' => 'str'],
        ['key' => 'GatewayPort',    'label' => 'Gateway Port',            'type' => 'int'],
        ['key' => 'LocalAddress',   'label' => 'Local Address',           'type' => 'str'],
        ['key' => 'LocalPort',      'label' => 'Local Port',              'type' => 'int'],
        ['key' => 'Debug',          'label' => 'Debug (0/1)',             'type' => 'int'],
        ['key' => 'Daemon',         'label' => 'Daemon (0/1)',            'type' => 'int'],
    ],
    'DMR Network' => [
        ['key' => 'Id',             'label' => 'DMR ID',                  'type' => 'int'],
        ['key' => 'RptAddress',     'label' => 'Repetidor Address',       'type' => 'str'],
        ['key' => 'RptPort',        'label' => 'Repetidor Port',          'type' => 'int'],
        ['key' => 'LocalAddress',   'label' => 'Local Address',           'type' => 'str'],
        ['key' => 'LocalPort',      'label' => 'Local Port',              'type' => 'int'],
        ['key' => 'DefaultDstTG',   'label' => 'TalkGroup por defecto',   'type' => 'int'],
        ['key' => 'TGUnlink',       'label' => 'TG Desenlace',            'type' => 'int'],
        ['key' => 'TGListFile',     'label' => 'Fichero lista TG',        'type' => 'str'],
        ['key' => 'Debug',          'label' => 'Debug (0/1)',             'type' => 'int'],
    ],
    'DMR Id Lookup' => [
        ['key' => 'File',           'label' => 'Fichero DMR IDs',         'type' => 'str'],
        ['key' => 'Time',           'label' => 'Recarga (horas)',         'type' => 'int'],
    ],
    'Log' => [
        ['key' => 'DisplayLevel',   'label' => 'Nivel pantalla (0-5)',    'type' => 'int'],
        ['key' => 'FileLevel',      'label' => 'Nivel fichero (0-5)',     'type' => 'int'],
        ['key' => 'FilePath',       'label' => 'Ruta logs',               'type' => 'str'],
        ['key' => 'FileRoot',       'label' => 'Prefijo fichero log',     'type' => 'str'],
    ],
];

$SEC_COLORS = [
    'YSF Network'   => '#00ffcc',
    'DMR Network'   => '#ffb300',
    'DMR Id Lookup' => '#00d4ff',
    'Log'           => '#b57aff',
];

// TalkGroups conocidos para el selector DefaultDstTG
$TG_OPTIONS = [
    ['label' => '--- Selecciona TalkGroup ---', 'value' => ''],
    ['label' => '9 · Local',                    'value' => '9'],
    ['label' => '214 · España',                 'value' => '214'],
    ['label' => '2141 · Cataluña',              'value' => '2141'],
    ['label' => '21465 · ADER',                 'value' => '21465'],
    ['label' => '91 · Mundial',                 'value' => '91'],
    ['label' => '113 · Europa',                 'value' => '113'],
    ['label' => '2 · Echo BM',                  'value' => '2'],
];

$FIELD_OPTIONS = [
    'DMR Network' => [
        'DefaultDstTG' => $TG_OPTIONS,
    ],
];

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
    $bytes = file_put_contents($path, implode('', $result));
    if ($bytes === false) return ['ok' => false, 'msg' => 'Error al escribir: ' . $path];
    return ['ok' => true, 'msg' => 'Configuración guardada correctamente'];
}

$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $newValues = [];
    foreach ($SECTIONS as $sec => $fields) {
        foreach ($fields as $field) {
            $postKey = str_replace([' ','-'], '_', $sec) . '_' . $field['key'];
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

function postKey($sec, $key) { return str_replace([' ','-'], '_', $sec) . '_' . $key; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DMR2YSF Config · EA3EIZ</title>
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
    --d2y:       #00ffcc;
    --text:      #a8b9cc;
    --text-dim:  #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-ui:   'Rajdhani', sans-serif;
  }
  * { box-sizing: border-box; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }
  .ctrl-header {
    background: var(--surface); border-bottom: 2px solid var(--d2y);
    padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem;
  }
  .ctrl-header img { height: 40px; width: auto; }
  .ctrl-header h1 {
    font-family: var(--font-ui); font-weight: 700; font-size: 1.4rem;
    letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase;
  }
  .ctrl-header h1 span { color: var(--d2y); }
  .page-body { padding: 2rem; max-width: 900px; margin: 0 auto; }
  .ini-path {
    font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim);
    background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px;
    padding: .4rem .8rem; margin-bottom: 1.5rem;
  }
  .ini-path span { color: var(--d2y); }
  .alert-custom {
    border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem;
    font-family: var(--font-mono); font-size: .85rem; border: 1px solid;
  }
  .alert-success { background: rgba(0,255,204,.08); border-color: var(--d2y); color: var(--d2y); }
  .alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);  color: var(--red); }
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
    border-radius: 4px; color: var(--d2y); font-family: var(--font-mono);
    font-size: .9rem; padding: .5rem .8rem; outline: none; transition: border-color .2s;
  }
  input[type=text]:focus, input[type=number]:focus { border-color: var(--d2y); }
  .field-hint { font-family: var(--font-mono); font-size: .65rem; color: var(--text-dim); margin-top: .2rem; }
  .custom-select-wrap { position: relative; width: 100%; }
  .custom-select-btn {
    width: 100%; background: var(--surface); border: 1px solid var(--border);
    border-radius: 4px; color: var(--d2y); font-family: var(--font-mono);
    font-size: .85rem; padding: .5rem .8rem; cursor: pointer;
    text-align: left; display: flex; justify-content: space-between; align-items: center;
    transition: border-color .2s;
  }
  .custom-select-btn:hover, .custom-select-btn.open { border-color: var(--d2y); }
  .custom-select-btn .arrow { font-size: .6rem; color: var(--text-dim); flex-shrink: 0; }
  .custom-select-btn .sel-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: .5rem; }
  .custom-select-list {
    display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999;
    background: #0d1e2a; border: 1px solid var(--d2y); border-radius: 4px;
    max-height: 240px; overflow-y: auto; margin-top: 2px;
  }
  .custom-select-list.open { display: block; }
  .custom-select-option {
    font-family: var(--font-mono); font-size: .82rem; color: var(--text);
    padding: .45rem .8rem; cursor: pointer; transition: background .15s;
  }
  .custom-select-option:hover { background: rgba(0,255,204,.1); color: var(--d2y); }
  .custom-select-option.selected { background: rgba(0,255,204,.08); color: var(--d2y); font-weight: bold; }
  .btn-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 1.5rem; }
  .btn-save {
    background: #00cc99; color: #000; border: none; border-radius: 6px;
    font-family: var(--font-ui); font-weight: 700; font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .75rem 2.5rem; cursor: pointer; transition: background .2s;
  }
  .btn-save:hover { background: #00ffcc; }
  .btn-reload {
    background: var(--surface); color: var(--text-dim); border: 1px solid var(--border);
    border-radius: 6px; font-family: var(--font-ui); font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .75rem 2rem; cursor: pointer; transition: background .2s, color .2s;
    text-decoration: none; display: inline-block;
  }
  .btn-reload:hover { background: #1e2d3d; color: var(--text); }
  .btn-back {
    background: #00cc99; color: #000; border: none; border-radius: 6px;
    font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em;
    text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none;
    margin-left: auto; transition: background .2s; display: inline-block;
  }
  .btn-back:hover { background: #00ffcc; color: #000; }
  .note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); }
  .sec-badge {
    font-family: var(--font-mono); font-size: .65rem; letter-spacing: .1em;
    text-transform: uppercase; padding: .2rem .6rem; border: 1px solid;
    border-radius: 3px; margin-bottom: .8rem; display: inline-block;
  }
</style>
</head>
<body>

<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <h1><span>DMR2YSF</span> Config Editor</h1>
  <a href="mmdvm.php" class="btn-back">← Volver al Panel</a>
</header>

<div class="page-body">

  <div class="ini-path">📄 <span><?= htmlspecialchars($INI_PATH) ?></span></div>

  <?php if ($message): ?>
  <div class="alert-custom alert-<?= $msgType ?>">
    <?= $msgType === 'success' ? '✔ ' : '✖ ' ?><?= $message ?>
    <?php if ($msgType === 'success'): ?>
      <br><small>Reinicia DMR2YSF para aplicar los cambios.</small>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!file_exists($INI_PATH)): ?>
  <div class="alert-custom alert-error">✖ Fichero no encontrado: <?= htmlspecialchars($INI_PATH) ?></div>
  <?php else: ?>

  <form method="POST">

    <div class="tabs">
      <?php $first = true; foreach ($SECTIONS as $sec => $fields):
        $color = $SEC_COLORS[$sec] ?? '#00ffcc';
        $tabId = 'tab_' . str_replace([' ','-'], '_', $sec);
      ?>
      <button type="button"
              class="tab-btn <?= $first ? 'active' : '' ?>"
              style="<?= $first ? "border-bottom-color:{$color};color:{$color}" : '' ?>"
              onclick="switchTab(this,'<?= $tabId ?>','<?= $color ?>')">
        <?= htmlspecialchars($sec) ?>
      </button>
      <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($SECTIONS as $sec => $fields):
      $color = $SEC_COLORS[$sec] ?? '#00ffcc';
      $tabId = 'tab_' . str_replace([' ','-'], '_', $sec);
    ?>
    <div class="tab-panel <?= $first ? 'active' : '' ?>" id="<?= $tabId ?>">
      <div class="sec-card" style="border-top: 3px solid <?= $color ?>;">
        <div class="sec-badge" style="color:<?= $color ?>;border-color:<?= $color ?>;">
          <?= htmlspecialchars($sec) ?>
        </div>
        <div class="field-grid">
          <?php foreach ($fields as $field):
            $pKey    = postKey($sec, $field['key']);
            $val     = $values[$sec][$field['key']] ?? '';
            $hasOpts = isset($FIELD_OPTIONS[$sec][$field['key']]) && count($FIELD_OPTIONS[$sec][$field['key']]) > 0;
          ?>
          <div class="field-wrap">
            <label for="<?= $pKey ?>"><?= htmlspecialchars($field['label']) ?></label>

            <?php if ($hasOpts): ?>
              <?php
                $opts     = $FIELD_OPTIONS[$sec][$field['key']];
                $selLabel = '--- Selecciona ---';
                foreach ($opts as $opt) {
                    if ($opt['value'] === $val) { $selLabel = $opt['label']; break; }
                }
              ?>
              <input type="hidden" id="<?= $pKey ?>" name="<?= $pKey ?>" value="<?= htmlspecialchars($val) ?>">
              <div class="custom-select-wrap">
                <button type="button" class="custom-select-btn" onclick="toggleDropdown(this)" data-input="<?= $pKey ?>">
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
                type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
                id="<?= $pKey ?>"
                name="<?= $pKey ?>"
                value="<?= htmlspecialchars($val) ?>"
                <?= $field['type'] === 'int' ? 'min="0"' : '' ?>
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
      <a href="dmr2ysf_config.php" class="btn-reload">🔄 Recargar</a>
      <span class="note">Los cambios requieren reiniciar DMR2YSF</span>
    </div>

  </form>
  <?php endif; ?>
</div>

<script>
function switchTab(btn, tabId, color) {
  document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.style.borderBottomColor=''; b.style.color=''; });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  btn.style.borderBottomColor = color;
  btn.style.color = color;
  document.getElementById(tabId).classList.add('active');
}
function toggleDropdown(btn) {
  const list = btn.nextElementSibling;
  const isOpen = list.classList.contains('open');
  document.querySelectorAll('.custom-select-list').forEach(l => l.classList.remove('open'));
  document.querySelectorAll('.custom-select-btn').forEach(b => b.classList.remove('open'));
  if (!isOpen) { list.classList.add('open'); btn.classList.add('open'); }
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
