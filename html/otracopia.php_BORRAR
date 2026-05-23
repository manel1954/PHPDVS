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

function latLonToLocator($lat, $lon) {
    $lat = floatval($lat) + 90; $lon = floatval($lon) + 180; $A = ord('A');
    $f1 = chr($A + intval($lon / 20)); $f2 = chr($A + intval($lat / 10));
    $f3 = strval(intval(fmod($lon, 20) / 2)); $f4 = strval(intval(fmod($lat, 10)));
    $f5 = chr($A + intval(fmod($lon, 2) * 12)); $f6 = chr($A + intval(fmod($lat, 1) * 24));
    return strtoupper($f1 . $f2 . $f3 . $f4) . strtolower($f5 . $f6);
}

function formatFreq($hz) {
    $mhz = intval($hz) / 1000000;
    return number_format($mhz, 3, '.', '') . ' MHz';
}

if ($action === 'read-file') {
    $path = trim($_POST['path'] ?? '');
    if ($path === '' || !file_exists($path)) {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>'Fichero no encontrado: '.$path]);
        exit;
    }
    $content = file_get_contents($path);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'content'=>$content,'path'=>$path]);
    exit;
}

if ($action === 'save-file') {
    $path    = trim($_POST['path'] ?? '');
    $content = $_POST['content'] ?? '';
    if ($path === '') {
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>'Ruta vacía']);
        exit;
    }
    $result = file_put_contents($path, $content);
    header('Content-Type: application/json');
    echo json_encode($result !== false ? ['ok'=>true,'msg'=>'Guardado correctamente'] : ['ok'=>false,'msg'=>'Error al escribir el fichero']);
    exit;
}

if ($action === 'terminal') {

    $cmd = trim($_POST['cmd'] ?? '');

    if (preg_match('/^\s*(vim|vi|less|more|top|htop|su)\s*/i', $cmd)) {
        header('Content-Type: application/json');
        echo json_encode([
            'output' => 'Comando interactivo no soportado. Usa: nano /ruta/fichero'
        ]);
        exit;
    }

    if (preg_match('/(rm\s+-rf|shutdown|reboot|mkfs|dd\s+if=)/i', $cmd)) {
        header('Content-Type: application/json');
        echo json_encode([
            'output' => '❌ Comando bloqueado por seguridad'
        ]);
        exit;
    }

    $out = $cmd !== ''
        ? (shell_exec('/usr/bin/sudo -n -u pi -H bash -c ' . escapeshellarg($cmd) . ' 2>&1') ?? '')
        : '';

    header('Content-Type: application/json');
    echo json_encode([
        'output' => htmlspecialchars($out)
    ]);

    exit;
}

if ($action === 'station-info') {
    $iniPath = '/home/pi/MMDVMHost/MMDVMHost.ini';
    $ini = parseMMDVMIni($iniPath);
    $callsign = $ini['General']['Callsign'] ?? 'EA3EIZ';
    $dmrid    = $ini['General']['Id'] ?? '214317526';
    $txfreq   = $ini['General']['TXFrequency'] ?? ($ini['General']['Frequency'] ?? '430000000');
    $lat      = $ini['Info']['Latitude']    ?? '41.3851';
    $lon      = $ini['Info']['Longitude']   ?? '2.1734';
    $location = $ini['Info']['Location']    ?? 'Barcelona';
    $desc     = $ini['Info']['Description'] ?? '';
    $locator  = (floatval($lat) != 0 || floatval($lon) != 0) ? latLonToLocator($lat, $lon) : 'JN11CK';
    $port     = $ini['Modem']['UARTPort'] ?? ($ini['modem']['UARTPort'] ?? '');
    $rxhz     = $ini['Info']['RXFrequency'] ?? '0';
    $txhz     = $ini['Info']['TXFrequency'] ?? $txfreq;
    $freqRX   = formatFreq($rxhz); $freq = formatFreq($txhz);
    $iniIp    = trim($ini['General']['Address'] ?? '');
    if ($iniIp === '' || $iniIp === '0.0.0.0') $iniIp = trim(shell_exec("hostname -I 2>/dev/null | awk '{print $1}'"));
    $ip = $iniIp ?: '—';
    $ysfIniPath = '/home/pi/MMDVMHost/MMDVMYSF.ini'; $ysfIni = parseMMDVMIni($ysfIniPath);
    $ysfPort   = $ysfIni['Modem']['UARTPort'] ?? ($ysfIni['modem']['UARTPort'] ?? '—');
    $ysfRxHz   = $ysfIni['Info']['RXFrequency'] ?? '0'; $ysfTxHz = $ysfIni['Info']['TXFrequency'] ?? '0';
    $ysfFreqRX = formatFreq($ysfRxHz); $ysfFreqTX = formatFreq($ysfTxHz);
    $ysfIpRaw  = trim($ysfIni['General']['Address'] ?? '');
    $ysfIp     = ($ysfIpRaw !== '' && $ysfIpRaw !== '0.0.0.0') ? $ysfIpRaw : $ip;

    $dstarIniPath = '/home/pi/MMDVMHost/MMDVMDSTAR.ini';
    $dstarIni = parseMMDVMIni($dstarIniPath);
    $dstarPort  = $dstarIni['Modem']['UARTPort'] ?? ($dstarIni['modem']['UARTPort'] ?? '—');
    $dstarRxHz  = $dstarIni['Info']['RXFrequency'] ?? '0';
    $dstarTxHz  = $dstarIni['Info']['TXFrequency'] ?? '0';
    $dstarFreqRX = formatFreq($dstarRxHz);
    $dstarFreqTX = formatFreq($dstarTxHz);
    $dstarIpRaw = trim($dstarIni['General']['Address'] ?? '');
    $dstarIp    = ($dstarIpRaw !== '' && $dstarIpRaw !== '0.0.0.0') ? $dstarIpRaw : $ip;

    $nxdnIniPath = '/home/pi/MMDVMHost/MMDVMNXDN.ini';
    $nxdnIni = parseMMDVMIni($nxdnIniPath);
    $nxdnPort   = $nxdnIni['Modem']['UARTPort'] ?? ($nxdnIni['modem']['UARTPort'] ?? '—');
    $nxdnRxHz   = $nxdnIni['Info']['RXFrequency'] ?? '0';
    $nxdnTxHz   = $nxdnIni['Info']['TXFrequency'] ?? '0';
    $nxdnFreqRX = formatFreq($nxdnRxHz);
    $nxdnFreqTX = formatFreq($nxdnTxHz);
    $nxdnIpRaw  = trim($nxdnIni['General']['Address'] ?? '');
    $nxdnIp     = ($nxdnIpRaw !== '' && $nxdnIpRaw !== '0.0.0.0') ? $nxdnIpRaw : $ip;

    header('Content-Type: application/json');
    echo json_encode([
        'callsign'=>strtoupper(trim($callsign)),'dmrid'=>trim($dmrid),'freq'=>$freq,'freqRX'=>$freqRX,
        'port'=>$port?:'—','ip'=>$ip,'locator'=>$locator,'location'=>trim($location),'desc'=>trim($desc),'lat'=>$lat,'lon'=>$lon,
        'ysfPort'=>$ysfPort?:'—','ysfFreqRX'=>$ysfFreqRX,'ysfFreqTX'=>$ysfFreqTX,'ysfIp'=>$ysfIp?:'—',
        'dstarPort'=>$dstarPort?:'—','dstarFreqRX'=>$dstarFreqRX,'dstarFreqTX'=>$dstarFreqTX,'dstarIp'=>$dstarIp?:'—',
        'nxdnPort'=>$nxdnPort?:'—','nxdnFreqRX'=>$nxdnFreqRX,'nxdnFreqTX'=>$nxdnFreqTX,'nxdnIp'=>$nxdnIp?:'—'
    ]);
    exit;
}

if ($action === 'sysinfo') {
    $s1 = file('/proc/stat'); $cpu1 = preg_split('/\s+/', trim($s1[0])); usleep(300000);
    $s2 = file('/proc/stat'); $cpu2 = preg_split('/\s+/', trim($s2[0]));
    $idle1 = $cpu1[4]; $total1 = array_sum(array_slice($cpu1, 1));
    $idle2 = $cpu2[4]; $total2 = array_sum(array_slice($cpu2, 1));
    $dTotal = $total2 - $total1; $dIdle = $idle2 - $idle1;
    $cpu = $dTotal > 0 ? round(100 * ($dTotal - $dIdle) / $dTotal, 1) : 0;
    $memRaw = file('/proc/meminfo'); $mem = [];
    foreach ($memRaw as $line) { if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) $mem[$m[1]] = intval($m[2]); }
    $ramTotal = round($mem['MemTotal'] / 1048576, 2);
    $ramFree  = round(($mem['MemAvailable'] ?? $mem['MemFree']) / 1048576, 2);
    $ramUsed  = round($ramTotal - $ramFree, 2);
    $diskTotal = round(disk_total_space('/') / 1073741824, 1);
    $diskFree  = round(disk_free_space('/') / 1073741824, 1);
    $diskUsed  = round($diskTotal - $diskFree, 1);
    $temp = '';
    if (file_exists('/sys/class/thermal/thermal_zone0/temp'))
        $temp = round(intval(trim(file_get_contents('/sys/class/thermal/thermal_zone0/temp'))) / 1000, 1) . ' °C';
    header('Content-Type: application/json');
    echo json_encode(['cpu'=>$cpu,'ramTotal'=>$ramTotal,'ramUsed'=>$ramUsed,'ramFree'=>$ramFree,'diskTotal'=>$diskTotal,'diskUsed'=>$diskUsed,'diskFree'=>$diskFree,'temp'=>$temp]);
    exit;
}

if ($action === 'status') {
    $gw = trim(shell_exec('systemctl is-active dmrgateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmhost 2>/dev/null'));
    header('Content-Type: application/json'); echo json_encode(['gateway'=>$gw,'mmdvm'=>$mmd]); exit;
}
if ($action === 'start') {
    saveState('dmr','on'); shell_exec('sudo systemctl start dmrgateway 2>/dev/null'); sleep(2);
    shell_exec('sudo systemctl start mmdvmhost 2>/dev/null');
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}
if ($action === 'stop') {
    saveState('dmr','off'); shell_exec('sudo systemctl stop mmdvmhost 2>/dev/null'); sleep(1);
    shell_exec('sudo systemctl stop dmrgateway 2>/dev/null');
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
}
if ($action === 'update-imagen') { $output = shell_exec('sudo sh /home/pi/A108/actualiza_imagen.sh 2>&1'); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'(sin salida)')]); exit; }
if ($action === 'update-ids')    { $output = shell_exec('sudo sh /home/pi/A108/actualizar_ids.sh 2>&1'); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'(sin salida)')]); exit; }
if ($action === 'update-ysf')    { $output = shell_exec('sudo sh /home/pi/A108/actualizar_reflectores_ysf.sh 2>&1'); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'(sin salida)')]); exit; }

if ($action === 'ysf-status') {
    $st = trim(shell_exec('sudo /usr/local/bin/ysf_status.sh 2>/dev/null'));
    if ($st === 'active') { header('Content-Type: application/json'); echo json_encode(['ysf'=>'active']); exit; }
    $pid = trim(@file_get_contents('/tmp/ysfgateway.pid'));
    $active = ($pid && is_numeric($pid) && file_exists('/proc/'.$pid)) ? 'active' : 'inactive';
    header('Content-Type: application/json'); echo json_encode(['ysf'=>$active]); exit;
}
if ($action === 'ysf-start')  { saveState('ysf','on'); shell_exec('sudo systemctl start ysfgateway 2>/dev/null'); sleep(1); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'ysf-stop')   { saveState('ysf','off'); shell_exec('sudo systemctl stop ysfgateway 2>/dev/null'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'mmdvmysf-status') { $st = trim(shell_exec('systemctl is-active mmdvmysf 2>/dev/null')); header('Content-Type: application/json'); echo json_encode(['mmdvmysf'=>$st]); exit; }
if ($action === 'mmdvmysf-start')  { saveState('ysf','on'); shell_exec('sudo systemctl start mmdvmysf 2>/dev/null'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'mmdvmysf-stop')   { saveState('ysf','off'); shell_exec('sudo systemctl stop ysfgateway 2>/dev/null'); sleep(1); shell_exec('sudo systemctl stop mmdvmysf 2>/dev/null'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'mmdvmysf-logs')   { $lines = intval($_GET['lines']??15); $log = shell_exec("sudo journalctl -u mmdvmysf -n {$lines} --no-pager --output=short 2>/dev/null"); header('Content-Type: application/json'); echo json_encode(['mmdvmysf'=>htmlspecialchars($log??'')]); exit; }
if ($action === 'reboot')          { shell_exec('sudo /usr/bin/systemctl reboot 2>/dev/null'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'display-restart') { shell_exec('sudo systemctl daemon-reload 2>/dev/null'); shell_exec('sudo systemctl enable displaydriver 2>/dev/null'); shell_exec('sudo systemctl restart displaydriver 2>/dev/null'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'install-display') { $output = shell_exec('sudo /home/pi/A108/instalar_displaydriver.sh 2>&1'); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'output'=>htmlspecialchars($output??'')]); exit; }

if ($action === 'backup-configs') {
    $zipName = 'Copia_PHP2.zip'; $zipPath = '/tmp/'.$zipName;
    $files = ['/home/pi/MMDVMHost/MMDVMHost.ini','/home/pi/MMDVMHost/MMDVMYSF.ini','/home/pi/MMDVMHost/MMDVMDSTAR.ini','/home/pi/MMDVMHost/MMDVMNXDN.ini','/home/pi/Display-Driver/DisplayDriver.ini','/home/pi/YSFClients/YSFGateway/YSFGateway.ini','/home/pi/DMRGateway/DMRGateway.ini','/home/pi/DStarGateway/DStarGateway.ini','/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini'];
    $fileList = implode(' ', array_map('escapeshellarg', $files));
    shell_exec("zip -j ".escapeshellarg($zipPath)." {$fileList} 2>/dev/null");
    if (file_exists($zipPath)) { header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$zipName.'"'); header('Content-Length: '.filesize($zipPath)); header('Pragma: no-cache'); header('Expires: 0'); readfile($zipPath); unlink($zipPath); } else { header('Content-Type: text/plain'); echo 'Error: No se pudo crear el ZIP.'; }
    exit;
}

if ($action === 'restore-configs') {
    ob_start(); error_reporting(0);
    $uploadOk = isset($_FILES['zipfile']) && $_FILES['zipfile']['error'] === UPLOAD_ERR_OK;
    if (!$uploadOk) { $errCode = $_FILES['zipfile']['error']??-1; ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'No se recibió el fichero. Error: '.$errCode]); exit; }
    $tmpZip = $_FILES['zipfile']['tmp_name'];
    if (!file_exists($tmpZip)||filesize($tmpZip)===0) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Fichero vacío.']); exit; }
    $destMap = ['MMDVMHost.ini'=>'/home/pi/MMDVMHost/MMDVMHost.ini','MMDVMYSF.ini'=>'/home/pi/MMDVMHost/MMDVMYSF.ini','DisplayDriver.ini'=>'/home/pi/Display-Driver/DisplayDriver.ini','YSFGateway.ini'=>'/home/pi/YSFClients/YSFGateway/YSFGateway.ini','DMRGateway.ini'=>'/home/pi/DMRGateway/DMRGateway.ini','DStarGateway.ini'=>'/home/pi/DStarGateway/DStarGateway.ini','NXDNGateway.ini'=>'/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini','MMDVMDSTAR.ini'=>'/home/pi/MMDVMHost/MMDVMDSTAR.ini','MMDVMNXDN.ini'=>'/home/pi/MMDVMHost/MMDVMNXDN.ini'];
    $zip = new ZipArchive(); $openResult = $zip->open($tmpZip);
    if ($openResult !== true) { ob_end_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'No se pudo abrir el ZIP. Código: '.$openResult]); exit; }
    $restored = []; $errors = [];
    for ($i=0;$i<$zip->numFiles;$i++) { $name=basename($zip->getNameIndex($i)); if(isset($destMap[$name])){$result=file_put_contents($destMap[$name],$zip->getFromIndex($i));if($result!==false)$restored[]=$name;else $errors[]=$name;} }
    $zip->close(); ob_end_clean();
    if (empty($restored)) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'No se encontraron ficheros compatibles.']); exit; }
    $msg = 'Restaurados: '.implode(', ',$restored); if($errors)$msg.=' | Errores: '.implode(', ',$errors);
    header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>$msg]); exit;
}

if ($action === 'logs') {
    $lines = intval($_GET['lines']??15);
    $gw  = shell_exec("sudo journalctl -u dmrgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmhost -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json'); echo json_encode(['gateway'=>htmlspecialchars($gw??''),'mmdvm'=>htmlspecialchars($mmd??'')]); exit;
}

if ($action === 'ysf-logs') {
    $lines = intval($_GET['lines']??15);
    $log = shell_exec("sudo journalctl -u ysfgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) $log = shell_exec("tail -n {$lines} /tmp/ysfgateway.log 2>/dev/null");
    if (empty(trim($log))) { $logFile = glob('/home/pi/YSFClients/YSFGateway/YSFGateway-*.log'); if($logFile){$latest=end($logFile);$log=shell_exec("tail -n {$lines} ".escapeshellarg($latest)." 2>/dev/null");} }
    header('Content-Type: application/json'); echo json_encode(['ysf'=>htmlspecialchars($log??'')]); exit;
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

if ($action === 'transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmhost -n 200 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n",$log));
    $active=false;$callsign='';$dmrid='';$name='';$tg='';$slot='';$source='';
    foreach ($lines as $line) {
        if(preg_match('/DMR Slot \d.*(end of voice|lost RF|watchdog)/i',$line)){$active=false;break;}
        if(preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i',$line,$m)){$active=true;$slot=$m[1];$source=strtoupper($m[2]);$callsign=strtoupper(rtrim($m[3],','));$tg=$m[4];break;}
    }
    if($callsign){$info=lookupCall($callsign);$dmrid=$info['dmrid'];$name=$info['name'];}
    $lastHeard=[];$seen=[];
    foreach ($lines as $line) {
        if(preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i',$line,$m)){
            $cs=strtoupper(rtrim($m[4],','));
            if(!in_array($cs,$seen)){$inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'dmrid'=>$inf['dmrid'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]];$seen[]=$cs;if(count($lastHeard)>=5)break;}
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dmrid'=>$dmrid,'tg'=>$tg,'slot'=>$slot,'source'=>$source,'lastHeard'=>$lastHeard]); exit;
}

if ($action === 'dstar-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmdstar -n 300 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log ?? ''));
    $active = false; $callsign = ''; $source = ''; $name = '';
    foreach ($lines as $line) {
        if (preg_match('/D-Star.*(end of|lost RF|watchdog|finished|timeout)/i', $line)) { $active = false; break; }
        if (preg_match('/D-Star.*received (RF|network).*from\s+([A-Z0-9\/]+)/i', $line, $m)) { $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); break; }
        if (preg_match('/received (RF|network) header from\s+([A-Z0-9\/]+)/i', $line, $m)) { $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); break; }
    }
    if ($callsign) { $info = lookupCall(preg_replace('/\/.*$/', '', $callsign)); $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs = ''; $src = ''; $time = '';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*D-Star.*received (RF|network).*from\s+([A-Z0-9\/]+)/i', $line, $m)) { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*received (RF|network) header from\s+([A-Z0-9\/]+)/i', $line, $m)) { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        if ($cs && !in_array($cs, $seen)) { $inf = lookupCall(preg_replace('/\/.*$/','',$cs)); $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'source'=>$src,'time'=>$time]; $seen[] = $cs; if (count($lastHeard) >= 5) break; }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}

if ($action === 'dstar-status') {
    $gw=trim(shell_exec('systemctl is-active dstargateway 2>/dev/null'));
    $mmd=trim(shell_exec('systemctl is-active mmdvmdstar 2>/dev/null'));
    $stopped=file_exists('/var/lib/dstar-stopped');
    header('Content-Type: application/json'); echo json_encode(['gateway'=>$gw,'mmdvm'=>$mmd,'stopped'=>$stopped]); exit;
}
if ($action === 'dstar-start') { shell_exec('sudo /usr/local/bin/dstar-start.sh 2>/dev/null &'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'dstar-stop')  { shell_exec('sudo /usr/local/bin/dstar-stop.sh 2>/dev/null &'); header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'dstar-logs')  {
    $lines=intval($_GET['lines']??15);
    $gw  = shell_exec("sudo journalctl -u dstargateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmdstar  -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json'); echo json_encode(['gateway'=>htmlspecialchars($gw??''),'mmdvm'=>htmlspecialchars($mmd??'')]); exit;
}

if ($action === 'ysf-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmysf -n 300 --no-pager --output=short 2>/dev/null");
    if(empty(trim($log)))$log=shell_exec("sudo journalctl -u ysfgateway -n 300 --no-pager --output=short 2>/dev/null");
    $lines=array_reverse(explode("\n",$log));
    $active=false;$callsign='';$name='';$dest='';$source='';
    foreach ($lines as $line) {
        if(preg_match('/YSF.*(end of|lost RF|lost net|watchdog|timeout|no reply|voice end|fin)/i',$line)){$active=false;break;}
        if(preg_match('/YSF.*voice (end|fin|stop)/i',$line)){$active=false;break;}
        if(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i',$line,$m)){$active=true;$source=strtoupper($m[2]);$callsign=strtoupper(trim($m[3]));break;}
        if(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i',$line,$m)){$active=true;$source='RF';$callsign=strtoupper(trim($m[2]));$dest=trim($m[3]);break;}
    }
    if($callsign){$info=lookupCall($callsign);$name=$info['name'];}
    $lastHeard=[];$seen=[];
    foreach ($lines as $line) {
        $cs='';$src='';$time='';$dst='';
        if(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i',$line,$m)){$time=$m[1];$src=strtoupper($m[2]);$cs=strtoupper(trim($m[3]));}
        elseif(preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i',$line,$m)){$time=$m[1];$src='RF';$cs=strtoupper(trim($m[2]));$dst=trim($m[3]);}
        if($cs&&!in_array($cs,$seen)){$inf=lookupCall($cs);$lastHeard[]=['callsign'=>$cs,'name'=>$inf['name'],'dest'=>$dst,'source'=>$src,'time'=>$time];$seen[]=$cs;if(count($lastHeard)>=5)break;}
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dest'=>$dest,'source'=>$source,'lastHeard'=>$lastHeard]); exit;
}

// ── NXDN ──────────────────────────────────────────────────────────────────────
if ($action === 'nxdn-status') {
    $gw  = trim(shell_exec('systemctl is-active nxdngateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmnxdn 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['gateway'=>$gw,'mmdvm'=>$mmd]);
    exit;
}
if ($action === 'nxdn-start') {
    shell_exec('sudo systemctl start mmdvmnxdn 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl start nxdngateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]);
    exit;
}
if ($action === 'nxdn-stop') {
    shell_exec('sudo systemctl stop nxdngateway 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop mmdvmnxdn 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]);
    exit;
}
if ($action === 'nxdn-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $gw  = shell_exec("sudo journalctl -u nxdngateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmnxdn  -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['gateway'=>htmlspecialchars($gw??''),'mmdvm'=>htmlspecialchars($mmd??'')]);
    exit;
}
if ($action === 'nxdn-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmnxdn -n 300 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log ?? ''));
    $active = false; $callsign = ''; $source = ''; $name = ''; $tg = '';
    foreach ($lines as $line) {
        if (preg_match('/NXDN.*(end of|lost RF|watchdog|finished|timeout)/i', $line)) { $active = false; break; }
        if (preg_match('/NXDN.*received (RF|network).*from\s+([A-Z0-9]+).*to\s+(\d+)/i', $line, $m)) {
            $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); $tg = $m[3]; break;
        }
        if (preg_match('/NXDN.*received (RF|network).*from\s+([A-Z0-9]+)/i', $line, $m)) {
            $active = true; $source = strtoupper($m[1]); $callsign = strtoupper(trim($m[2])); break;
        }
    }
    if ($callsign) { $info = lookupCall($callsign); $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs = ''; $src = ''; $time = ''; $tgr = '';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*received (RF|network).*from\s+([A-Z0-9]+).*to\s+(\d+)/i', $line, $m)) {
            $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); $tgr=$m[4];
        } elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*received (RF|network).*from\s+([A-Z0-9]+)/i', $line, $m)) {
            $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3]));
        }
        if ($cs && !in_array($cs, $seen)) {
            $inf = lookupCall($cs);
            $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$tgr,'source'=>$src,'time'=>$time];
            $seen[] = $cs;
            if (count($lastHeard) >= 5) break;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'tg'=>$tg,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel PHPPLUS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg: #0a0e14; --surface: #111720; --border: #1e2d3d; --green: #00ff9f; --green-dim: #00cc7a; --red: #ff4560; --amber: #ffb300; --cyan: #00d4ff; --violet: #b57aff; --text: #a8b9cc; --text-dim: #4a5568; --font-mono: 'Share Tech Mono', monospace; --font-ui: 'Rajdhani', sans-serif; --font-orb: 'Orbitron', monospace; }
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); font-size: 1rem; min-height: 100vh; padding: 0; margin: 0; }
.ctrl-header { border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; flex-direction: column; align-items: center; gap: .6rem; background: var(--surface); }
.ctrl-header-top { display: flex; align-items: center; gap: .8rem; }
.ctrl-header-top h1 { font-family: var(--font-ui); font-weight: 700; font-size: 1.5rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
.ctrl-header-btns { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; justify-content: center; }
.btn-header { font-family: var(--font-mono); font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; background: transparent; border-radius: 4px; padding: .28rem .75rem; cursor: pointer; transition: background .2s; text-decoration: none; display: inline-block; }
.btn-header.cyan { color: var(--cyan); border: 1px solid var(--cyan); }
.btn-header.cyan:hover { background: rgba(0,212,255,.1); }
.btn-header.amber { color: var(--amber); border: 1px solid var(--amber); }
.btn-header.amber:hover { background: rgba(255,179,0,.1); }
.btn-header.red { color: var(--red); border: 1px solid var(--red); }
.btn-header.red:hover { background: rgba(255,69,96,.15); }
button.btn-header { font-family: var(--font-mono); }
.ctrl-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.station-card { background: linear-gradient(135deg,#111720 60%,#0d1e2a 100%); border: 1px solid var(--border); border-radius: 10px; padding: 1.2rem 2rem; display: flex; align-items: center; gap: 2.5rem; margin-bottom: 1.8rem; flex-wrap: wrap; position: relative; overflow: hidden; }
.station-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,transparent,var(--cyan),var(--violet),transparent); }
.station-card-main { display: flex; flex-direction: column; align-items: flex-start; gap: .3rem; }
.station-callsign { font-family: var(--font-orb); font-size: 2.4rem; font-weight: 900; color: var(--cyan); letter-spacing: .08em; line-height: 1; text-shadow: 0 0 20px rgba(0,212,255,.4); }
.station-divider { width: 1px; height: 70px; background: var(--border); flex-shrink: 0; }
.station-meta-item { display: flex; flex-direction: column; gap: .15rem; }
.station-meta-label { font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .15em; text-transform: uppercase; }
.station-meta-value { font-family: var(--font-mono); font-size: .95rem; color: var(--amber); letter-spacing: .06em; font-weight: bold; }
@media (max-width:700px) { .station-card { gap: 1.2rem; padding: 1rem; } .station-divider { display: none; } }
.status-bar { display: flex; gap: 2rem; margin-bottom: 1.8rem; flex-wrap: wrap; align-items: center; }
.status-item { display: flex; align-items: center; gap: .5rem; font-family: var(--font-mono); font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); transition: background .4s, box-shadow .4s; }
.dot.active { background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 2s infinite; }
.dot.error { background: var(--red); box-shadow: 0 0 8px var(--red); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.section-divider { width: 1px; height: 20px; background: var(--border); margin: 0 .5rem; }
.controls-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 2rem; }
@media (max-width:800px) { .controls-section { grid-template-columns: 1fr; } }
.service-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.2rem 1.6rem; }
.service-card-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 1rem; }
.service-card-label.dmr { color: var(--white); }
.service-card-label.ysf { color: var(--violet); }
.toggle-row { display: flex; align-items: center; gap: 1rem; padding: .5rem 0; }
.toggle-label { font-family: var(--font-mono); font-size: .85rem; letter-spacing: .06em; color: var(--text-dim); text-transform: uppercase; flex: 1; transition: color .3s; }
.toggle-label.on-dmr { color: var(--amber); }
.toggle-label.on-ysf { color: var(--violet); }
.toggle-status { font-family: var(--font-mono); font-size: .72rem; letter-spacing: .1em; color: var(--text-dim); min-width: 3rem; text-align: right; transition: color .3s; }
.toggle-status.on { color: var(--green); }
.sw { position: relative; width: 56px; height: 28px; flex-shrink: 0; cursor: pointer; }
.sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid #999999; transition: background .3s, border-color .3s, box-shadow .3s; }
.sw-knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #e95c04; box-shadow: 0 1px 4px rgba(0,0,0,.5); transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s, box-shadow .3s; }
.sw.dmr input:checked ~ .sw-track, .sw.ysf input:checked ~ .sw-track, .sw.dstar input:checked ~ .sw-track { border-radius: 2px; background: #1a2535; border: 2px solid #999999; }
.sw.dmr input:checked ~ .sw-knob, .sw.ysf input:checked ~ .sw-knob, .sw.dstar input:checked ~ .sw-knob { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw#swNXDN input:checked ~ .sw-knob { transform: translateX(28px); background: var(--green); box-shadow: 0 0 8px rgba(255,215,0,.6); }
.sw#swNXDN input:checked ~ .sw-track { border-color: #999; }
.sw-busy-dot { display: none; position: absolute; top: 50%; right: -18px; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--amber); border-top-color: transparent; animation: spin .7s linear infinite; }
.sw.busy .sw-busy-dot { display: block; }
@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
.auto-badge { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); display: flex; align-items: center; gap: .4rem; margin-top: .4rem; }
.auto-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
.auto-badge.ysf .dot-sm { background: var(--violet); }
.service-card-btns { display: flex; gap: .6rem; flex-wrap: nowrap; margin-top: 1rem; }
.ini-btn { font-family: var(--font-mono); font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; padding: .3rem .7rem; border-radius: 3px; border: 1px solid var(--border); background: transparent; cursor: pointer; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: .3rem; }
.ini-btn.edit { color: var(--white); border-color: rgba(255,179,0,.3); }
.ini-btn.edit:hover { border-color: var(--white); background: rgba(255,179,0,.08); }
.ini-btn.view { color: var(--cyan); border-color: rgba(0,212,255,.3); }
.ini-btn.view:hover { border-color: var(--cyan); background: rgba(0,212,255,.08); }
.ini-btn.edit.ysf { color: var(--violet); border-color: rgba(181,122,255,.3); }
.ini-btn.edit.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.08); }
.ini-btn.view.ysf { color: #c9a0ff; border-color: rgba(181,122,255,.2); }
.ini-btn.view.ysf:hover { border-color: var(--violet); background: rgba(181,122,255,.06); }
.display-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin: 2rem 0; align-items: start; }
@media (max-width:900px) { .display-row { grid-template-columns: 1fr; } }
.panel-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; color: var(--amber); text-transform: uppercase; margin-bottom: .5rem; }
.panel-label.ysf-label { color: var(--violet); }
.nextion { background: #060c10; border: 2px solid #1a3a4a; border-radius: 6px; box-shadow: 0 0 0 1px #0d2030, inset 0 0 40px rgba(0,212,255,.04), 0 0 30px rgba(0,212,255,.08); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion::before,.nextion::after { content: '◈'; position: absolute; font-size: .6rem; color: #1a3a4a; }
.nextion::before { top: .5rem; left: .7rem; }
.nextion::after { bottom: .5rem; right: .7rem; }
.nextion-ysf { background: #08060e; border: 2px solid #2d1a4a; border-radius: 6px; box-shadow: 0 0 0 1px #1a0d30, inset 0 0 40px rgba(181,122,255,.04), 0 0 30px rgba(181,122,255,.1); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-ysf::before,.nextion-ysf::after { content: '◈'; position: absolute; font-size: .6rem; color: #2d1a4a; }
.nextion-ysf::before { top: .5rem; left: .7rem; }
.nextion-ysf::after { bottom: .5rem; right: .7rem; }
.nx-topbar { position: absolute; top: 0; left: 0; right: 0; height: 30px; background: #1c1c24; border-bottom: 1px solid #1a3a4a; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .1em; }
.nx-topbar.ysf-bar { background: #1a1424; border-bottom: 1px solid #2d1a4a; color: #4a2a7a; }
.nx-topbar .nx-mode { color: var(--cyan); opacity: .7; }
.nx-topbar.ysf-bar .nx-mode { color: var(--violet); opacity: .8; }
.nx-topbar .nx-tg { color: var(--amber); opacity: .85; min-width: 5rem; text-align: right; }
.nx-topbar.ysf-bar .nx-dest { color: #d4a8ff; opacity: .85; min-width: 5rem; text-align: right; font-size: .6rem; }
.nx-botbar { position: absolute; bottom: 0; left: 0; right: 0; height: 28px; background: #0d1e2a; border-top: 1px solid #1a3a4a; display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; font-family: var(--font-mono); font-size: .65rem; color: #2a5a7a; letter-spacing: .08em; }
.nx-botbar.ysf-bar { background: #110d1e; border-top: 1px solid #2d1a4a; color: #4a2a7a; }
.nx-botbar .nx-dmrid { color: #3a6a8a; min-width: 6rem; }
.nx-botbar .nx-source { padding: .1rem .45rem; border-radius: 2px; font-size: .6rem; letter-spacing: .1em; }
.nx-botbar .nx-source.rf { background: rgba(0,255,159,.15); color: var(--green); border: 1px solid rgba(0,255,159,.3); }
.nx-botbar .nx-source.net { background: rgba(0,212,255,.15); color: var(--cyan); border: 1px solid rgba(0,212,255,.3); }
.nx-vu { position: absolute; left: 1rem; top: 56px; bottom: 32px; width: 6px; display: flex; flex-direction: column-reverse; gap: 2px; }
.nx-vu.right { left: auto; right: 1rem; }
.nx-vu-bar { height: 5px; border-radius: 1px; background: #0d2030; transition: background .08s; }
.nx-vu-bar.lit-g { background: var(--green); box-shadow: 0 0 4px var(--green); }
.nx-vu-bar.lit-a { background: var(--amber); box-shadow: 0 0 4px var(--amber); }
.nx-vu-bar.lit-r { background: var(--red); box-shadow: 0 0 4px var(--red); }
.nx-vu-bar.lit-v { background: var(--violet); box-shadow: 0 0 4px var(--violet); }
.nx-vu-bar.lit-vd { background: #d4a8ff; box-shadow: 0 0 4px #d4a8ff; }
.nx-vu-bar.lit-y { background: #ffd700; box-shadow: 0 0 4px #ffd700; }
.nx-vu-bar.lit-ya { background: #ffc400; box-shadow: 0 0 4px #ffc400; }
.nx-txbar { position: absolute; bottom: 28px; left: 0; right: 0; height: 3px; }
.nx-txbar.active { background: linear-gradient(90deg,transparent,var(--green),transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.active-ysf { background: linear-gradient(90deg,transparent,var(--violet),transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.active-nxdn { background: linear-gradient(90deg,transparent,#ffd700,transparent); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
@keyframes scan { from{background-position:200% 0} to{background-position:-200% 0} }
.nx-center { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; z-index: 1; }
.nx-clock { font-family: var(--font-orb); font-size: 4rem; font-weight: 700; color: #f5bd06; letter-spacing: .06em; line-height: 1; }
.nx-date { font-family: var(--font-mono); font-size: .7rem; color: #ff0; letter-spacing: .12em; text-transform: uppercase; margin-top: .2rem; }
.nx-callsign { font-family: var(--font-orb); font-size: 3.4rem; font-weight: 900; letter-spacing: .04em; line-height: 1; color: var(--green); text-shadow: 0 0 20px rgba(0,255,159,.55); }
.nx-callsign.ysf { color: var(--violet); text-shadow: 0 0 20px rgba(181,122,255,.6); }
.nx-callsign.nxdn { color: #ffd700; text-shadow: 0 0 20px rgba(255,215,0,.6); }
.nx-name { font-family: var(--font-ui); font-weight: 500; font-size: 1.2rem; color: var(--cyan); letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-top: .15rem; }
.nx-name.ysf { color: #d4a8ff; }
.nx-name.nxdn { color: #ffc400; }
.nx-infobar { position: absolute; top: 30px; left: 0; right: 0; height: 26px; background: rgba(0,0,0,.35); border-bottom: 1px solid #0d2030; display: flex; align-items: center; justify-content: space-around; padding: 0 3rem; gap: 1rem; z-index: 2; }
.nx-info-item { display: flex; align-items: center; gap: .4rem; }
.nx-info-lbl { font-family: var(--font-mono); font-size: .58rem; color: var(--text-dim); letter-spacing: .12em; text-transform: uppercase; }
.nx-info-val { font-family: var(--font-mono); font-size: .72rem; color: var(--text); letter-spacing: .06em; font-weight: bold; }
.nx-info-val.cyan { color: var(--cyan); }
.nx-info-val.amber { color: var(--amber); }
.nx-info-val.green { color: var(--green); }
.nx-infobar-ysf { background: rgba(0,0,0,.4); border-bottom: 1px solid #1a0d30; }
/* ── Nextion D-STAR ── */
.nextion-dstar { background: #06100e; border: 2px solid #004a4a; border-radius: 6px; box-shadow: 0 0 0 1px #002030, inset 0 0 40px rgba(0,229,255,.04), 0 0 30px rgba(0,229,255,.12); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-dstar::before,.nextion-dstar::after { content: '◈'; position: absolute; font-size: .6rem; color: #004a4a; }
.nextion-dstar::before { top: .5rem; left: .7rem; }
.nextion-dstar::after { bottom: .5rem; right: .7rem; }
.nx-topbar.dstar-bar { background: #0a1a1a; border-bottom: 1px solid #004a4a; color: #006070; }
.nx-topbar.dstar-bar .nx-mode { color: #00e5ff; opacity: .8; }
.nx-botbar.dstar-bar { background: #06100e; border-top: 1px solid #004a4a; color: #006070; }
.nx-infobar-dstar { background: rgba(0,0,0,.4); border-bottom: 1px solid #003040; }
.nx-callsign.dstar { color: #00e5ff; text-shadow: 0 0 20px rgba(0,229,255,.6); }
.nx-name.dstar { color: #80f0ff; }
/* ── Nextion NXDN ── */
.nextion-nxdn { background: #0e0e06; border: 2px solid #4a4a00; border-radius: 6px; box-shadow: 0 0 0 1px #303000, inset 0 0 40px rgba(255,215,0,.04), 0 0 30px rgba(255,215,0,.12); position: relative; overflow: hidden; height: 240px; display: flex; align-items: center; justify-content: center; }
.nextion-nxdn::before,.nextion-nxdn::after { content: '◈'; position: absolute; font-size: .6rem; color: #4a4a00; }
.nextion-nxdn::before { top: .5rem; left: .7rem; }
.nextion-nxdn::after { bottom: .5rem; right: .7rem; }
.nx-topbar.nxdn-bar { background: #1a1a0a; border-bottom: 1px solid #4a4a00; color: #707000; }
.nx-topbar.nxdn-bar .nx-mode { color: #ffd700; opacity: .8; }
.nx-botbar.nxdn-bar { background: #0e0e06; border-top: 1px solid #4a4a00; color: #707000; }
.nx-infobar-nxdn { background: rgba(0,0,0,.4); border-bottom: 1px solid #303000; }
.lh-panel { background: var(--surface); border: 3px solid #1a3a4a; border-radius: 6px; display: flex; flex-direction: column; }
.lh-header { background: #1c1c24; border-bottom: 1px solid var(--border); padding: .4rem 1rem; display: grid; grid-template-columns: 1.1fr 1.5fr .7fr .7fr .5fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .1em; text-transform: uppercase; }
.lh-body { flex: 1; overflow-y: auto; }
.lh-body::-webkit-scrollbar { width: 3px; }
.lh-body::-webkit-scrollbar-thumb { background: var(--border); }
.lh-row { display: grid; grid-template-columns: 1.1fr 1.5fr .7fr .7fr .5fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(30,45,61,.6); align-items: center; transition: background .2s; }
.lh-row:last-child { border-bottom: none; }
.lh-row:hover { background: rgba(0,212,255,.04); }
.lh-row.lh-active { background: rgba(0,255,159,.06); }
.lh-call-wrap { display: flex; align-items: center; gap: .35rem; }
.lh-tx-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); box-shadow: 0 0 6px var(--green); animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call { font-family: var(--font-mono); font-size: .82rem; color: var(--green); letter-spacing: .05em; font-weight: bold; }
.lh-name { font-family: var(--font-ui); font-size: .82rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lh-tg { font-family: var(--font-mono); font-size: .72rem; color: var(--amber); }
.lh-time { font-family: var(--font-mono); font-size: .68rem; color: var(--text-dim); }
.lh-src { font-family: var(--font-mono); font-size: .6rem; }
.lh-src.rf { color: var(--green); }
.lh-src.net { color: var(--cyan); }
.lh-empty { padding: 1.5rem 1rem; font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); text-align: center; }
.lh-panel-ysf { background: var(--surface); border: 3px solid #2d1a4a; border-radius: 6px; display: flex; flex-direction: column; }
.lh-header-ysf { background: #1a1424; border-bottom: 1px solid #2d1a4a; padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.8fr 1fr .6fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: #4a2a7a; letter-spacing: .1em; text-transform: uppercase; }
.lh-row-ysf { display: grid; grid-template-columns: 1.2fr 1.8fr 1fr .6fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(45,26,74,.5); align-items: center; transition: background .2s; }
.lh-row-ysf:last-child { border-bottom: none; }
.lh-row-ysf:hover { background: rgba(181,122,255,.04); }
.lh-row-ysf.lh-active { background: rgba(181,122,255,.08); }
.lh-tx-dot-ysf { width: 6px; height: 6px; border-radius: 50%; background: var(--violet); box-shadow: 0 0 6px var(--violet); animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call-ysf { font-family: var(--font-mono); font-size: .82rem; color: var(--violet); letter-spacing: .05em; font-weight: bold; }
/* NXDN last heard */
.lh-panel-nxdn { background: var(--surface); border: 3px solid #4a4a00; border-radius: 6px; display: flex; flex-direction: column; }
.lh-header-nxdn { background: #1a1a0a; border-bottom: 1px solid #4a4a00; padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.8fr .8fr 1fr .6fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: #707000; letter-spacing: .1em; text-transform: uppercase; }
.lh-row-nxdn { display: grid; grid-template-columns: 1.2fr 1.8fr .8fr 1fr .6fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(74,74,0,.5); align-items: center; transition: background .2s; }
.lh-row-nxdn:last-child { border-bottom: none; }
.lh-row-nxdn:hover { background: rgba(255,215,0,.04); }
.lh-row-nxdn.lh-active { background: rgba(255,215,0,.08); }
.lh-tx-dot-nxdn { width: 6px; height: 6px; border-radius: 50%; background: #ffd700; box-shadow: 0 0 6px #ffd700; animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call-nxdn { font-family: var(--font-mono); font-size: .82rem; color: #ffd700; letter-spacing: .05em; font-weight: bold; }
#ysfLastHeardPanel { grid-column: 2; }
#ysfDisplayPanel { grid-column: 2; }
@media (max-width:900px) { #ysfLastHeardPanel { grid-column: 1; } #ysfDisplayPanel { grid-column: 1; } }
.log-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
@media (max-width:900px) { .log-grid { grid-template-columns: 1fr; } }
.log-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
.log-panel-header { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; border-bottom: 1px solid var(--border); background: rgba(0,0,0,.3); }
.log-panel-header .svc-name { font-family: var(--font-mono); font-size: .8rem; letter-spacing: .1em; color: var(--green); text-transform: uppercase; }
.log-panel-header .svc-name.gw { color: var(--amber); }
.log-panel-header .svc-name.ysf { color: var(--violet); }
.log-panel-header .btn-clear { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); background: none; border: none; cursor: pointer; padding: 0; transition: color .2s; }
.log-panel-header .btn-clear:hover { color: var(--text); }
.log-output { font-family: var(--font-mono); font-size: .72rem; line-height: 1.55; color: #7a9ab5; padding: .8rem 1rem; height: 190px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
.log-output::-webkit-scrollbar { width: 4px; }
.log-output::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.ln-info { color: #7a9ab5; }
.ln-warn { color: var(--amber); }
.ln-err { color: var(--red); }
.ln-ok { color: var(--green-dim,#00cc7a); }
.restore-modal,.install-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.75); z-index: 9000; align-items: center; justify-content: center; }
.restore-modal.open,.install-modal.open { display: flex; }
.restore-box,.install-box { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 2rem; min-width: 380px; max-width: 90vw; }
.install-box { min-width: 480px; }
.restore-title { font-family: var(--font-mono); font-size: .8rem; color: var(--amber); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1.2rem; }
.install-title { font-family: var(--font-mono); font-size: .8rem; color: var(--green); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1.2rem; }
.restore-label { font-family: var(--font-mono); font-size: .72rem; color: var(--text); display: block; margin-bottom: .5rem; }
.restore-file { width: 100%; background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; color: var(--green); font-family: var(--font-mono); font-size: .8rem; padding: .5rem; margin-bottom: 1rem; }
.restore-btns { display: flex; gap: .8rem; }
.restore-btn-ok { flex: 1; background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .6rem; cursor: pointer; transition: background .2s; }
.restore-btn-ok:hover { background: #218838; }
.restore-btn-cancel { flex: 1; background: transparent; color: var(--text-dim); border: 1px solid var(--border); border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .6rem; cursor: pointer; transition: all .2s; }
.restore-btn-cancel:hover { border-color: var(--text); color: var(--text); }
.restore-msg { margin-top: .8rem; font-family: var(--font-mono); font-size: .75rem; display: none; padding: .5rem .8rem; border-radius: 4px; border: 1px solid; }
.restore-msg.ok { color: var(--green); border-color: var(--green); background: rgba(0,255,159,.06); }
.restore-msg.err { color: var(--red); border-color: var(--red); background: rgba(255,69,96,.06); }
.restore-msg.loading { color: var(--amber); border-color: var(--amber); background: rgba(255,179,0,.06); }
.install-output { font-family: var(--font-mono); font-size: .72rem; color: #7a9ab5; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 200px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: 1rem; display: none; }
.install-output.visible { display: block; }
.dropdown-wrap { position: relative; display: inline-block; }
.dropdown-menu-custom { display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background: var(--surface); border: 1px solid var(--border); border-radius: 6px; min-width: 270px; z-index: 1000; box-shadow: 0 8px 24px rgba(0,0,0,.5); overflow: hidden; padding-top: .4rem; }
.dropdown-wrap:hover .dropdown-menu-custom { display: block; }
.dropdown-wrap::after { content: ''; position: absolute; top: 100%; left: 0; right: 0; height: .4rem; }
.dropdown-item-custom { display: block; width: 100%; padding: .55rem 1rem; font-family: var(--font-mono); font-size: .75rem; letter-spacing: .07em; text-transform: uppercase; color: var(--text); background: none; border: none; cursor: pointer; text-align: left; transition: background .15s, color .15s; border-bottom: 1px solid var(--border); }
.dropdown-item-custom:last-child { border-bottom: none; }
.dropdown-item-custom:hover { background: rgba(0,212,255,.08); color: var(--cyan); }
.update-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.8); z-index: 9500; align-items: center; justify-content: center; }
.update-modal.open { display: flex; }
.update-box { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; width: 680px; max-width: 95vw; }
.update-title { font-family: var(--font-mono); font-size: .8rem; color: var(--cyan); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1rem; }
.update-console { font-family: var(--font-mono); font-size: .75rem; color: #7a9ab5; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 280px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: 1rem; }
.update-console .ok { color: var(--green); }
.update-console .err { color: var(--red); }
.update-console .inf { color: #7a9ab5; }
.xterm-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:9600; align-items:center; justify-content:center; }
.xterm-modal.open { display:flex; }
.xterm-box { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:1.5rem; width:780px; max-width:95vw; }
.xterm-title { font-family:var(--font-mono); font-size:.8rem; color:var(--cyan); letter-spacing:.12em; text-transform:uppercase; margin-bottom:1rem; }
.xterm-out { font-family:var(--font-mono); font-size:.75rem; color:#7a9ab5; background:#060c10; border:1px solid var(--border); border-radius:4px; padding:.8rem; height:340px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; margin-bottom:.6rem; }
.xterm-out::-webkit-scrollbar{width:4px;} .xterm-out::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px;}
.xterm-row { display:flex; align-items:center; gap:.5rem; background:#060c10; border:1px solid var(--border); border-radius:4px; padding:.5rem .8rem; margin-bottom:1rem; }
.xterm-pr { font-family:var(--font-mono); font-size:.78rem; color:#00ff9f; white-space:nowrap; }
.xterm-inp { flex:1; background:transparent; border:none; outline:none; font-family:var(--font-mono); font-size:.78rem; color:#c9d1d9; caret-color:#00ff9f; }
.xt-cmd{color:#c9d1d9;} .xt-out{color:#7a9ab5;} .xt-err{color:#f85149;}
.fedit-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9700;align-items:center;justify-content:center;}
.fedit-modal.open{display:flex;}
.fedit-box{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:1.5rem;width:900px;max-width:96vw;display:flex;flex-direction:column;gap:.8rem;}
.fedit-title{font-family:var(--font-mono);font-size:.8rem;color:var(--cyan);letter-spacing:.12em;text-transform:uppercase;}
.fedit-path{font-family:var(--font-mono);font-size:.72rem;color:var(--amber);letter-spacing:.06em;margin-bottom:.2rem;}
.fedit-area{font-family:var(--font-mono);font-size:.78rem;color:#c9d1d9;background:#060c10;border:1px solid var(--border);border-radius:4px;padding:.8rem;height:420px;resize:vertical;outline:none;line-height:1.5;width:100%;tab-size:4;}
.fedit-area:focus{border-color:var(--cyan);}
.fedit-msg{font-family:var(--font-mono);font-size:.75rem;display:none;padding:.4rem .8rem;border-radius:4px;border:1px solid;}
.fedit-msg.ok{color:var(--green);border-color:var(--green);background:rgba(0,255,159,.06);}
.fedit-msg.err{color:var(--red);border-color:var(--red);background:rgba(255,69,96,.06);}