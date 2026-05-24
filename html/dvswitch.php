<?php
// =============================================
// dvswitch.php  –  DVSwitch Control Panel
// EA3EIZ · Associació ADER
// =============================================

// ── Rutas ficheros ini ──────────────────────
$AB_INI  = '/opt/Analog_Bridge/Analog_Bridge.ini';
$MB_INI  = '/opt/MMDVM_Bridge/MMDVM_Bridge.ini';

// ── Helper: leer clave de un ini ────────────
function iniGet(string $file, string $section, string $key): string {
    if (!file_exists($file)) return '';
    $data = parse_ini_file($file, true, INI_SCANNER_RAW);
    return $data[$section][$key] ?? '';
}

// ── Helper: reemplazar clave en un ini ──────
function iniSet(string $file, string $section, string $key, string $value): bool {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    // Reemplaza la línea dentro de la sección correcta
    $inSection = false;
    $lines = explode("\n", $content);
    $found = false;
    foreach ($lines as &$line) {
        $trimmed = trim($line);
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            $inSection = (strtolower($m[1]) === strtolower($section));
        }
        if ($inSection && preg_match('/^' . preg_quote($key, '/') . '\s*=/', $trimmed)) {
            $line = $key . '=' . $value;
            $found = true;
        }
    }
    unset($line);
    if ($found) file_put_contents($file, implode("\n", $lines));
    return $found;
}

// ── AJAX handler ────────────────────────────
header('X-Content-Type-Options: nosniff');

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($_GET['action']) {

        // Status de los servicios
        case 'status':
            $ab = trim(shell_exec('systemctl is-active analog_bridge 2>/dev/null'));
            $mb = trim(shell_exec('systemctl is-active mmdvm_bridge 2>/dev/null'));
            echo json_encode(['ab' => $ab, 'mb' => $mb]);
            break;

        // Toggle servicio
        case 'toggle':
            $svc = in_array($_POST['svc'] ?? '', ['analog_bridge','mmdvm_bridge'])
                   ? $_POST['svc'] : '';
            if (!$svc) { echo json_encode(['ok'=>false,'msg'=>'Servicio inválido']); break; }
            $st = trim(shell_exec("systemctl is-active $svc 2>/dev/null"));
            if ($st === 'active') {
                shell_exec("sudo systemctl stop $svc 2>/dev/null");
                shell_exec("sudo systemctl disable $svc 2>/dev/null");
                echo json_encode(['ok'=>true,'active'=>false]);
            } else {
                shell_exec("sudo systemctl enable $svc 2>/dev/null");
                shell_exec("sudo systemctl start $svc 2>/dev/null");
                sleep(1);
                $new = trim(shell_exec("systemctl is-active $svc 2>/dev/null"));
                echo json_encode(['ok'=>true,'active'=>($new==='active')]);
            }
            break;

        // Guardar configuración
        case 'save':
            $errors = [];

            // ── Analog_Bridge ──
            $fields_ab = [
                'AMBE_AUDIO' => [
                    'gatewayDmrId' => $_POST['ab_gatewayDmrId'] ?? '',
                    'repeaterID'   => $_POST['ab_repeaterID']   ?? '',
                    'txTg'         => $_POST['ab_txTg']         ?? '',
                    'txTs'         => $_POST['ab_txTs']         ?? '',
                    'ambeMode'     => $_POST['ab_ambeMode']     ?? '',
                ],
            ];
            foreach ($fields_ab as $sec => $keys) {
                foreach ($keys as $k => $v) {
                    if ($v !== '' && !iniSet($AB_INI, $sec, $k, trim($v))) {
                        $errors[] = "AB:$k";
                    }
                }
            }

            // ── MMDVM_Bridge ──
            $fields_mb = [
                'General' => [
                    'Callsign' => $_POST['mb_Callsign'] ?? '',
                    'Id'       => $_POST['mb_Id']       ?? '',
                ],
                'DMR Network' => [
                    'Address'  => $_POST['mb_Address']  ?? '',
                    'Port'     => $_POST['mb_Port']     ?? '',
                    'Password' => $_POST['mb_Password'] ?? '',
                    'Slot1'    => $_POST['mb_Slot1']    ?? '',
                    'Slot2'    => $_POST['mb_Slot2']    ?? '',
                ],
            ];
            foreach ($fields_mb as $sec => $keys) {
                foreach ($keys as $k => $v) {
                    if ($v !== '' && !iniSet($MB_INI, $sec, $k, trim($v))) {
                        $errors[] = "MB:$k";
                    }
                }
            }

            // Reiniciar servicios
            shell_exec('sudo systemctl restart analog_bridge 2>/dev/null');
            sleep(1);
            shell_exec('sudo systemctl restart mmdvm_bridge 2>/dev/null');

            echo json_encode([
                'ok'     => empty($errors),
                'errors' => $errors,
                'msg'    => empty($errors) ? 'Configuración guardada y servicios reiniciados' : 'Errores en: ' . implode(', ', $errors)
            ]);
            break;

        // Log en tiempo real
        case 'log':
            $svc = in_array($_POST['svc'] ?? '', ['analog_bridge','mmdvm_bridge'])
                   ? $_POST['svc'] : 'mmdvm_bridge';
            header('Content-Type: text/plain');
            echo shell_exec("sudo journalctl -u $svc -n 60 --no-pager --output=short 2>/dev/null") ?: '(sin log)';
            exit;

        default:
            echo json_encode(['ok'=>false,'msg'=>'Acción desconocida']);
    }
    exit;
}

// ── Leer valores actuales para el formulario ─
$ab_gatewayDmrId = iniGet($AB_INI, 'AMBE_AUDIO', 'gatewayDmrId') ?: '2143175';
$ab_repeaterID   = iniGet($AB_INI, 'AMBE_AUDIO', 'repeaterID')   ?: '214317526';
$ab_txTg         = iniGet($AB_INI, 'AMBE_AUDIO', 'txTg')         ?: '214';
$ab_txTs         = iniGet($AB_INI, 'AMBE_AUDIO', 'txTs')         ?: '2';
$ab_ambeMode     = iniGet($AB_INI, 'AMBE_AUDIO', 'ambeMode')     ?: 'DMR';

$mb_Callsign     = iniGet($MB_INI, 'General',     'Callsign')    ?: 'EA3EIZ';
$mb_Id           = iniGet($MB_INI, 'General',     'Id')          ?: '214317526';
$mb_Address      = iniGet($MB_INI, 'DMR Network', 'Address')     ?: 'master.spain-dmr.es';
$mb_Port         = iniGet($MB_INI, 'DMR Network', 'Port')        ?: '62031';
$mb_Password     = iniGet($MB_INI, 'DMR Network', 'Password')    ?: '';
$mb_Slot1        = iniGet($MB_INI, 'DMR Network', 'Slot1')       ?: '0';
$mb_Slot2        = iniGet($MB_INI, 'DMR Network', 'Slot2')       ?: '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>⚡ DVSwitch Control · EA3EIZ</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:      #0a0e1a;
    --surface: #0f1520;
    --card:    #111827;
    --border:  #1e3a5f;
    --cyan:    #00d4ff;
    --amber:   #ffb300;
    --violet:  #a855f7;
    --green:   #00ff88;
    --red:     #ff4444;
    --text:    #c8d8e8;
    --muted:   #4a6080;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Share Tech Mono', monospace;
    min-height: 100vh;
    padding: 1rem;
  }

  /* ── Header ── */
  .header {
    display: flex; align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
    padding-bottom: .75rem; margin-bottom: 1.5rem;
    flex-wrap: wrap; gap: .5rem;
  }
  .header h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.1rem; color: var(--cyan);
    text-shadow: 0 0 10px rgba(0,212,255,.4);
    letter-spacing: 2px;
  }
  .btn-back {
    background: transparent; border: 1px solid var(--muted);
    color: var(--muted); font-family: 'Share Tech Mono', monospace;
    font-size: .8rem; padding: .4rem .9rem; cursor: pointer;
    text-decoration: none; transition: all .2s;
  }
  .btn-back:hover { border-color: var(--cyan); color: var(--cyan); }

  /* ── Grid principal ── */
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }

  /* ── Cards ── */
  .card {
    background: var(--card); border: 1px solid var(--border);
    padding: 1.2rem;
  }
  .card-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1rem; padding-bottom: .6rem;
    border-bottom: 1px solid var(--border);
  }
  .card-title {
    font-family: 'Orbitron', sans-serif; font-size: .8rem;
    letter-spacing: 2px;
  }
  .card-title.cyan   { color: var(--cyan); }
  .card-title.amber  { color: var(--amber); }
  .card-title.violet { color: var(--violet); }
  .card-title.green  { color: var(--green); }

  /* ── Switch ── */
  .switch-wrap { display: flex; align-items: center; gap: .6rem; }
  .switch-label { font-size: .7rem; color: var(--muted); text-transform: uppercase; }
  .sw { position: relative; width: 52px; height: 26px; cursor: pointer; flex-shrink: 0; }
  .sw input { opacity: 0; width: 0; height: 0; position: absolute; }
  .sw-track {
    position: absolute; inset: 0; border-radius: 2px;
    background: #1a2535; border: 2px solid var(--red);
    transition: border-color .25s;
  }
  .sw-knob {
    position: absolute; top: 3px; left: 3px;
    width: 16px; height: 16px; background: var(--red);
    border-radius: 1px;
    transition: transform .28s cubic-bezier(.4,0,.2,1), background .25s;
  }
  .sw input:checked ~ .sw-track { border-color: var(--green); }
  .sw input:checked ~ .sw-knob  { transform: translateX(26px); background: var(--green); }

  /* ── Status badge ── */
  .badge {
    font-size: .65rem; padding: .2rem .5rem;
    text-transform: uppercase; letter-spacing: 1px;
    border: 1px solid;
  }
  .badge.on  { color: var(--green); border-color: var(--green); background: rgba(0,255,136,.08); }
  .badge.off { color: var(--red);   border-color: var(--red);   background: rgba(255,68,68,.08); }

  /* ── Formulario ── */
  .form-group { margin-bottom: .9rem; }
  .form-group label {
    display: block; font-size: .68rem; color: var(--muted);
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: .3rem;
  }
  .form-group input,
  .form-group select {
    width: 100%; background: #0a0e1a; border: 1px solid var(--border);
    color: var(--text); font-family: 'Share Tech Mono', monospace;
    font-size: .85rem; padding: .45rem .6rem; outline: none;
    transition: border-color .2s;
  }
  .form-group input:focus,
  .form-group select:focus { border-color: var(--cyan); }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }

  /* ── Botones ── */
  .btn-save {
    width: 100%; padding: .65rem; margin-top: .5rem;
    background: var(--cyan); color: #000;
    border: none; font-family: 'Orbitron', sans-serif;
    font-size: .8rem; letter-spacing: 2px; cursor: pointer;
    transition: opacity .2s;
  }
  .btn-save:hover { opacity: .85; }
  .btn-save:disabled { opacity: .4; cursor: not-allowed; }

  /* ── TalkGroup rápido ── */
  .tg-grid { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .8rem; }
  .tg-btn {
    background: #1a2535; border: 1px solid var(--border);
    color: var(--text); font-family: 'Share Tech Mono', monospace;
    font-size: .75rem; padding: .3rem .6rem; cursor: pointer;
    transition: all .2s;
  }
  .tg-btn:hover { border-color: var(--amber); color: var(--amber); }
  .tg-btn.active { background: var(--amber); color: #000; border-color: var(--amber); font-weight: 700; }

  /* ── Terminal ── */
  .term-wrap { margin-top: 1rem; border: 1px solid var(--border); }
  .term-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: .5rem .8rem; background: #0d141d;
    border-bottom: 1px solid var(--border);
    font-size: .7rem; color: var(--cyan);
  }
  .term-tabs { display: flex; gap: .4rem; }
  .term-tab {
    background: transparent; border: 1px solid var(--border);
    color: var(--muted); font-family: 'Share Tech Mono', monospace;
    font-size: .68rem; padding: .2rem .6rem; cursor: pointer;
  }
  .term-tab.active { background: var(--cyan); color: #000; border-color: var(--cyan); }
  .term-box {
    background: #060c10; color: #7fa2bf;
    font-family: 'Share Tech Mono', monospace;
    font-size: .7rem; line-height: 1.5;
    padding: .8rem; height: 280px;
    overflow-y: auto; white-space: pre-wrap;
  }
  .term-box::-webkit-scrollbar { width: 4px; }
  .term-box::-webkit-scrollbar-thumb { background: var(--border); }

  /* ── Toast ── */
  #toast {
    position: fixed; bottom: 1.5rem; right: 1.5rem;
    background: var(--card); border-left: 3px solid var(--green);
    color: var(--green); font-size: .85rem;
    padding: .6rem 1.2rem; display: none; z-index: 200;
  }
  #toast.err { border-color: var(--red); color: var(--red); }

  /* ── Separador ── */
  .sep { height: 1px; background: var(--border); margin: .8rem 0; }

  /* ── Full width card ── */
  .full { grid-column: 1 / -1; }
</style>
</head>
<body>

<div class="header">
  <h1>⚡ DVSWITCH CONTROL · EA3EIZ</h1>
  <div style="display:flex;gap:.5rem;">
    <a href="/dvswitch" class="btn-back" style="border-color:var(--cyan);color:var(--cyan);">📊 DVSWITCH DASHBOARD</a>
    <a href="mmdvm.php" class="btn-back">🏠 PANEL PHPPLUS</a>
  </div>
</div>

<div class="grid">

  <!-- ══════════════════ ANALOG BRIDGE ══════════════════ -->
  <div class="card">
    <div class="card-header">
      <span class="card-title cyan">📡 ANALOG_BRIDGE</span>
      <div class="switch-wrap">
        <span class="badge off" id="ab-badge">···</span>
        <label class="sw">
          <input type="checkbox" id="sw-ab" onchange="toggleSvc('analog_bridge', this)">
          <span class="sw-track"></span>
          <span class="sw-knob"></span>
        </label>
        <span class="switch-label">ON/OFF</span>
      </div>
    </div>

    <div class="form-group">
      <label>Gateway DMR ID (7 dígitos)</label>
      <input type="text" id="ab_gatewayDmrId" value="<?= htmlspecialchars($ab_gatewayDmrId) ?>" maxlength="7">
    </div>
    <div class="form-group">
      <label>Repeater ID / ESSID (9 dígitos)</label>
      <input type="text" id="ab_repeaterID" value="<?= htmlspecialchars($ab_repeaterID) ?>" maxlength="9">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>TalkGroup TX (txTg)</label>
        <input type="number" id="ab_txTg" value="<?= htmlspecialchars($ab_txTg) ?>">
      </div>
      <div class="form-group">
        <label>Slot TX (txTs)</label>
        <select id="ab_txTs">
          <option value="1" <?= $ab_txTs=='1'?'selected':'' ?>>Slot 1</option>
          <option value="2" <?= $ab_txTs=='2'?'selected':'' ?>>Slot 2</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Modo AMBE (ambeMode)</label>
      <select id="ab_ambeMode">
        <?php foreach (['DMR','DSTAR','YSFN','YSFW','NXDN','P25'] as $m): ?>
          <option value="<?= $m ?>" <?= $ab_ambeMode===$m?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- ══════════════════ MMDVM BRIDGE ══════════════════ -->
  <div class="card">
    <div class="card-header">
      <span class="card-title amber">🔗 MMDVM_BRIDGE</span>
      <div class="switch-wrap">
        <span class="badge off" id="mb-badge">···</span>
        <label class="sw">
          <input type="checkbox" id="sw-mb" onchange="toggleSvc('mmdvm_bridge', this)">
          <span class="sw-track"></span>
          <span class="sw-knob"></span>
        </label>
        <span class="switch-label">ON/OFF</span>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Callsign</label>
        <input type="text" id="mb_Callsign" value="<?= htmlspecialchars($mb_Callsign) ?>">
      </div>
      <div class="form-group">
        <label>DMR ID</label>
        <input type="text" id="mb_Id" value="<?= htmlspecialchars($mb_Id) ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Servidor BrandMeister (Address)</label>
      <input type="text" id="mb_Address" value="<?= htmlspecialchars($mb_Address) ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Puerto (Port)</label>
        <input type="number" id="mb_Port" value="<?= htmlspecialchars($mb_Port) ?>">
      </div>
      <div class="form-group">
        <label>Password BM</label>
        <input type="password" id="mb_Password" value="<?= htmlspecialchars($mb_Password) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Slot 1</label>
        <select id="mb_Slot1">
          <option value="0" <?= $mb_Slot1=='0'?'selected':'' ?>>0 — Desactivado</option>
          <option value="1" <?= $mb_Slot1=='1'?'selected':'' ?>>1 — Activado</option>
        </select>
      </div>
      <div class="form-group">
        <label>Slot 2</label>
        <select id="mb_Slot2">
          <option value="0" <?= $mb_Slot2=='0'?'selected':'' ?>>0 — Desactivado</option>
          <option value="1" <?= $mb_Slot2=='1'?'selected':'' ?>>1 — Activado</option>
        </select>
      </div>
    </div>
  </div>

  <!-- ══════════════════ TG RÁPIDO ══════════════════ -->
  <div class="card full">
    <div class="card-header">
      <span class="card-title violet">⚡ CAMBIO RÁPIDO DE TALKGROUP</span>
      <span style="font-size:.7rem;color:var(--muted)">Cambia txTg y reinicia servicios</span>
    </div>
    <div class="tg-grid" id="tgGrid">
      <?php
      $tgs = [
        '214'   => 'España',
        '2141'  => 'Cataluña',
        '21465' => 'ADER',
        '9'     => 'Local 9',
        '8'     => 'Local 8',
        '91'    => 'Mundial',
        '113'   => 'Europa',
        '2'     => 'BM Echo',
      ];
      foreach ($tgs as $tg => $label): ?>
        <button class="tg-btn <?= $ab_txTg==$tg?'active':'' ?>"
          onclick="setTG('<?= $tg ?>', this)">
          <?= $tg ?> · <?= $label ?>
        </button>
      <?php endforeach; ?>
    </div>
    <div style="font-size:.7rem;color:var(--muted);">
      TG activo: <span id="tgActivo" style="color:var(--violet)"><?= htmlspecialchars($ab_txTg) ?></span>
    </div>
  </div>

  <!-- ══════════════════ BOTÓN GUARDAR ══════════════════ -->
  <div class="card full">
    <button class="btn-save" id="btnSave" onclick="saveAll()">
      💾 GUARDAR CONFIGURACIÓN Y REINICIAR SERVICIOS
    </button>
  </div>

  <!-- ══════════════════ LOG TERMINAL ══════════════════ -->
  <div class="card full">
    <div class="term-wrap">
      <div class="term-header">
        <span>📋 JOURNAL LOG</span>
        <div class="term-tabs">
          <button class="term-tab active" id="tab-mb" onclick="switchLog('mmdvm_bridge')">MMDVM_Bridge</button>
          <button class="term-tab" id="tab-ab" onclick="switchLog('analog_bridge')">Analog_Bridge</button>
        </div>
      </div>
      <div class="term-box" id="termBox">Cargando...</div>
    </div>
  </div>

</div><!-- /grid -->

<div id="toast">✔ OK</div>

<script>
let _logSvc = 'mmdvm_bridge';

// ── Status ────────────────────────────────
async function loadStatus() {
  try {
    const r = await fetch('?action=status&t=' + Date.now());
    const d = await r.json();
    setSvcUI('ab', d.ab === 'active');
    setSvcUI('mb', d.mb === 'active');
  } catch(e) {}
}

function setSvcUI(prefix, active) {
  const badge = document.getElementById(prefix + '-badge');
  const sw    = document.getElementById('sw-' + prefix);
  badge.textContent = active ? 'ACTIVO' : 'DETENIDO';
  badge.className   = 'badge ' + (active ? 'on' : 'off');
  sw.checked        = active;
}

// ── Toggle servicio ───────────────────────
async function toggleSvc(svc, el) {
  el.disabled = true;
  try {
    const fd = new FormData();
    fd.append('svc', svc);
    const r = await fetch('?action=toggle', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      const prefix = svc === 'analog_bridge' ? 'ab' : 'mb';
      setSvcUI(prefix, d.active);
      showToast(svc + (d.active ? ' ACTIVADO' : ' DETENIDO'));
    }
  } catch(e) {}
  setTimeout(() => el.disabled = false, 800);
}

// ── TalkGroup rápido ──────────────────────
function setTG(tg, btn) {
  document.getElementById('ab_txTg').value = tg;
  document.getElementById('tgActivo').textContent = tg;
  document.querySelectorAll('.tg-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

// ── Guardar ───────────────────────────────
async function saveAll() {
  const btn = document.getElementById('btnSave');
  btn.disabled = true;
  btn.textContent = '⏳ GUARDANDO...';

  const fd = new FormData();
  const fields = [
    'ab_gatewayDmrId','ab_repeaterID','ab_txTg','ab_txTs','ab_ambeMode',
    'mb_Callsign','mb_Id','mb_Address','mb_Port','mb_Password','mb_Slot1','mb_Slot2'
  ];
  fields.forEach(f => {
    const el = document.getElementById(f);
    if (el) fd.append(f, el.value);
  });

  try {
    const r = await fetch('?action=save', { method: 'POST', body: fd });
    const d = await r.json();
    showToast(d.msg, !d.ok);
  } catch(e) {
    showToast('Error de conexión', true);
  }

  btn.disabled = false;
  btn.textContent = '💾 GUARDAR CONFIGURACIÓN Y REINICIAR SERVICIOS';
}

// ── Log ───────────────────────────────────
async function loadLog() {
  try {
    const fd = new FormData();
    fd.append('svc', _logSvc);
    const r = await fetch('?action=log', { method: 'POST', body: fd });
    const txt = await r.text();
    const box = document.getElementById('termBox');
    box.textContent = txt;
    box.scrollTop = box.scrollHeight;
  } catch(e) {}
}

function switchLog(svc) {
  _logSvc = svc;
  document.getElementById('tab-mb').classList.toggle('active', svc === 'mmdvm_bridge');
  document.getElementById('tab-ab').classList.toggle('active', svc === 'analog_bridge');
  loadLog();
}

// ── Toast ─────────────────────────────────
function showToast(msg, err = false) {
  const t = document.getElementById('toast');
  t.textContent = (err ? '✕ ' : '✔ ') + msg;
  t.className   = err ? 'err' : '';
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 3000);
}

// ── Init ──────────────────────────────────
loadStatus();
loadLog();
setInterval(loadStatus, 3000);
setInterval(loadLog, 4000);
</script>
</body>
</html>
