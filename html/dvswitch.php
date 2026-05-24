<?php
// =============================================
// dvswitch.php  –  DVSwitch Control Panel
// EA3EIZ · Associació ADER
// =============================================

$AB_INI  = '/opt/Analog_Bridge/Analog_Bridge.ini';
$MB_INI  = '/opt/MMDVM_Bridge/MMDVM_Bridge.ini';

function iniGet(string $file, string $section, string $key): string {
    if (!file_exists($file)) return '';
    $data = parse_ini_file($file, true, INI_SCANNER_RAW);
    foreach ($data as $sec => $vals) {
        if (strtolower($sec) === strtolower($section)) {
            foreach ($vals as $k => $v) {
                if (strtolower($k) === strtolower($key)) return trim($v);
            }
        }
    }
    return '';
}

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
            $sistema = $_POST['sistema'] ?? 'dmr_bm';

            // ── Analog_Bridge básico ──
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
                    if ($v !== '' && !iniSet($AB_INI, $sec, $k, trim($v))) $errors[] = "AB:$k";
                }
            }

            // ── MMDVM_Bridge General ──
            iniSet($MB_INI, 'General', 'Callsign', trim($_POST['mb_Callsign'] ?? 'EA3EIZ'));
            iniSet($MB_INI, 'General', 'Id',       trim($_POST['mb_Id']       ?? '214317526'));

            // ── Desactivar TODOS los modos y redes primero ──
            $allModes = ['D-Star','DMR','System Fusion','NXDN','P25'];
            $allNets  = ['D-Star Network','DMR Network','System Fusion Network','NXDN Network','P25 Network'];
            foreach ($allModes as $m) iniSet($MB_INI, $m, 'Enable', '0');
            foreach ($allNets  as $n) iniSet($MB_INI, $n, 'Enable', '0');

            // ── Activar según sistema elegido ──
            switch ($sistema) {

                case 'dmr_bm':
                    iniSet($MB_INI, 'DMR', 'Enable', '1');
                    iniSet($MB_INI, 'DMR Network', 'Enable',   '1');
                    iniSet($MB_INI, 'DMR Network', 'Address',  trim($_POST['bm_address']  ?? 'master.spain-dmr.es'));
                    iniSet($MB_INI, 'DMR Network', 'Port',     trim($_POST['bm_port']     ?? '62031'));
                    iniSet($MB_INI, 'DMR Network', 'Password', trim($_POST['bm_password'] ?? ''));
                    iniSet($MB_INI, 'DMR Network', 'Slot1',    trim($_POST['bm_slot1']    ?? '0'));
                    iniSet($MB_INI, 'DMR Network', 'Slot2',    trim($_POST['bm_slot2']    ?? '1'));
                    // Borrar Options BM
                    iniSet($MB_INI, 'DMR Network', 'Options',  '');
                    iniSet($AB_INI, 'AMBE_AUDIO',  'ambeMode', 'DMR');
                    break;

                case 'dmr_plus':
                    iniSet($MB_INI, 'DMR', 'Enable', '1');
                    iniSet($MB_INI, 'DMR Network', 'Enable',   '1');
                    iniSet($MB_INI, 'DMR Network', 'Address',  trim($_POST['dmrplus_address']  ?? 'ipsc2-spain.xreflector.net'));
                    iniSet($MB_INI, 'DMR Network', 'Port',     trim($_POST['dmrplus_port']     ?? '62031'));
                    iniSet($MB_INI, 'DMR Network', 'Password', trim($_POST['dmrplus_password'] ?? 'passw0rd'));
                    iniSet($MB_INI, 'DMR Network', 'Slot1',    trim($_POST['dmrplus_slot1']    ?? '1'));
                    iniSet($MB_INI, 'DMR Network', 'Slot2',    trim($_POST['dmrplus_slot2']    ?? '1'));
                    $ipsc2_id = trim($_POST['dmrplus_essid'] ?? '4374');
                    iniSet($MB_INI, 'DMR Network', 'Options',  "TS2_FIXED_ID=$ipsc2_id");
                    iniSet($AB_INI, 'AMBE_AUDIO',  'ambeMode', 'DMR');
                    break;

                case 'ysf':
                    iniSet($MB_INI, 'System Fusion', 'Enable', '1');
                    iniSet($MB_INI, 'System Fusion Network', 'Enable',         '1');
                    iniSet($MB_INI, 'System Fusion Network', 'GatewayAddress', trim($_POST['ysf_gw']     ?? '127.0.0.1'));
                    iniSet($MB_INI, 'System Fusion Network', 'GatewayPort',    trim($_POST['ysf_gwport'] ?? '4210'));
                    iniSet($MB_INI, 'System Fusion Network', 'LocalPort',      trim($_POST['ysf_lport']  ?? '3200'));
                    iniSet($AB_INI, 'AMBE_AUDIO',            'ambeMode',       'YSFN');
                    break;

                case 'dstar':
                    iniSet($MB_INI, 'D-Star', 'Enable', '1');
                    iniSet($MB_INI, 'D-Star Network', 'Enable',         '1');
                    iniSet($MB_INI, 'D-Star Network', 'GatewayAddress', trim($_POST['dstar_gw']     ?? '127.0.0.1'));
                    iniSet($MB_INI, 'D-Star Network', 'GatewayPort',    trim($_POST['dstar_gwport'] ?? '20021'));
                    iniSet($MB_INI, 'D-Star Network', 'LocalPort',      trim($_POST['dstar_lport']  ?? '20020'));
                    iniSet($AB_INI, 'AMBE_AUDIO',     'ambeMode',       'DSTAR');
                    break;

                case 'nxdn':
                    iniSet($MB_INI, 'NXDN', 'Enable', '1');
                    iniSet($MB_INI, 'NXDN Network', 'Enable',         '1');
                    iniSet($MB_INI, 'NXDN Network', 'GatewayAddress', trim($_POST['nxdn_gw']     ?? '127.0.0.1'));
                    iniSet($MB_INI, 'NXDN Network', 'GatewayPort',    trim($_POST['nxdn_gwport'] ?? '14030'));
                    iniSet($MB_INI, 'NXDN Network', 'LocalPort',      trim($_POST['nxdn_lport']  ?? '14031'));
                    iniSet($AB_INI, 'AMBE_AUDIO',   'ambeMode',       'NXDN');
                    break;
            }

            // Reiniciar servicios
            shell_exec('sudo systemctl restart analog_bridge 2>/dev/null');
            sleep(1);
            shell_exec('sudo systemctl restart mmdvm_bridge 2>/dev/null');

            echo json_encode([
                'ok'  => empty($errors),
                'msg' => empty($errors) ? "Sistema [$sistema] activado y servicios reiniciados" : 'Errores: ' . implode(', ', $errors)
            ]);
            break;

        case 'log':
            $svc = in_array($_POST['svc'] ?? '', ['analog_bridge','mmdvm_bridge']) ? $_POST['svc'] : 'mmdvm_bridge';
            header('Content-Type: text/plain');
            echo shell_exec("sudo journalctl -u $svc -n 60 --no-pager --output=short 2>/dev/null") ?: '(sin log)';
            exit;

        default:
            echo json_encode(['ok'=>false,'msg'=>'Acción desconocida']);
    }
    exit;
}

// ── Leer valores actuales ────────────────────
$ab_gatewayDmrId = iniGet($AB_INI,'AMBE_AUDIO','gatewayDmrId') ?: '2143175';
$ab_repeaterID   = iniGet($AB_INI,'AMBE_AUDIO','repeaterID')   ?: '214317526';
$ab_txTg         = iniGet($AB_INI,'AMBE_AUDIO','txTg')         ?: '214';
$ab_txTs         = iniGet($AB_INI,'AMBE_AUDIO','txTs')         ?: '2';
$ab_ambeMode     = iniGet($AB_INI,'AMBE_AUDIO','ambeMode')     ?: 'DMR';

$mb_Callsign     = iniGet($MB_INI,'General','Callsign') ?: 'EA3EIZ';
$mb_Id           = iniGet($MB_INI,'General','Id')       ?: '214317526';

// BrandMeister
$bm_address      = iniGet($MB_INI,'DMR Network','Address')  ?: 'master.spain-dmr.es';
$bm_port         = iniGet($MB_INI,'DMR Network','Port')     ?: '62031';
$bm_password     = iniGet($MB_INI,'DMR Network','Password') ?: '';
$bm_slot1        = iniGet($MB_INI,'DMR Network','Slot1')    ?: '0';
$bm_slot2        = iniGet($MB_INI,'DMR Network','Slot2')    ?: '1';

// DMR+
$dmrplus_address  = 'ipsc2-spain.xreflector.net';
$dmrplus_port     = '62031';
$dmrplus_password = 'passw0rd';
$dmrplus_slot1    = '1';
$dmrplus_slot2    = '1';
$dmrplus_essid    = '4374';

// YSF
$ysf_gw     = iniGet($MB_INI,'System Fusion Network','GatewayAddress') ?: '127.0.0.1';
$ysf_gwport = iniGet($MB_INI,'System Fusion Network','GatewayPort')    ?: '4210';
$ysf_lport  = iniGet($MB_INI,'System Fusion Network','LocalPort')      ?: '3200';

// D-Star
$dstar_gw     = iniGet($MB_INI,'D-Star Network','GatewayAddress') ?: '127.0.0.1';
$dstar_gwport = iniGet($MB_INI,'D-Star Network','GatewayPort')    ?: '20021';
$dstar_lport  = iniGet($MB_INI,'D-Star Network','LocalPort')      ?: '20020';

// NXDN
$nxdn_gw     = iniGet($MB_INI,'NXDN Network','GatewayAddress') ?: '127.0.0.1';
$nxdn_gwport = iniGet($MB_INI,'NXDN Network','GatewayPort')    ?: '14030';
$nxdn_lport  = iniGet($MB_INI,'NXDN Network','LocalPort')      ?: '14031';

// Detectar sistema activo
$mode_dmr  = iniGet($MB_INI,'DMR','Enable');
$mode_ysf  = iniGet($MB_INI,'System Fusion','Enable');
$mode_dstar= iniGet($MB_INI,'D-Star','Enable');
$mode_nxdn = iniGet($MB_INI,'NXDN','Enable');
$mb_options= iniGet($MB_INI,'DMR Network','Options');

$sistema_activo = 'dmr_bm';
if ($mode_dmr === '1' && strpos($mb_options,'TS2_FIXED_ID') !== false) $sistema_activo = 'dmr_plus';
elseif ($mode_dmr === '1')   $sistema_activo = 'dmr_bm';
elseif ($mode_ysf === '1')   $sistema_activo = 'ysf';
elseif ($mode_dstar === '1') $sistema_activo = 'dstar';
elseif ($mode_nxdn === '1')  $sistema_activo = 'nxdn';

$tgs = ['214'=>'España','2141'=>'Cataluña','21465'=>'ADER','9'=>'Local 9','8'=>'Local 8','91'=>'Mundial','113'=>'Europa','2'=>'Echo BM'];
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
    --orange:  #f97316;
    --text:    #c8d8e8;
    --muted:   #4a6080;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'Share Tech Mono', monospace; min-height: 100vh; padding: 1rem; }

  /* Header */
  .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); padding-bottom: .75rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: .5rem; }
  .header h1 { font-family: 'Orbitron', sans-serif; font-size: 1rem; color: var(--cyan); text-shadow: 0 0 10px rgba(0,212,255,.4); letter-spacing: 2px; }
  .header-btns { display: flex; gap: .5rem; flex-wrap: wrap; }
  .btn-hdr { background: transparent; border: 1px solid var(--muted); color: var(--muted); font-family: 'Share Tech Mono', monospace; font-size: .78rem; padding: .4rem .9rem; cursor: pointer; text-decoration: none; transition: all .2s; }
  .btn-hdr:hover, .btn-hdr.accent { border-color: var(--cyan); color: var(--cyan); }

  /* Grid */
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .full  { grid-column: 1 / -1; }
  @media (max-width: 900px) { .grid2 { grid-template-columns: 1fr; } }

  /* Card */
  .card { background: var(--card); border: 1px solid var(--border); padding: 1.1rem; margin-bottom: 1rem; }
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: .9rem; padding-bottom: .5rem; border-bottom: 1px solid var(--border); }
  .card-title { font-family: 'Orbitron', sans-serif; font-size: .78rem; letter-spacing: 1px; }
  .cyan   { color: var(--cyan); }   .amber  { color: var(--amber); }
  .violet { color: var(--violet); } .green  { color: var(--green); }
  .blue   { color: var(--blue); }   .orange { color: var(--orange); }
  .red    { color: var(--red); }

  /* Switch servicio */
  .switch-wrap { display: flex; align-items: center; gap: .6rem; }
  .switch-label { font-size: .65rem; color: var(--muted); text-transform: uppercase; }
  .sw { position: relative; width: 52px; height: 26px; cursor: pointer; flex-shrink: 0; }
  .sw input { opacity: 0; width: 0; height: 0; position: absolute; }
  .sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid var(--red); transition: border-color .25s; }
  .sw-knob  { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: var(--red); border-radius: 1px; transition: transform .28s cubic-bezier(.4,0,.2,1), background .25s; }
  .sw input:checked ~ .sw-track { border-color: var(--green); }
  .sw input:checked ~ .sw-knob  { transform: translateX(26px); background: var(--green); }

  /* Badge */
  .badge { font-size: .62rem; padding: .2rem .5rem; text-transform: uppercase; letter-spacing: 1px; border: 1px solid; }
  .badge.on  { color: var(--green); border-color: var(--green); background: rgba(0,255,136,.08); }
  .badge.off { color: var(--red);   border-color: var(--red);   background: rgba(255,68,68,.08); }

  /* Selector sistema */
  .sistema-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: .5rem; margin-bottom: 1rem; }
  @media (max-width: 700px) { .sistema-grid { grid-template-columns: repeat(3,1fr); } }
  .sis-btn {
    border: 2px solid var(--border); background: #0d1525;
    color: var(--muted); font-family: 'Share Tech Mono', monospace;
    font-size: .72rem; padding: .6rem .3rem; cursor: pointer;
    text-align: center; transition: all .2s; line-height: 1.4;
  }
  .sis-btn:hover { border-color: var(--text); color: var(--text); }
  .sis-btn.active-dmr_bm   { border-color: var(--cyan);   color: var(--cyan);   background: rgba(0,212,255,.08); }
  .sis-btn.active-dmr_plus { border-color: var(--orange);  color: var(--orange);  background: rgba(249,115,22,.08); }
  .sis-btn.active-ysf      { border-color: var(--green);  color: var(--green);  background: rgba(0,255,136,.08); }
  .sis-btn.active-dstar    { border-color: var(--blue);   color: var(--blue);   background: rgba(59,130,246,.08); }
  .sis-btn.active-nxdn     { border-color: var(--violet); color: var(--violet); background: rgba(168,85,247,.08); }

  /* Paneles de config por sistema */
  .sys-panel { display: none; }
  .sys-panel.visible { display: block; }

  /* Form */
  .form-group { margin-bottom: .8rem; }
  .form-group label { display: block; font-size: .66rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: .25rem; }
  .form-group input, .form-group select { width: 100%; background: #0a0e1a; border: 1px solid var(--border); color: var(--text); font-family: 'Share Tech Mono', monospace; font-size: .82rem; padding: .42rem .55rem; outline: none; transition: border-color .2s; }
  .form-group input:focus, .form-group select:focus { border-color: var(--cyan); }
  .form-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }
  .form-row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .7rem; }

  /* Enable sel */
  .enable-sel { background: #0a0e1a; border: 1px solid var(--border); color: var(--text); font-family: 'Share Tech Mono', monospace; font-size: .8rem; padding: .3rem .5rem; }
  .enable-sel.is-on  { border-color: var(--green); color: var(--green); }
  .enable-sel.is-off { border-color: var(--red);   color: var(--red); }

  /* TG rápido */
  .tg-grid { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .6rem; }
  .tg-btn { background: #1a2535; border: 1px solid var(--border); color: var(--text); font-family: 'Share Tech Mono', monospace; font-size: .72rem; padding: .28rem .55rem; cursor: pointer; transition: all .2s; }
  .tg-btn:hover  { border-color: var(--amber); color: var(--amber); }
  .tg-btn.active { background: var(--amber); color: #000; border-color: var(--amber); font-weight: 700; }

  /* Botón guardar */
  .btn-save { width: 100%; padding: .65rem; background: var(--cyan); color: #000; border: none; font-family: 'Orbitron', sans-serif; font-size: .8rem; letter-spacing: 2px; cursor: pointer; transition: opacity .2s; margin-top: .4rem; }
  .btn-save:hover { opacity: .85; }
  .btn-save:disabled { opacity: .4; cursor: not-allowed; }

  /* Terminal */
  .term-wrap { border: 1px solid var(--border); margin-top: 1rem; }
  .term-header { display: flex; justify-content: space-between; align-items: center; padding: .5rem .8rem; background: #0d141d; border-bottom: 1px solid var(--border); font-size: .7rem; color: var(--cyan); }
  .term-tabs { display: flex; gap: .4rem; }
  .term-tab { background: transparent; border: 1px solid var(--border); color: var(--muted); font-family: 'Share Tech Mono', monospace; font-size: .66rem; padding: .2rem .55rem; cursor: pointer; }
  .term-tab.active { background: var(--cyan); color: #000; border-color: var(--cyan); }
  .term-box { background: #060c10; color: #7fa2bf; font-family: 'Share Tech Mono', monospace; font-size: .69rem; line-height: 1.5; padding: .8rem; height: 260px; overflow-y: auto; white-space: pre-wrap; }
  .term-box::-webkit-scrollbar { width: 4px; }
  .term-box::-webkit-scrollbar-thumb { background: var(--border); }

  /* Sistema activo indicator */
  .sis-activo {
    display: inline-block; font-size: .7rem; padding: .25rem .7rem;
    border: 1px solid; text-transform: uppercase; letter-spacing: 1px;
    margin-left: .5rem;
  }

  /* Sep */
  .sep { height: 1px; background: var(--border); margin: .7rem 0; }
  .section-title { font-family: 'Orbitron', sans-serif; font-size: .72rem; letter-spacing: 3px; padding: .5rem 0; margin: 1rem 0 .5rem; border-bottom: 1px solid var(--border); }

  /* Toast */
  #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: var(--card); border-left: 3px solid var(--green); color: var(--green); font-size: .85rem; padding: .6rem 1.2rem; display: none; z-index: 200; }
  #toast.err { border-color: var(--red); color: var(--red); }
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

<!-- ══ SERVICIOS ══════════════════════════════════════ -->
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
    <div class="form-row2">
      <div class="form-group">
        <label>Gateway DMR ID (7 dígitos)</label>
        <input type="text" id="ab_gatewayDmrId" value="<?= htmlspecialchars($ab_gatewayDmrId) ?>" maxlength="7">
      </div>
      <div class="form-group">
        <label>Repeater ID / ESSID</label>
        <input type="text" id="ab_repeaterID" value="<?= htmlspecialchars($ab_repeaterID) ?>" maxlength="9">
      </div>
    </div>
    <div class="form-row2">
      <div class="form-group">
        <label>Slot TX (txTs)</label>
        <select id="ab_txTs">
          <option value="1" <?= $ab_txTs=='1'?'selected':'' ?>>Slot 1</option>
          <option value="2" <?= $ab_txTs=='2'?'selected':'' ?>>Slot 2</option>
        </select>
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
    <div class="sep"></div>
    <div style="font-size:.68rem;color:var(--muted);margin-bottom:.4rem;text-transform:uppercase;letter-spacing:1px;">
      Sistema activo:
      <span id="sisActivoLabel" class="sis-activo cyan" style="border-color:var(--cyan)">
        <?= strtoupper($sistema_activo) ?>
      </span>
    </div>
  </div>
</div>

<!-- ══ TG RÁPIDO ════════════════════════════════════ -->
<div class="section-title violet">⚡ CAMBIO RÁPIDO DE TALKGROUP</div>
<div class="card">
  <div class="tg-grid" id="tgGrid">
    <?php foreach ($tgs as $tg => $lbl): ?>
      <button class="tg-btn <?= $ab_txTg==$tg?'active':'' ?>" onclick="setTG('<?= $tg ?>',this)">
        <?= $tg ?> · <?= $lbl ?>
      </button>
    <?php endforeach; ?>
  </div>
  <div class="form-row2">
    <div class="form-group">
      <label>TalkGroup manual</label>
      <input type="number" id="ab_txTg" value="<?= htmlspecialchars($ab_txTg) ?>">
    </div>
    <div style="display:flex;align-items:flex-end;padding-bottom:.8rem;">
      <span style="font-size:.7rem;color:var(--muted)">TG activo: <span id="tgActivo" style="color:var(--violet)"><?= htmlspecialchars($ab_txTg) ?></span></span>
    </div>
  </div>
</div>

<!-- ══ SELECTOR DE SISTEMA ══════════════════════════ -->
<div class="section-title cyan">🎛️ SELECCIONAR SISTEMA</div>
<div class="card">
  <div class="sistema-grid">
    <button class="sis-btn <?= $sistema_activo==='dmr_bm'?'active-dmr_bm':'' ?>"
      onclick="setSistema('dmr_bm',this)">
      🌐 DMR<br><small>BrandMeister</small>
    </button>
    <button class="sis-btn <?= $sistema_activo==='dmr_plus'?'active-dmr_plus':'' ?>"
      onclick="setSistema('dmr_plus',this)">
      🟠 DMR+<br><small>IPSC2</small>
    </button>
    <button class="sis-btn <?= $sistema_activo==='ysf'?'active-ysf':'' ?>"
      onclick="setSistema('ysf',this)">
      🟢 YSF<br><small>ES-ADER</small>
    </button>
    <button class="sis-btn <?= $sistema_activo==='dstar'?'active-dstar':'' ?>"
      onclick="setSistema('dstar',this)">
      🔵 D-STAR<br><small>XLX266</small>
    </button>
    <button class="sis-btn <?= $sistema_activo==='nxdn'?'active-nxdn':'' ?>"
      onclick="setSistema('nxdn',this)">
      🟣 NXDN<br><small>Ref 21465</small>
    </button>
  </div>
  <input type="hidden" id="sistema" value="<?= htmlspecialchars($sistema_activo) ?>">

  <!-- Panel DMR BrandMeister -->
  <div class="sys-panel <?= $sistema_activo==='dmr_bm'?'visible':'' ?>" id="panel-dmr_bm">
    <div class="section-title cyan">🌐 DMR · BrandMeister</div>
    <div class="form-row3">
      <div class="form-group">
        <label>Servidor BM</label>
        <input type="text" id="bm_address" value="<?= htmlspecialchars($bm_address) ?>">
      </div>
      <div class="form-group">
        <label>Puerto</label>
        <input type="number" id="bm_port" value="<?= htmlspecialchars($bm_port) ?>">
      </div>
      <div class="form-group">
        <label>Password BM</label>
        <input type="password" id="bm_password" value="<?= htmlspecialchars($bm_password) ?>">
      </div>
    </div>
    <div class="form-row2">
      <div class="form-group">
        <label>Slot 1</label>
        <select id="bm_slot1" class="enable-sel <?= $bm_slot1==='1'?'is-on':'is-off' ?>" onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
          <option value="0" <?= $bm_slot1==='0'?'selected':'' ?>>0 — OFF</option>
          <option value="1" <?= $bm_slot1==='1'?'selected':'' ?>>1 — ON</option>
        </select>
      </div>
      <div class="form-group">
        <label>Slot 2</label>
        <select id="bm_slot2" class="enable-sel <?= $bm_slot2==='1'?'is-on':'is-off' ?>" onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
          <option value="0" <?= $bm_slot2==='0'?'selected':'' ?>>0 — OFF</option>
          <option value="1" <?= $bm_slot2==='1'?'selected':'' ?>>1 — ON</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Panel DMR+ IPSC2 -->
  <div class="sys-panel <?= $sistema_activo==='dmr_plus'?'visible':'' ?>" id="panel-dmr_plus">
    <div class="section-title orange">🟠 DMR+ · IPSC2</div>
    <div class="form-row3">
      <div class="form-group">
        <label>Servidor IPSC2</label>
        <input type="text" id="dmrplus_address" value="<?= htmlspecialchars($dmrplus_address) ?>">
      </div>
      <div class="form-group">
        <label>Puerto</label>
        <input type="number" id="dmrplus_port" value="<?= htmlspecialchars($dmrplus_port) ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" id="dmrplus_password" value="<?= htmlspecialchars($dmrplus_password) ?>">
      </div>
    </div>
    <div class="form-row3">
      <div class="form-group">
        <label>ESSID / ID (Options)</label>
        <input type="text" id="dmrplus_essid" value="<?= htmlspecialchars($dmrplus_essid) ?>" placeholder="4374">
      </div>
      <div class="form-group">
        <label>Slot 1</label>
        <select id="dmrplus_slot1" class="enable-sel <?= $dmrplus_slot1==='1'?'is-on':'is-off' ?>" onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
          <option value="0">0 — OFF</option>
          <option value="1" selected>1 — ON</option>
        </select>
      </div>
      <div class="form-group">
        <label>Slot 2</label>
        <select id="dmrplus_slot2" class="enable-sel <?= $dmrplus_slot2==='1'?'is-on':'is-off' ?>" onchange="this.className='enable-sel '+(this.value==='1'?'is-on':'is-off')">
          <option value="0">0 — OFF</option>
          <option value="1" selected>1 — ON</option>
        </select>
      </div>
    </div>
    <div style="font-size:.7rem;color:var(--muted);margin-top:.3rem;">
      Se guardará como: <span style="color:var(--orange)">Options=TS2_FIXED_ID=<span id="dmrplus_essid_preview"><?= htmlspecialchars($dmrplus_essid) ?></span></span>
    </div>
  </div>

  <!-- Panel YSF -->
  <div class="sys-panel <?= $sistema_activo==='ysf'?'visible':'' ?>" id="panel-ysf">
    <div class="section-title green">🟢 SYSTEM FUSION · ES-ADER</div>
    <div class="form-row3">
      <div class="form-group">
        <label>Gateway Address</label>
        <input type="text" id="ysf_gw" value="<?= htmlspecialchars($ysf_gw) ?>">
      </div>
      <div class="form-group">
        <label>Gateway Port</label>
        <input type="number" id="ysf_gwport" value="<?= htmlspecialchars($ysf_gwport) ?>">
      </div>
      <div class="form-group">
        <label>Local Port</label>
        <input type="number" id="ysf_lport" value="<?= htmlspecialchars($ysf_lport) ?>">
      </div>
    </div>
  </div>

  <!-- Panel D-Star -->
  <div class="sys-panel <?= $sistema_activo==='dstar'?'visible':'' ?>" id="panel-dstar">
    <div class="section-title blue">🔵 D-STAR · XLX266</div>
    <div class="form-row3">
      <div class="form-group">
        <label>Gateway Address</label>
        <input type="text" id="dstar_gw" value="<?= htmlspecialchars($dstar_gw) ?>">
      </div>
      <div class="form-group">
        <label>Gateway Port</label>
        <input type="number" id="dstar_gwport" value="<?= htmlspecialchars($dstar_gwport) ?>">
      </div>
      <div class="form-group">
        <label>Local Port</label>
        <input type="number" id="dstar_lport" value="<?= htmlspecialchars($dstar_lport) ?>">
      </div>
    </div>
  </div>

  <!-- Panel NXDN -->
  <div class="sys-panel <?= $sistema_activo==='nxdn'?'visible':'' ?>" id="panel-nxdn">
    <div class="section-title violet">🟣 NXDN · Reflector 21465</div>
    <div class="form-row3">
      <div class="form-group">
        <label>Gateway Address</label>
        <input type="text" id="nxdn_gw" value="<?= htmlspecialchars($nxdn_gw) ?>">
      </div>
      <div class="form-group">
        <label>Gateway Port</label>
        <input type="number" id="nxdn_gwport" value="<?= htmlspecialchars($nxdn_gwport) ?>">
      </div>
      <div class="form-group">
        <label>Local Port</label>
        <input type="number" id="nxdn_lport" value="<?= htmlspecialchars($nxdn_lport) ?>">
      </div>
    </div>
  </div>

</div><!-- /card selector -->

<!-- ══ GUARDAR ══════════════════════════════════════ -->
<button class="btn-save" id="btnSave" onclick="saveAll()">
  💾 GUARDAR CONFIGURACIÓN Y REINICIAR SERVICIOS
</button>

<!-- ══ LOG ══════════════════════════════════════════ -->
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

// ── Status servicios ──────────────────────────
async function loadStatus() {
  try {
    const r = await fetch('?action=status&t=' + Date.now());
    const d = await r.json();
    setSvcUI('ab', d.ab === 'active');
    setSvcUI('mb', d.mb === 'active');
  } catch(e) {}
}
function setSvcUI(prefix, active) {
  document.getElementById(prefix+'-badge').textContent = active ? 'ACTIVO' : 'DETENIDO';
  document.getElementById(prefix+'-badge').className   = 'badge ' + (active ? 'on' : 'off');
  document.getElementById('sw-'+prefix).checked        = active;
}
async function toggleSvc(svc, el) {
  el.disabled = true;
  try {
    const fd = new FormData(); fd.append('svc', svc);
    const r = await fetch('?action=toggle', {method:'POST',body:fd});
    const d = await r.json();
    if (d.ok) { setSvcUI(svc==='analog_bridge'?'ab':'mb', d.active); showToast(svc+(d.active?' ACTIVADO':' DETENIDO')); }
  } catch(e) {}
  setTimeout(() => el.disabled = false, 800);
}

// ── Selector de sistema ───────────────────────
const sisColors = {
  dmr_bm: 'var(--cyan)', dmr_plus: 'var(--orange)',
  ysf: 'var(--green)', dstar: 'var(--blue)', nxdn: 'var(--violet)'
};
const sisLabels = {
  dmr_bm: 'DMR BRANDMEISTER', dmr_plus: 'DMR+ IPSC2',
  ysf: 'YSF / C4FM', dstar: 'D-STAR', nxdn: 'NXDN'
};

function setSistema(sis, btn) {
  // Ocultar todos los paneles
  document.querySelectorAll('.sys-panel').forEach(p => p.classList.remove('visible'));
  // Desactivar todos los botones
  document.querySelectorAll('.sis-btn').forEach(b => b.className = 'sis-btn');
  // Activar el elegido
  btn.classList.add('active-' + sis);
  document.getElementById('panel-' + sis).classList.add('visible');
  document.getElementById('sistema').value = sis;
  // Actualizar label
  const lbl = document.getElementById('sisActivoLabel');
  lbl.textContent = sisLabels[sis];
  lbl.style.borderColor = sisColors[sis];
  lbl.style.color = sisColors[sis];
}

// ── TalkGroup rápido ──────────────────────────
function setTG(tg, btn) {
  document.getElementById('ab_txTg').value = tg;
  document.getElementById('tgActivo').textContent = tg;
  document.querySelectorAll('.tg-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}
document.getElementById('ab_txTg').addEventListener('input', function() {
  document.getElementById('tgActivo').textContent = this.value;
  document.querySelectorAll('.tg-btn').forEach(b => b.classList.remove('active'));
});

// Preview ESSID DMR+
document.addEventListener('input', e => {
  if (e.target.id === 'dmrplus_essid') {
    document.getElementById('dmrplus_essid_preview').textContent = e.target.value;
  }
});

// ── Guardar ───────────────────────────────────
async function saveAll() {
  const btn = document.getElementById('btnSave');
  btn.disabled = true; btn.textContent = '⏳ GUARDANDO...';

  const fd = new FormData();
  const ids = [
    'ab_gatewayDmrId','ab_repeaterID','ab_txTg','ab_txTs','ab_ambeMode',
    'mb_Callsign','mb_Id','sistema',
    'bm_address','bm_port','bm_password','bm_slot1','bm_slot2',
    'dmrplus_address','dmrplus_port','dmrplus_password','dmrplus_essid','dmrplus_slot1','dmrplus_slot2',
    'ysf_gw','ysf_gwport','ysf_lport',
    'dstar_gw','dstar_gwport','dstar_lport',
    'nxdn_gw','nxdn_gwport','nxdn_lport'
  ];
  ids.forEach(id => { const el = document.getElementById(id); if(el) fd.append(id, el.value); });

  try {
    const r = await fetch('?action=save', {method:'POST',body:fd});
    const d = await r.json();
    showToast(d.msg, !d.ok);
  } catch(e) { showToast('Error de conexión', true); }

  btn.disabled = false;
  btn.textContent = '💾 GUARDAR CONFIGURACIÓN Y REINICIAR SERVICIOS';
}

// ── Log ───────────────────────────────────────
async function loadLog() {
  try {
    const fd = new FormData(); fd.append('svc', _logSvc);
    const r = await fetch('?action=log', {method:'POST',body:fd});
    const box = document.getElementById('termBox');
    box.textContent = await r.text();
    box.scrollTop = box.scrollHeight;
  } catch(e) {}
}
function switchLog(svc) {
  _logSvc = svc;
  document.getElementById('tab-mb').classList.toggle('active', svc==='mmdvm_bridge');
  document.getElementById('tab-ab').classList.toggle('active', svc==='analog_bridge');
  loadLog();
}

// ── Toast ─────────────────────────────────────
function showToast(msg, err=false) {
  const t = document.getElementById('toast');
  t.textContent = (err?'✕ ':'✔ ') + msg;
  t.className = err ? 'err' : '';
  t.style.display = 'block';
  setTimeout(() => t.style.display='none', 3500);
}

loadStatus(); loadLog();
setInterval(loadStatus, 3000);
setInterval(loadLog, 4000);
</script>
</body>
</html>
