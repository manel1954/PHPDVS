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

// ── TG-YSFList editor ─────────────────────────────────────────────────────────
$TGYSF_FILE  = '/home/pi/MMDVM_CM/DMR2YSF/TG-YSFList.txt';
$TGYSF_NAMES = '/home/pi/MMDVM_CM/DMR2YSF/TG-YSFNames.json';

if ($action === 'tgysf-hosts') {
    $hostsFile = '/home/pi/YSFClients/YSFGateway/YSFHosts.json';
    $list = [];
    if (file_exists($hostsFile)) {
        $json = json_decode(file_get_contents($hostsFile), true);
        if (isset($json['reflectors']) && is_array($json['reflectors'])) {
            foreach ($json['reflectors'] as $ref) {
                $id      = intval($ref['designator'] ?? 0);
                $name    = trim($ref['name']    ?? '');
                $desc    = trim($ref['sponsor'] ?? '');
                $country = strtoupper(trim($ref['country'] ?? ''));
                if ($id <= 0) continue;
                $list[] = ['id'=>$id,'name'=>$name,'desc'=>$desc,'country'=>$country];
            }
        }
    }
    usort($list, function($a,$b){
        $aES = $a['country']==='ES'?0:1;
        $bES = $b['country']==='ES'?0:1;
        if ($aES !== $bES) return $aES - $bES;
        return strcmp($a['name'], $b['name']);
    });
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'hosts'=>$list]);
    exit;
}

if ($action === 'tgysf-read') {
    $entries = [];
    $names = file_exists($TGYSF_NAMES) ? (json_decode(file_get_contents($TGYSF_NAMES), true) ?: []) : [];
    $hostNames = [];
    $hostsFile = '/home/pi/YSFClients/YSFGateway/YSFHosts.json';
    if (file_exists($hostsFile)) {
        $hjson = json_decode(file_get_contents($hostsFile), true);
        if (isset($hjson['reflectors'])) {
            foreach ($hjson['reflectors'] as $ref) {
                $hid = intval($ref['designator'] ?? 0);
                $hnm = trim($ref['name'] ?? '');
                if ($hid > 0 && $hnm !== '') $hostNames[(string)$hid] = $hnm;
            }
        }
    }
    if (file_exists($TGYSF_FILE)) {
        foreach (file($TGYSF_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $parts = explode(';', $line, 2);
            if (count($parts) === 2 && is_numeric(trim($parts[0]))) {
                $tg  = trim($parts[0]);
                $ysf = trim($parts[1]);
                $nm  = $names[$tg] ?? $hostNames[$ysf] ?? '';
                $entries[] = ['tg' => $tg, 'ysf' => $ysf, 'name' => $nm];
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'entries' => $entries]);
    exit;
}

if ($action === 'tgysf-save') {
    $raw = json_decode(file_get_contents('php://input'), true);
    $entries = $raw['entries'] ?? [];
    $lines = ["# DMR TG - YSF ID mapping", "# DMR TG ID;YSF reflector ID", "#"];
    $names = [];
    foreach ($entries as $e) {
        $tg  = intval($e['tg']  ?? 0);
        $ysf = intval($e['ysf'] ?? 0);
        $nm  = trim($e['name']  ?? '');
        if ($tg > 0 && $ysf > 0) {
            $lines[] = $tg . ';' . $ysf;
            if ($nm !== '') $names[(string)$tg] = $nm;
        }
    }
    $b1 = file_put_contents($TGYSF_FILE,  implode("\n", $lines) . "\n");
    $b2 = file_put_contents($TGYSF_NAMES, json_encode($names, JSON_PRETTY_PRINT));
    $ok = ($b1 !== false && $b2 !== false);
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Guardado correctamente' : 'Error al escribir']);
    exit;
}

// ── Acciones DMR2YSF ──────────────────────────────────────────────────────────
if ($action === 'dmr2ysf-status') {
    $s1 = trim(shell_exec('systemctl is-active mmdvmdmr2ysf 2>/dev/null'));
    $s2 = trim(shell_exec('systemctl is-active ysfgw-dmr2ysf 2>/dev/null'));
    $s3 = trim(shell_exec('systemctl is-active dmr2ysf 2>/dev/null'));
    $active = ($s1 === 'active' || $s2 === 'active' || $s3 === 'active') ? 'active' : 'inactive';
    header('Content-Type: application/json');
    echo json_encode(['dmr2ysf' => $active, 's1' => $s1, 's2' => $s2, 's3' => $s3]);
    exit;
}
if ($action === 'dmr2ysf-start') {
    saveState('dmr2ysf','on');
    shell_exec('sudo systemctl enable mmdvmdmr2ysf 2>/dev/null');
    shell_exec('sudo systemctl start mmdvmdmr2ysf 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl enable ysfgw-dmr2ysf 2>/dev/null');
    shell_exec('sudo systemctl start ysfgw-dmr2ysf 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl enable dmr2ysf 2>/dev/null');
    shell_exec('sudo systemctl start dmr2ysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'dmr2ysf-stop') {
    saveState('dmr2ysf','off');
    shell_exec('sudo systemctl stop dmr2ysf 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop ysfgw-dmr2ysf 2>/dev/null');
    shell_exec('sudo systemctl stop mmdvmdmr2ysf 2>/dev/null');
    shell_exec('sudo systemctl disable dmr2ysf mmdvmdmr2ysf ysfgw-dmr2ysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'dmr2ysf-logs') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u dmr2ysf -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) {
        $logFiles = glob('/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF-*.log');
        if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n {$lines} " . escapeshellarg($latest) . " 2>/dev/null"); }
    }
    header('Content-Type: application/json');
    echo json_encode(['dmr2ysf' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'ysfgw-dmr2ysf-logs') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u ysfgw-dmr2ysf -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['ysfgwdmr2ysf' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'mmdvmdmr2ysf-logs') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u mmdvmdmr2ysf -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['mmdvmdmr2ysf' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'dmr2ysf-transmission') {
    $stateFile = '/tmp/dmr2ysf_tx_state.json';
    $lhFile    = '/tmp/dmr2ysf_lastheard.json';
    $logFiles  = glob('/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF-*.log');
    $log = '';
    if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n 200 " . escapeshellarg($latest) . " 2>/dev/null") ?? ''; }
    if (empty(trim($log))) $log = shell_exec("sudo journalctl -u dmr2ysf -n 200 --no-pager --output=short 2>/dev/null") ?? '';
    $lines = array_reverse(explode("\n", $log));
    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','source'=>''];
    if (file_exists($stateFile)) { $saved = json_decode(file_get_contents($stateFile), true); if (is_array($saved)) $state = $saved; }
    foreach ($lines as $line) {
        if (preg_match('/DMR received end of voice|YSF received end of voice|end of voice transmission|lost|watchdog|timeout/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/DMR audio received from\s+([A-Z0-9]+).*TG\s+(\d+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>$m[2],'source'=>'DMR'];
            file_put_contents($stateFile,json_encode($state));break;
        }
        if (preg_match('/DMR audio received from\s+([A-Z0-9]+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>'','source'=>'DMR'];
            file_put_contents($stateFile,json_encode($state));break;
        }
        if (preg_match('/Received YSF Header:\s+Src:\s+([A-Z0-9]+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>'','source'=>'YSF'];
            file_put_contents($stateFile,json_encode($state));break;
        }
    }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs=''; $time=''; $tgr=''; $src='DMR';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*DMR audio received from\s+([A-Z0-9]+).*TG\s+(\d+)/i', $line, $m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$tgr=$m[3];$src='DMR'; }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*DMR audio received from\s+([A-Z0-9]+)/i', $line, $m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$src='DMR'; }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*Received YSF Header:\s+Src:\s+([A-Z0-9]+)/i', $line, $m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$src='YSF'; }
        if ($cs && !in_array($cs, $seen)) { $inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$tgr,'source'=>$src,'time'=>$time];$seen[]=$cs;if(count($lastHeard)>=5)break; }
    }
    if (!empty($lastHeard)) file_put_contents($lhFile, json_encode($lastHeard));
    elseif (file_exists($lhFile)) $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json');
    echo json_encode($state);
    exit;
}

// ── Config MMDVMDMR2YSF ───────────────────────────────────────────────────────
if ($action === 'mmdvmdmr2ysf-config-read') {
    $path = '/home/pi/MMDVMHost/MMDVMDMR2YSF.ini';
    $ini  = parseMMDVMIni($path);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>file_exists($path),'Callsign'=>$ini['General']['Callsign']??'','Id'=>$ini['General']['Id']??'','Timeout'=>$ini['General']['Timeout']??'180','Duplex'=>$ini['General']['Duplex']??'0','RXFrequency'=>$ini['Info']['RXFrequency']??'0','TXFrequency'=>$ini['Info']['TXFrequency']??'0','DmrEnable'=>$ini['DMR Network']['Enable']??'1','DmrType'=>$ini['DMR Network']['Type']??'Direct','DmrLocalAddr'=>$ini['DMR Network']['LocalAddress']??'127.0.0.1','DmrLocalPort'=>$ini['DMR Network']['LocalPort']??'62031','DmrRemoteAddr'=>$ini['DMR Network']['RemoteAddress']??'127.0.0.1','DmrRemotePort'=>$ini['DMR Network']['RemotePort']??'62032','DmrPassword'=>$ini['DMR Network']['Password']??'','DmrJitter'=>$ini['DMR Network']['Jitter']??'360','UARTPort'=>$ini['Modem']['UARTPort']??'']);
    exit;
}
if ($action === 'mmdvmdmr2ysf-config-save') {
    $path = '/home/pi/MMDVMHost/MMDVMDMR2YSF.ini';
    if (!file_exists($path)) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Fichero no encontrado']); exit; }
    $content = file_get_contents($path);
    $map = ['General'=>['Callsign','Id','Timeout','Duplex'],'Info'=>['RXFrequency','TXFrequency'],'DMR Network'=>['Enable'=>'DmrEnable','Type'=>'DmrType','LocalAddress'=>'DmrLocalAddr','LocalPort'=>'DmrLocalPort','RemoteAddress'=>'DmrRemoteAddr','RemotePort'=>'DmrRemotePort','Password'=>'DmrPassword','Jitter'=>'DmrJitter'],'Modem'=>['UARTPort'=>'UARTPort']];
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

// ── Leer info del ini para el display ─────────────────────────────────────────
if ($action === 'dmr2ysf-info') {
    $ini = parseMMDVMIni('/home/pi/MMDVM_CM/DMR2YSF/DMR2YSF.ini');
    $cs  = parseMMDVMIni('/home/pi/MMDVMHost/MMDVMDMR2YSF.ini');
    header('Content-Type: application/json');
    echo json_encode([
        'callsign' => strtoupper(trim($cs['General']['Callsign'] ?? 'EA3EIZ')),
        'dmrId'    => $ini['DMR Network']['Id']             ?? '—',
        'gw'       => $ini['YSF Network']['GatewayAddress'] ?? '—',
        'defTG'    => $ini['DMR Network']['DefaultDstTG']   ?? '—',
        'ysfGw'    => ($ini['YSF Network']['GatewayAddress'] ?? '127.0.0.1').':'.($ini['YSF Network']['GatewayPort'] ?? '4200'),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DMR2YSF · Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#032354;--surface:#111720;--border:#1e2d3d;--green:#00ff9f;--red:#ff4560;--amber:#ffb300;--cyan:#00d4ff;--violet:#b57aff;--text:#a8b9cc;--text-dim:#4a5568;--d2y:#00ffcc;--font-mono:'Share Tech Mono',monospace;--font-ui:'Rajdhani',sans-serif;--font-orb:'Orbitron',monospace;}
*{box-sizing:border-box;}
body{background:#00004d;color:var(--text);font-family:var(--font-ui);font-size:1rem;min-height:100vh;padding:0;margin:0;}
.ctrl-header{border-bottom:2px solid var(--d2y);padding:1rem 2rem;display:flex;align-items:center;gap:.8rem;background:#000;}
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
.nextion-dmr2ysf{background:#060e0c;border:2px solid #00ffcc44;border-radius:6px;box-shadow:0 0 0 1px #003028,inset 0 0 40px rgba(0,255,204,.04),0 0 30px rgba(0,255,204,.1);position:relative;overflow:hidden;height:240px;display:flex;align-items:center;justify-content:center;}
.nextion-dmr2ysf::before,.nextion-dmr2ysf::after{content:'◈';position:absolute;font-size:.6rem;color:#00ffcc33;}
.nextion-dmr2ysf::before{top:.5rem;left:.7rem;}
.nextion-dmr2ysf::after{bottom:.5rem;right:.7rem;}
.nx-topbar.dmr2ysf-bar{background:#0a1a16;border-bottom:1px solid #00ffcc33;color:#007060;position:absolute;top:0;left:0;right:0;height:30px;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-family:var(--font-mono);font-size:.65rem;letter-spacing:.1em;}
.nx-topbar.dmr2ysf-bar .nx-mode{color:var(--d2y);opacity:.8;}
.nx-botbar.dmr2ysf-bar{background:#060e0c;border-top:1px solid #00ffcc33;color:#007060;position:absolute;bottom:0;left:0;right:0;height:28px;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-family:var(--font-mono);font-size:.65rem;letter-spacing:.08em;}
.nx-infobar-dmr2ysf{position:absolute;top:30px;left:0;right:0;height:26px;background:rgba(0,0,0,.4);border-bottom:1px solid #003028;display:flex;align-items:center;justify-content:space-around;padding:0 8rem;gap:1rem;z-index:2;}
.nx-info-item{display:flex;align-items:center;gap:.4rem;}
.nx-info-lbl{font-family:var(--font-mono);font-size:10px;color:#999;text-transform:uppercase;}
.nx-info-val{font-family:var(--font-mono);font-size:10px;color:#ff0;font-weight:bold;}
.nx-vu{position:absolute;left:1rem;top:56px;bottom:32px;width:6px;display:flex;flex-direction:column-reverse;gap:2px;}
.nx-vu.right{left:auto;right:1rem;}
.nx-vu-bar{height:5px;border-radius:1px;background:#0d2030;transition:background .08s;}
.nx-vu-bar.lit-g{background:var(--green);box-shadow:0 0 4px var(--green);}
.nx-vu-bar.lit-a{background:var(--amber);box-shadow:0 0 4px var(--amber);}
.nx-vu-bar.lit-r{background:var(--red);box-shadow:0 0 4px var(--red);}
.nx-center{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.15rem;z-index:1;}
.nx-clock{font-family:var(--font-orb);font-size:4rem;font-weight:700;color:#fff;letter-spacing:.06em;line-height:1;}
.nx-date{font-family:var(--font-mono);font-size:.7rem;color:#ff0;letter-spacing:.12em;text-transform:uppercase;margin-top:.2rem;}
.nx-callsign{font-family:var(--font-orb);font-size:3.4rem;font-weight:900;letter-spacing:.04em;line-height:1;color:var(--d2y);text-shadow:0 0 20px rgba(0,255,204,.6);}
.nx-name{font-family:var(--font-ui);font-weight:500;font-size:1.2rem;color:#80ffe8;letter-spacing:.18em;text-transform:uppercase;opacity:.9;margin-top:.15rem;}
.nx-txbar{position:absolute;bottom:28px;left:0;right:0;height:3px;}
.nx-txbar.active-dmr2ysf{background:linear-gradient(90deg,transparent,var(--d2y),transparent);background-size:200% 100%;animation:scan 1.4s linear infinite;}
@keyframes scan{from{background-position:200% 0}to{background-position:-200% 0}}
.nx-source{padding:.1rem .45rem;border-radius:2px;font-size:.6rem;letter-spacing:.1em;}
.nx-source.rf{background:rgba(0,255,159,.15);color:var(--green);border:1px solid rgba(0,255,159,.3);}
/* Last heard */
.lh-panel-dmr2ysf{background:var(--surface);border:3px solid #00ffcc33;border-radius:6px;display:flex;flex-direction:column;}
.lh-header-dmr2ysf{background:#0a1a16;border-bottom:1px solid #00ffcc33;padding:.4rem 1rem;display:grid;grid-template-columns:1.2fr 1.8fr .8fr 1fr .6fr;gap:.3rem;font-family:var(--font-mono);font-size:.6rem;color:#007060;letter-spacing:.1em;text-transform:uppercase;}
.lh-row-dmr2ysf{display:grid;grid-template-columns:1.2fr 1.8fr .8fr 1fr .6fr;gap:.3rem;padding:.45rem 1rem;border-bottom:1px solid rgba(0,255,204,.1);align-items:center;}
.lh-call-dmr2ysf{font-family:var(--font-mono);font-size:.82rem;color:var(--d2y);letter-spacing:.05em;font-weight:bold;}
.lh-tx-dot-dmr2ysf{width:6px;height:6px;border-radius:50%;background:var(--d2y);box-shadow:0 0 6px var(--d2y);animation:pulse 1s infinite;flex-shrink:0;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
/* Logs */
.log-panel{background:var(--surface);border:1px solid var(--border);border-radius:4px;overflow:hidden;margin-bottom:1rem;}
.log-panel-header{display:flex;align-items:center;justify-content:space-between;padding:.5rem 1rem;border-bottom:1px solid var(--border);background:rgba(0,0,0,.3);}
.svc-name{font-family:var(--font-mono);font-size:.8rem;letter-spacing:.1em;color:var(--d2y);text-transform:uppercase;}
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
.card{background:var(--surface);border:1px solid #00ffcc33;border-radius:8px;padding:1.2rem 1.6rem;margin-bottom:1.2rem;}
.ini-btn{font-family:var(--font-mono);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;padding:.3rem .7rem;border-radius:3px;border:1px solid var(--border);background:transparent;cursor:pointer;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:.3rem;color:var(--d2y);border-color:rgba(0,255,204,.3);}
.ini-btn:hover{border-color:var(--d2y);background:rgba(0,255,204,.08);}
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
  <a href="mmdvm.php" style="background:#1a2535;color:var(--d2y);border:1px solid rgba(0,255,204,.3);font-family:var(--font-mono);font-size:.75rem;padding:.35rem .9rem;border-radius:4px;text-decoration:none;">← Panel PHPPLUS</a>
  <span style="font-family:var(--font-orb);color:var(--d2y);font-size:1.2rem;letter-spacing:.1em;">DMR2YSF · CROSS-MODE BRIDGE</span>
  <div style="margin-left:auto;display:flex;align-items:center;gap:.8rem;">
    <label class="sw" id="swDMR2YSF">
      <input type="checkbox" id="chkDMR2YSF" onchange="toggleDMR2YSF(this)">
      <span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span>
    </label>
    <span class="toggle-status" id="dmr2ysfToggleStatus">OFF</span>
  </div>
</header>

<div class="ctrl-body">

  <!-- Status bar -->
  <div class="status-bar">
    <div class="status-item"><div class="dot" id="dot-dmr2ysf-mmd"></div><span style="color:var(--d2y)">MMDVMDmr2ysf</span></div>
    <div class="status-item"><div class="dot" id="dot-dmr2ysf-ysf"></div><span style="color:var(--d2y)">YSFgw-Dmr2ysf</span></div>
    <div class="status-item"><div class="dot" id="dot-dmr2ysf"></div><span style="color:var(--d2y)">DMR2YSF</span></div>
  </div>

  <!-- Botones de config -->
  <div class="card">
    <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--d2y);letter-spacing:.12em;text-transform:uppercase;margin-bottom:.8rem;">▸ Configuración</div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <a href="dmr2ysf_config.php" class="ini-btn">⚙ DMR2YSF CONFIG</a>
      <button onclick="openMmdvmDmr2ysfCfg()" class="ini-btn">⚙ MMDVMDMR2YSF CONFIG</button>
      <a href="edit_ini.php?file=dmr2ysf" class="ini-btn">📄 editar DMR2YSF.ini</a>
      <button onclick="feditOpen('/home/pi/MMDVMHost/MMDVMDMR2YSF.ini')" class="ini-btn">📄 editar MMDVMDMR2YSF.ini</button>
      <button onclick="openTgYsfModal()" class="ini-btn" style="background:rgba(0,255,204,.06);">📋 TG-YSF List</button>
    </div>
  </div>

  <!-- Display + Last heard -->
  <div class="display-grid">
    <div>
      <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--d2y);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.5rem;">▸ DMR2YSF Display</div>
      <div class="nextion-dmr2ysf">
        <div class="nx-topbar dmr2ysf-bar"><span class="nx-mode">DMR2YSF · BRIDGE</span><span style="color:var(--d2y);opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="dmr2ysfTGLabel">—</span></div>
        <div class="nx-infobar-dmr2ysf">
          <span class="nx-info-item"><span class="nx-info-lbl">DMR ID</span><span class="nx-info-val" style="color:var(--d2y)" id="dmr2ysfDmrId">—</span></span>
          <span class="nx-info-item"><span class="nx-info-lbl">GW</span><span class="nx-info-val" style="color:#80ffe8" id="dmr2ysfGw">—</span></span>
          <span class="nx-info-item"><span class="nx-info-lbl">TG Defecto</span><span class="nx-info-val" style="color:var(--d2y)" id="dmr2ysfDefTG">—</span></span>
        </div>
        <div class="nx-vu" id="dmr2ysfVuLeft"></div><div class="nx-vu right" id="dmr2ysfVuRight"></div>
        <div class="nx-center" id="dmr2ysfNxCenter"><div class="nx-clock" id="dmr2ysfNxClock" style="color:var(--d2y);">00:00:00</div><div class="nx-date" id="dmr2ysfNxDate" style="color:#007060;">—</div></div>
        <div class="nx-txbar" id="dmr2ysfTxBar"></div>
        <div class="nx-botbar dmr2ysf-bar"><span style="color:#007060;font-family:var(--font-mono);font-size:.65rem;">DMR2YSF · CROSS-MODE</span><span style="color:var(--d2y);font-family:var(--font-mono);font-size:.65rem;" id="dmr2ysfYsfGw">YSF: —</span><span class="nx-source" id="dmr2ysfSource"></span></div>
      </div>
    </div>
    <div>
      <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--d2y);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.5rem;">▸ Últimos escuchados</div>
      <div class="lh-panel-dmr2ysf">
        <div class="lh-header-dmr2ysf"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
        <div id="dmr2ysfLhBody" style="flex:1;overflow-y:auto;"><div style="padding:1.5rem 1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin actividad</div></div>
      </div>
    </div>
  </div>

  <!-- Logs -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
    <div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ DMR2YSF</span><button class="btn-clear" onclick="clearLog('logDmr2ysf')">limpiar</button></div><div class="log-output" id="logDmr2ysf">Cargando…</div></div>
    <div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ YSFGw DMR2YSF</span><button class="btn-clear" onclick="clearLog('logYsfGwDmr2ysf')">limpiar</button></div><div class="log-output" id="logYsfGwDmr2ysf">Cargando…</div></div>
    <div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ MMDVMDmr2ysf</span><button class="btn-clear" onclick="clearLog('logMmdvmDmr2ysf')">limpiar</button></div><div class="log-output" id="logMmdvmDmr2ysf">Cargando…</div></div>
  </div>

</div><!-- /ctrl-body -->

<!-- Modal Config MMDVMDMR2YSF -->
<div id="mmdvmD2yCfgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9700;align-items:center;justify-content:center;" onclick="if(event.target===this)closeMmdvmDmr2ysfCfg()">
<div style="background:var(--surface);border:1px solid #00ffcc44;border-radius:8px;padding:1.5rem;width:700px;max-width:96vw;max-height:90vh;overflow-y:auto;display:flex;flex-direction:column;gap:.8rem;">
  <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--d2y);letter-spacing:.12em;text-transform:uppercase;border-bottom:1px solid #00ffcc33;padding-bottom:.6rem;">⚙ MMDVMDMR2YSF.ini</div>
  <?php
  function d2field($id, $label) {
      return '<div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.65rem;color:#4a5568;display:block;margin-bottom:.25rem;">'
           . htmlspecialchars($label)
           . '</label><input id="d2cfg_' . $id . '" style="width:100%;background:#060c10;border:1px solid #00ffcc33;border-radius:3px;color:#00ffcc;font-family:\'Share Tech Mono\',monospace;font-size:.82rem;padding:.35rem .6rem;outline:none;" onfocus="this.style.borderColor=\'#00ffcc\'" onblur="this.style.borderColor=\'#00ffcc33\'"></div>';
  }
  function d2sec($label) {
      return '<div style="font-family:\'Share Tech Mono\',monospace;font-size:.65rem;color:#007060;letter-spacing:.1em;text-transform:uppercase;margin-top:.4rem;">' . htmlspecialchars($label) . '</div>';
  }
  echo d2sec('[General]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">';
  echo d2field('Callsign','Callsign').d2field('Id','DMR ID').d2field('Timeout','Timeout (s)').d2field('Duplex','Duplex (0/1)');
  echo '</div>';
  echo d2sec('[Modem]');
  echo '<div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.65rem;color:#4a5568;display:block;margin-bottom:.25rem;">UART Port</label><select id="d2cfg_UARTPort" style="width:100%;background:#060c10;border:1px solid #00ffcc33;border-radius:3px;color:#00ffcc;font-family:\'Share Tech Mono\',monospace;font-size:.82rem;padding:.35rem .6rem;outline:none;cursor:pointer;">';
  foreach(['/dev/ttyAMA0','/dev/ttyACM0','/dev/ttyACM1','/dev/ttyACM2','/dev/ttyUSB0','/dev/ttyUSB1'] as $p) echo "<option value=\"$p\">$p</option>";
  echo '</select></div>';
  echo d2sec('[Info]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">'.d2field('RXFrequency','RX Frequency (Hz)').d2field('TXFrequency','TX Frequency (Hz)').'</div>';
  echo d2sec('[DMR Network]');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.7rem;">';
  echo d2field('DmrEnable','Enable (0/1)').d2field('DmrType','Type (Direct/Gateway)').d2field('DmrLocalAddr','Local Address').d2field('DmrLocalPort','Local Port').d2field('DmrRemoteAddr','Remote Address').d2field('DmrRemotePort','Remote Port').d2field('DmrPassword','Password').d2field('DmrJitter','Jitter (ms)');
  echo '</div>';
  ?>
  <div id="d2cfgMsg" style="font-family:var(--font-mono);font-size:.75rem;display:none;padding:.4rem .8rem;border-radius:4px;border:1px solid;margin-top:.4rem;"></div>
  <div style="display:flex;gap:.8rem;margin-top:.4rem;">
    <button onclick="saveMmdvmDmr2ysfCfg()" style="flex:1;background:#00ffcc22;color:var(--d2y);border:1px solid #00ffcc55;border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">💾 Guardar</button>
    <button onclick="closeMmdvmDmr2ysfCfg()" style="flex:1;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">✖ Cerrar</button>
  </div>
</div>
</div>

<!-- Modal TG-YSF List -->
<div id="tgYsfModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9900;align-items:center;justify-content:center;" onclick="if(event.target===this)closeTgYsfModal()">
<div style="background:var(--surface);border:1px solid #00ffcc44;border-radius:8px;padding:1.5rem;width:720px;max-width:96vw;display:flex;flex-direction:column;gap:.8rem;">
  <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--d2y);letter-spacing:.12em;text-transform:uppercase;">📋 TG-YSF List · Mapeo TalkGroup → Reflector YSF</div>
  <div style="font-family:var(--font-mono);font-size:.65rem;color:var(--text-dim);">/home/pi/MMDVM_CM/DMR2YSF/TG-YSFList.txt</div>
  <div style="background:#060c10;border:1px solid #00ffcc22;border-radius:4px;overflow:hidden;">
    <div style="display:grid;grid-template-columns:90px 110px 1fr 36px;padding:.4rem .8rem;background:#0a1a16;font-family:var(--font-mono);font-size:.65rem;color:#007060;letter-spacing:.1em;text-transform:uppercase;gap:.5rem;">
      <span>TG DMR</span><span>YSF ID</span><span>Nombre</span><span></span>
    </div>
    <div id="tgYsfRows" style="max-height:220px;overflow-y:auto;"></div>
  </div>
  <div style="display:grid;grid-template-columns:90px 110px 1fr auto;gap:.5rem;align-items:end;">
    <div><div style="font-family:var(--font-mono);font-size:.62rem;color:var(--text-dim);margin-bottom:.25rem;text-transform:uppercase;">TG DMR</div><input type="text" id="tgYsfNewTG" placeholder="21465" style="width:100%;background:var(--surface);border:1px solid #00ffcc44;border-radius:4px;color:var(--d2y);font-family:var(--font-mono);font-size:.82rem;padding:.42rem .5rem;outline:none;"></div>
    <div><div style="font-family:var(--font-mono);font-size:.62rem;color:var(--text-dim);margin-bottom:.25rem;text-transform:uppercase;">YSF ID</div><input type="text" id="tgYsfNewYSF" placeholder="32027" style="width:100%;background:var(--surface);border:1px solid #00ffcc44;border-radius:4px;color:var(--d2y);font-family:var(--font-mono);font-size:.82rem;padding:.42rem .5rem;outline:none;"></div>
    <div><div style="font-family:var(--font-mono);font-size:.62rem;color:var(--text-dim);margin-bottom:.25rem;text-transform:uppercase;">Nombre</div><input type="text" id="tgYsfNewName" placeholder="ej: ES-ADER" style="width:100%;background:var(--surface);border:1px solid #00ffcc44;border-radius:4px;color:#80ffe8;font-family:var(--font-mono);font-size:.82rem;padding:.42rem .5rem;outline:none;"></div>
    <div style="display:flex;flex-direction:column;gap:.25rem;">
      <button onclick="tgYsfAdd()" style="background:#00cc99;color:#000;border:none;border-radius:4px;font-family:var(--font-mono);font-size:.78rem;padding:.42rem .8rem;cursor:pointer;">➕ Añadir</button>
      <button onclick="tgYsfToggleHosts()" style="background:#0d2535;color:var(--d2y);border:1px solid #00ffcc44;border-radius:4px;font-family:var(--font-mono);font-size:.78rem;padding:.42rem .8rem;cursor:pointer;">📡 Buscar Sala</button>
    </div>
  </div>
  <div id="tgYsfHostPanel" style="display:none;background:#060c10;border:1px solid #00ffcc33;border-radius:4px;padding:.8rem;">
    <div style="display:flex;gap:.5rem;margin-bottom:.6rem;">
      <input type="text" id="tgYsfSearch" placeholder="🔍 Buscar reflector…" oninput="tgYsfFilterHosts(this.value)" style="flex:1;background:var(--surface);border:1px solid #00ffcc44;border-radius:4px;color:var(--d2y);font-family:var(--font-mono);font-size:.78rem;padding:.38rem .6rem;outline:none;">
      <button onclick="tgYsfToggleHosts()" style="background:transparent;border:1px solid #ff456044;color:var(--red);border-radius:4px;font-family:var(--font-mono);font-size:.7rem;padding:.35rem .6rem;cursor:pointer;">✖</button>
    </div>
    <div id="tgYsfHostList" style="max-height:200px;overflow-y:auto;font-family:var(--font-mono);font-size:.72rem;"></div>
    <div style="font-family:var(--font-mono);font-size:.6rem;color:var(--text-dim);margin-top:.4rem;">↑ Haz clic para rellenar YSF ID y Nombre</div>
  </div>
  <div id="tgYsfMsg" style="display:none;font-family:var(--font-mono);font-size:.75rem;padding:.4rem .8rem;border-radius:4px;border:1px solid;"></div>
  <div style="display:flex;gap:.8rem;">
    <button onclick="tgYsfSave()" style="flex:1;background:#00cc99;color:#000;border:none;border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">💾 Guardar</button>
    <button onclick="closeTgYsfModal()" style="flex:1;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">✖ Cerrar</button>
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
let dmr2ysfVuTimerAnim=null;
function buildDmr2ysfVU(){['dmr2ysfVuLeft','dmr2ysfVuRight'].forEach(id=>{const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}});}
buildDmr2ysfVU();
function animateDmr2ysfVU(on){clearInterval(dmr2ysfVuTimerAnim);['dmr2ysfVuLeft','dmr2ysfVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;dmr2ysfVuTimerAnim=setInterval(()=>{['dmr2ysfVuLeft','dmr2ysfVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-g':i<14?' lit-a':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}

// ── Clock ──
let dmr2ysfCurrentlyActive=false,dmr2ysfLastActiveTs=0;
const DMR2YSF_IDLE_TIMEOUT=12000;
function updateDmr2ysfClock(){if(!dmr2ysfCurrentlyActive){const now=new Date();const clk=document.getElementById('dmr2ysfNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('dmr2ysfNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateDmr2ysfClock,1000);updateDmr2ysfClock();

// ── Display ──
function showDmr2ysfIdle(){dmr2ysfCurrentlyActive=false;animateDmr2ysfVU(false);document.getElementById('dmr2ysfTxBar').className='nx-txbar';document.getElementById('dmr2ysfTGLabel').textContent='—';document.getElementById('dmr2ysfSource').textContent='';document.getElementById('dmr2ysfSource').className='nx-source';document.getElementById('dmr2ysfNxCenter').innerHTML='<div class="nx-clock" id="dmr2ysfNxClock" style="color:#00ffcc;">00:00:00</div><div class="nx-date" id="dmr2ysfNxDate" style="color:#007060;">—</div>';updateDmr2ysfClock();}
function showDmr2ysfActive(d){dmr2ysfCurrentlyActive=true;animateDmr2ysfVU(true);document.getElementById('dmr2ysfTxBar').className='nx-txbar active-dmr2ysf';document.getElementById('dmr2ysfTGLabel').textContent=d.tg?'TG '+d.tg:'—';const src=document.getElementById('dmr2ysfSource');const isYsf=d.source==='YSF';src.textContent=isYsf?'YSF':'DMR';src.className='nx-source '+(isYsf?'net':'rf');const flag=getFlagByCall(d.callsign);document.getElementById('dmr2ysfNxCenter').innerHTML=`<div class="nx-callsign">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name">${esc(d.name)}</div>`:'');}

function renderDmr2ysfLastHeard(list,activeCall){const body=document.getElementById('dmr2ysfLhBody');if(!list||!list.length){body.innerHTML='<div style="padding:1.5rem 1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const dot=isActive?'<span class="lh-tx-dot-dmr2ysf"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row-dmr2ysf${isActive?' lh-active':''}"><div style="display:flex;align-items:center;gap:.35rem;">${dot}<span class="lh-call-dmr2ysf">${flag} ${esc(r.callsign)}</span></div><span style="font-family:var(--font-mono);font-size:.82rem;color:var(--text);">${esc(r.name||'—')}</span><span style="font-family:var(--font-mono);font-size:.72rem;color:#00ffcc;">${esc(r.tg||'—')}</span><span style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);">${esc(r.time||'—')}</span><span style="font-family:var(--font-mono);font-size:.6rem;" class="nx-source rf">DMR</span></div>`;}).join('');}

// ── Status / Toggle ──
let dmr2ysfRunning=false,dmr2ysfTimer=null,dmr2ysfTxTimer=null;

function setDMR2YSFToggle(on){
    const chk=document.getElementById('chkDMR2YSF'),sta=document.getElementById('dmr2ysfToggleStatus');
    chk.checked=on;sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';
    const track=document.querySelector('#swDMR2YSF .sw-track');
    const knob=document.querySelector('#swDMR2YSF .sw-knob');
    if(track)track.style.borderColor=on?'#00ff4c':'#ff4560';
    if(knob){knob.style.background=on?'#00ff4c':'#ff4560';knob.style.transform=on?'translateX(28px)':'translateX(0)';}
}

async function checkDmr2ysfStatus(){try{const r=await fetch('?action=dmr2ysf-status');const d=await r.json();const active=d.dmr2ysf==='active';setDot('dot-dmr2ysf-mmd',d.s1==='active'?'active':'off');setDot('dot-dmr2ysf-ysf',d.s2==='active'?'active':'off');setDot('dot-dmr2ysf',d.s3==='active'?'active':'off');dmr2ysfRunning=active;setDMR2YSFToggle(active);if(active){startDmr2ysfLogs();startDmr2ysfTxPoll();}}catch(e){}}

async function toggleDMR2YSF(chk){
    const wasOn=!chk.checked;const sw=document.getElementById('swDMR2YSF');chk.checked=wasOn;sw.classList.add('busy');
    try{
        await fetch(wasOn?'?action=dmr2ysf-stop':'?action=dmr2ysf-start');
        let ok=false;
        for(let i=0;i<15;i++){
            await new Promise(r=>setTimeout(r,1000));
            const r=await fetch('?action=dmr2ysf-status');const d=await r.json();const isOn=d.dmr2ysf==='active';
            if(wasOn&&!isOn){ok=true;setDot('dot-dmr2ysf-mmd','off');setDot('dot-dmr2ysf-ysf','off');setDot('dot-dmr2ysf','off');dmr2ysfRunning=false;setDMR2YSFToggle(false);stopDmr2ysfLogs();stopDmr2ysfTxPoll();showDmr2ysfIdle();clearLog('logDmr2ysf');clearLog('logYsfGwDmr2ysf');clearLog('logMmdvmDmr2ysf');break;}
            if(!wasOn&&isOn){ok=true;setDot('dot-dmr2ysf-mmd',d.s1==='active'?'active':'off');setDot('dot-dmr2ysf-ysf',d.s2==='active'?'active':'off');setDot('dot-dmr2ysf',d.s3==='active'?'active':'off');dmr2ysfRunning=true;setDMR2YSFToggle(true);startDmr2ysfLogs();startDmr2ysfTxPoll();break;}
        }
        if(!ok)await checkDmr2ysfStatus();
    }catch(e){}finally{sw.classList.remove('busy');}
}

async function fetchDmr2ysfLogs(){
    try{const r=await fetch('?action=dmr2ysf-logs&lines=30');const d=await r.json();const el=document.getElementById('logDmr2ysf');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.dmr2ysf);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}
    try{const r2=await fetch('?action=ysfgw-dmr2ysf-logs&lines=30');const d2=await r2.json();const el2=document.getElementById('logYsfGwDmr2ysf');const atBot2=el2.scrollHeight-el2.clientHeight<=el2.scrollTop+10;el2.innerHTML=colorize(d2.ysfgwdmr2ysf);if(atBot2)el2.scrollTop=el2.scrollHeight;}catch(e){}
    try{const r3=await fetch('?action=mmdvmdmr2ysf-logs&lines=30');const d3=await r3.json();const el3=document.getElementById('logMmdvmDmr2ysf');const atBot3=el3.scrollHeight-el3.clientHeight<=el3.scrollTop+10;el3.innerHTML=colorize(d3.mmdvmdmr2ysf);if(atBot3)el3.scrollTop=el3.scrollHeight;}catch(e){}
}
async function fetchDmr2ysfTransmission(){try{const r=await fetch('?action=dmr2ysf-transmission');const d=await r.json();if(d.active){dmr2ysfLastActiveTs=Date.now();showDmr2ysfActive(d);}else{if(dmr2ysfCurrentlyActive)showDmr2ysfIdle();}renderDmr2ysfLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){if(dmr2ysfCurrentlyActive&&(Date.now()-dmr2ysfLastActiveTs)>DMR2YSF_IDLE_TIMEOUT)showDmr2ysfIdle();}}
function startDmr2ysfLogs(){fetchDmr2ysfLogs();dmr2ysfTimer=setInterval(fetchDmr2ysfLogs,5000);}
function stopDmr2ysfLogs(){clearInterval(dmr2ysfTimer);dmr2ysfTimer=null;}
function startDmr2ysfTxPoll(){fetchDmr2ysfTransmission();dmr2ysfTxTimer=setInterval(fetchDmr2ysfTransmission,1500);}
function stopDmr2ysfTxPoll(){clearInterval(dmr2ysfTxTimer);dmr2ysfTxTimer=null;}

// ── Info display ──
async function loadDmr2ysfInfo(){try{const r=await fetch('?action=dmr2ysf-info');const d=await r.json();document.getElementById('dmr2ysfDmrId').textContent=d.dmrId||'—';document.getElementById('dmr2ysfGw').textContent=d.gw||'—';document.getElementById('dmr2ysfDefTG').textContent=d.defTG||'—';document.getElementById('dmr2ysfYsfGw').textContent='YSF: '+(d.ysfGw||'—');}catch(e){}}

// ── Modal Config MMDVMDMR2YSF ──
const d2cfgFields=['Callsign','Id','Timeout','Duplex','RXFrequency','TXFrequency','DmrEnable','DmrType','DmrLocalAddr','DmrLocalPort','DmrRemoteAddr','DmrRemotePort','DmrPassword','DmrJitter','UARTPort'];
async function openMmdvmDmr2ysfCfg(){
    const modal=document.getElementById('mmdvmD2yCfgModal');const msg=document.getElementById('d2cfgMsg');msg.style.display='none';modal.style.display='flex';
    d2cfgFields.forEach(f=>{const el=document.getElementById('d2cfg_'+f);if(el)el.value='';});
    try{const r=await fetch('?action=mmdvmdmr2ysf-config-read');const d=await r.json();d2cfgFields.forEach(f=>{const el=document.getElementById('d2cfg_'+f);if(!el||d[f]===undefined)return;if(el.tagName==='SELECT'){let found=false;for(const opt of el.options){if(opt.value===d[f]){opt.selected=true;found=true;break;}}if(!found){const opt=document.createElement('option');opt.value=d[f];opt.textContent=d[f];el.appendChild(opt);opt.selected=true;}}else el.value=d[f];});}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error al leer el fichero';}
}
function closeMmdvmDmr2ysfCfg(){document.getElementById('mmdvmD2yCfgModal').style.display='none';}
async function saveMmdvmDmr2ysfCfg(){
    const msg=document.getElementById('d2cfgMsg');msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--amber);color:var(--amber);background:rgba(255,179,0,.06);';msg.textContent='⏳ Guardando…';
    const body=d2cfgFields.map(f=>{const el=document.getElementById('d2cfg_'+f);return el?encodeURIComponent(f)+'='+encodeURIComponent(el.value):'';}).filter(Boolean).join('&');
    try{const r=await fetch('?action=mmdvmdmr2ysf-config-save',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const d=await r.json();if(d.ok){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid #00ffcc;color:#00ffcc;background:rgba(0,255,204,.06);';msg.textContent='✔ Guardado correctamente';setTimeout(()=>msg.style.display='none',3000);}else{msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ '+(d.msg||'Error');}}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error de red';}
}

// ── Modal TG-YSF ──
let _tgYsfEntries=[],_tgYsfHosts=[],_tgYsfHostsLoaded=false;
function openTgYsfModal(){document.getElementById('tgYsfModal').style.display='flex';document.getElementById('tgYsfMsg').style.display='none';document.getElementById('tgYsfHostPanel').style.display='none';tgYsfLoad();}
function closeTgYsfModal(){document.getElementById('tgYsfModal').style.display='none';}
async function tgYsfLoad(){try{const r=await fetch('?action=tgysf-read');const d=await r.json();_tgYsfEntries=d.entries||[];tgYsfRender();}catch(e){}}
function tgYsfRender(){const c=document.getElementById('tgYsfRows');if(!_tgYsfEntries.length){c.innerHTML='<div style="padding:1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin entradas</div>';return;}c.innerHTML=_tgYsfEntries.map((e,i)=>`<div style="display:grid;grid-template-columns:90px 110px 1fr 36px;padding:.38rem .8rem;border-bottom:1px solid #00ffcc11;align-items:center;gap:.5rem;"><span style="font-family:var(--font-mono);font-size:.82rem;color:var(--d2y);">${esc(e.tg)}</span><span style="font-family:var(--font-mono);font-size:.82rem;color:#80ffe8;">${esc(e.ysf)}</span><input type="text" value="${esc(e.name||'')}" placeholder="—" onchange="_tgYsfEntries[${i}].name=this.value" style="background:transparent;border:none;border-bottom:1px solid #00ffcc22;color:#a8b9cc;font-family:var(--font-mono);font-size:.78rem;padding:.15rem .2rem;outline:none;width:100%;"><button onclick="tgYsfRemove(${i})" style="background:transparent;border:1px solid #ff456044;border-radius:3px;color:#ff4560;font-size:.7rem;cursor:pointer;padding:.15rem .3rem;">✖</button></div>`).join('');}
function tgYsfAdd(){const tg=document.getElementById('tgYsfNewTG').value.trim();const ysf=document.getElementById('tgYsfNewYSF').value.trim();const name=document.getElementById('tgYsfNewName').value.trim();if(!tg||!ysf||isNaN(tg)||isNaN(ysf)){tgYsfShowMsg('Introduce valores numéricos válidos',false);return;}if(_tgYsfEntries.some(e=>e.tg===tg)){tgYsfShowMsg('El TG '+tg+' ya existe',false);return;}_tgYsfEntries.push({tg,ysf,name});document.getElementById('tgYsfNewTG').value='';document.getElementById('tgYsfNewYSF').value='';document.getElementById('tgYsfNewName').value='';tgYsfRender();}
function tgYsfRemove(i){_tgYsfEntries.splice(i,1);tgYsfRender();}
async function tgYsfSave(){try{const r=await fetch('?action=tgysf-save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({entries:_tgYsfEntries})});const d=await r.json();tgYsfShowMsg(d.msg,d.ok);}catch(e){tgYsfShowMsg('Error de red',false);}}
async function tgYsfToggleHosts(){const p=document.getElementById('tgYsfHostPanel');const v=p.style.display!=='none';p.style.display=v?'none':'block';if(!v&&!_tgYsfHostsLoaded)await tgYsfLoadHosts();}
async function tgYsfLoadHosts(){document.getElementById('tgYsfHostList').innerHTML='<div style="color:var(--text-dim);text-align:center;padding:.5rem;">Cargando…</div>';try{const r=await fetch('?action=tgysf-hosts');const d=await r.json();_tgYsfHosts=d.hosts||[];_tgYsfHostsLoaded=true;tgYsfRenderHosts(_tgYsfHosts);}catch(e){document.getElementById('tgYsfHostList').innerHTML='<div style="color:var(--red);text-align:center;padding:.5rem;">Error</div>';}}
function tgYsfFilterHosts(q){const term=q.trim().toLowerCase();tgYsfRenderHosts(term===''?_tgYsfHosts:_tgYsfHosts.filter(h=>String(h.id).includes(term)||h.name.toLowerCase().includes(term)||h.desc.toLowerCase().includes(term)||h.country.toLowerCase().includes(term)));}

// ── FIX: usar data-attributes para evitar truncado de nombres con guiones u otros caracteres ──
function tgYsfRenderHosts(list){
    const el=document.getElementById('tgYsfHostList');
    if(!list.length){
        el.innerHTML='<div style="color:var(--text-dim);text-align:center;padding:.5rem;">Sin resultados</div>';
        return;
    }
    el.innerHTML=list.map(h=>{
        const nm=h.name||'—';
        const desc=h.desc?' · '+h.desc:'';
        const flag=h.country==='ES'?'🇪🇸 ':h.country?h.country+' ':'';
        return `<div
            data-id="${h.id}"
            data-name="${esc(nm)}"
            onclick="tgYsfSelectHostEl(this)"
            style="padding:.35rem .6rem;cursor:pointer;border-bottom:1px solid #00ffcc11;display:flex;gap:.8rem;align-items:center;"
            onmouseover="this.style.background='rgba(0,255,204,.08)'"
            onmouseout="this.style.background='transparent'">
            <span style="color:var(--d2y);min-width:52px;">${h.id}</span>
            <span style="color:#80ffe8;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${flag}${esc(nm)}${esc(desc)}</span>
            <span style="color:var(--text-dim);font-size:.6rem;">${h.country}</span>
        </div>`;
    }).join('');
}

// Lee el nombre desde el atributo data-name — sin riesgo de truncado
function tgYsfSelectHostEl(el){
    document.getElementById('tgYsfNewYSF').value=el.dataset.id;
    document.getElementById('tgYsfNewName').value=el.dataset.name;
    document.getElementById('tgYsfHostPanel').style.display='none';
    document.getElementById('tgYsfSearch').value='';
    document.getElementById('tgYsfNewTG').focus();
}

function tgYsfShowMsg(msg,ok){const el=document.getElementById('tgYsfMsg');el.textContent=(ok?'✔ ':'✖ ')+msg;el.style.display='block';el.style.color=ok?'var(--green)':'var(--red)';el.style.borderColor=ok?'var(--green)':'var(--red)';el.style.background=ok?'rgba(0,255,159,.06)':'rgba(255,69,96,.06)';if(ok)setTimeout(()=>el.style.display='none',3000);}

// ── fedit ──
async function feditOpen(path){const msg=document.getElementById('feditMsg');msg.style.display='none';document.getElementById('feditPath').textContent=path;document.getElementById('feditArea').value='Cargando…';document.getElementById('feditModal').style.display='flex';try{const r=await fetch('mmdvm.php?action=read-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)});const d=await r.json();if(d.ok){document.getElementById('feditArea').value=d.content;}else{document.getElementById('feditArea').value='';msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ '+d.msg;}}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error: '+e.message;}}
async function feditSave(){const path=document.getElementById('feditPath').textContent;const content=document.getElementById('feditArea').value;const msg=document.getElementById('feditMsg');try{const r=await fetch('mmdvm.php?action=save-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)+'&content='+encodeURIComponent(content)});const d=await r.json();msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid '+(d.ok?'var(--green)':'var(--red)')+';color:'+(d.ok?'var(--green)':'var(--red)')+';background:'+(d.ok?'rgba(0,255,159,.06)':'rgba(255,69,96,.06)')+';';msg.textContent=(d.ok?'✔ ':'✖ ')+d.msg;if(d.ok)setTimeout(()=>msg.style.display='none',3000);}catch(e){msg.style.cssText='font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid var(--red);color:var(--red);background:rgba(255,69,96,.06);';msg.textContent='✖ Error: '+e.message;}}
function feditClose(){document.getElementById('feditModal').style.display='none';}

// ── Init ──
(async()=>{
    await loadDmr2ysfInfo();
    setInterval(loadDmr2ysfInfo,60000);
    await checkDmr2ysfStatus();
    setInterval(checkDmr2ysfStatus,10000);
    showDmr2ysfIdle();
})();
</script>
</body>
</html>
