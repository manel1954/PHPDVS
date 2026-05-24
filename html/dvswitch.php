<?php
// =============================================
// dvswitch.php  –  DVSwitch Control Panel
// EA3EIZ · Associació ADER
// =============================================

$AB_INI  = '/opt/Analog_Bridge/Analog_Bridge.ini';
$MB_INI  = '/opt/MMDVM_Bridge/MMDVM_Bridge.ini';

// ── Helper: leer clave de un ini ────────────
function iniGet(string $file, string $section, string $key): string {
    if (!file_exists($file)) return '';
    $data = parse_ini_file($file, true, INI_SCANNER_RAW);
    // Buscar sección case-insensitive
    foreach ($data as $sec => $vals) {
        if (strtolower($sec) === strtolower($section)) {
            foreach ($vals as $k => $v) {
                if (strtolower($k) === strtolower($key)) return trim($v);
            }
        }
    }
    return '';
}

// ── Helper: reemplazar clave en un ini ──────
function iniSet(string $file, string $section, string $key, string $value): bool {
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    $inSection = false;
    $lines = explode("\n", $content);
    $found = false;
    foreach ($lines as &$line) {
        $trimmed = trim($line);
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            $inSection = (strtolower($m[1]) === strtolower($section));
        }
        if ($inSection && preg_match('/^' . preg_quote($key, '/') . '\s*=/i', $trimmed)) {
            $line = $key . '=' . $value;
            $found = true;
        }
    }
    unset($line);
    if ($found) file_put_contents($file, implode("\n", $lines));
    return $found;
}

header('X-Content-Type-Options: nosniff');

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($_GET['action']) {

        case 'status':
            $ab = trim(shell_exec('systemctl is-active analog_bridge 2>/dev/null'));
            $mb = trim(shell_exec('systemctl is-active mmdvm_bridge 2>/dev/null'));
            echo json_encode(['ab' => $ab, 'mb' => $mb]);
            break;

        case 'toggle':
            $svc = in_array($_POST['svc'] ?? '', ['analog_bridge','mmdvm_bridge']) ? $_POST['svc'] : '';
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

        case 'save':
            $errors = [];

            // ── Analog_Bridge ──
            $ab_fields = [
                'AMBE_AUDIO' => [
                    'gatewayDmrId' => $_POST['ab_gatewayDmrId'] ?? '',
                    'repeaterID'   => $_POST['ab_repeaterID']   ?? '',
                    'txTg'         => $_POST['ab_txTg']         ?? '',
                    'txTs'         => $_POST['ab_txTs']         ?? '',
                    'ambeMode'     => $_POST['ab_ambeMode']     ?? '',
                ],
            ];
            foreach ($ab_fields as $sec => $keys) {
                foreach ($keys as $k => $v) {
                    if ($v !== '' && !iniSet($AB_INI, $sec, $k, trim($v)))
                        $errors[] = "AB:$k";
                }
            }

            // ── MMDVM_Bridge General ──
            $mb_general = [
                'General' => [
                    'Callsign' => $_POST['mb_Callsign'] ?? '',
                    'Id'       => $_POST['mb_Id']       ?? '',
                ],
            ];
            foreach ($mb_general as $sec => $keys) {
                foreach ($keys as $k => $v) {
                    if ($v !== '' && !iniSet($MB_INI, $sec, $k, trim($v)))
                        $errors[] = "MB:$k";
                }
            }

            // ── Modos Enable ──
            $modes = ['D-Star','DMR','System Fusion','NXDN','P25'];
            foreach ($modes as $mode) {
                $slug = strtolower(str_replace([' ','-'], '_', $mode));
                $val  = ($_POST["mode_{$slug}_enable"] ?? '0') === '1' ? '1' : '0';
                iniSet($MB_INI, $mode, 'Enable', $val);
            }

            // ── Redes ──
            $networks = [
                'DMR Network' => [
                    'Enable'   => $_POST['dmrnet_enable']   ?? '0',
                    'Address'  => $_POST['dmrnet_address']  ?? '',
                    'Port'     => $_POST['dmrnet_port']     ?? '',
                    'Password' => $_POST['dmrnet_password'] ?? '',
                    'Slot1'    => $_POST['dmrnet_slot1']    ?? '',
                    'Slot2'    => $_POST['dmrnet_slot2']    ?? '',
                ],
                'D-Star Network' => [
                    'Enable'         => $_POST['dstarnet_enable']  ?? '0',
                    'GatewayAddress' => $_POST['dstarnet_gw']      ?? '',
                    'GatewayPort'    => $_POST['dstarnet_gwport']  ?? '',
                    'LocalPort'      => $_POST['dstarnet_lport']   ?? '',
                ],
                'System Fusion Network' => [
                    'Enable'         => $_POST['ysfnet_enable']    ?? '0',
                    'LocalPort'      => $_POST['ysfnet_lport']     ?? '',
                    'GatewayAddress' => $_POST['ysfnet_gw']        ?? '',
                    'GatewayPort'    => $_POST['ysfnet_gwport']    ?? '',
                ],
                'NXDN Network' => [
                    'Enable'         => $_POST['nxdnnet_enable']   ?? '0',
                    'LocalPort'      => $_POST['nxdnnet_lport']    ?? '',
                    'GatewayAddress' => $_POST['nxdnnet_gw']       ?? '',
                    'GatewayPort'    => $_POST['nxdnnet_gwport']   ?? '',
                ],
            ];
            foreach ($networks as $sec => $keys) {
                foreach ($keys as $k => $v) {
                    if ($v !== '') iniSet($MB_INI, $sec, $k, trim($v));
                }
            }

            // Reiniciar servicios
            shell_exec('sudo systemctl restart analog_bridge 2>/dev/null');
            sleep(1);
            shell_exec('sudo systemctl restart mmdvm_bridge 2>/dev/null');

            echo json_encode([
                'ok'  => empty($errors),
                'msg' => empty($errors)
                    ? 'Configuración guardada y servicios reiniciados'
                    : 'Errores en: ' . implode(', ', $errors)
            ]);
            break;

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

// ── Leer valores actuales ────────────────────
// Analog_Bridge
$ab_gatewayDmrId = iniGet($AB_INI,'AMBE_AUDIO','gatewayDmrId') ?: '2143175';
$ab_repeaterID   = iniGet($AB_INI,'AMBE_AUDIO','repeaterID')   ?: '214317526';
$ab_txTg         = iniGet($AB_INI,'AMBE_AUDIO','txTg')         ?: '214';
$ab_txTs         = iniGet($AB_INI,'AMBE_AUDIO','txTs')         ?: '2';
$ab_ambeMode     = iniGet($AB_INI,'AMBE_AUDIO','ambeMode')     ?: 'DMR';

// MMDVM_Bridge General
$mb_Callsign = iniGet($MB_INI,'General','Callsign') ?: 'EA3EIZ';
$mb_Id       = iniGet($MB_INI,'General','Id')       ?: '214317526';

// Modos
$mode_dstar  = iniGet($MB_INI,'D-Star','Enable')       ?: '0';
$mode_dmr    = iniGet($MB_INI,'DMR','Enable')           ?: '1';
$mode_ysf    = iniGet($MB_INI,'System Fusion','Enable') ?: '0';
$mode_nxdn   = iniGet($MB_INI,'NXDN','Enable')          ?: '0';
$mode_p25    = iniGet($MB_INI,'P25','Enable')            ?: '0';

// DMR Network
$dmrnet_enable   = iniGet($MB_INI,'DMR Network','Enable')   ?: '1';
$dmrnet_address  = iniGet($MB_INI,'DMR Network','Address')  ?: 'master.spain-dmr.es';
$dmrnet_port     = iniGet($MB_INI,'DMR Network','Port')     ?: '62031';
$dmrnet_password = iniGet($MB_INI,'DMR Network','Password') ?: '';
$dmrnet_slot1    = iniGet($MB_INI,'DMR Network','Slot1')    ?: '0';
$dmrnet_slot2    = iniGet($MB_INI,'DMR Network','Slot2')    ?: '1';

// D-Star Network
$dstarnet_enable = iniGet($MB_INI,'D-Star Network','Enable')         ?: '0';
$dstarnet_gw     = iniGet($MB_INI,'D-Star Network','GatewayAddress') ?: '127.0.0.1';
$dstarnet_gwport = iniGet($MB_INI,'D-Star Network','GatewayPort')    ?: '20010';
$dstarnet_lport  = iniGet($MB_INI,'D-Star Network','LocalPort')      ?: '20011';

// YSF Network
$ysfnet_enable   = iniGet($MB_INI,'System Fusion Network','Enable')         ?: '0';
$ysfnet_lport    = iniGet($MB_INI,'System Fusion Network','LocalPort')      ?: '3200';
$ysfnet_gw       = iniGet($MB_INI,'System Fusion Network','GatewayAddress') ?: '127.0.0.1';
$ysfnet_gwport   = iniGet($MB_INI,'System Fusion Network','GatewayPort')    ?: '4200';

// NXDN Network
$nxdnnet_enable  = iniGet($MB_INI,'NXDN Network','Enable')         ?: '0';
$nxdnnet_lport   = iniGet($MB_INI,'NXDN Network','LocalPort')      ?: '14021';
$nxdnnet_gw      = iniGet($MB_INI,'NXDN Network','GatewayAddress') ?: '127.0.0.1';
$nxdnnet_gwport  = iniGet($MB_INI,'NXDN Network','GatewayPort')    ?: '14020';

function en(string $v): string { return $v === '1' ? 'selected' : ''; }
function dis(string $v): string { return $v === '0' ? 'selected' : ''; }
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
    --blue:    #3b82f6;
    --text:    #c8d8e8;
    --muted:   #4a6080;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'Share Tech Mono', monospace; min-height: 100vh; padding: 1rem; }

  /* ── Header ── */
  .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: .75rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .5rem; }
  .header h1 { font-family: 'Orbitron', sans-serif; font-size: 1rem; color: var(--cyan); text-shadow: 0 0 10px rgba(0,212,255,.4); letter-spacing: 2px; }
  .header-btns { display: flex; gap: .5rem; flex-wrap: wrap; }
  .btn-hdr { background: transparent; border: 1px solid var(--muted); color: var(--muted); font-family: 'Share Tech Mono', monospace; font-size: .78rem; padding: .4rem .9rem; cursor: pointer; text-decoration: none; transition: all .2s; }
  .btn-hdr:hover { border-color: var(--cyan); color: var(--cyan); }
  .btn-hdr.accent { border-color: var(--cyan); color: var(--cyan); }

  /* ── Grid ── */
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
  .full  { grid-column: 1 / -1; }
  @media (max-width: 900px) { .grid2, .grid3 { grid-template-columns: 1fr; } }

  /* ── Sección ── */
  .section-title { font-family: 'Orbitron', sans-serif; font-size: .75rem; letter-spacing: 3px; padding: .5rem 0; margin: 1.2rem 0 .6rem; border-bottom: 1px solid var(--border); }
  .section-title.cyan   { color: var(--cyan); }
  .section-title.amber  { color: var(--amber); }
  .section-title.violet { color: var(--violet); }
  .section-title.green  { color: var(--green); }
  .section-title.blue   { color: var(--blue); }
  .section-title.red    { color: var(--red); }

  /* ── Card ── */
  .card { background: var(--card); border: 1px solid var(--border); padding: 1.1rem; }
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .9rem; padding-bottom: .5rem; border-bottom: 1px solid var(--border); }
  .card-title { font-family: 'Orbitron', sans-serif; font-size: .75rem; letter-spacing: 1px; }
  .card-title.cyan   { color: var(--cyan); }
  .card-title.amber  { color: var(--amber); }
  .card-title.violet { color: var(--violet); }
  .card-title.green  { color: var(--green); }
  .card-title.blue   { color: var(--blue); }
  .card-title.red    { color: var(--red); }

  /* ── Switch servicio ── */
  .switch-wrap { display: flex; align-items: center; gap: .6rem; }
  .switch-label { font-size: .65rem; color: var(--muted); text-transform: uppercase; }
  .sw { position: relative; width: 52px; height: 26px; cursor: pointer; flex-shrink: 0; }
  .sw input { opacity: 0; width: 0; height: 0; position: absolute; }
  .sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid var(--red); transition: border-color .25s; }
  .sw-knob  { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: var(--red); border-radius: 1px; transition: transform .28s cubic-bezier(.4,0,.2,1), background .25s; }
  .sw input:checked ~ .sw-track { border-color: var(--green); }
  .sw input:checked ~ .sw-knob  { transform: translateX(26px); background: var(--green); }

  /* ── Badge ── */
  .badge { font-size: .62rem; padding: .2rem .5rem; text-transform: uppercase; letter-spacing: 1px; border: 1px solid; }
  .badge.on  { color: var(--green); border-color: var(--green); background: rgba(0,255,136,.08); }
  .badge.off { color: var(--red);   border-color: var(--red);   background: rgba(255,68,68,.08); }

  /* ── Enable selector ── */
  .enable-row { display: flex; align-items: center; gap: .8rem; margin-bottom: .8rem; }
  .enable-label { font-size: .7rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; flex: 1; }
  .enable-sel { background: #0a0e1a; border: 1px solid var(--border); color: var(--text); font-family: 'Share Tech Mono', monospace; font-size: .8rem; padding: .3rem .5rem; }
  .enable-sel.is-on  { border-color: var(--green); color: var(--green); }
  .enable-sel.is-off { border-color: var(--red);   color: var(--red); }

  /* ── Form ── */
  .form-group { margin-bottom: .8rem; }
  .form-group label { display: block; font-size: .66rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: .25rem; }
  .form-group input, .form-group select { width: 100%; background: #0a0e1a; border: 1px solid var(--border); color: var(--text); font-family: 'Share Tech Mono', monospace; font-size: .82rem; padding: .42rem .55rem; outline: none; transition: border-color .2s; }
  .form-group input:focus, .form-group select:focus { border-color: var(--cyan); }
  .form-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
  .form-row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .7rem; }

  /* ── TG rápido ── */
  .tg-grid { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .6rem; }
  .tg-btn { background: #1a2535; border: 1px solid var(--border); color: var(--text); font-family: 'Share Tech Mono', monospace; font-size: .72rem; padding: .28rem .55rem; cursor: pointer; transition: all .2s; }
  .tg-btn:hover  { border-color: var(--amber); color: var(--amber); }
  .tg-btn.active { background: var(--amber); color: #000; border-color: var(--amber); font-weight: 700; }

  /* ── Botón guardar ── */
  .btn-save { width: 100%; padding: .65rem; background: var(--cyan); color: #000; border: none; font-family: 'Orbitron', sans-serif; font-size: .8rem; letter-spacing: 2px; cursor: pointer; transition: opacity .2s; margin-top: .4rem; }
  .btn-save:hover { opacity: .85; }
  .btn-save:disabled { opacity: .4; cursor: not-allowed; }

  /* ── Terminal ── */
  .term-wrap { border: 1px solid var(--border); margin-top: 1rem; }
  .term-header { display: flex; justify-content: space-between; align-items: center; padding: .5rem .8rem; background: #0d141d; border-bottom: 1px solid var(--border); font-size: .7rem; color: var(--cyan); }
  .term-tabs { display: flex; gap: .4rem; }
  .term-tab { background: transparent; border: 1px solid var(--border); color: var(--muted); font-family: 'Share Tech Mono', monospace; font-size: .66rem; padding: .2rem .55rem; cursor: pointer; }
  .term-tab.active { background: var(--cyan); color: #000; border-color: var(--cyan); }
  .term-box { background: #060c10; color: #7fa2bf; font-family: 'Share Tech Mono', monospace; font-size: .69rem; line-height: 1.5; padding: .8rem; height: 260px; overflow-y: auto; white-space: pre-wrap; }
  .term-box::-webkit-scrollbar { width: 4px; }
  .term-box::-webkit-scrollbar-thumb { background: var(--border); }

  /* ── Toast ── */
  #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: var(--card); border-left: 3px solid var(--green); color: var(--green); font-size: .85rem; padding: .6rem 1.2rem; display: none; z-index: 200; }
  #toast.err { border-color: var(--red); color: var(--red); }

  .sep { height: 1px; background: var(--border); margin: .7rem 0; }
</style>
</head>
<body>

<div class="header">
  <h1>⚡ DVSWITCH CONTROL · EA3EIZ</h1>
  <div class="header-btns">
    <a href="/dvswitch" class="btn-hdr accent">📊 DVSWITCH DASHBOARD</a>
    <a href="mmdvm.php" class="btn-hdr">🏠 PANEL PHPPLUS</a>
  </div>
</div>

<!-- ══ ESTADO SERVICIOS ══════════════════════════════════ -->
<div class="grid2">
  <div class="card">
    <div class="card-header">
      <span class="card-title cyan">📡 ANALOG_BRIDGE</span>
      <div class="switch-wrap">
        <span class="badge off" id="ab-badge">···</span>
        <label class="sw">
          <input type="checkbox" id="sw-ab" onchange="toggleSvc('analog_bridge',this)">
          <span class="sw-track"></span><span class="sw-knob"></span>
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
    <div class="form-row2">
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
      <label>Modo AMBE</label>
      <select id="ab_ambeMode">
        <?php foreach (['DMR','DSTAR','YSFN','YSFW','NXDN','P25'] as $m): ?>
          <option value="<?= $m ?>" <?= $ab_ambeMode===$m?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title amber">🔗 MMDVM_BRIDGE</span>
      <div class="switch-wrap">
        <span class="badge off" id="mb-badge">···</span>
        <label class="sw">
          <input type="checkbox" id="sw-mb" onchange="toggleSvc('mmdvm_bridge',this)">
          <span class="sw-track"></span><span class="sw-knob"></span>
        </label>
        <span class="switch-label">ON/OFF</span>
      </div>
    </div>
    <div class="form-row2">
      <div class="form-group">
        <label>Callsign</label>
        <input type="text" id="mb_Callsign" value="<?= htmlspecialchars($mb_Callsign) ?>">
      </div>
      <div class="form-group">
        <label>DMR ID</label>
        <input type="text" id="mb_Id" value="<?= htmlspecialchars($mb_Id) ?>">
      </div>
    </div>

    <!-- MODOS -->
    <div class="sep"></div>
    <div style="font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:.5rem;">Modos activos</div>
    <div class="form-row3">
      <?php
      $modos = [
        'd_star'         => ['label'=>'D-STAR',  'val'=>$mode_dstar,  'color'=>'blue'],
        'dmr'            => ['label'=>'DMR',      'val'=>$mode_dmr,    'color'=>'cyan'],
        'system_fusion'  => ['label'=>'YSF/C4FM', 'val'=>$mode_ysf,   'color'=>'green'],
        'nxdn'           => ['label'=>'NXDN',     'val'=>$mode_nxdn,  'color'=>'violet'],
        'p25'            => ['label'=>'P25',       'val'=>$mode_p25,   'color'=>'amber'],
      ];
      foreach ($modos as $slug => $info):
      ?>
      <div class="form-group">
        <label style="color:var(--<?= $info['color'] ?>)"><?= $info['label'] ?></label>
        <select id="mode_<?= $slug ?>_enable" class="enable-sel <?= $info['val']==='1'?'is-on':'is-off' ?>"
          onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
          <option value="1" <?= en($info['val']) ?>>1 — ON</option>
          <option value="0" <?= dis($info['val']) ?>>0 — OFF</option>
        </select>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══ TG RÁPIDO ═══════════════════════════════════════ -->
<div class="section-title violet">⚡ CAMBIO RÁPIDO DE TALKGROUP</div>
<div class="card">
  <div class="tg-grid" id="tgGrid">
    <?php
    $tgs = ['214'=>'España','2141'=>'Cataluña','21465'=>'ADER','9'=>'Local 9','8'=>'Local 8','91'=>'Mundial','113'=>'Europa','2'=>'Echo BM'];
    foreach ($tgs as $tg => $lbl): ?>
      <button class="tg-btn <?= $ab_txTg==$tg?'active':'' ?>" onclick="setTG('<?= $tg ?>',this)">
        <?= $tg ?> · <?= $lbl ?>
      </button>
    <?php endforeach; ?>
  </div>
  <div style="font-size:.7rem;color:var(--muted)">TG activo: <span id="tgActivo" style="color:var(--violet)"><?= htmlspecialchars($ab_txTg) ?></span></div>
</div>

<!-- ══ REDES ════════════════════════════════════════════ -->

<!-- DMR Network -->
<div class="section-title cyan">🌐 DMR NETWORK · BrandMeister</div>
<div class="card">
  <div class="enable-row">
    <span class="enable-label">Enable</span>
    <select id="dmrnet_enable" class="enable-sel <?= $dmrnet_enable==='1'?'is-on':'is-off' ?>"
      onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
      <option value="1" <?= en($dmrnet_enable) ?>>1 — ON</option>
      <option value="0" <?= dis($dmrnet_enable) ?>>0 — OFF</option>
    </select>
  </div>
  <div class="form-row3">
    <div class="form-group">
      <label>Address (Servidor BM)</label>
      <input type="text" id="dmrnet_address" value="<?= htmlspecialchars($dmrnet_address) ?>">
    </div>
    <div class="form-group">
      <label>Port</label>
      <input type="number" id="dmrnet_port" value="<?= htmlspecialchars($dmrnet_port) ?>">
    </div>
    <div class="form-group">
      <label>Password BM</label>
      <input type="password" id="dmrnet_password" value="<?= htmlspecialchars($dmrnet_password) ?>">
    </div>
  </div>
  <div class="form-row2">
    <div class="form-group">
      <label>Slot 1</label>
      <select id="dmrnet_slot1" class="enable-sel <?= $dmrnet_slot1==='1'?'is-on':'is-off' ?>"
        onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
        <option value="0" <?= dis($dmrnet_slot1) ?>>0 — OFF</option>
        <option value="1" <?= en($dmrnet_slot1) ?>>1 — ON</option>
      </select>
    </div>
    <div class="form-group">
      <label>Slot 2</label>
      <select id="dmrnet_slot2" class="enable-sel <?= $dmrnet_slot2==='1'?'is-on':'is-off' ?>"
        onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
        <option value="0" <?= dis($dmrnet_slot2) ?>>0 — OFF</option>
        <option value="1" <?= en($dmrnet_slot2) ?>>1 — ON</option>
      </select>
    </div>
  </div>
</div>

<!-- D-Star Network -->
<div class="section-title blue">🌐 D-STAR NETWORK · XLX266</div>
<div class="card">
  <div class="enable-row">
    <span class="enable-label">Enable</span>
    <select id="dstarnet_enable" class="enable-sel <?= $dstarnet_enable==='1'?'is-on':'is-off' ?>"
      onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
      <option value="1" <?= en($dstarnet_enable) ?>>1 — ON</option>
      <option value="0" <?= dis($dstarnet_enable) ?>>0 — OFF</option>
    </select>
  </div>
  <div class="form-row3">
    <div class="form-group">
      <label>Gateway Address</label>
      <input type="text" id="dstarnet_gw" value="<?= htmlspecialchars($dstarnet_gw) ?>">
    </div>
    <div class="form-group">
      <label>Gateway Port</label>
      <input type="number" id="dstarnet_gwport" value="<?= htmlspecialchars($dstarnet_gwport) ?>">
    </div>
    <div class="form-group">
      <label>Local Port</label>
      <input type="number" id="dstarnet_lport" value="<?= htmlspecialchars($dstarnet_lport) ?>">
    </div>
  </div>
</div>

<!-- YSF Network -->
<div class="section-title green">🌐 SYSTEM FUSION NETWORK · ES-ADER</div>
<div class="card">
  <div class="enable-row">
    <span class="enable-label">Enable</span>
    <select id="ysfnet_enable" class="enable-sel <?= $ysfnet_enable==='1'?'is-on':'is-off' ?>"
      onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
      <option value="1" <?= en($ysfnet_enable) ?>>1 — ON</option>
      <option value="0" <?= dis($ysfnet_enable) ?>>0 — OFF</option>
    </select>
  </div>
  <div class="form-row3">
    <div class="form-group">
      <label>Gateway Address</label>
      <input type="text" id="ysfnet_gw" value="<?= htmlspecialchars($ysfnet_gw) ?>">
    </div>
    <div class="form-group">
      <label>Gateway Port</label>
      <input type="number" id="ysfnet_gwport" value="<?= htmlspecialchars($ysfnet_gwport) ?>">
    </div>
    <div class="form-group">
      <label>Local Port</label>
      <input type="number" id="ysfnet_lport" value="<?= htmlspecialchars($ysfnet_lport) ?>">
    </div>
  </div>
</div>

<!-- NXDN Network -->
<div class="section-title violet">🌐 NXDN NETWORK · 21465</div>
<div class="card">
  <div class="enable-row">
    <span class="enable-label">Enable</span>
    <select id="nxdnnet_enable" class="enable-sel <?= $nxdnnet_enable==='1'?'is-on':'is-off' ?>"
      onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
      <option value="1" <?= en($nxdnnet_enable) ?>>1 — ON</option>
      <option value="0" <?= dis($nxdnnet_enable) ?>>0 — OFF</option>
    </select>
  </div>
  <div class="form-row3">
    <div class="form-group">
      <label>Gateway Address</label>
      <input type="text" id="nxdnnet_gw" value="<?= htmlspecialchars($nxdnnet_gw) ?>">
    </div>
    <div class="form-group">
      <label>Gateway Port</label>
      <input type="number" id="nxdnnet_gwport" value="<?= htmlspecialchars($nxdnnet_gwport) ?>">
    </div>
    <div class="form-group">
      <label>Local Port</label>
      <input type="number" id="nxdnnet_lport" value="<?= htmlspecialchars($nxdnnet_lport) ?>">
    </div>
  </div>
</div>

<!-- ══ GUARDAR ══════════════════════════════════════════ -->
<div style="margin-top:1rem;">
  <button class="btn-save" id="btnSave" onclick="saveAll()">
    💾 GUARDAR CONFIGURACIÓN Y REINICIAR SERVICIOS
  </button>
</div>

<!-- ══ LOG ══════════════════════════════════════════════ -->
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

<div id="toast">✔ OK</div>

<script>
let _logSvc = 'mmdvm_bridge';

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

async function toggleSvc(svc, el) {
  el.disabled = true;
  try {
    const fd = new FormData();
    fd.append('svc', svc);
    const r = await fetch('?action=toggle', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      setSvcUI(svc === 'analog_bridge' ? 'ab' : 'mb', d.active);
      showToast(svc + (d.active ? ' ACTIVADO' : ' DETENIDO'));
    }
  } catch(e) {}
  setTimeout(() => el.disabled = false, 800);
}

function setTG(tg, btn) {
  document.getElementById('ab_txTg').value = tg;
  document.getElementById('tgActivo').textContent = tg;
  document.querySelectorAll('.tg-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

async function saveAll() {
  const btn = document.getElementById('btnSave');
  btn.disabled = true; btn.textContent = '⏳ GUARDANDO...';

  const fd = new FormData();
  const ids = [
    'ab_gatewayDmrId','ab_repeaterID','ab_txTg','ab_txTs','ab_ambeMode',
    'mb_Callsign','mb_Id',
    'mode_d_star_enable','mode_dmr_enable','mode_system_fusion_enable','mode_nxdn_enable','mode_p25_enable',
    'dmrnet_enable','dmrnet_address','dmrnet_port','dmrnet_password','dmrnet_slot1','dmrnet_slot2',
    'dstarnet_enable','dstarnet_gw','dstarnet_gwport','dstarnet_lport',
    'ysfnet_enable','ysfnet_gw','ysfnet_gwport','ysfnet_lport',
    'nxdnnet_enable','nxdnnet_gw','nxdnnet_gwport','nxdnnet_lport'
  ];
  ids.forEach(id => { const el = document.getElementById(id); if(el) fd.append(id, el.value); });

  try {
    const r = await fetch('?action=save', { method:'POST', body:fd });
    const d = await r.json();
    showToast(d.msg, !d.ok);
  } catch(e) { showToast('Error de conexión', true); }

  btn.disabled = false;
  btn.textContent = '💾 GUARDAR CONFIGURACIÓN Y REINICIAR SERVICIOS';
}

async function loadLog() {
  try {
    const fd = new FormData(); fd.append('svc', _logSvc);
    const r = await fetch('?action=log', { method:'POST', body:fd });
    const txt = await r.text();
    const box = document.getElementById('termBox');
    box.textContent = txt; box.scrollTop = box.scrollHeight;
  } catch(e) {}
}

function switchLog(svc) {
  _logSvc = svc;
  document.getElementById('tab-mb').classList.toggle('active', svc==='mmdvm_bridge');
  document.getElementById('tab-ab').classList.toggle('active', svc==='analog_bridge');
  loadLog();
}

function showToast(msg, err=false) {
  const t = document.getElementById('toast');
  t.textContent = (err?'✕ ':'✔ ') + msg;
  t.className = err ? 'err' : '';
  t.style.display = 'block';
  setTimeout(() => t.style.display='none', 3000);
}

loadStatus(); loadLog();
setInterval(loadStatus, 3000);
setInterval(loadLog, 4000);
</script>
</body>
</html>
