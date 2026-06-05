<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
$action = $_GET['action'] ?? '';

function saveState($key, $value) {
    $file = '/var/lib/mmdvm-state';
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $found = false;
    foreach ($lines as &$line) {
        if (strpos($line, $key . '=') === 0) { $line = $key . '=' . $value; $found = true; }
    }
    unset($line);
    if (!$found) $lines[] = $key . '=' . $value;
    file_put_contents($file, implode("\n", $lines) . "\n");
}

function parseMMDVMIni($path) {
    $result = []; if (!file_exists($path)) return $result;
    $section = '';
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') continue;
        if (preg_match('/^\[(.+)\]$/', $line, $m)) { $section = trim($m[1]); continue; }
        if (preg_match('/^([^=]+)=(.*)$/', $line, $m)) { $result[$section][trim($m[1])] = trim($m[2]); }
    }
    return $result;
}

function lookupCall($callsign) {
    $datFiles=['/home/pi/MMDVMHost/DMRIds.dat','/etc/DMRIds.dat','/usr/local/etc/DMRIds.dat'];
    $cs=strtoupper(trim($callsign));
    foreach ($datFiles as $f) {
        if(!file_exists($f))continue;
        $cmd="awk -F'\t' '{if (toupper(\$2)==\"" . $cs . "\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' ".escapeshellarg($f)." 2>/dev/null";
        $row=trim(shell_exec($cmd));
        if($row!==''){$parts=explode("\t",$row);return['dmrid'=>trim($parts[0]??''),'name'=>trim($parts[2]??'')];}
    }
    return ['dmrid'=>'','name'=>''];
}

// ── Acciones DMR2NXDN ──────────────────────────────────────────────────────────
if ($action === 'dmr2nxdn-status') {
    $s1 = trim(shell_exec('systemctl is-active mmdvmdmr2nxdn 2>/dev/null'));
    $s2 = trim(shell_exec('systemctl is-active nxdngateway 2>/dev/null'));
    $s3 = trim(shell_exec('systemctl is-active dmr2nxdn 2>/dev/null'));
    $active = ($s1 === 'active' || $s2 === 'active' || $s3 === 'active') ? 'active' : 'inactive';
    header('Content-Type: application/json');
    echo json_encode(['dmr2nxdn' => $active, 's1' => $s1, 's2' => $s2, 's3' => $s3]);
    exit;
}
if ($action === 'dmr2nxdn-start') {
    saveState('dmr2nxdn','on');
    shell_exec('sudo systemctl enable mmdvmdmr2nxdn 2>/dev/null');
    shell_exec('sudo systemctl start mmdvmdmr2nxdn 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl enable nxdngateway 2>/dev/null');
    shell_exec('sudo systemctl start nxdngateway 2>/dev/null');
    sleep(8);
    shell_exec('sudo systemctl enable dmr2nxdn 2>/dev/null');
    shell_exec('sudo systemctl start dmr2nxdn 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'dmr2nxdn-stop') {
    saveState('dmr2nxdn','off');
    shell_exec('sudo systemctl stop dmr2nxdn 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop nxdngateway 2>/dev/null');
    shell_exec('sudo systemctl stop mmdvmdmr2nxdn 2>/dev/null');
    shell_exec('sudo systemctl disable dmr2nxdn nxdngateway mmdvmdmr2nxdn 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'dmr2nxdn-logs') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u dmr2nxdn -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) {
        $logFiles = glob('/home/pi/MMDVM_CM/DMR2NXDN/DMR2NXDN-*.log');
        if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n {$lines} " . escapeshellarg($latest) . " 2>/dev/null"); }
    }
    header('Content-Type: application/json');
    echo json_encode(['dmr2nxdn' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'nxdngateway-logs') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u nxdngateway -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['nxdngateway' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'mmdvmdmr2nxdn-logs') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u mmdvmdmr2nxdn -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['mmdvmdmr2nxdn' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'dmr2nxdn-transmission') {
    $stateFile = '/tmp/dmr2nxdn_tx_state.json';
    $lhFile    = '/tmp/dmr2nxdn_lastheard.json';
    $logFiles  = glob('/home/pi/MMDVM_CM/DMR2NXDN/DMR2NXDN-*.log');
    $log = '';
    if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n 200 " . escapeshellarg($latest) . " 2>/dev/null") ?? ''; }
    if (empty(trim($log))) $log = shell_exec("sudo journalctl -u dmr2nxdn -n 200 --no-pager --output=short 2>/dev/null") ?? '';
    $lines = array_reverse(explode("\n", $log));
    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','source'=>''];
    if (file_exists($stateFile)) { $saved = json_decode(file_get_contents($stateFile), true); if (is_array($saved)) $state = $saved; }
    foreach ($lines as $line) {
        if (preg_match('/DMR received end of voice|NXDN received end of voice|end of voice transmission|lost|watchdog|timeout/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/DMR header received from\s+([A-Z0-9]+).*TG\s+(\d+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>$m[2],'source'=>'DMR'];
            file_put_contents($stateFile,json_encode($state));break;
        }
        if (preg_match('/DMR header received from\s+([A-Z0-9]+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>'','source'=>'DMR'];
            file_put_contents($stateFile,json_encode($state));break;
        }
        if (preg_match('/NXDN.*from\s+([A-Z0-9]+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>'','source'=>'NXDN'];
            file_put_contents($stateFile,json_encode($state));break;
        }
    }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs=''; $time=''; $tgr=''; $src='DMR';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*DMR header received from\s+([A-Z0-9]+).*TG\s+(\d+)/i', $line, $m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$tgr=$m[3];$src='DMR'; }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*DMR header received from\s+([A-Z0-9]+)/i', $line, $m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$src='DMR'; }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*from\s+([A-Z0-9]+)/i', $line, $m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$src='NXDN'; }
        if ($cs && !in_array($cs, $seen)) { $inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$tgr,'source'=>$src,'time'=>$time];$seen[]=$cs;if(count($lastHeard)>=5)break; }
    }
    if (!empty($lastHeard)) file_put_contents($lhFile, json_encode($lastHeard));
    elseif (file_exists($lhFile)) $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json');
    echo json_encode($state);
    exit;
}

// ── Config MMDVMDMR2NXDN ──────────────────────────────────────────────────────
if ($action === 'mmdvmdmr2nxdn-config-read') {
    $path = '/home/pi/MMDVMHost/MMDVMDMR2NXDN.ini';
    $ini  = parseMMDVMIni($path);
    header('Content-Type: application/json');
    echo json_encode([
        'ok'          => file_exists($path),
        'Callsign'    => $ini['General']['Callsign']              ?? '',
        'Id'          => $ini['General']['Id']                    ?? '',
        'Timeout'     => $ini['General']['Timeout']               ?? '180',
        'Duplex'      => $ini['General']['Duplex']                ?? '0',
        'RXFrequency' => $ini['Info']['RXFrequency']              ?? '0',
        'TXFrequency' => $ini['Info']['TXFrequency']              ?? '0',
        'NxdnEnable'  => $ini['NXDN Network']['Enable']           ?? '1',
        'NxdnLocalAddr'=> $ini['NXDN Network']['LocalAddress']    ?? '127.0.0.1',
        'NxdnLocalPort'=> $ini['NXDN Network']['LocalPort']       ?? '14021',
        'NxdnGwAddr'  => $ini['NXDN Network']['GatewayAddress']   ?? '127.0.0.1',
        'NxdnGwPort'  => $ini['NXDN Network']['GatewayPort']      ?? '14020',
        'DmrEnable'   => $ini['DMR Network']['Enable']            ?? '1',
        'DmrLocalAddr'=> $ini['DMR Network']['LocalAddress']      ?? '127.0.0.1',
        'DmrLocalPort'=> $ini['DMR Network']['LocalPort']         ?? '62032',
        'DmrGwAddr'   => $ini['DMR Network']['GatewayAddress']    ?? '127.0.0.1',
        'DmrGwPort'   => $ini['DMR Network']['GatewayPort']       ?? '62031',
        'UARTPort'    => $ini['Modem']['UARTPort']                ?? '',
    ]);
    exit;
}
if ($action === 'mmdvmdmr2nxdn-config-save') {
    $path = '/home/pi/MMDVMHost/MMDVMDMR2NXDN.ini';
    if (!file_exists($path)) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Fichero no encontrado']); exit; }
    $content = file_get_contents($path);
    $map = [
        'General'      => ['Callsign','Id','Timeout','Duplex'],
        'Info'         => ['RXFrequency','TXFrequency'],
        'NXDN Network' => ['Enable'=>'NxdnEnable','LocalAddress'=>'NxdnLocalAddr','LocalPort'=>'NxdnLocalPort','GatewayAddress'=>'NxdnGwAddr','GatewayPort'=>'NxdnGwPort'],
        'DMR Network'  => ['Enable'=>'DmrEnable','LocalAddress'=>'DmrLocalAddr','LocalPort'=>'DmrLocalPort','GatewayAddress'=>'DmrGwAddr','GatewayPort'=>'DmrGwPort'],
        'Modem'        => ['UARTPort'=>'UARTPort'],
    ];
    $currentSection='';$lines=explode("\n",$content);
    foreach ($lines as &$line) {
        $trimmed=trim($line);
        if (preg_match('/^\[(.+)\]$/',$trimmed,$m)){$currentSection=trim($m[1]);continue;}
        if (preg_match('/^([^=;#]+)=(.*)$/',$trimmed,$m)) {
            $key=trim($m[1]);if(!isset($map[$currentSection]))continue;
            $sectionMap=$map[$currentSection];
            $postKey=is_array($sectionMap)&&isset($sectionMap[$key])?$sectionMap[$key]:(in_array($key,$sectionMap)?$key:null);
            if($postKey&&isset($_POST[$postKey]))$line=$key.'='.trim($_POST[$postKey]);
        }
    }
    unset($line);
    $newContent=implode("\n",$lines);
    $result=@file_put_contents($path,$newContent);
    if($result===false){$tmp=tempnam('/tmp','mmdvm_cfg_');file_put_contents($tmp,$newContent);shell_exec("sudo /bin/cp ".escapeshellarg($tmp)." ".escapeshellarg($path)." 2>&1");@unlink($tmp);}
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'msg'=>'Guardado correctamente']);
    exit;
}

// ── Info display ──────────────────────────────────────────────────────────────
if ($action === 'dmr2nxdn-info') {
    $ini = parseMMDVMIni('/home/pi/MMDVM_CM/DMR2NXDN/DMR2NXDN.ini');
    $cs  = parseMMDVMIni('/home/pi/MMDVMHost/MMDVMDMR2NXDN.ini');
    header('Content-Type: application/json');
    echo json_encode([
        'callsign' => strtoupper(trim($cs['General']['Callsign'] ?? 'EA3EIZ')),
        'dmrId'    => $ini['DMR Network']['Id']                   ?? '—',
        'gw'       => $ini['NXDN Network']['GatewayAddress']      ?? '—',
        'defTG'    => $ini['DMR Network']['DefaultDstTG']         ?? '—',
        'nxdnGw'   => ($ini['NXDN Network']['GatewayAddress'] ?? '127.0.0.1').':'.($ini['NXDN Network']['GatewayPort'] ?? '14020'),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DMR2NXDN · Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#032354;--surface:#111720;--border:#1e2d3d;--green:#00ff9f;--red:#ff4560;--amber:#ffb300;--cyan:#00d4ff;--violet:#b57aff;--text:#a8b9cc;--text-dim:#4a5568;--d2n:#b57aff;--d2n2:#d4a0ff;--font-mono:'Share Tech Mono',monospace;--font-ui:'Rajdhani',sans-serif;--font-orb:'Orbitron',monospace;}
*{box-sizing:border-box;}
body{background:#0d001a;color:var(--text);font-family:var(--font-ui);font-size:1rem;min-height:100vh;padding:0;margin:0;}
.ctrl-header{border-bottom:2px solid var(--d2n);background:#000;}
.ctrl-header-inner{max-width:1200px;width:100%;margin:0 auto;padding:1rem 2rem;display:flex;align-items:center;gap:.8rem;box-sizing:border-box;}
.ctrl-body{padding:2rem;max-width:1200px;margin:0 auto;}
/* Switch */
.sw{position:relative;width:56px;height:28px;flex-shrink:0;cursor:pointer;}
.sw input{opacity:0;width:0;height:0;position:absolute;}
.sw-track{position:absolute;inset:0;border-radius:2px;background:#1a2535;border:2px solid var(--red);transition:background .3s,border-color .3s;}
.sw-knob{position:absolute;top:3px;left:3px;width:20px;height:20px;background:var(--red);box-shadow:0 1px 4px rgba(0,0,0,.5);transition:transform .3s cubic-bezier(.4,0,.2,1),background .3s,box-shadow .3s;}
.sw-busy-dot{display:none;position:absolute;top:50%;right:-18px;transform:translateY(-50%);width:8px;height:8px;border-radius:50%;border:2px solid var(--amber);border-top-color:transparent;animation:spin .7s linear infinite;}
.sw.busy .sw-busy-dot{display:block;}
@keyframes spin{to{transform:translateY(-50%) rotate(360deg);}}
.toggle-status{font-family:var(--font-mono);font-size:.72rem;letter-spacing:.1em;min-width:3rem;text-align:right;transition:color .3s;}
.toggle-status.on{color:var(--green);}
/* Nextion */
.nextion-dmr2nxdn{background:#0a0612;border:2px solid #b57aff44;border-radius:6px;box-shadow:0 0 0 1px #1a0030,inset 0 0 40px rgba(181,122,255,.04),0 0 30px rgba(181,122,255,.12);position:relative;overflow:hidden;height:240px;display:flex;align-items:center;justify-content:center;}
.nextion-dmr2nxdn::before,.nextion-dmr2nxdn::after{content:'◈';position:absolute;font-size:.6rem;color:#b57aff33;}
.nextion-dmr2nxdn::before{top:.5rem;left:.7rem;}
.nextion-dmr2nxdn::after{bottom:.5rem;right:.7rem;}
.nx-topbar.d2n-bar{background:#100820;border-bottom:1px solid #b57aff33;color:#5a2080;position:absolute;top:0;left:0;right:0;height:30px;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-family:var(--font-mono);font-size:.65rem;letter-spacing:.1em;}
.nx-topbar.d2n-bar .nx-mode{color:var(--d2n);opacity:.8;}
.nx-botbar.d2n-bar{background:#0a0612;border-top:1px solid #b57aff33;color:#5a2080;position:absolute;bottom:0;left:0;right:0;height:28px;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-family:var(--font-mono);font-size:.65rem;letter-spacing:.08em;}
.nx-infobar-d2n{position:absolute;top:30px;left:0;right:0;height:26px;background:rgba(0,0,0,.4);border-bottom:1px solid #1a0030;display:flex;align-items:center;justify-content:space-around;padding:0 8rem;gap:1rem;z-index:2;}
.nx-info-item{display:flex;align-items:center;gap:.4rem;}
.nx-info-lbl{font-family:var(--font-mono);font-size:10px;color:#999;text-transform:uppercase;}
.nx-info-val{font-family:var(--font-mono);font-size:10px;color:#ff0;font-weight:bold;}
.nx-vu{position:absolute;left:1rem;top:56px;bottom:32px;width:6px;display:flex;flex-direction:column-reverse;gap:2px;}
.nx-vu.right{left:auto;right:1rem;}
.nx-vu-bar{height:5px;border-radius:1px;background:#0d0820;transition:background .08s;}
.nx-vu-bar.lit-g{background:var(--green);box-shadow:0 0 4px var(--green);}
.nx-vu-bar.lit-a{background:var(--amber);box-shadow:0 0 4px var(--amber);}
.nx-vu-bar.lit-r{background:var(--red);box-shadow:0 0 4px var(--red);}
.nx-center{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.15rem;z-index:1;}
.nx-clock{font-family:var(--font-orb);font-size:4rem;font-weight:700;color:#fff;letter-spacing:.06em;line-height:1;}
.nx-date{font-family:var(--font-mono);font-size:.7rem;color:#ff0;letter-spacing:.12em;text-transform:uppercase;margin-top:.2rem;}
.nx-callsign{font-family:var(--font-orb);font-size:3.4rem;font-weight:900;letter-spacing:.04em;line-height:1;color:var(--d2n);text-shadow:0 0 20px rgba(181,122,255,.6);}
.nx-name{font-family:var(--font-ui);font-weight:500;font-size:1.2rem;color:var(--d2n2);letter-spacing:.18em;text-transform:uppercase;opacity:.9;margin-top:.15rem;}
.nx-txbar{position:absolute;bottom:28px;left:0;right:0;height:3px;}
.nx-txbar.active-d2n{background:linear-gradient(90deg,transparent,var(--d2n),transparent);background-size:200% 100%;animation:scan 1.4s linear infinite;}
@keyframes scan{from{background-position:200% 0}to{background-position:-200% 0}}
.nx-source{padding:.1rem .45rem;border-radius:2px;font-size:.6rem;letter-spacing:.1em;}
.nx-source.rf{background:rgba(181,122,255,.15);color:var(--d2n);border:1px solid rgba(181,122,255,.3);}
.nx-source.net{background:rgba(0,212,255,.15);color:var(--cyan);border:1px solid rgba(0,212,255,.3);}
/* Last heard */
.lh-panel-d2n{background:var(--surface);border:3px solid #b57aff33;border-radius:6px;display:flex;flex-direction:column;}
.lh-header-d2n{background:#100820;border-bottom:1px solid #b57aff33;padding:.4rem 1rem;display:grid;grid-template-columns:1.2fr 1.8fr .8fr 1fr .6fr;gap:.3rem;font-family:var(--font-mono);font-size:.6rem;color:#5a2080;letter-spacing:.1em;text-transform:uppercase;}
.lh-row-d2n{display:grid;grid-template-columns:1.2fr 1.8fr .8fr 1fr .6fr;gap:.3rem;padding:.45rem 1rem;border-bottom:1px solid rgba(181,122,255,.1);align-items:center;}
.lh-call-d2n{font-family:var(--font-mono);font-size:.82rem;color:var(--d2n);letter-spacing:.05em;font-weight:bold;}
.lh-tx-dot-d2n{width:6px;height:6px;border-radius:50%;background:var(--d2n);box-shadow:0 0 6px var(--d2n);animation:pulse 1s infinite;flex-shrink:0;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
/* Logs */
.log-panel{background:var(--surface);border:1px solid var(--border);border-radius:4px;overflow:hidden;margin-bottom:1rem;}
.log-panel-header{display:flex;align-items:center;justify-content:space-between;padding:.5rem 1rem;border-bottom:1px solid var(--border);background:rgba(0,0,0,.3);}
.svc-name{font-family:var(--font-mono);font-size:.8rem;letter-spacing:.1em;color:var(--d2n);text-transform:uppercase;}
.btn-clear{font-family:var(--font-mono);font-size:.7rem;color:var(--text-dim);background:none;border:none;cursor:pointer;}
.log-output{font-family:var(--font-mono);font-size:.72rem;line-height:1.55;color:#7a9ab5;padding:.8rem 1rem;height:190px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;}
.ln-info{color:#7a9ab5;}.ln-warn{color:var(--amber);}.ln-err{color:var(--red);}.ln-ok{color:#00cc7a;}
/* Status bar */
.status-bar{display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center;background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:.8rem 1.2rem;}
.status-item{display:flex;align-items:center;gap:.5rem;font-family:var(--font-mono);font-size:12px;text-transform:uppercase;letter-spacing:.08em;}
.dot{width:10px;height:10px;border-radius:50%;background:var(--text-dim);transition:background .4s,box-shadow .4s;}
.dot.active{background:var(--green);box-shadow:0 0 8px var(--green);animation:pulse 2s infinite;}
.dot.error{background:var(--red);box-shadow:0 0 8px var(--red);}
/* Cards */
.card{background:var(--surface);border:1px solid #b57aff33;border-radius:8px;padding:1.2rem 1.6rem;margin-bottom:1.2rem;}
.ini-btn{font-family:var(--font-mono);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;padding:.3rem .7rem;border-radius:3px;border:1px solid var(--border);background:transparent;cursor:pointer;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:.3rem;color:var(--d2n);border-color:rgba(181,122,255,.3);}
.ini-btn:hover{border-color:var(--d2n);background:rgba(181,122,255,.08);}
.display-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.5rem;}
@media(max-width:900px){.display-grid{grid-template-columns:1fr;}}
.flag-emoji{font-family:'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif;font-size:1.6rem;display:inline-block;vertical-align:middle;margin-right:4px;line-height:1;}
.flag-emoji-img{height:20px;width:auto;vertical-align:middle;margin-right:4px;border-radius:2px;}
.nx-callsign .flag-emoji{font-size:3.2rem;}
.nx-callsign .flag-emoji-img{height:42px;}
</style>
</head>
<body>
<header class="ctrl-header">
<div class="ctrl-header-inner">
  <a href="mmdvm.php" style="background:#1a1535;color:var(--d2n);border:1px solid rgba(181,122,255,.3);font-family:var(--font-mono);font-size:.75rem;padding:.35rem .9rem;border-radius:4px;text-decoration:none;">← Panel PHPPLUS</a>
  <span style="font-family:var(--font-orb);color:var(--d2n);font-size:1.2rem;letter-spacing:.1em;">DMR2NXDN · CROSS-MODE BRIDGE</span>
  <div style="margin-left:auto;display:flex;align-items:center;gap:.8rem;">
    <label class="sw" id="swDMR2NXDN">
      <input type="checkbox" id="chkDMR2NXDN" onchange="toggleDMR2NXDN(this)">
      <span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span>
    </label>
    <span class="toggle-status" id="dmr2nxdnToggleStatus">OFF</span>
  </div>
</div>
</header>

<div class="ctrl-body">

  <!-- Status bar -->
  <div class="status-bar">
    <div class="status-item"><div class="dot" id="dot-dmr2nxdn-mmd"></div><span style="color:var(--d2n)">MMDVMDmr2nxdn</span></div>
    <div class="status-item"><div class="dot" id="dot-dmr2nxdn-nxdn"></div><span style="color:var(--d2n)">NXDNGateway</span></div>
    <div class="status-item"><div class="dot" id="dot-dmr2nxdn"></div><span style="color:var(--d2n)">DMR2NXDN</span></div>
  </div>

  <!-- Botones config -->
  <div class="card">
    <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--d2n);letter-spacing:.12em;text-transform:uppercase;margin-bottom:.8rem;">▸ Configuración</div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <button onclick="openMmdvmDmr2nxdnCfg()" class="ini-btn">⚙ MMDVMDMR2NXDN CONFIG</button>
      <button onclick="feditOpen('/home/pi/MMDVM_CM/DMR2NXDN/DMR2NXDN.ini')" class="ini-btn">📄 Editar DMR2NXDN.ini</button>
      <button onclick="feditOpen('/home/pi/MMDVMHost/MMDVMDMR2NXDN.ini')" class="ini-btn">📄 Editar MMDVMDMR2NXDN.ini</button>
      <button onclick="feditOpen('/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini')" class="ini-btn">📄 Editar NXDNGateway.ini</button>
    </div>
  </div>

  <!-- Display + Last heard -->
  <div class="display-grid">
    <div>
      <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--d2n);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.5rem;">▸ DMR2NXDN Display</div>
      <div class="nextion-dmr2nxdn">
        <div class="nx-topbar d2n-bar"><span class="nx-mode">DMR2NXDN · BRIDGE</span><span style="color:var(--d2n);opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="dmr2nxdnTGLabel">—</span></div>
        <div class="nx-infobar-d2n">
          <span class="nx-info-item"><span class="nx-info-lbl">DMR ID</span><span class="nx-info-val" style="color:var(--d2n)" id="dmr2nxdnDmrId">—</span></span>
          <span class="nx-info-item"><span class="nx-info-lbl">GW</span><span class="nx-info-val" style="color:var(--d2n2)" id="dmr2nxdnGw">—</span></span>
          <span class="nx-info-item"><span class="nx-info-lbl">TG Defecto</span><span class="nx-info-val" style="color:var(--d2n)" id="dmr2nxdnDefTG">—</span></span>
        </div>
        <div class="nx-vu" id="dmr2nxdnVuLeft"></div><div class="nx-vu right" id="dmr2nxdnVuRight"></div>
        <div class="nx-center" id="dmr2nxdnNxCenter"><div class="nx-clock" id="dmr2nxdnNxClock" style="color:var(--d2n);">00:00:00</div><div class="nx-date" id="dmr2nxdnNxDate" style="color:#5a2080;">—</div></div>
        <div class="nx-txbar" id="dmr2nxdnTxBar"></div>
        <div class="nx-botbar d2n-bar">
          <span style="color:#5a2080;font-family:var(--font-mono);font-size:.65rem;">DMR2NXDN · CROSS-MODE</span>
          <span style="color:var(--d2n);font-family:var(--font-mono);font-size:.65rem;" id="dmr2nxdnNxdnGw">NXDN: —</span>
          <span class="nx-source" id="dmr2nxdnSource"></span>
        </div>
      </div>
    </div>
    <div>
      <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--d2n);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.5rem;">▸ Últimos escuchados</div>
      <div class="lh-panel-d2n">
        <div class="lh-header-d2n"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
        <div id="dmr2nxdnLhBody" style="flex:1;overflow-y:auto;"><div style="padding:1.5rem 1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin actividad</div></div>
      </div>
    </div>
  </div>

  <!-- Logs -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
    <div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ DMR2NXDN</span><button class="btn-clear" onclick="clearLog('logDmr2nxdn')">limpiar</button></div><div class="log-output" id="logDmr2nxdn">Cargando…</div></div>
    <div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ NXDN Gateway</span><button class="btn-clear" onclick="clearLog('logNxdnGateway')">limpiar</button></div><div class="log-output" id="logNxdnGateway">Cargando…</div></div>
    <div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ MMDVMDmr2nxdn</span><button class="btn-clear" onclick="clearLog('logMmdvmDmr2nxdn')">limpiar</button></div><div class="log-output" id="logMmdvmDmr2nxdn">Cargando…</div></div>
  </div>

</div><!-- /ctrl-body -->

<!-- Modal Config MMDVMDMR2NXDN -->
<div id="mmdvmD2nCfgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9700;align-items:center;justify-content:center;" onclick="if(event.target===this)closeMmdvmDmr2nxdnCfg()">
<div style="background:var(--surface);border:1px solid #b57aff44;border-radius:8px;padding:1.5rem;width:700px;max-width:96vw;max-height:90vh;overflow-y:auto;display:flex;flex-direction:column;gap:.8rem;">
  <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--d2n);letter-spacing:.12em;text-transform:uppercase;border-bottom:1px solid #b57aff33;padding-bottom:.6rem;">⚙ MMDVMDMR2NXDN.ini</div>
  <?php
  function d2nField($id, $label) {
      return '<div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.65rem;color:#4a5568;display:block;margin-bottom:.25rem;">'
           . htmlspecialchars($label)
           . '</label><input id="d2ncfg_' . $id . '" style="width:100%;background:#060c10;border:1px solid #b57aff33;border-radius:3px;color:#b57aff;font-family:\'Share Tech Mono\',monospace;font-size:.82rem;padding:.35rem .6rem;outline:none;" onfocus="this.style.borderColor=\'#b57aff\'" onblur="this.style.borderColor=\'#b57aff33\'"></div>';
  }
  function d2nSec($label) {
      return '<div style="font-family:\'Share Tech Mono\',monospace;font-size:.65rem;color:#5a2080;letter-spacing:.1em;text-transform:uppercase;margin-top:.4rem;">' . htmlspecialchars($label) . '</div>';
  }
  echo d2nSec('[General]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">';
  echo d2nField('Callsign','Callsign').d2nField('Id','DMR ID').d2nField('Timeout','Timeout (s)').d2nField('Duplex','Duplex (0/1)');
  echo '</div>';
  echo d2nSec('[Modem]');
  echo '<div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.65rem;color:#4a5568;display:block;margin-bottom:.25rem;">UART Port</label><select id="d2ncfg_UARTPort" style="width:100%;background:#060c10;border:1px solid #b57aff33;border-radius:3px;color:#b57aff;font-family:\'Share Tech Mono\',monospace;font-size:.82rem;padding:.35rem .6rem;outline:none;cursor:pointer;">';
  foreach(['/dev/ttyAMA0','/dev/ttyACM0','/dev/ttyACM1','/dev/ttyACM2','/dev/ttyUSB0','/dev/ttyUSB1'] as $p) echo "<option value=\"$p\">$p</option>";
  echo '</select></div>';
  echo d2nSec('[Info]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">'.d2nField('RXFrequency','RX Frequency (Hz)').d2nField('TXFrequency','TX Frequency (Hz)').'</div>';
  echo d2nSec('[DMR Network]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">';
  echo d2nField('DmrEnable','Enable (0/1)').d2nField('DmrLocalAddr','Local Address').d2nField('DmrLocalPort','Local Port').d2nField('DmrGwAddr','Gateway Address').d2nField('DmrGwPort','Gateway Port');
  echo '</div>';
  echo d2nSec('[NXDN Network]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">';
  echo d2nField('NxdnEnable','Enable (0/1)').d2nField('NxdnLocalAddr','Local Address').d2nField('NxdnLocalPort','Local Port').d2nField('NxdnGwAddr','Gateway Address').d2nField('NxdnGwPort','Gateway Port');
  echo '</div>';
  ?>
  <div id="d2ncfgMsg" style="font-family:var(--font-mono);font-size:.75rem;display:none;padding:.4rem .8rem;border-radius:4px;border:1px solid;margin-top:.4rem;"></div>
  <div style="display:flex;gap:.8rem;margin-top:.4rem;">
    <button onclick="saveMmdvmDmr2nxdnCfg()" style="flex:1;background:#b57aff22;color:var(--d2n);border:1px solid #b57aff55;border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">💾 Guardar</button>
    <button onclick="closeMmdvmDmr2nxdnCfg()" style="flex:1;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">✖ Cerrar</button>
  </div>
</div>
</div>

<!-- Modal Editor Ficheros -->
<div id="feditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9700;align-items:center;justify-content:center;" onclick="if(event.target===this)feditClose()">
<div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:1.5rem;width:900px;max-width:96vw;display:flex;flex-direction:column;gap:.8rem;">
  <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--cyan);letter-spacing:.12em;text-transform:uppercase;">📝 Editor de fichero</div>
  <div style="font-family:var(--font-mono);font-size:.72rem;color:var(--amber);" id="feditPath">—</div>
  <textarea id="feditArea" spellcheck="false" style="font-family:var(--font-mono);font-size:.78rem;color:#c9d1d9;background:#060c10;border:1px solid var(--border);border-radius:4px;padding:.8rem;height:420px;resize:vertical;outline:none;line-height:1.5;width:100%;tab-size:4;"></textarea>
  <div id="feditMsg" style="display:none;font-family:var(--font-mono);font-size:.75rem;padding:.4rem .8rem;border-radius:4px;border:1px solid;"></div>
  <div style="display:flex;gap:.8rem;">
    <button onclick="feditSave()" style="flex:1;background:#28a745;color:#fff;border:none;border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">💾 Guardar</button>
    <button onclick="feditClose()" style="flex:1;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">✖ Cerrar</button>
  </div>
</div>
</div>

<script>
const _winOS=/Windows/i.test(navigator.userAgent);
const _TBASE='https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/';
const _FLAGS=[
    {re:/^E[ABCDEFGH][1-9]/,e:'🇪🇸',t:'1f1ea-1f1f8'},
    {re:/^C[TUQ]/,e:'🇵🇹',t:'1f1f5-1f1f9'},
    {re:/^F[A-Z]/,e:'🇫🇷',t:'1f1eb-1f1f7'},
    {re:/^D[A-R]|^Y[2-9]/,e:'🇩🇪',t:'1f1e9-1f1ea'},
    {re:/^[KWN][0-9]|^AA|^AB|^AC/,e:'🇺🇸',t:'1f1fa-1f1f8'},
];
function getFlagByCall(cs){if(!cs)return'';cs=cs.toUpperCase();for(const p of _FLAGS){if(p.re.test(cs)){if(_winOS)return'<img class="flag-emoji-img" src="'+_TBASE+p.t+'.png" alt="">';return'<span class="flag-emoji">'+p.e+'</span>';}}return'';}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function colorize(text){return(text||'').split('\n').map(l=>{const ll=l.toLowerCase();if(/error|fail|abort/.test(ll))return`<span class="ln-err">${l}</span>`;if(/warn/.test(ll))return`<span class="ln-warn">${l}</span>`;if(/connect|start|open|loaded|success/.test(ll))return`<span class="ln-ok">${l}</span>`;return`<span class="ln-info">${l}</span>`;}).join('\n');}
function clearLog(id){document.getElementById(id).innerHTML='';}
function setDot(id,state){const el=document.getElementById(id);if(el)el.className='dot'+(state==='active'?' active':state==='error'?' error':'');}

// ── VU Meters ──
let d2nVuTimerAnim=null;
function buildD2nVU(){['dmr2nxdnVuLeft','dmr2nxdnVuRight'].forEach(id=>{const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}});}
buildD2nVU();
function animateD2nVU(on){clearInterval(d2nVuTimerAnim);['dmr2nxdnVuLeft','dmr2nxdnVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;d2nVuTimerAnim=setInterval(()=>{['dmr2nxdnVuLeft','dmr2nxdnVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-g':i<14?' lit-a':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}

// ── Clock ──
let d2nCurrentlyActive=false,d2nLastActiveTs=0;
const D2N_IDLE_TIMEOUT=12000;
function updateD2nClock(){if(!d2nCurrentlyActive){const now=new Date();const clk=document.getElementById('dmr2nxdnNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('dmr2nxdnNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateD2nClock,1000);updateD2nClock();

// ── Display ──
function showD2nIdle(){d2nCurrentlyActive=false;animateD2nVU(false);document.getElementById('dmr2nxdnTxBar').className='nx-txbar';document.getElementById('dmr2nxdnTGLabel').textContent='—';document.getElementById('dmr2nxdnSource').textContent='';document.getElementById('dmr2nxdnSource').className='nx-source';document.getElementById('dmr2nxdnNxCenter').innerHTML='<div class="nx-clock" id="dmr2nxdnNxClock" style="color:var(--d2n);">00:00:00</div><div class="nx-date" id="dmr2nxdnNxDate" style="color:#5a2080;">—</div>';updateD2nClock();}
function showD2nActive(d){d2nCurrentlyActive=true;animateD2nVU(true);document.getElementById('dmr2nxdnTxBar').className='nx-txbar active-d2n';document.getElementById('dmr2nxdnTGLabel').textContent=d.tg?'TG '+d.tg:'—';const src=document.getElementById('dmr2nxdnSource');const isNxdn=d.source==='NXDN';src.textContent=isNxdn?'NXDN':'DMR';src.className='nx-source '+(isNxdn?'net':'rf');const flag=getFlagByCall(d.callsign);document.getElementById('dmr2nxdnNxCenter').innerHTML=`<div class="nx-callsign">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name">${esc(d.name)}</div>`:'');}

function renderD2nLastHeard(list,activeCall){const body=document.getElementById('dmr2nxdnLhBody');if(!list||!list.length){body.innerHTML='<div style="padding:1.5rem 1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const dot=isActive?'<span class="lh-tx-dot-d2n"></span>':'';const flag=getFlagByCall(r.callsign);const srcClass=r.source==='NXDN'?'net':'rf';return`<div class="lh-row-d2n${isActive?' lh-active':''}"><div style="display:flex;align-items:center;gap:.35rem;">${dot}<span class="lh-call-d2n">${flag} ${esc(r.callsign)}</span></div><span style="font-family:var(--font-mono);font-size:.82rem;color:var(--text);">${esc(r.name||'—')}</span><span style="font-family:var(--font-mono);font-size:.72rem;color:var(--d2n);">${esc(r.tg||'—')}</span><span style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);">${esc(r.time||'—')}</span><span style="font-family:var(--font-mono);font-size:.6rem;" class="nx-source ${srcClass}">${esc(r.source||'DMR')}</span></div>`;}).join('');}

// ── Status / Toggle ──
let dmr2nxdnRunning=false,d2nTimer=null,d2nTxTimer=null;

function setDMR2NXDNToggle(on){
    const chk=document.getElementById('chkDMR2NXDN'),sta=document.getElementById('dmr2nxdnToggleStatus');
    chk.checked=on;sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';
    const track=document.querySelector('#swDMR2NXDN .sw-track');
    const knob=document.querySelector('#swDMR2NXDN .sw-knob');
    if(track)track.style.borderColor=on?'#00ff4c':'#ff4560';
    if(knob){knob.style.background=on?'#00ff4c':'#ff4560';knob.style.transform=on?'translateX(28px)':'translateX(0)';}
}

async function checkDmr2nxdnStatus(){try{const r=await fetch('?action=dmr2nxdn-status');const d=await r.json();const active=d.dmr2nxdn==='active';setDot('dot-dmr2nxdn-mmd',d.s1==='active'?'active':'off');setDot('dot-dmr2nxdn-nxdn',d.s2==='active'?'active':'off');setDot('dot-dmr2nxdn',d.s3==='active'?'active':'off');dmr2nxdnRunning=active;setDMR2NXDNToggle(active);if(active){startD2nLogs();startD2nTxPoll();}}catch(e){}}

async function toggleDMR2NXDN(chk){
    const wantOn=chk.checked;const sw=document.getElementById('swDMR2NXDN');chk.checked=!wantOn;sw.classList.add('busy');
    try{
        await fetch(wantOn?'?action=dmr2nxdn-start':'?action=dmr2nxdn-stop');
        let ok=false;
        for(let i=0;i<15;i++){
            await new Promise(r=>setTimeout(r,1000));
            const r=await fetch('?action=dmr2nxdn-status');const d=await r.json();const isOn=d.dmr2nxdn==='active';
            if(wantOn&&isOn){ok=true;setDot('dot-dmr2nxdn-mmd',d.s1==='active'?'active':'off');setDot('dot-dmr2nxdn-nxdn',d.s2==='active'?'active':'off');setDot('dot-dmr2nxdn',d.s3==='active'?'active':'off');dmr2nxdnRunning=true;setDMR2NXDNToggle(true);startD2nLogs();startD2nTxPoll();break;}
            if(!wantOn&&!isOn){ok=true;setDot('dot-dmr2nxdn-mmd','off');setDot('dot-dmr2nxdn-nxdn','off');setDot('dot-dmr2nxdn','off');dmr2nxdnRunning=false;setDMR2NXDNToggle(false);stopD2nLogs();stopD2nTxPoll();showD2nIdle();clearLog('logDmr2nxdn');clearLog('logNxdnGateway');clearLog('logMmdvmDmr2nxdn');break;}
        }
        if(!ok)await checkDmr2nxdnStatus();
    }catch(e){}finally{sw.classList.remove('busy');}
}

async function fetchD2nLogs(){
    try{const r=await fetch('?action=dmr2nxdn-logs&lines=30');const d=await r.json();const el=document.getElementById('logDmr2nxdn');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.dmr2nxdn);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}
    try{const r2=await fetch('?action=nxdngateway-logs&lines=30');const d2=await r2.json();const el2=document.getElementById('logNxdnGateway');const atBot2=el2.scrollHeight-el2.clientHeight<=el2.scrollTop+10;el2.innerHTML=colorize(d2.nxdngateway);if(atBot2)el2.scrollTop=el2.scrollHeight;}catch(e){}
    try{const r3=await fetch('?action=mmdvmdmr2nxdn-logs&lines=30');const d3=await r3.json();const el3=document.getElementById('logMmdvmDmr2nxdn');const atBot3=el3.scrollHeight-el3.clientHeight<=el3.scrollTop+10;el3.innerHTML=colorize(d3.mmdvmdmr2nxdn);if(atBot3)el3.scrollTop=el3.scrollHeight;}catch(e){}
}
async function fetchD2nTransmission(){try{const r=await fetch('?action=dmr2nxdn-transmission');const d=await r.json();if(d.active){d2nLastActiveTs=Date.now();showD2nActive(d);}else{if(d2nCurrentlyActive)showD2nIdle();}renderD2nLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){if(d2nCurrentlyActive&&(Date.now()-d2nLastActiveTs)>D2N_IDLE_TIMEOUT)showD2nIdle();}}
function startD2nLogs(){fetchD2nLogs();d2nTimer=setInterval(fetchD2nLogs,5000);}
function stopD2nLogs(){clearInterval(d2nTimer);d2nTimer=null;}
function startD2nTxPoll(){fetchD2nTransmission();d2nTxTimer=setInterval(fetchD2nTransmission,1500);}
function stopD2nTxPoll(){clearInterval(d2nTxTimer);d2nTxTimer=null;}

// ── Info display ──
async function loadD2nInfo(){try{const r=await fetch('?action=dmr2nxdn-info');const d=await r.json();document.getElementById('dmr2nxdnDmrId').textContent=d.dmrId||'—';document.getElementById('dmr2nxdnGw').textContent=d.gw||'—';document.getElementById('dmr2nxdnDefTG').textContent=d.defTG||'—';document.getElementById('dmr2nxdnNxdnGw').textContent='NXDN: '+(d.nxdnGw||'—');}catch(e){}}

// ── Modal Config MMDVMDMR2NXDN ──
const d2nCfgFields=['Callsign','Id','Timeout','Duplex','RXFrequency','TXFrequency','DmrEnable','DmrLocalAddr','DmrLocalPort','DmrGwAddr','DmrGwPort','NxdnEnable','NxdnLocalAddr','NxdnLocalPort','NxdnGwAddr','NxdnGwPort','UARTPort'];
async function openMmdvmDmr2nxdnCfg(){
    const modal=document.getElementById('mmdvmD2nCfgModal');const msg=document.getElementById('d2ncfgMsg');msg.style.display='none';modal.style.display='flex';
    d2nCfgFields.forEach(f=>{const el=document.getElementById('d2ncfg_'+f);if(el)el.value='';});
    try{const r=await fetch('?action=mmdvmdmr2nxdn-config-read');const d=await r.json();d2nCfgFields.forEach(f=>{const el=document.getElementById('d2ncfg_'+f);if(!el||d[f]===undefined)return;if(el.tagName==='SELECT'){let found=false;for(const opt of el.options){if(opt.value===d[f]){opt.selected=true;found=true;break;}}if(!found){const opt=document.createElement('option');opt.value=d[f];opt.textContent=d[f];el.appendChild(opt);opt.selected=true;}}else el.value=d[f];});}catch(e){const msg=document.getElementById('d2ncfgMsg');msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error al leer el fichero';}
}
function closeMmdvmDmr2nxdnCfg(){document.getElementById('mmdvmD2nCfgModal').style.display='none';}
async function saveMmdvmDmr2nxdnCfg(){
    const msg=document.getElementById('d2ncfgMsg');msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--amber);color:var(--amber);background:rgba(255,179,0,.06);';msg.textContent='⏳ Guardando…';
    const body=d2nCfgFields.map(f=>{const el=document.getElementById('d2ncfg_'+f);return el?encodeURIComponent(f)+'='+encodeURIComponent(el.value):'';}).filter(Boolean).join('&');
    try{const r=await fetch('?action=mmdvmdmr2nxdn-config-save',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const d=await r.json();if(d.ok){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid #b57aff;color:#b57aff;background:rgba(181,122,255,.06);';msg.textContent='✔ Guardado correctamente';setTimeout(()=>msg.style.display='none',3000);}else{msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ '+(d.msg||'Error');}}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error de red';}
}

// ── fedit ──
async function feditOpen(path){const msg=document.getElementById('feditMsg');msg.style.display='none';document.getElementById('feditPath').textContent=path;document.getElementById('feditArea').value='Cargando…';document.getElementById('feditModal').style.display='flex';try{const r=await fetch('mmdvm.php?action=read-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)});const d=await r.json();if(d.ok){document.getElementById('feditArea').value=d.content;}else{document.getElementById('feditArea').value='';msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ '+d.msg;}}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error: '+e.message;}}
async function feditSave(){const path=document.getElementById('feditPath').textContent;const content=document.getElementById('feditArea').value;const msg=document.getElementById('feditMsg');try{const r=await fetch('mmdvm.php?action=save-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)+'&content='+encodeURIComponent(content)});const d=await r.json();msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid '+(d.ok?'var(--green)':'var(--red)')+';color:'+(d.ok?'var(--green)':'var(--red)')+';background:'+(d.ok?'rgba(0,255,159,.06)':'rgba(255,69,96,.06)')+';';msg.textContent=(d.ok?'✔ ':'✖ ')+d.msg;if(d.ok)setTimeout(()=>msg.style.display='none',3000);}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error: '+e.message;}}
function feditClose(){document.getElementById('feditModal').style.display='none';}

// ── Init ──
(async()=>{
    await loadD2nInfo();
    setInterval(loadD2nInfo,60000);
    await checkDmr2nxdnStatus();
    setInterval(checkDmr2nxdnStatus,10000);
    showD2nIdle();
})();
</script>
</body>
</html>
