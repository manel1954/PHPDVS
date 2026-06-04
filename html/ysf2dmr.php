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

// ── Acciones YSF2DMR ──────────────────────────────────────────────────────────
if ($action === 'ysf2dmr-status') {
    $s = trim(shell_exec('systemctl is-active ysf2dmr 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['ysf2dmr' => $s]);
    exit;
}
if ($action === 'ysf2dmr-start') {
    saveState('ysf2dmr','on');
    shell_exec('sudo systemctl enable ysf2dmr 2>/dev/null');
    shell_exec('sudo systemctl start ysf2dmr 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'ysf2dmr-stop') {
    saveState('ysf2dmr','off');
    shell_exec('sudo systemctl stop ysf2dmr 2>/dev/null');
    shell_exec('sudo systemctl disable ysf2dmr 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}
if ($action === 'ysf2dmr-logs-mmdvm') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u ysf2dmr -n {$lines} --no-pager --output=short 2>/dev/null | grep -i MMDVMHost");
    if (empty(trim($log))) {
        $logFiles = glob('/home/pi/MMDVMHost/MMDVMYSF2DMR-*.log');
        if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n {$lines} ".escapeshellarg($latest)." 2>/dev/null"); }
    }
    header('Content-Type: application/json');
    echo json_encode(['log' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'ysf2dmr-logs-ysf') {
    $lines = intval($_GET['lines'] ?? 30);
    $log = shell_exec("sudo journalctl -u ysf2dmr -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) {
        $logFiles = glob('/home/pi/MMDVM_CM/YSF2DMR/YSF2DMR-*.log');
        if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n {$lines} ".escapeshellarg($latest)." 2>/dev/null"); }
    }
    header('Content-Type: application/json');
    echo json_encode(['log' => htmlspecialchars($log ?? '')]);
    exit;
}
if ($action === 'ysf2dmr-transmission') {
    $stateFile = '/tmp/ysf2dmr_tx_state.json';
    $lhFile    = '/tmp/ysf2dmr_lastheard.json';
    $log = shell_exec("sudo journalctl -u ysf2dmr -n 200 --no-pager --output=short 2>/dev/null") ?? '';
    if (empty(trim($log))) {
        $logFiles = glob('/home/pi/MMDVM_CM/YSF2DMR/YSF2DMR-*.log');
        if ($logFiles) { $latest = end($logFiles); $log = shell_exec("tail -n 200 ".escapeshellarg($latest)." 2>/dev/null") ?? ''; }
    }
    $lines = array_reverse(explode("\n", $log));
    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','source'=>''];
    if (file_exists($stateFile)) { $saved = json_decode(file_get_contents($stateFile), true); if (is_array($saved)) $state = $saved; }
    foreach ($lines as $line) {
        if (preg_match('/end of voice|lost|watchdog|timeout/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/DMR audio.*from\s+([A-Z0-9]+).*TG\s+(\d+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>$m[2],'source'=>'DMR'];
            file_put_contents($stateFile,json_encode($state));break;
        }
        if (preg_match('/YSF.*Src:\s+([A-Z0-9]+)/i', $line, $m)) {
            $cs=strtoupper(trim($m[1]));$inf=lookupCall($cs);
            $state=['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>'','source'=>'YSF'];
            file_put_contents($stateFile,json_encode($state));break;
        }
    }
    $lastHeard=[]; $seen=[];
    foreach ($lines as $line) {
        $cs=''; $time=''; $tgr=''; $src='YSF';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*DMR audio.*from\s+([A-Z0-9]+).*TG\s+(\d+)/i',$line,$m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$tgr=$m[3];$src='DMR'; }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*Src:\s+([A-Z0-9]+)/i',$line,$m))
            { $time=$m[1];$cs=strtoupper(trim($m[2]));$src='YSF'; }
        if ($cs && !in_array($cs,$seen)) { $inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$tgr,'source'=>$src,'time'=>$time];$seen[]=$cs;if(count($lastHeard)>=5)break; }
    }
    if (!empty($lastHeard)) file_put_contents($lhFile, json_encode($lastHeard));
    elseif (file_exists($lhFile)) $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json');
    echo json_encode($state);
    exit;
}
// ── Config MMDVMYSF2DMR.ini ───────────────────────────────────────────────────
function parseIni($path) {
    $r=[]; if(!file_exists($path))return $r; $sec='';
    foreach(file($path,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
        $line=trim($line);
        if($line===''||$line[0]==='#'||$line[0]===';')continue;
        if(preg_match('/^\[(.+)\]$/',$line,$m)){$sec=trim($m[1]);continue;}
        if(preg_match('/^([^=]+)=(.*)$/',$line,$m))$r[$sec][trim($m[1])]=trim($m[2]);
    }
    return $r;
}
function saveIni($path, $posts, $map) {
    if(!file_exists($path))return false;
    $lines=explode("\n",file_get_contents($path));
    $sec='';
    foreach($lines as &$line){
        $t=trim($line);
        if(preg_match('/^\[(.+)\]$/',$t,$m)){$sec=trim($m[1]);continue;}
        if(preg_match('/^([^=;#]+)=(.*)$/',$t,$m)){
            $key=trim($m[1]);
            if(isset($map[$sec][$key])&&isset($posts[$map[$sec][$key]]))
                $line=$key.'='.trim($posts[$map[$sec][$key]]);
        }
    }
    unset($line);
    $nc=implode("\n",$lines);
    $r=@file_put_contents($path,$nc);
    if($r===false){$tmp=tempnam('/tmp','ysf_');file_put_contents($tmp,$nc);shell_exec("sudo /bin/cp ".escapeshellarg($tmp)." ".escapeshellarg($path));@unlink($tmp);}
    return true;
}

if($action==='mmdvmysf2dmr-config-read'){
    $p='/home/pi/MMDVMHost/MMDVMYSF2DMR.ini'; $i=parseIni($p);
    header('Content-Type: application/json');
    echo json_encode([
        'ok'=>file_exists($p),
        'Callsign'       =>$i['General']['Callsign']??'',
        'Id'             =>$i['General']['Id']??'',
        'Timeout'        =>$i['General']['Timeout']??'180',
        'Duplex'         =>$i['General']['Duplex']??'0',
        'RFModeHang'     =>$i['General']['RFModeHang']??'5',
        'NetModeHang'    =>$i['General']['NetModeHang']??'3',
        'RXFrequency'    =>$i['Info']['RXFrequency']??'',
        'TXFrequency'    =>$i['Info']['TXFrequency']??'',
        'Latitude'       =>$i['Info']['Latitude']??'',
        'Longitude'      =>$i['Info']['Longitude']??'',
        'Location'       =>$i['Info']['Location']??'',
        'Description'    =>$i['Info']['Description']??'',
        'URL'            =>$i['Info']['URL']??'',
        'UARTPort'       =>$i['Modem']['UARTPort']??'',
        'TXDelay'        =>$i['Modem']['TXDelay']??'100',
        'RXLevel'        =>$i['Modem']['RXLevel']??'50',
        'TXLevel'        =>$i['Modem']['TXLevel']??'50',
        'RXOffset'       =>$i['Modem']['RXOffset']??'0',
        'TXOffset'       =>$i['Modem']['TXOffset']??'0',
        'YsfEnable'      =>$i['System Fusion']['Enable']??'1',
        'YsfTXHang'      =>$i['System Fusion']['TXHang']??'4',
        'DmrNetEnable'   =>$i['DMR Network']['Enable']??'1',
        'DmrNetType'     =>$i['DMR Network']['Type']??'Direct',
        'DmrLocalPort'   =>$i['DMR Network']['LocalPort']??'62042',
        'DmrRemoteAddr'  =>$i['DMR Network']['RemoteAddress']??'',
        'DmrRemotePort'  =>$i['DMR Network']['RemotePort']??'62041',
        'DmrPassword'    =>$i['DMR Network']['Password']??'',
        'YsfNetLocalPort'=>$i['System Fusion Network']['LocalPort']??'32013',
        'YsfNetGwAddr'   =>$i['System Fusion Network']['GatewayAddress']??'127.0.0.1',
        'YsfNetGwPort'   =>$i['System Fusion Network']['GatewayPort']??'42013',
    ]);
    exit;
}
if($action==='mmdvmysf2dmr-config-save'){
    $p='/home/pi/MMDVMHost/MMDVMYSF2DMR.ini';
    $map=[
        'General'              =>['Callsign'=>'Callsign','Id'=>'Id','Timeout'=>'Timeout','Duplex'=>'Duplex','RFModeHang'=>'RFModeHang','NetModeHang'=>'NetModeHang'],
        'Info'                 =>['RXFrequency'=>'RXFrequency','TXFrequency'=>'TXFrequency','Latitude'=>'Latitude','Longitude'=>'Longitude','Location'=>'Location','Description'=>'Description','URL'=>'URL'],
        'Modem'                =>['UARTPort'=>'UARTPort','TXDelay'=>'TXDelay','RXLevel'=>'RXLevel','TXLevel'=>'TXLevel','RXOffset'=>'RXOffset','TXOffset'=>'TXOffset'],
        'System Fusion'        =>['Enable'=>'YsfEnable','TXHang'=>'YsfTXHang'],
        'DMR Network'          =>['Enable'=>'DmrNetEnable','Type'=>'DmrNetType','LocalPort'=>'DmrLocalPort','RemoteAddress'=>'DmrRemoteAddr','RemotePort'=>'DmrRemotePort','Password'=>'DmrPassword'],
        'System Fusion Network'=>['LocalPort'=>'YsfNetLocalPort','GatewayAddress'=>'YsfNetGwAddr','GatewayPort'=>'YsfNetGwPort'],
    ];
    $ok=saveIni($p,$_POST,$map);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>$ok,'msg'=>$ok?'Guardado correctamente':'Error al guardar']);
    exit;
}

// ── Config YSF2DMR.ini ────────────────────────────────────────────────────────
if($action==='ysf2dmr-config-read'){
    $p='/home/pi/MMDVM_CM/YSF2DMR/YSF2DMR.ini'; $i=parseIni($p);
    header('Content-Type: application/json');
    echo json_encode([
        'ok'=>file_exists($p),
        'Callsign'      =>$i['YSF Network']['Callsign']??'',
        'Suffix'        =>$i['YSF Network']['Suffix']??'ND',
        'YsfDstAddr'    =>$i['YSF Network']['DstAddress']??'127.0.0.1',
        'YsfDstPort'    =>$i['YSF Network']['DstPort']??'32013',
        'YsfLocalPort'  =>$i['YSF Network']['LocalPort']??'42013',
        'EnableWiresX'  =>$i['YSF Network']['EnableWiresX']??'1',
        'HangTime'      =>$i['YSF Network']['HangTime']??'1000',
        'RXFrequency'   =>$i['Info']['RXFrequency']??'',
        'TXFrequency'   =>$i['Info']['TXFrequency']??'',
        'Latitude'      =>$i['Info']['Latitude']??'',
        'Longitude'     =>$i['Info']['Longitude']??'',
        'Location'      =>$i['Info']['Location']??'',
        'Description'   =>$i['Info']['Description']??'',
        'URL'           =>$i['Info']['URL']??'',
        'DmrId'         =>$i['DMR Network']['Id']??'',
        'DmrAddress'    =>$i['DMR Network']['Address']??'',
        'DmrPort'       =>$i['DMR Network']['Port']??'55555',
        'DmrPassword'   =>$i['DMR Network']['Password']??'',
        'StartupDstId'  =>$i['DMR Network']['StartupDstId']??'9',
        'Options'       =>$i['DMR Network']['Options']??'',
        'TGUnlink'      =>$i['DMR Network']['TGUnlink']??'4000',
        'EnableUnlink'  =>$i['DMR Network']['EnableUnlink']??'1',
    ]);
    exit;
}
if($action==='ysf2dmr-config-save'){
    $p='/home/pi/MMDVM_CM/YSF2DMR/YSF2DMR.ini';
    $map=[
        'Info'        =>['RXFrequency'=>'RXFrequency','TXFrequency'=>'TXFrequency','Latitude'=>'Latitude','Longitude'=>'Longitude','Location'=>'Location','Description'=>'Description','URL'=>'URL'],
        'YSF Network' =>['Callsign'=>'Callsign','Suffix'=>'Suffix','DstAddress'=>'YsfDstAddr','DstPort'=>'YsfDstPort','LocalPort'=>'YsfLocalPort','EnableWiresX'=>'EnableWiresX','HangTime'=>'HangTime'],
        'DMR Network' =>['Id'=>'DmrId','Address'=>'DmrAddress','Port'=>'DmrPort','Password'=>'DmrPassword','StartupDstId'=>'StartupDstId','Options'=>'Options','TGUnlink'=>'TGUnlink','EnableUnlink'=>'EnableUnlink'],
    ];
    $ok=saveIni($p,$_POST,$map);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>$ok,'msg'=>$ok?'Guardado correctamente':'Error al guardar']);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>YSF2DMR · Panel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#032354;--surface:#111720;--border:#1e2d3d;--green:#00ff9f;--red:#ff4560;--amber:#ffb300;--cyan:#00d4ff;--violet:#b57aff;--text:#a8b9cc;--text-dim:#4a5568;--y2d:#ff9900;--font-mono:'Share Tech Mono',monospace;--font-ui:'Rajdhani',sans-serif;--font-orb:'Orbitron',monospace;}
*{box-sizing:border-box;}
body{background:#00004d;color:var(--text);font-family:var(--font-ui);font-size:1rem;min-height:100vh;padding:0;margin:0;}
.ctrl-header{border-bottom:2px solid var(--y2d);padding:1rem 2rem;display:flex;align-items:center;gap:.8rem;background:#000;}
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
.nextion-y2d{background:#060e0c;border:2px solid #ff990044;border-radius:6px;box-shadow:0 0 0 1px #301800,inset 0 0 40px rgba(255,153,0,.04),0 0 30px rgba(255,153,0,.1);position:relative;overflow:hidden;height:240px;display:flex;align-items:center;justify-content:center;}
.nextion-y2d::before,.nextion-y2d::after{content:'◈';position:absolute;font-size:.6rem;color:#ff990033;}
.nextion-y2d::before{top:.5rem;left:.7rem;}
.nextion-y2d::after{bottom:.5rem;right:.7rem;}
.nx-topbar-y2d{background:#1a0f00;border-bottom:1px solid #ff990033;color:#7a4400;position:absolute;top:0;left:0;right:0;height:30px;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-family:var(--font-mono);font-size:.65rem;letter-spacing:.1em;}
.nx-topbar-y2d .nx-mode{color:var(--y2d);opacity:.8;}
.nx-botbar-y2d{background:#0d0600;border-top:1px solid #ff990033;color:#7a4400;position:absolute;bottom:0;left:0;right:0;height:28px;display:flex;align-items:center;justify-content:space-between;padding:0 1rem;font-family:var(--font-mono);font-size:.65rem;letter-spacing:.08em;}
.nx-infobar-y2d{position:absolute;top:30px;left:0;right:0;height:26px;background:rgba(0,0,0,.4);border-bottom:1px solid #301800;display:flex;align-items:center;justify-content:space-around;padding:0 8rem;gap:1rem;z-index:2;}
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
.nx-clock{font-family:var(--font-orb);font-size:4rem;font-weight:700;color:#ff9900;letter-spacing:.06em;line-height:1;}
.nx-date{font-family:var(--font-mono);font-size:.7rem;color:#7a4400;letter-spacing:.12em;text-transform:uppercase;margin-top:.2rem;}
.nx-callsign{font-family:var(--font-orb);font-size:3.4rem;font-weight:900;letter-spacing:.04em;line-height:1;color:var(--y2d);text-shadow:0 0 20px rgba(255,153,0,.6);}
.nx-name{font-family:var(--font-ui);font-weight:500;font-size:1.2rem;color:#ffcc80;letter-spacing:.18em;text-transform:uppercase;opacity:.9;margin-top:.15rem;}
.nx-txbar{position:absolute;bottom:28px;left:0;right:0;height:3px;}
.nx-txbar.active-y2d{background:linear-gradient(90deg,transparent,var(--y2d),transparent);background-size:200% 100%;animation:scan 1.4s linear infinite;}
@keyframes scan{from{background-position:200% 0}to{background-position:-200% 0}}
.nx-source{padding:.1rem .45rem;border-radius:2px;font-size:.6rem;letter-spacing:.1em;}
.nx-source.rf{background:rgba(0,255,159,.15);color:var(--green);border:1px solid rgba(0,255,159,.3);}
.nx-source.net{background:rgba(255,153,0,.15);color:var(--y2d);border:1px solid rgba(255,153,0,.3);}
/* Last heard */
.lh-panel-y2d{background:var(--surface);border:3px solid #ff990033;border-radius:6px;display:flex;flex-direction:column;}
.lh-header-y2d{background:#1a0f00;border-bottom:1px solid #ff990033;padding:.4rem 1rem;display:grid;grid-template-columns:1.2fr 1.8fr .8fr 1fr .6fr;gap:.3rem;font-family:var(--font-mono);font-size:.6rem;color:#7a4400;letter-spacing:.1em;text-transform:uppercase;}
.lh-row-y2d{display:grid;grid-template-columns:1.2fr 1.8fr .8fr 1fr .6fr;gap:.3rem;padding:.45rem 1rem;border-bottom:1px solid rgba(255,153,0,.1);align-items:center;}
.lh-call-y2d{font-family:var(--font-mono);font-size:.82rem;color:var(--y2d);letter-spacing:.05em;font-weight:bold;}
/* Logs */
.log-panel{background:var(--surface);border:1px solid var(--border);border-radius:4px;overflow:hidden;margin-bottom:1rem;}
.log-panel-header{display:flex;align-items:center;justify-content:space-between;padding:.5rem 1rem;border-bottom:1px solid var(--border);background:rgba(0,0,0,.3);}
.svc-name{font-family:var(--font-mono);font-size:.8rem;letter-spacing:.1em;color:var(--y2d);text-transform:uppercase;}
.btn-clear{font-family:var(--font-mono);font-size:.7rem;color:var(--text-dim);background:none;border:none;cursor:pointer;}
.log-output{font-family:var(--font-mono);font-size:.72rem;line-height:1.55;color:#7a9ab5;padding:.8rem 1rem;height:190px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;}
.ln-info{color:#7a9ab5;}.ln-warn{color:var(--amber);}.ln-err{color:var(--red);}.ln-ok{color:#00cc7a;}
/* Status */
.status-item{display:flex;align-items:center;gap:.5rem;font-family:var(--font-mono);font-size:12px;text-transform:uppercase;letter-spacing:.08em;}
.dot{width:10px;height:10px;border-radius:50%;background:var(--text-dim);transition:background .4s,box-shadow .4s;}
.dot.active{background:var(--green);box-shadow:0 0 8px var(--green);animation:pulse 2s infinite;}
.dot.error{background:var(--red);box-shadow:0 0 8px var(--red);}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.display-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.5rem;}
.card{background:var(--surface);border:1px solid #ff990033;border-radius:8px;padding:1.2rem 1.6rem;margin-bottom:1.2rem;}
.ini-btn{font-family:var(--font-mono);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;padding:.3rem .7rem;border-radius:3px;border:1px solid rgba(255,153,0,.3);background:transparent;cursor:pointer;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:.3rem;color:var(--y2d);}
.ini-btn:hover{border-color:var(--y2d);background:rgba(255,153,0,.08);}
@media(max-width:900px){.display-grid{grid-template-columns:1fr;}}
.flag-emoji{font-family:'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif;font-size:1.6rem;display:inline-block;vertical-align:middle;margin-right:4px;line-height:1;}
.flag-emoji-img{height:20px;width:auto;vertical-align:middle;margin-right:4px;border-radius:2px;}
.nx-callsign .flag-emoji{font-size:3.2rem;}
.nx-callsign .flag-emoji-img{height:42px;}
</style>
</head>
<body>
<header class="ctrl-header">
  <a href="mmdvm.php" style="background:#1a2535;color:var(--y2d);border:1px solid rgba(255,153,0,.3);font-family:var(--font-mono);font-size:.75rem;padding:.35rem .9rem;border-radius:4px;text-decoration:none;">← Panel PHPPLUS</a>
  <span style="font-family:var(--font-orb);color:var(--y2d);font-size:1.2rem;letter-spacing:.1em;">YSF2DMR · CROSS-MODE BRIDGE</span>
  <div style="margin-left:auto;display:flex;align-items:center;gap:.8rem;">
    <div class="status-item"><div class="dot" id="dot-ysf2dmr"></div><span style="color:var(--y2d);">ysf2dmr</span></div>
    <label class="sw" id="swYSF2DMR">
      <input type="checkbox" id="chkYSF2DMR" onchange="toggleYSF2DMR(this)">
      <span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span>
    </label>
    <span class="toggle-status" id="ysf2dmrToggleStatus">OFF</span>
  </div>
</header>

<div class="ctrl-body">

  <!-- Configuración -->
  <div class="card" style="border-color:#ff990033;margin-bottom:1.2rem;">
    <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--y2d);letter-spacing:.12em;text-transform:uppercase;margin-bottom:.8rem;">▸ Configuración</div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
      <button onclick="openMmdvmYsf2dmrCfg()" class="ini-btn">⚙ MMDVMYSF2DMR CONFIG</button>
      <button onclick="openYsf2dmrCfg()" class="ini-btn">⚙ YSF2DMR CONFIG</button>
    </div>
  </div>

  <!-- Display + Last heard -->
  <div class="display-grid">
    <div>
      <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--y2d);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.5rem;">▸ YSF2DMR Display</div>
      <div class="nextion-y2d">
        <div class="nx-topbar-y2d">
          <span class="nx-mode">YSF2DMR · BRIDGE</span>
          <span style="color:var(--y2d);opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="ysf2dmrTGLabel">—</span>
        </div>
        <div class="nx-infobar-y2d">
          <span class="nx-info-item"><span class="nx-info-lbl">SERV</span><span class="nx-info-val" style="color:var(--y2d);">ysf2dmr</span></span>
        </div>
        <div class="nx-vu" id="ysf2dmrVuLeft"></div><div class="nx-vu right" id="ysf2dmrVuRight"></div>
        <div class="nx-center" id="ysf2dmrNxCenter">
          <div class="nx-clock" id="ysf2dmrNxClock">00:00:00</div>
          <div class="nx-date" id="ysf2dmrNxDate">—</div>
        </div>
        <div class="nx-txbar" id="ysf2dmrTxBar"></div>
        <div class="nx-botbar-y2d">
          <span style="color:#7a4400;font-family:var(--font-mono);font-size:.65rem;">YSF2DMR · CROSS-MODE</span>
          <span class="nx-source" id="ysf2dmrSource"></span>
        </div>
      </div>
    </div>
    <div>
      <div style="font-family:var(--font-mono);font-size:.7rem;color:var(--y2d);letter-spacing:.15em;text-transform:uppercase;margin-bottom:.5rem;">▸ Últimos escuchados</div>
      <div class="lh-panel-y2d">
        <div class="lh-header-y2d"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
        <div id="ysf2dmrLhBody" style="flex:1;overflow-y:auto;"><div style="padding:1.5rem 1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin actividad</div></div>
      </div>
    </div>
  </div>

  <!-- Logs -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="log-panel">
      <div class="log-panel-header"><span class="svc-name">▸ MMDVMHost/MMDVMYSF2DMR</span><button class="btn-clear" onclick="clearLog('logYsf2dmrMmdvm')">limpiar</button></div>
      <div class="log-output" id="logYsf2dmrMmdvm">—</div>
    </div>
    <div class="log-panel">
      <div class="log-panel-header"><span class="svc-name">▸ MMDVM_CM/YSF2DMR</span><button class="btn-clear" onclick="clearLog('logYsf2dmrYsf')">limpiar</button></div>
      <div class="log-output" id="logYsf2dmrYsf">—</div>
    </div>
  </div>

</div><!-- /ctrl-body -->


<!-- Modal MMDVMYSF2DMR.ini -->
<div id="mmdvmYsfCfgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9700;align-items:center;justify-content:center;" onclick="if(event.target===this)closeMmdvmYsf2dmrCfg()">
<div style="background:var(--surface);border:1px solid #ff990044;border-radius:8px;padding:1.5rem;width:780px;max-width:96vw;max-height:90vh;overflow-y:auto;display:flex;flex-direction:column;gap:.8rem;">
  <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--y2d);letter-spacing:.12em;text-transform:uppercase;border-bottom:1px solid #ff990033;padding-bottom:.6rem;">⚙ MMDVMYSF2DMR.ini · /home/pi/MMDVMHost/</div>
  <?php
  function yf($id,$label){return '<div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.62rem;color:#4a5568;display:block;margin-bottom:.2rem;">'.htmlspecialchars($label).'</label><input id="mmdvmYsf_'.htmlspecialchars($id).'" style="width:100%;background:#060c10;border:1px solid #ff990033;border-radius:3px;color:#ff9900;font-family:\'Share Tech Mono\',monospace;font-size:.8rem;padding:.32rem .55rem;outline:none;" onfocus="this.style.borderColor=\'#ff9900\'" onblur="this.style.borderColor=\'#ff990033\'"></div>';}
  function ys($label){return '<div style="font-family:\'Share Tech Mono\',monospace;font-size:.62rem;color:#7a4400;letter-spacing:.1em;text-transform:uppercase;margin-top:.5rem;padding-top:.3rem;border-top:1px solid #ff990022;">['.$label.']</div>';}
  echo ys('General');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.6rem;">'.yf('Callsign','Callsign').yf('Id','DMR ID').yf('Timeout','Timeout (s)').yf('Duplex','Duplex (0/1)').yf('RFModeHang','RF Mode Hang').yf('NetModeHang','Net Mode Hang').'</div>';
  echo ys('Info');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">'.yf('RXFrequency','RX Frequency (Hz)').yf('TXFrequency','TX Frequency (Hz)').yf('Latitude','Latitude').yf('Longitude','Longitude').yf('Location','Location').yf('Description','Description').'</div>';
  echo '<div style="display:grid;grid-template-columns:1fr;gap:.6rem;">'.yf('URL','URL').'</div>';
  echo ys('Modem');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.6rem;"><div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.62rem;color:#4a5568;display:block;margin-bottom:.2rem;">UARTPort</label><select id="mmdvmYsf_UARTPort" style="width:100%;background:#060c10;border:1px solid #ff990033;border-radius:3px;color:#ff9900;font-family:\'Share Tech Mono\',monospace;font-size:.8rem;padding:.32rem .55rem;outline:none;cursor:pointer;">';
  foreach(['/dev/ttyAMA0','/dev/ttyACM0','/dev/ttyACM1','/dev/ttyACM2','/dev/ttyUSB0','/dev/ttyUSB1'] as $p) echo "<option value=\"$p\">$p</option>";
  echo '</select></div>'.yf('TXDelay','TX Delay').yf('RXLevel','RX Level').yf('TXLevel','TX Level').yf('RXOffset','RX Offset').yf('TXOffset','TX Offset').'</div>';
  echo ys('System Fusion');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">'.yf('YsfEnable','Enable (0/1)').yf('YsfTXHang','TX Hang').'</div>';
  echo ys('DMR Network');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;"><div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.62rem;color:#4a5568;display:block;margin-bottom:.2rem;">Type</label><select id="mmdvmYsf_DmrNetType" style="width:100%;background:#060c10;border:1px solid #ff990033;border-radius:3px;color:#ff9900;font-family:\'Share Tech Mono\',monospace;font-size:.8rem;padding:.32rem .55rem;outline:none;cursor:pointer;"><option value="Direct">Direct</option><option value="Gateway">Gateway</option></select></div>'.yf('DmrNetEnable','Enable (0/1)').yf('DmrLocalPort','Local Port').yf('DmrRemoteAddr','Remote Address').yf('DmrRemotePort','Remote Port').yf('DmrPassword','Password').'</div>';
  echo ys('System Fusion Network');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.6rem;">'.yf('YsfNetLocalPort','Local Port').yf('YsfNetGwAddr','Gateway Address').yf('YsfNetGwPort','Gateway Port').'</div>';
  ?>
  <div id="mmdvmYsfCfgMsg" style="display:none;font-family:var(--font-mono);font-size:.75rem;padding:.4rem .8rem;border-radius:4px;border:1px solid;margin-top:.2rem;"></div>
  <div style="display:flex;gap:.8rem;margin-top:.4rem;">
    <button onclick="saveMmdvmYsf2dmrCfg()" style="flex:1;background:#ff990022;color:var(--y2d);border:1px solid #ff990055;border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">💾 Guardar</button>
    <button onclick="closeMmdvmYsf2dmrCfg()" style="flex:1;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">✖ Cerrar</button>
  </div>
</div>
</div>

<!-- Modal YSF2DMR.ini -->
<div id="ysf2dmrCfgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9700;align-items:center;justify-content:center;" onclick="if(event.target===this)closeYsf2dmrCfg()">
<div style="background:var(--surface);border:1px solid #ff990044;border-radius:8px;padding:1.5rem;width:780px;max-width:96vw;max-height:90vh;overflow-y:auto;display:flex;flex-direction:column;gap:.8rem;">
  <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--y2d);letter-spacing:.12em;text-transform:uppercase;border-bottom:1px solid #ff990033;padding-bottom:.6rem;">⚙ YSF2DMR.ini · /home/pi/MMDVM_CM/YSF2DMR/</div>
  <?php
  function yf2($id,$label){return '<div><label style="font-family:\'Share Tech Mono\',monospace;font-size:.62rem;color:#4a5568;display:block;margin-bottom:.2rem;">'.htmlspecialchars($label).'</label><input id="ysf2dmrCfg_'.htmlspecialchars($id).'" style="width:100%;background:#060c10;border:1px solid #ff990033;border-radius:3px;color:#ff9900;font-family:\'Share Tech Mono\',monospace;font-size:.8rem;padding:.32rem .55rem;outline:none;" onfocus="this.style.borderColor=\'#ff9900\'" onblur="this.style.borderColor=\'#ff990033\'"></div>';}
  echo ys('YSF Network');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.6rem;">'.yf2('Callsign','Callsign').yf2('Suffix','Suffix').yf2('HangTime','Hang Time (ms)').yf2('YsfDstAddr','Dst Address').yf2('YsfDstPort','Dst Port').yf2('YsfLocalPort','Local Port').yf2('EnableWiresX','Enable WiresX (0/1)').'</div>';
  echo ys('Info');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">'.yf2('RXFrequency','RX Frequency (Hz)').yf2('TXFrequency','TX Frequency (Hz)').yf2('Latitude','Latitude').yf2('Longitude','Longitude').yf2('Location','Location').yf2('Description','Description').'</div>';
  echo '<div style="display:grid;grid-template-columns:1fr;gap:.6rem;">'.yf2('URL','URL').'</div>';
  echo ys('DMR Network');
  echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">'.yf2('DmrId','DMR ID').yf2('DmrAddress','Address').yf2('DmrPort','Port').yf2('DmrPassword','Password').yf2('StartupDstId','Startup TG').yf2('TGUnlink','TG Unlink').yf2('EnableUnlink','Enable Unlink (0/1)').'</div>';
  echo '<div style="display:grid;grid-template-columns:1fr;gap:.6rem;">'.yf2('Options','Options').'</div>';
  ?>
  <div id="ysf2dmrCfgMsg" style="display:none;font-family:var(--font-mono);font-size:.75rem;padding:.4rem .8rem;border-radius:4px;border:1px solid;margin-top:.2rem;"></div>
  <div style="display:flex;gap:.8rem;margin-top:.4rem;">
    <button onclick="saveYsf2dmrCfg()" style="flex:1;background:#ff990022;color:var(--y2d);border:1px solid #ff990055;border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">💾 Guardar</button>
    <button onclick="closeYsf2dmrCfg()" style="flex:1;background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:6px;font-family:var(--font-mono);font-size:.8rem;text-transform:uppercase;padding:.6rem;cursor:pointer;">✖ Cerrar</button>
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

// VU
let ysf2dmrVuTimerAnim=null;
function buildYsf2dmrVU(){['ysf2dmrVuLeft','ysf2dmrVuRight'].forEach(id=>{const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}});}
buildYsf2dmrVU();
function animateYsf2dmrVU(on){clearInterval(ysf2dmrVuTimerAnim);['ysf2dmrVuLeft','ysf2dmrVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;ysf2dmrVuTimerAnim=setInterval(()=>{['ysf2dmrVuLeft','ysf2dmrVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-g':i<14?' lit-a':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}

// Clock
let ysf2dmrCurrentlyActive=false,ysf2dmrLastActiveTs=0;
const YSF2DMR_IDLE_TIMEOUT=12000;
function updateYsf2dmrClock(){if(!ysf2dmrCurrentlyActive){const now=new Date();const clk=document.getElementById('ysf2dmrNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('ysf2dmrNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateYsf2dmrClock,1000);updateYsf2dmrClock();

// Display
function showYsf2dmrIdle(){ysf2dmrCurrentlyActive=false;animateYsf2dmrVU(false);document.getElementById('ysf2dmrTxBar').className='nx-txbar';document.getElementById('ysf2dmrTGLabel').textContent='—';document.getElementById('ysf2dmrSource').textContent='';document.getElementById('ysf2dmrSource').className='nx-source';document.getElementById('ysf2dmrNxCenter').innerHTML='<div class="nx-clock" id="ysf2dmrNxClock" style="color:#ff9900;">00:00:00</div><div class="nx-date" id="ysf2dmrNxDate" style="color:#7a4400;">—</div>';updateYsf2dmrClock();}
function showYsf2dmrActive(d){ysf2dmrCurrentlyActive=true;animateYsf2dmrVU(true);document.getElementById('ysf2dmrTxBar').className='nx-txbar active-y2d';document.getElementById('ysf2dmrTGLabel').textContent=d.tg?'TG '+d.tg:'—';const src=document.getElementById('ysf2dmrSource');src.textContent=d.source||'YSF';src.className='nx-source '+(d.source==='DMR'?'rf':'net');const flag=getFlagByCall(d.callsign);document.getElementById('ysf2dmrNxCenter').innerHTML=`<div class="nx-callsign">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name">${esc(d.name)}</div>`:'');}

function renderYsf2dmrLastHeard(list,activeCall){const body=document.getElementById('ysf2dmrLhBody');if(!list||!list.length){body.innerHTML='<div style="padding:1.5rem 1rem;font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);text-align:center;">Sin actividad</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const dot=isActive?'<span style="width:6px;height:6px;border-radius:50%;background:#ff9900;box-shadow:0 0 6px #ff9900;animation:pulse 1s infinite;flex-shrink:0;display:inline-block;margin-right:4px;"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row-y2d${isActive?' lh-active':''}"><div style="display:flex;align-items:center;gap:.35rem;">${dot}<span class="lh-call-y2d">${flag} ${esc(r.callsign)}</span></div><span style="font-family:var(--font-mono);font-size:.82rem;color:var(--text);">${esc(r.name||'—')}</span><span style="font-family:var(--font-mono);font-size:.72rem;color:var(--y2d);">${esc(r.tg||'—')}</span><span style="font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);">${esc(r.time||'—')}</span><span style="font-family:var(--font-mono);font-size:.6rem;" class="nx-source rf">${esc(r.source||'YSF')}</span></div>`;}).join('');}

// Toggle
function setYSF2DMRToggle(on){
    const chk=document.getElementById('chkYSF2DMR'),sta=document.getElementById('ysf2dmrToggleStatus');
    chk.checked=on;sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';
    const track=document.querySelector('#swYSF2DMR .sw-track');
    const knob=document.querySelector('#swYSF2DMR .sw-knob');
    if(track)track.style.borderColor=on?'#00ff4c':'#ff4560';
    if(knob){knob.style.background=on?'#00ff4c':'#ff4560';knob.style.transform=on?'translateX(28px)':'translateX(0)';}
}

let ysf2dmrRunning=false,ysf2dmrTimer=null,ysf2dmrTxTimer=null;

async function checkYsf2dmrStatus(){try{const r=await fetch('?action=ysf2dmr-status');const d=await r.json();const active=d.ysf2dmr==='active';setDot('dot-ysf2dmr',active?'active':'off');ysf2dmrRunning=active;setYSF2DMRToggle(active);if(active){startYsf2dmrLogs();startYsf2dmrTxPoll();}}catch(e){}}

async function toggleYSF2DMR(chk){
    const turningOn=chk.checked;const sw=document.getElementById('swYSF2DMR');sw.classList.add('busy');
    try{
        await fetch(turningOn?'?action=ysf2dmr-start':'?action=ysf2dmr-stop');
        let ok=false;
        for(let i=0;i<15;i++){
            await new Promise(r=>setTimeout(r,1000));
            const r=await fetch('?action=ysf2dmr-status');const d=await r.json();const isOn=d.ysf2dmr==='active';
            if(!turningOn&&!isOn){ok=true;setDot('dot-ysf2dmr','off');ysf2dmrRunning=false;setYSF2DMRToggle(false);stopYsf2dmrLogs();stopYsf2dmrTxPoll();showYsf2dmrIdle();clearLog('logYsf2dmrMmdvm');clearLog('logYsf2dmrYsf');break;}
            if(turningOn&&isOn){ok=true;setDot('dot-ysf2dmr','active');ysf2dmrRunning=true;setYSF2DMRToggle(true);startYsf2dmrLogs();startYsf2dmrTxPoll();break;}
        }
        if(!ok)await checkYsf2dmrStatus();
    }catch(e){}finally{sw.classList.remove('busy');}
}

async function fetchYsf2dmrLogs(){
    try{const r=await fetch('?action=ysf2dmr-logs-mmdvm&lines=30');const d=await r.json();const el=document.getElementById('logYsf2dmrMmdvm');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.log);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}
    try{const r2=await fetch('?action=ysf2dmr-logs-ysf&lines=30');const d2=await r2.json();const el2=document.getElementById('logYsf2dmrYsf');const atBot2=el2.scrollHeight-el2.clientHeight<=el2.scrollTop+10;el2.innerHTML=colorize(d2.log);if(atBot2)el2.scrollTop=el2.scrollHeight;}catch(e){}
}
async function fetchYsf2dmrTransmission(){try{const r=await fetch('?action=ysf2dmr-transmission');const d=await r.json();if(d.active){ysf2dmrLastActiveTs=Date.now();showYsf2dmrActive(d);}else{if(ysf2dmrCurrentlyActive)showYsf2dmrIdle();}renderYsf2dmrLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){if(ysf2dmrCurrentlyActive&&(Date.now()-ysf2dmrLastActiveTs)>YSF2DMR_IDLE_TIMEOUT)showYsf2dmrIdle();}}

function startYsf2dmrLogs(){fetchYsf2dmrLogs();ysf2dmrTimer=setInterval(fetchYsf2dmrLogs,5000);}
function stopYsf2dmrLogs(){clearInterval(ysf2dmrTimer);ysf2dmrTimer=null;}
function startYsf2dmrTxPoll(){fetchYsf2dmrTransmission();ysf2dmrTxTimer=setInterval(fetchYsf2dmrTransmission,1500);}
function stopYsf2dmrTxPoll(){clearInterval(ysf2dmrTxTimer);ysf2dmrTxTimer=null;}

(async()=>{
    await checkYsf2dmrStatus();
    setInterval(checkYsf2dmrStatus,10000);
    showYsf2dmrIdle();
})();

// ── Config MMDVMYSF2DMR.ini ──
const mmdvmFields=['Callsign','Id','Timeout','Duplex','RFModeHang','NetModeHang','RXFrequency','TXFrequency','Latitude','Longitude','Location','Description','URL','UARTPort','TXDelay','RXLevel','TXLevel','RXOffset','TXOffset','YsfEnable','YsfTXHang','DmrNetEnable','DmrNetType','DmrLocalPort','DmrRemoteAddr','DmrRemotePort','DmrPassword','YsfNetLocalPort','YsfNetGwAddr','YsfNetGwPort'];
async function openMmdvmYsf2dmrCfg(){
    const modal=document.getElementById('mmdvmYsfCfgModal');const msg=document.getElementById('mmdvmYsfCfgMsg');msg.style.display='none';modal.style.display='flex';
    mmdvmFields.forEach(f=>{const el=document.getElementById('mmdvmYsf_'+f);if(el)el.value='';});
    try{const r=await fetch('?action=mmdvmysf2dmr-config-read');const d=await r.json();
    mmdvmFields.forEach(f=>{const el=document.getElementById('mmdvmYsf_'+f);if(!el||d[f]===undefined)return;if(el.tagName==='SELECT'){for(const o of el.options)if(o.value===d[f]){o.selected=true;break;}}else el.value=d[f];});
    }catch(e){cfgMsg('mmdvmYsfCfgMsg','✖ Error al leer',false);}
}
function closeMmdvmYsf2dmrCfg(){document.getElementById('mmdvmYsfCfgModal').style.display='none';}
async function saveMmdvmYsf2dmrCfg(){
    cfgMsg('mmdvmYsfCfgMsg','⏳ Guardando…','loading');
    const body=mmdvmFields.map(f=>{const el=document.getElementById('mmdvmYsf_'+f);return el?encodeURIComponent(f)+'='+encodeURIComponent(el.value):'';}).filter(Boolean).join('&');
    try{const r=await fetch('?action=mmdvmysf2dmr-config-save',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const d=await r.json();cfgMsg('mmdvmYsfCfgMsg',(d.ok?'✔ ':'✖ ')+d.msg,d.ok);if(d.ok)setTimeout(()=>document.getElementById('mmdvmYsfCfgMsg').style.display='none',3000);}
    catch(e){cfgMsg('mmdvmYsfCfgMsg','✖ Error de red',false);}
}

// ── Config YSF2DMR.ini ──
const ysfFields=['Callsign','Suffix','YsfDstAddr','YsfDstPort','YsfLocalPort','EnableWiresX','HangTime','RXFrequency','TXFrequency','Latitude','Longitude','Location','Description','URL','DmrId','DmrAddress','DmrPort','DmrPassword','StartupDstId','Options','TGUnlink','EnableUnlink'];
async function openYsf2dmrCfg(){
    const modal=document.getElementById('ysf2dmrCfgModal');const msg=document.getElementById('ysf2dmrCfgMsg');msg.style.display='none';modal.style.display='flex';
    ysfFields.forEach(f=>{const el=document.getElementById('ysf2dmrCfg_'+f);if(el)el.value='';});
    try{const r=await fetch('?action=ysf2dmr-config-read');const d=await r.json();
    ysfFields.forEach(f=>{const el=document.getElementById('ysf2dmrCfg_'+f);if(!el||d[f]===undefined)return;el.value=d[f];});
    }catch(e){cfgMsg('ysf2dmrCfgMsg','✖ Error al leer',false);}
}
function closeYsf2dmrCfg(){document.getElementById('ysf2dmrCfgModal').style.display='none';}
async function saveYsf2dmrCfg(){
    cfgMsg('ysf2dmrCfgMsg','⏳ Guardando…','loading');
    const body=ysfFields.map(f=>{const el=document.getElementById('ysf2dmrCfg_'+f);return el?encodeURIComponent(f)+'='+encodeURIComponent(el.value):'';}).filter(Boolean).join('&');
    try{const r=await fetch('?action=ysf2dmr-config-save',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const d=await r.json();cfgMsg('ysf2dmrCfgMsg',(d.ok?'✔ ':'✖ ')+d.msg,d.ok);if(d.ok)setTimeout(()=>document.getElementById('ysf2dmrCfgMsg').style.display='none',3000);}
    catch(e){cfgMsg('ysf2dmrCfgMsg','✖ Error de red',false);}
}

function cfgMsg(id,txt,ok){const el=document.getElementById(id);const c=ok==='loading'?'var(--amber)':ok?'var(--green)':'var(--red)';const bg=ok==='loading'?'rgba(255,179,0,.06)':ok?'rgba(0,255,159,.06)':'rgba(255,69,96,.06)';el.style.cssText=`font-family:var(--font-mono);font-size:.75rem;display:block;padding:.4rem .8rem;border-radius:4px;border:1px solid ${c};color:${c};background:${bg};`;el.textContent=txt;}
</script>
</body>
</html>
