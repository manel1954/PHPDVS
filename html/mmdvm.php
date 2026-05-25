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
    $cacheFile = '/tmp/mmdvm_cpu_prev.json';
    $s2 = file('/proc/stat');
    $cpu2 = preg_split('/\s+/', trim($s2[0]));
    if (file_exists($cacheFile)) {
        $cpu1 = json_decode(file_get_contents($cacheFile), true);
    } else {
        $cpu1 = $cpu2;
    }
    file_put_contents($cacheFile, json_encode($cpu2));
    $idle1 = $cpu1[4]; $total1 = array_sum(array_slice($cpu1, 1));
    $idle2 = $cpu2[4]; $total2 = array_sum(array_slice($cpu2, 1));
    $dTotal = $total2 - $total1; $dIdle = $idle2 - $idle1;
    $onlineCores = intval(trim(shell_exec('nproc 2>/dev/null'))) ?: 1;
    $totalCores  = intval(trim(shell_exec('nproc --all 2>/dev/null'))) ?: $onlineCores;
    $rawCpu = $dTotal > 0 ? (100 * ($dTotal - $dIdle) / $dTotal) : 0;
    $cpu = round($rawCpu * $onlineCores / $totalCores, 1);
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

// ── BACKUP: llama a copiar.sh y sirve el ZIP resultante ──────────────────────
if ($action === 'backup-configs') {
    $zipPath = '/tmp/Copia_PHPPLUS.zip';
    $zipName = 'Copia_PHPPLUS.zip';
    shell_exec('sudo /home/pi/A108/copiar.sh 2>/dev/null');
    if (file_exists($zipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$zipName.'"');
        header('Content-Length: '.filesize($zipPath));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zipPath);
        unlink($zipPath);
    } else {
        header('Content-Type: text/plain');
        echo 'Error: No se pudo crear el ZIP.';
    }
    exit;
}

// ── RESTORE: recibe el ZIP, lo pasa a restaurar.sh y devuelve JSON ───────────
if ($action === 'restore-configs') {
    ob_start(); error_reporting(0);
    $uploadOk = isset($_FILES['zipfile']) && $_FILES['zipfile']['error'] === UPLOAD_ERR_OK;
    if (!$uploadOk) {
        $errCode = $_FILES['zipfile']['error'] ?? -1;
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>'No se recibió el fichero. Error: '.$errCode]);
        exit;
    }
    $tmpZip = $_FILES['zipfile']['tmp_name'];
    if (!file_exists($tmpZip) || filesize($tmpZip) === 0) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>'Fichero vacío.']);
        exit;
    }
    $destZip = '/tmp/restore_upload_'.time().'.zip';
    move_uploaded_file($tmpZip, $destZip);
    $output = trim(shell_exec('sudo /home/pi/A108/restaurar.sh '.escapeshellarg($destZip).' 2>&1'));
    @unlink($destZip);
    ob_end_clean();
    if (strpos($output, 'OK:') === 0) {
        $msg = substr($output, 3);
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true,'msg'=>$msg]);
    } else {
        $msg = strpos($output, 'ERROR:') === 0 ? substr($output, 6) : ($output ?: 'Error desconocido.');
        header('Content-Type: application/json');
        echo json_encode(['ok'=>false,'msg'=>$msg]);
    }
    exit;
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

// ── DMR ──────────────────────────────────────────────────────────────────────
if ($action === 'transmission') {
    $stateFile = '/tmp/mmdvm_tx_state.json';
    $lhFile    = '/tmp/mmdvm_lastheard.json';
    $log   = shell_exec("sudo journalctl -u mmdvmhost -n 500 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));

    $state = ['active'=>false,'callsign'=>'','name'=>'','dmrid'=>'','tg'=>'','slot'=>'','source'=>''];
    if (file_exists($stateFile)) {
        $saved = json_decode(file_get_contents($stateFile), true);
        if (is_array($saved)) $state = $saved;
    }
    foreach ($lines as $line) {
        if (preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $callsign = strtoupper(rtrim($m[3], ','));
            $info = lookupCall($callsign);
            $state = ['active'=>true,'callsign'=>$callsign,'name'=>$info['name'],'dmrid'=>$info['dmrid'],'tg'=>$m[4],'slot'=>$m[1],'source'=>strtoupper($m[2])];
            file_put_contents($stateFile, json_encode($state));
            break;
        }
        if (preg_match('/DMR Slot \d.*end of voice/i', $line)) {
            $state['active'] = false;
            file_put_contents($stateFile, json_encode($state));
            break;
        }
    }

    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
    if (preg_match('/^[A-Z][a-z]{2}\s+\d+\s+(\d{2}:\d{2}:\d{2}).*DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $cs = strtoupper(rtrim($m[4], ','));
            if (!in_array($cs, $seen)) {
                $inf = lookupCall($cs);
                $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dmrid'=>$inf['dmrid'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]];
                $seen[] = $cs;
                if (count($lastHeard) >= 5) break;
            }
        }
    }
    if (!empty($lastHeard)) {
        file_put_contents($lhFile, json_encode($lastHeard));
    } elseif (file_exists($lhFile)) {
        $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    }

    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json');
    echo json_encode($state);
    exit;
}

// ── DSTAR ──────────────────────────────────────────────────────────────────────
if ($action === 'dstar-transmission') {
    $stateFile = '/tmp/dstar_tx_state.json';
    $lhFile    = '/tmp/dstar_lastheard.json';
    $log   = shell_exec("sudo journalctl -u mmdvmdstar -n 500 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log ?? ''));

    $state = ['active'=>false,'callsign'=>'','name'=>'','source'=>''];
    if (file_exists($stateFile)) {
        $saved = json_decode(file_get_contents($stateFile), true);
        if (is_array($saved)) $state = $saved;
    }
    foreach ($lines as $line) {
        if (preg_match('/D-Star.*(end of|lost RF|watchdog|finished|timeout)/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/D-Star.*received (RF|network).*from\s+([A-Z0-9\/]+)/i', $line, $m)) {
            $cs = strtoupper(trim($m[2])); $inf = lookupCall(preg_replace('/\/.*$/', '', $cs));
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'source'=>strtoupper($m[1])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/received (RF|network) header from\s+([A-Z0-9\/]+)/i', $line, $m)) {
            $cs = strtoupper(trim($m[2])); $inf = lookupCall(preg_replace('/\/.*$/', '', $cs));
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'source'=>strtoupper($m[1])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
    }

    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs=''; $src=''; $time='';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*D-Star.*received (RF|network).*from\s+([A-Z0-9\/]+)/i', $line, $m))
            { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*received (RF|network) header from\s+([A-Z0-9\/]+)/i', $line, $m))
            { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        if ($cs && !in_array($cs, $seen)) {
            $inf = lookupCall(preg_replace('/\/.*$/', '', $cs));
            $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'source'=>$src,'time'=>$time];
            $seen[] = $cs; if (count($lastHeard) >= 5) break;
        }
    }
    if (!empty($lastHeard)) {
        file_put_contents($lhFile, json_encode($lastHeard));
    } elseif (file_exists($lhFile)) {
        $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    }

    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json'); echo json_encode($state); exit;
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

// ── YSF ──────────────────────────────────────────────────────────────────────
if ($action === 'ysf-transmission') {
    $stateFile = '/tmp/ysf_tx_state.json';
    $lhFile    = '/tmp/ysf_lastheard.json';
    $log = shell_exec("sudo journalctl -u mmdvmysf -n 500 --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) $log = shell_exec("sudo journalctl -u ysfgateway -n 500 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));

    $state = ['active'=>false,'callsign'=>'','name'=>'','dest'=>'','source'=>''];
    if (file_exists($stateFile)) {
        $saved = json_decode(file_get_contents($stateFile), true);
        if (is_array($saved)) $state = $saved;
    }
    foreach ($lines as $line) {
        if (preg_match('/YSF.*(end of|lost RF|lost net|watchdog|timeout|no reply|voice end|fin)/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/YSF.*voice (end|fin|stop)/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i', $line, $m)) {
            $cs = strtoupper(trim($m[3])); $inf = lookupCall($cs);
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'dest'=>'','source'=>strtoupper($m[2])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*?(RF|network).*?from\s+(\S+)\s+to\s+(\S+)/i', $line, $m)) {
            $cs = strtoupper(trim($m[3])); $inf = lookupCall($cs);
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'dest'=>trim($m[4]),'source'=>strtoupper($m[2])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
    }

    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs=''; $src=''; $time=''; $dst='';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i', $line, $m))
            { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*?(RF|network).*?from\s+(\S+)\s+to\s+(\S+)/i', $line, $m))
            { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); $dst=trim($m[4]); }
        if ($cs && !in_array($cs, $seen)) {
            $inf = lookupCall($cs);
            $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dest'=>$dst,'source'=>$src,'time'=>$time];
            $seen[] = $cs; if (count($lastHeard) >= 5) break;
        }
    }
    if (!empty($lastHeard)) {
        file_put_contents($lhFile, json_encode($lastHeard));
    } elseif (file_exists($lhFile)) {
        $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    }

    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json'); echo json_encode($state); exit;
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
    saveState('nxdn','on');
    shell_exec('sudo systemctl start mmdvmnxdn 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl start nxdngateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true]);
    exit;
}
if ($action === 'nxdn-stop') {
    saveState('nxdn','off');
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
    $stateFile = '/tmp/nxdn_tx_state.json';
    $lhFile    = '/tmp/nxdn_lastheard.json';
    $log   = shell_exec("sudo journalctl -u mmdvmnxdn -n 500 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log ?? ''));

    $state = ['active'=>false,'callsign'=>'','name'=>'','tg'=>'','source'=>''];
    if (file_exists($stateFile)) {
        $saved = json_decode(file_get_contents($stateFile), true);
        if (is_array($saved)) $state = $saved;
    }
    foreach ($lines as $line) {
        if (preg_match('/NXDN.*(end of|lost RF|watchdog|finished|timeout)/i', $line)) {
            $state['active'] = false; file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/NXDN.*received (RF|network).*from\s+([A-Z0-9]+).*to\s+(\d+)/i', $line, $m)) {
            $cs = strtoupper(trim($m[2])); $inf = lookupCall($cs);
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>$m[3],'source'=>strtoupper($m[1])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
        if (preg_match('/NXDN.*received (RF|network).*from\s+([A-Z0-9]+)/i', $line, $m)) {
            $cs = strtoupper(trim($m[2])); $inf = lookupCall($cs);
            $state = ['active'=>true,'callsign'=>$cs,'name'=>$inf['name'],'tg'=>'','source'=>strtoupper($m[1])];
            file_put_contents($stateFile, json_encode($state)); break;
        }
    }

    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs=''; $src=''; $time=''; $tgr='';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*received (RF|network).*from\s+([A-Z0-9]+).*to\s+(\d+)/i', $line, $m))
            { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); $tgr=$m[4]; }
        elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*NXDN.*received (RF|network).*from\s+([A-Z0-9]+)/i', $line, $m))
            { $time=$m[1]; $src=strtoupper($m[2]); $cs=strtoupper(trim($m[3])); }
        if ($cs && !in_array($cs, $seen)) {
            $inf = lookupCall($cs);
            $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'tg'=>$tgr,'source'=>$src,'time'=>$time];
            $seen[] = $cs; if (count($lastHeard) >= 5) break;
        }
    }
    if (!empty($lastHeard)) {
        file_put_contents($lhFile, json_encode($lastHeard));
    } elseif (file_exists($lhFile)) {
        $lastHeard = json_decode(file_get_contents($lhFile), true) ?: [];
    }

    $state['lastHeard'] = $lastHeard;
    header('Content-Type: application/json'); echo json_encode($state); exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel PHPPLUS ADER</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg: #032354; --surface: #111720; --border: #1e2d3d; --green: #00ff9f; --green-dim: #00cc7a; --red: #ff4560; --amber: #ffb300; --cyan: #00d4ff; --violet: #b57aff; --text: #a8b9cc; --text-dim: #4a5568; --font-mono: 'Share Tech Mono', monospace; --font-ui: 'Rajdhani', sans-serif; --font-orb: 'Orbitron', monospace; }
* { box-sizing: border-box; }
body { background:#00004d; color: var(--text); font-family: var(--font-ui); font-size: 1rem; min-height: 100vh; padding: 0; margin: 0; }

.ctrl-header-inner { width: 100%; max-width: 1400px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: .6rem; }


.ctrl-header { border-bottom: 2px solid #ffffff; padding: 1rem 2rem; display: flex; flex-direction: column; align-items: center; gap: .6rem; }

.ctrl-header-top { display: flex; align-items: center; gap: .8rem; }
.ctrl-header-top h1 { font-family: var(--font-ui); font-weight: 700; font-size: 1.5rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
.ctrl-header-btns { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; justify-content: center; margin-top: .9rem; }
.btn-header { font-family: var(--font-mono); font-size: .65rem; letter-spacing: .08em; text-transform: uppercase; background: transparent; border-radius: 4px; padding: .28rem .75rem; cursor: pointer; transition: background .2s; text-decoration: none; display: inline-block; }
.btn-header.cyan { color: var(--cyan); border: 1px solid var(--cyan); }
.btn-header.cyan:hover { background: rgba(0,212,255,.1); }
.btn-header.amber { color: var(--amber); border: 1px solid var(--amber); }
.btn-header.amber:hover { background: rgba(255,179,0,.1); }
.btn-header.red { color: var(--red); border: 1px solid var(--red); }
.btn-header.red:hover { background: rgba(255,69,96,.15); }
button.btn-header { font-family: var(--font-mono); }
.ctrl-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.station-card { background: linear-gradient(135deg,#111720 60%,#0d1e2a 100%); border: 1px solid var(--border); border-radius: 10px; padding: 1.2rem 2rem; display: flex; align-items: center; gap: 2.0rem; margin-bottom: 1.8rem; flex-wrap: wrap; position: relative; overflow: hidden; }
.station-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg,transparent,var(--cyan),var(--violet),transparent); }
.station-card-main { display: flex; flex-direction: column; align-items: flex-start; gap: .3rem; }
.station-callsign { font-family: var(--font-orb); font-size: 2.4rem; font-weight: 900; color: var(--cyan); letter-spacing: .08em; line-height: 1; text-shadow: 0 0 20px rgba(0,212,255,.4); }
.station-divider { width: 2px; height: 70px; background: var(--border); flex-shrink: 0; }
.station-meta-item { display: flex; flex-direction: column; gap: .15rem; }
.station-meta-label { font-family: var(--font-mono); font-size: .6rem; color: var(--text-dim); letter-spacing: .15em; text-transform: uppercase; }
.station-meta-value { font-family: var(--font-mono); font-size: .95rem; color: var(--amber); letter-spacing: .06em; font-weight: bold; }
@media (max-width:700px) { .station-card { gap: 1.2rem; padding: 1rem; } .station-divider { display: none; } }
.status-bar { display: flex; gap: 2rem; margin-bottom: 1.8rem; flex-wrap: wrap; align-items: center; }
.status-item { display: flex; align-items: center; gap: .5rem; font-family: var(--font-mono); font-size:13px; text-transform: uppercase; letter-spacing: .08em; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); transition: background .4s, box-shadow .4s; }
.dot.active { background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 2s infinite; }
.dot.error { background: var(--red); box-shadow: 0 0 8px var(--red); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.section-divider { width: 1px; height: 20px; background: var(--border); margin: 0 .2rem; }
.controls-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 2rem; }
@media (max-width:800px) { .controls-section { grid-template-columns: 1fr; } }
.service-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.2rem 1.6rem; }
.service-card-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: 1rem; }
.service-card-label.dmr { color: var(--amber); }
.service-card-label.ysf { color: var(--violet); }
.toggle-row { display: flex; align-items: center; gap: 1rem; padding: .5rem 0; }
.toggle-label { font-family: var(--font-mono); font-size: .85rem; letter-spacing: .06em; color: var(--text-dim); text-transform: uppercase; flex: 1; transition: color .3s; }
.toggle-label.on-dmr { color: var(--amber); }
.toggle-label.on-ysf { color: var(--violet); }
.toggle-status { font-family: var(--font-mono); font-size: .72rem; letter-spacing: .1em; color: var(--text-dim); min-width: 3rem; text-align: right; transition: color .3s; }
.toggle-status.on { color: var(--green); }
.sw { position: relative; width: 56px; height: 28px; flex-shrink: 0; cursor: pointer; }
.sw input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track { position: absolute; inset: 0; border-radius: 2px; background: #1a2535; border: 2px solid #f00; transition: background .3s, border-color .3s, box-shadow .3s; }
.sw-knob { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #f00; box-shadow: 0 1px 4px rgba(0,0,0,.5); transition: transform .3s cubic-bezier(.4,0,.2,1), background .3s, box-shadow .3s; }
.sw.dmr input:checked ~ .sw-track, .sw.ysf input:checked ~ .sw-track, .sw.dstar input:checked ~ .sw-track { border-radius: 2px; background: #1a2535; border: 2px solid #00ff4c }
.sw.dmr input:checked ~ .sw-knob, .sw.ysf input:checked ~ .sw-knob, .sw.dstar input:checked ~ .sw-knob { transform: translateX(28px); background:#00ff4c; box-shadow: 0 0 8px rgba(0,255,159,.6); }
.sw#swNXDN input:checked ~ .sw-knob { transform: translateX(28px); background: #00ff4c; box-shadow: 0 0 8px rgba(255,215,0,.6); }
.sw#swNXDN input:checked ~ .sw-track { border-color: #00ff4c; }
.sw-busy-dot { display: none; position: absolute; top: 50%; right: -18px; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; border: 2px solid var(--amber); border-top-color: transparent; animation: spin .7s linear infinite; }
.sw.busy .sw-busy-dot { display: block; }
@keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }
.auto-badge { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); display: flex; align-items: center; gap: .4rem; margin-top: .4rem; }
.auto-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
.auto-badge.ysf .dot-sm { background: var(--violet); }
.service-card-btns { display: flex; gap: .6rem; flex-wrap: nowrap; margin-top: 1rem; }
.ini-btn { font-family: var(--font-mono); font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; padding: .3rem .7rem; border-radius: 3px; border: 1px solid var(--border); background: transparent; cursor: pointer; text-decoration: none; transition: all .2s; display: inline-flex; align-items: center; gap: .3rem; }
.ini-btn.edit { color: var(--amber); border-color: rgba(255,179,0,.3); }
.ini-btn.edit:hover { border-color: var(--amber); background: rgba(255,179,0,.08); }
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

.nx-botbar .nx-dmrid { color: #2a5a7a; font-size:0.65rem; min-width: 6rem; }

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
.nx-clock { font-family: var(--font-orb); font-size: 4rem; font-weight: 700; color: #fff; letter-spacing: .06em; line-height: 1; }
.nx-date { font-family: var(--font-mono); font-size: .7rem; color: #ff0; letter-spacing: .12em; text-transform: uppercase; margin-top: .2rem; }
.nx-callsign { font-family: var(--font-orb); font-size: 3.4rem; font-weight: 900; letter-spacing: .04em; line-height: 1; color: var(--green); text-shadow: 0 0 20px rgba(255,255,159,.55); }
.nx-callsign.ysf { color: var(--violet); text-shadow: 0 0 20px rgba(181,122,255,.6); }
.nx-callsign.nxdn { color: #ffd700; text-shadow: 0 0 20px rgba(255,215,0,.6); }
.nx-name { font-family: var(--font-ui); font-weight: 500; font-size: 1.2rem; color: var(--cyan); letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-top: .15rem; }
.nx-name.ysf { color: #d4a8ff; }
.nx-name.nxdn { color: #ffc400; }
.nx-infobar { position: absolute; top: 30px; left: 0; right: 0; height: 26px; background: rgba(0,0,0,.35); border-bottom: 1px solid #0d2030; display: flex; align-items: center; justify-content: space-around; padding: 0 8rem; gap: 1rem; z-index: 2; }
.nx-info-item { display: flex; align-items: center; gap: .4rem; }
.nx-info-lbl { font-family: var(--font-mono); font-size: 10px; color:#999; text-transform: uppercase; }
.nx-info-val { font-family: var(--font-mono); font-size: 10px; color:#ff0;font-weight: bold; }
.nx-info-val.cyan { color: var(--cyan); }
.nx-info-val.amber { color: var(--amber); }
.nx-info-val.green { color: var(--green); }
.nx-infobar-ysf { background: rgba(0,0,0,.4); border-bottom: 1px solid #1a0d30; }
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
.lh-panel-nxdn { background: var(--surface); border: 3px solid #4a4a00; border-radius: 6px; display: flex; flex-direction: column; }
.lh-header-nxdn { background: #1a1a0a; border-bottom: 1px solid #4a4a00; padding: .4rem 1rem; display: grid; grid-template-columns: 1.2fr 1.8fr .8fr 1fr .6fr; gap: .3rem; font-family: var(--font-mono); font-size: .6rem; color: #707000; letter-spacing: .1em; text-transform: uppercase; }
.lh-row-nxdn { display: grid; grid-template-columns: 1.2fr 1.8fr .8fr 1fr .6fr; gap: .3rem; padding: .45rem 1rem; border-bottom: 1px solid rgba(74,74,0,.5); align-items: center; transition: background .2s; }
.lh-row-nxdn:last-child { border-bottom: none; }
.lh-row-nxdn:hover { background: rgba(255,215,0,.04); }
.lh-row-nxdn.lh-active { background: rgba(255,215,0,.08); }
.lh-tx-dot-nxdn { width: 6px; height: 6px; border-radius: 50%; background: #ffd700; box-shadow: 0 0 6px #ffd700; animation: pulse 1s infinite; flex-shrink: 0; }
.lh-call-nxdn { font-family: var(--font-mono); font-size: .82rem; color: #ffd700; letter-spacing: .05em; font-weight: bold; }
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
.restore-btn-cancel:disabled { opacity: .3; cursor: not-allowed; pointer-events: none; }
.restore-msg { margin-top: .8rem; font-family: var(--font-mono); font-size: .75rem; display: none; padding: .5rem .8rem; border-radius: 4px; border: 1px solid; }
.restore-msg.ok { color: var(--green); border-color: var(--green); background: rgba(0,255,159,.06); }
.restore-msg.err { color: var(--red); border-color: var(--red); background: rgba(255,69,96,.06); }
.restore-msg.loading { color: var(--amber); border-color: var(--amber); background: rgba(255,179,0,.06); }
.install-output { font-family: var(--font-mono); font-size: .72rem; color: #b5a97a; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 200px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: 1rem; display: none; }
.install-output.visible { display: block; }
.dropdown-wrap { position: relative; display: inline-block; }
.dropdown-menu-custom { display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background:#696969; border: 1px solid var(--border); border-radius: 6px; min-width: 270px; z-index: 1000; box-shadow: 0 8px 24px rgba(0,0,0,.5); overflow: hidden; padding-top: .4rem; }
.dropdown-wrap:hover .dropdown-menu-custom { display: block; }
.dropdown-wrap::after { content: ''; position: absolute; top: 100%; left: 0; right: 0; height: .4rem; }
.dropdown-item-custom { display: block; width: 100%; padding: .55rem 1rem; font-family: var(--font-mono); font-size: .75rem; letter-spacing: .04em; text-transform: uppercase; color:#ffffff; font-weight: bold;background: none; border: none; cursor: pointer; text-align: left; transition: background .15s, color .15s; border-bottom: 1px solid var(--border); }
.dropdown-item-custom:last-child { border-bottom: none; }
.dropdown-item-custom:hover { background:#333333; color:#fff; }
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
.flag-emoji { font-family: 'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif; font-size: 1.6rem; display: inline-block; vertical-align: middle; margin-right: 4px; line-height: 1; }
.flag-emoji-img { height: 20px; width: auto; vertical-align: middle; margin-right: 4px; border-radius: 2px; }
.nx-callsign .flag-emoji { font-size: 3.2rem; }
.nx-callsign .flag-emoji-img { height: 42px; }
</style>
</head>
<body>
<header class="ctrl-header" style="background-color:#000000">
<div class="ctrl-header-top">
<a href="https://associacioader.com" target="_blank">
  <img src="Logo_Ader.png" alt="EA3EIZ" style="height:40px; width:auto;">
</a>
<span style="color:#c9d1d9;font-size:1.2rem;">PANEL SISTEMAS DIGITALES PARA RADIOAFICIONADOS</span>
<span style="color:cyan;font-size:1.2rem;">PHPDVS</span>

</div>
<div class="ctrl-header-btns">
<a href="editor_general_config.php" class="btn btn-primary btn-sm"> ✏️ Editor General </a>
<a href="?action=backup-configs" class="btn btn-success btn-sm"> 💾 Hacer copia de seguridad </a>
<button onclick="openRestore()" class="btn btn-info btn-sm"> 💿 Restaurar copia de seguridad </button>
<div class="dropdown-wrap" id="dropActualizaciones">
  <button class="btn btn-light btn-sm">⬇ Actualizaciones ▾</button>
  <div class="dropdown-menu-custom">
    <button class="dropdown-item-custom" onclick="runUpdate('imagen')">⬇ Actualizar Imagen</button>
    <button class="dropdown-item-custom" onclick="runUpdate('ids')">📋 Actualizar IDs</button>
    <button class="dropdown-item-custom" onclick="runUpdate('ysf')">📡 Actualizar Reflectores YSF</button>
    <button class="dropdown-item-custom" onclick="window.location.href='dstar_json_converter.php'">📡 Actualizar Reflectores D-STAR</button>
  </div>
</div>
<button class="btn btn-secondary btn-sm" onclick="xtTtydOpen()">🖥️ Terminal</button>
<a href="extra.php" class="btn btn-warning btn-sm">⚙️ Menu Extra</a>
<button id="btnReboot" class="btn btn-danger btn-sm" onclick="rebootPi()">⏻ Reiniciar Pi</button>
</div>
</header>
<main class="ctrl-body">
<div class="station-card" style="justify-content:space-between;">
    <div class="station-card-main"><div class="station-callsign" id="scCallsign">📡 —</div></div>
    <div class="station-divider" style="height:50px;"></div>
    <div class="station-meta-item" style="flex:1;align-items:flex-start;"><span class="station-meta-label" style="font-size:.55rem;">🖥️ CPU</span><span class="station-meta-value" id="siCpu" style="color:var(--green);font-size:.75rem;min-width:4.5rem;white-space:nowrap;">—</span></div>
    <div class="station-meta-item" style="flex:1;align-items:flex-start;"><span class="station-meta-label" style="font-size:.55rem;">🌡️ Temp</span><span class="station-meta-value" id="siTemp" style="color:var(--amber);font-size:.75rem;min-width:4.5rem;white-space:nowrap;">—</span></div>
    <div class="station-meta-item" style="flex:1;align-items:flex-start;"><span class="station-meta-label" style="font-size:.55rem;">💾 RAM usada</span><span class="station-meta-value" id="siRam" style="color:var(--cyan);font-size:.75rem;min-width:4.5rem;white-space:nowrap;">—</span></div>
    <div class="station-meta-item" style="flex:1;align-items:flex-start;"><span class="station-meta-label" style="font-size:.55rem;">💾 RAM libre</span><span class="station-meta-value" id="siRamFree" style="color:var(--text);font-size:.75rem;min-width:4.5rem;white-space:nowrap;">—</span></div>
    <div class="station-meta-item" style="flex:1;align-items:flex-start;"><span class="station-meta-label" style="font-size:.55rem;">💿 Disco usado</span><span class="station-meta-value" id="siDisk" style="color:var(--amber);font-size:.75rem;min-width:4.5rem;white-space:nowrap;">—</span></div>
    <div class="station-meta-item" style="flex:1;align-items:flex-start;"><span class="station-meta-label" style="font-size:.55rem;">💿 Libre</span><span 
    
    class="station-meta-value" id="siDiskFree" style="color:var(--green);font-size:.75rem;min-width:4.5rem;white-space:nowrap;">—</span></div>
    <div class="station-divider" style="height:50px;"></div>
    <div style="flex:1;display:flex;flex-direction:row;align-items:center;gap:.4rem;"><?php
            $model = '';
            if (file_exists('/proc/device-tree/model'))
                $model = trim(str_replace("\0", '', file_get_contents('/proc/device-tree/model')));
            if ($model === '') $model = trim(shell_exec('uname -m 2>/dev/null') ?? '');
            $ml = strtolower($model);
            $icon = str_contains($ml, 'raspberry') ? '🍓' : (str_contains($ml, 'orange') ? '🍊' : '🖥️');
            $modelShort = str_contains($ml, 'raspberry') ? substr($model, 0, 14) : $model;
        ?>
        <span id="siModelLabel" style="font-size:1.4rem;line-height:1;"><?php echo $icon; ?></span>
        <span class="station-meta-value" id="siModel" style="color:var(--violet);font-size:.7rem;white-space:nowrap;"><?php echo htmlspecialchars($modelShort); ?></span>
    </div>
</div>
<div class="status-bar">
<div class="status-item"><div class="dot" id="dot-mosquitto"></div><span>Mosquitto</span></div>
<div class="status-item"><div class="dot" id="dot-mmdvm"></div><span>MMDVMHost</span></div>
<div class="status-item"><div class="dot" id="dot-gateway"></div><span>DMRGateway</span></div>
<div class="section-divider"></div>
<div class="status-item"><div class="dot" id="dot-mmdvmysf"></div><span style="color:#26c6da">MMDVMHost YSF</span></div>
<div class="status-item"><div class="dot" id="dot-ysf"></div><span style="color:var(--violet)">YSFGateway</span></div>
<div class="section-divider"></div>
<div class="status-item"><div class="dot" id="dot-dstarmmd"></div><span style="color:#00e5ff">MMDVMDStar</span></div>
<div class="status-item"><div class="dot" id="dot-dstargw"></div><span style="color:#00e5ff">DStarGateway</span></div>
<div class="section-divider"></div>
<div class="status-item"><div class="dot" id="dot-nxdnmmd"></div><span style="color:#ffd700">MMDVMHost NXDN</span></div>
<div class="status-item"><div class="dot" id="dot-nxdngw"></div><span style="color:#ffd700">NXDNGateway</span></div>
</div>
<div class="controls-section">
  <div class="service-card">
    <div class="service-card-label dmr" style="color:#fff;">▸ DMR · MMDVMHost + DMRGateway</div>
    <div class="toggle-row">
      <span class="toggle-label" id="dmrToggleLabel"style="color:#fff;">DMR</span>
      <label class="sw dmr" id="swDMR"><input type="checkbox" id="chkDMR" onchange="toggleServices(this)"><span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span></label>
      <span class="toggle-status" id="dmrToggleStatus">OFF</span>
    </div>
    <div class="auto-badge" id="autoRefreshBadge" style="display:none"><div class="dot-sm"></div> DMR activo</div>
    <div class="service-card-btns">
      <a href="mmdvm_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:var(--cyan);border-color:rgba(0,212,255,.3);">⚙ MMDVMHOST Config</a>
      <a href="dmrgateway_config.php" class="ini-btn edit" style="flex:1;color:#fff;justify-content:center;">⚙ DMRGateway Config</a>
    </div>
    <div class="service-card-btns" style="margin-top:.4rem;">
      <a href="edit_ini.php?file=mmdvm" class="ini-btn view" style="flex:1;justify-content:center;">📄 EDITAR FICHERO MMDVMHOST.ini</a>
      <a href="edit_ini.php?file=dmrgateway" class="ini-btn view" style="flex:1;color:#00e5ff;justify-content:center;color:var(--amber);border-color:rgba(255,179,0,.3);color:#fff;">📄 EDITAR FICHERO DMRGateway.ini</a>
    </div>
  </div>
  <div class="service-card">
    <div class="service-card-label ysf">▸ C4FM · MMDVMHOST + YSFGATEWAY</div>
    <div class="toggle-row">
      <span class="toggle-label" id="ysfToggleLabel">C4FM</span>
      <label class="sw ysf" id="swYSF"><input type="checkbox" id="chkYSF" onchange="toggleYSF(this)"><span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span></label>
      <span class="toggle-status" id="ysfToggleStatus">OFF</span>
    </div>
    <div class="auto-badge ysf" id="ysfRefreshBadge" style="display:none"><div class="dot-sm"></div> C4FM activo</div>
    <div class="service-card-btns" style="margin-top:.4rem;">
      <a href="mmdvmysf_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(38,198,218,.3);">⚙ MMDVMYSF CONFIG</a>
      <a href="ysfgateway_config.php" class="ini-btn edit ysf" style="flex:1;justify-content:center;">⚙ YSFGATEWAY CONFIG</a>
    </div>
    <div class="service-card-btns">
      <a href="edit_ini.php?file=mmdvmysf" class="ini-btn view" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(38,198,218,.2);">📄 editar fichero MMDVMYSF.ini</a>
      <a href="edit_ini.php?file=ysfgateway" class="ini-btn view ysf" style="flex:1;justify-content:center;">📄 editar fichero YSFGateway.ini</a>
    </div>
  </div>
  <div class="service-card" style="border-color:rgba(0,229,255,.25);">
    <div class="service-card-label" style="color:#00ff9f;">▸ D-STAR · MMDVMHost + DStarGateway</div>
    <div class="toggle-row">
      <span class="toggle-label" id="dstarToggleLabel">D-STAR</span>
      <label class="sw dstar" id="swDSTAR"><input type="checkbox" id="chkDSTAR" onchange="toggleDStar(this)"><span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span></label>
      <span class="toggle-status" id="dstarToggleStatus">OFF</span>
    </div>
    <div class="auto-badge" id="dstarRefreshBadge" style="display:none;color:#00e5ff;"><div class="dot-sm" style="background:#00e5ff;"></div> D-STAR activo</div>
    <div class="service-card-btns" style="margin-top:.6rem;">
      <a href="mmdvmdstar_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(0,229,255,.3);">⚙ MMDVMDSTAR CONFIG</a>
      <a href="dstargateway_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00ff9f;border-color:rgba(0,255,159,.3);">⚙ DSTARGATEWAY CONFIG</a>
    </div>
    <div class="service-card-btns" style="margin-top:.4rem;">
      <a href="edit_ini.php?file=mmdvmdstar" class="ini-btn view" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(0,229,255,.3);">📄 editar fichero MMDVMDSTAR.ini</a>
      <a href="edit_ini.php?file=dstargateway" class="ini-btn view" style="flex:1;justify-content:center;color:#00ff9f;border-color:rgba(0,255,159,.3);">📄 editar fichero DStarGateway.ini</a>
    </div>
  </div>
  <div class="service-card" style="border-color:rgba(255,215,0,.25);">
    <div class="service-card-label" style="color:#ffd700;">▸ NXDN · MMDVMHost + NXDNGateway</div>
    <div class="toggle-row">
      <span class="toggle-label" id="nxdnToggleLabel">NXDN</span>
      <label class="sw" id="swNXDN">
        <input type="checkbox" id="chkNXDN" onchange="toggleNXDN(this)">
        <span class="sw-track"></span><span class="sw-knob"></span><span class="sw-busy-dot"></span>
      </label>
      <span class="toggle-status" id="nxdnToggleStatus">OFF</span>
    </div>
    <div class="auto-badge" id="nxdnRefreshBadge" style="display:none;color:#ffd700;"><div class="dot-sm" style="background:#ffd700;"></div> NXDN activo</div>
    <div class="service-card-btns" style="margin-top:.6rem;">
      <a href="mmdvmnxdn_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(255,215,0,.3);">⚙ MMDVMNXDN CONFIG</a>
      <a href="nxdngateway_config.php" class="ini-btn edit" style="flex:1;justify-content:center;color:#ffc400;border-color:rgba(255,196,0,.3);">⚙ NXDNGATEWAY CONFIG</a>
    </div>
    <div class="service-card-btns" style="margin-top:.4rem;">
      <a href="edit_ini.php?file=mmdvmnxdn" class="ini-btn view" style="flex:1;justify-content:center;color:#00e5ff;border-color:rgba(255,215,0,.3);">📄 editar fichero MMDVMNXDN.ini</a>
      <a href="edit_ini.php?file=nxdngateway" class="ini-btn view" style="flex:1;justify-content:center;color:#ffc400;border-color:rgba(255,196,0,.3);">📄 editar fichero NXDNGateway.ini</a>
    </div>
  </div>
</div>
<div class="display-row">
  <div id="dmrDisplayPanel">
    <div class="panel-label">▸ DMR Display</div>
    <div class="nextion">
      <div class="nx-topbar"><span class="nx-mode">DMR · SIMPLEX</span><span id="nxStationLabel" style="display:none"></span><span class="nx-tg" id="nxTG">—</span></div>
      <div class="nx-infobar"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="nxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val cyan" id="nxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val amber" id="nxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val green" id="nxIp">—</span></span></div>
      <div class="nx-vu" id="vuLeft"></div><div class="nx-vu right" id="vuRight"></div>
      <div class="nx-center" id="nxCenter"><div class="nx-clock" id="nxClock">00:00:00</div><div class="nx-date" id="nxDate">—</div></div>
      <div class="nx-txbar" id="nxTxBar"></div>
      <div class="nx-botbar"><span class="nx-dmrid" id="nxDmrid">—</span><span>DMR · DIGITAL VOICE</span><span id="nxSlot" style="display:none">—</span><span class="nx-source" id="nxSource"></span></div>
    </div>
  </div>
  <div id="ysfDisplayPanel">
    <div class="panel-label ysf-label">▸ C4FM Display</div>
    <div class="nextion-ysf">
      <div class="nx-topbar ysf-bar"><span class="nx-mode">C4FM · YSF</span><span style="color:#6a3a9a;display:none" id="ysfStationLabel"></span><span class="nx-dest" id="ysfDest">—</span></div>
      <div class="nx-infobar nx-infobar-ysf"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="ysfNxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val" style="color:#d4a8ff" id="ysfNxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val" style="color:#c084ff" id="ysfNxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val" style="color:#9b6dff" id="ysfNxIp">—</span></span></div>
      <div class="nx-vu" id="ysfVuLeft"></div><div class="nx-vu right" id="ysfVuRight"></div>
      <div class="nx-center" id="ysfNxCenter"><div class="nx-clock" id="ysfNxClock" style="color:#c084ff;">00:00:00</div><div class="nx-date" id="ysfNxDate" style="color:#9b59d4;">—</div></div>
      <div class="nx-txbar" id="ysfTxBar"></div>
      <div class="nx-botbar ysf-bar"><span style="color:#5a3a8a;font-family:var(--font-mono);font-size:.65rem;" id="ysfProto">C4FM . DIGITAL VOICE</span><span style="color:#5a3a8a;font-family:var(--font-mono);font-size:.65rem;"><?php $ysfGwIni=parseMMDVMIni('/home/pi/YSFClients/YSFGateway/YSFGateway.ini');$ysfRefStart=trim($ysfGwIni['Network']['Startup']??'—');echo "Reflector: " . htmlspecialchars($ysfRefStart); ?></span><span class="nx-source" id="ysfSource"></span></div>
    </div>
  </div>
</div>
<div class="display-row" style="margin-top:1.2rem;">
  <div id="dstarDisplayPanel">
    <div class="panel-label" style="color:#00e5ff;">▸ D-STAR Display</div>
    <div class="nextion-dstar">
      <div class="nx-topbar dstar-bar"><span class="nx-mode">D-STAR · DIGITAL</span><span style="color:#006070;display:none" id="dstarStationLabel"></span><span style="color:#00b0c0;opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="dstarDest">CQCQCQ</span></div>
      <div class="nx-infobar nx-infobar-dstar"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="dstarNxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val" style="color:#00e5ff" id="dstarNxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val" style="color:#00b0c0" id="dstarNxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val" style="color:#80f0ff" id="dstarNxIp">—</span></span></div>
      <div class="nx-vu" id="dstarVuLeft"></div><div class="nx-vu right" id="dstarVuRight"></div>
      <div class="nx-center" id="dstarNxCenter"><div class="nx-clock" id="dstarNxClock" style="color:#00e5ff;">00:00:00</div><div class="nx-date" id="dstarNxDate" style="color:#009090;">—</div></div>
      <div class="nx-txbar" id="dstarTxBar"></div>
      <div class="nx-botbar dstar-bar"><span style="color:#006070;font-family:var(--font-mono);font-size:.65rem;">D-STAR · DIGITAL VOICE</span><span style="color:green;font-family:var(--font-mono);font-size:.65rem;"><?php $dgwIni=parseMMDVMIni('/home/pi/DStarGateway/DStarGateway.ini');$dstarRef=trim($dgwIni['Repeater 1']['Reflector']??'—');echo "Reflector: " . htmlspecialchars($dstarRef); ?></span><span class="nx-source" id="dstarSource"></span></div>
    </div>
  </div>
  <div id="nxdnDisplayPanel">
    <div class="panel-label" style="color:#ffd700;">▸ NXDN Display</div>
    <div class="nextion-nxdn">
      <div class="nx-topbar nxdn-bar"><span class="nx-mode">NXDN · DIGITAL</span><span style="color:#707000" id="nxdnStationLabel">EA3EIZ · ADER</span><span style="color:#ffd700;opacity:.85;min-width:5rem;text-align:right;font-size:.6rem;" id="nxdnTGLabel">—</span></div>
      <div class="nx-infobar nx-infobar-nxdn"><span class="nx-info-item"><span class="nx-info-lbl">PORT</span><span class="nx-info-val" id="nxdnNxPort">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FRX</span><span class="nx-info-val" style="color:#ffd700" id="nxdnNxFrx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">FTX</span><span class="nx-info-val" style="color:#ffc400" id="nxdnNxFtx">—</span></span><span class="nx-info-item"><span class="nx-info-lbl">IP</span><span class="nx-info-val" style="color:#ffe066" id="nxdnNxIp">—</span></span></div>
      <div class="nx-vu" id="nxdnVuLeft"></div><div class="nx-vu right" id="nxdnVuRight"></div>
      <div class="nx-center" id="nxdnNxCenter"><div class="nx-clock" id="nxdnNxClock" style="color:#ffd700;">00:00:00</div><div class="nx-date" id="nxdnNxDate" style="color:#b8a000;">—</div></div>
      <div class="nx-txbar" id="nxdnTxBar"></div>
      <div class="nx-botbar nxdn-bar"><span style="color:#707000;font-family:var(--font-mono);font-size:.65rem;">NXDN · DIGITAL VOICE</span><span style="color:#ff0;font-family:var(--font-mono);font-size:.65rem;"><?php $nxdnGwIni=parseMMDVMIni('/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini');$nxdnRef=trim($nxdnGwIni['Network']['Static']??'—');echo 'Reflector: ' . htmlspecialchars($nxdnRef); ?></span><span class="nx-source" id="nxdnSource"></span></div>
    </div>
  </div>
</div>
<div class="display-row" style="margin-top:1rem;">
  <div id="dmrLastHeardPanel">
    <div class="panel-label">▸ Últimos escuchados DMR</div>
    <div class="lh-panel">
      <div class="lh-header"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="lhBody"><div class="lh-empty">Sin actividad reciente</div></div>
    </div>
  </div>
  <div id="ysfLastHeardPanel">
    <div class="panel-label ysf-label">▸ Últimos escuchados C4FM</div>
    <div class="lh-panel-ysf">
      <div class="lh-header-ysf"><span>Indicativo</span><span>Nombre</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="ysfLhBody"><div class="lh-empty">Sin actividad C4FM</div></div>
    </div>
  </div>
</div>
<div class="display-row" style="margin-top:1rem;">
  <div id="dstarLastHeardPanel">
    <div class="panel-label" style="color:#00e5ff;">▸ Últimos escuchados D-STAR</div>
    <div class="lh-panel" style="border-color:#004a4a;">
      <div class="lh-header" style="background:#0a1a1a;border-bottom-color:#004a4a;color:#006070;">
        <span>Indicativo</span><span>Nombre</span><span>Hora</span><span>Src</span>
      </div>
      <div class="lh-body" id="dstarLhBody"><div class="lh-empty">Sin actividad D-STAR</div></div>
    </div>
  </div>
  <div id="nxdnLastHeardPanel">
    <div class="panel-label" style="color:#ffd700;">▸ Últimos escuchados NXDN</div>
    <div class="lh-panel-nxdn">
      <div class="lh-header-nxdn"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
      <div class="lh-body" id="nxdnLhBody"><div class="lh-empty">Sin actividad NXDN</div></div>
    </div>
  </div>
</div>
<div class="log-grid" style="margin-top:2rem;">
<div id="dmrLogPanels" style="display:contents;">
<div class="log-panel"><div class="log-panel-header"><span class="svc-name">▸ MMDVMHost</span><button class="btn-clear" onclick="clearLog('logMmd')">limpiar</button></div><div class="log-output" id="logMmd">Esperando servicios…</div></div>
<div class="log-panel"><div class="log-panel-header"><span class="svc-name gw">▸ DMRGateway</span><button class="btn-clear" onclick="clearLog('logGw')">limpiar</button></div><div class="log-output" id="logGw">Esperando servicios…</div></div>
</div>
<div id="ysfLogPanels" style="display:contents;">
<div class="log-panel"><div class="log-panel-header"><span class="svc-name" style="color:#26c6da">▸ MMDVMHost YSF</span><button class="btn-clear" onclick="clearLog('logMmdvmYsf')">limpiar</button></div><div class="log-output" id="logMmdvmYsf">Esperando MMDVMHost YSF…</div></div>
<div class="log-panel"><div class="log-panel-header"><span class="svc-name ysf">▸ YSFGateway</span><button class="btn-clear" onclick="clearLog('logYsf')">limpiar</button></div><div class="log-output" id="logYsf">Esperando YSFGateway…</div></div>
</div>
<div id="dstarPanelMmd" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#80f0ff;">▸ MMDVMHost DStar</span><button class="btn-clear" onclick="clearLog('logDstarMmd')">limpiar</button></div><div class="log-output" id="logDstarMmd">Esperando MMDVMHost DStar…</div></div>
<div id="dstarPanelGw" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#00e5ff;">▸ DStarGateway</span><button class="btn-clear" onclick="clearLog('logDstarGw')">limpiar</button></div><div class="log-output" id="logDstarGw">Esperando DStarGateway…</div></div>
<div id="nxdnPanelMmd" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#ffd700;">▸ MMDVMHost NXDN</span><button class="btn-clear" onclick="clearLog('logNxdnMmd')">limpiar</button></div><div class="log-output" id="logNxdnMmd">Esperando MMDVMHost NXDN…</div></div>
<div id="nxdnPanelGw" class="log-panel" style="display:none;"><div class="log-panel-header"><span class="svc-name" style="color:#ffc400;">▸ NXDNGateway</span><button class="btn-clear" onclick="clearLog('logNxdnGw')">limpiar</button></div><div class="log-output" id="logNxdnGw">Esperando NXDNGateway…</div></div>
</div>
</main>

<!-- Modal Editor Ficheros -->
<div id="feditModal" class="fedit-modal" onclick="if(event.target===this)feditClose()">
<div class="fedit-box">
  <div class="fedit-title">📝 Editor de fichero</div>
  <div class="fedit-path" id="feditPath">—</div>
  <textarea class="fedit-area" id="feditArea" spellcheck="false"></textarea>
  <div class="fedit-msg" id="feditMsg"></div>
  <div class="restore-btns">
    <button class="restore-btn-ok" onclick="feditSave()">💾 Guardar</button>
    <button class="restore-btn-cancel" onclick="feditClose()">✖ Cerrar</button>
  </div>
</div>
</div>

<!-- Modal Terminal ttyd -->
<div id="xtTtydModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9800;align-items:center;justify-content:center;">
  <div style="background:#0a0e14;border:1px solid #1e2d3d;border-radius:8px;width:960px;max-width:96vw;height:620px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.2rem;background:#111720;border-bottom:1px solid #1e2d3d;flex-shrink:0;">
      <span style="font-family:'Share Tech Mono',monospace;font-size:.8rem;color:var(--cyan);letter-spacing:.12em;text-transform:uppercase;">⌨ Terminal</span>
      <button onclick="xtTtydClose()" style="background:transparent;border:1px solid var(--red);color:var(--red);font-family:'Share Tech Mono',monospace;font-size:.7rem;border-radius:4px;padding:.25rem .8rem;cursor:pointer;transition:background .2s;" onmouseover="this.style.background='rgba(255,69,96,.15)'" onmouseout="this.style.background='transparent'">✖ Cerrar</button>
    </div>
    <iframe id="xtTtydFrame" src="" style="flex:1;border:none;width:100%;background:#000;" allow="clipboard-read; clipboard-write"></iframe>
  </div>
</div>

<!-- Modal Terminal -->
<div id="xtModal" class="xterm-modal" onclick="if(event.target===this)xtClose()">
<div class="xterm-box">
  <div class="xterm-title">⌨ Emulador de terminal</div>
  <div class="xterm-out" id="xtOut">pi@raspberry:~$ Terminal lista
</div>
  <div class="xterm-row">
    <span class="xterm-pr" id="xtPr">pi@raspberry:~$</span>
    <input id="xtInp" class="xterm-inp" autocomplete="off" spellcheck="false" placeholder="escribe un comando…">
  </div>
  <div class="restore-btns">
    <button class="restore-btn-cancel" onclick="xtClose()">✖ Cerrar</button>
  </div>
</div>
</div>

<!-- Modal Actualización -->
<div id="updateModal" class="update-modal">
<div class="update-box">
<div class="update-title" id="updateTitle">⬇ Actualizando…</div>
<div class="update-console" id="updateConsole">Iniciando…</div>
<div class="restore-btns"><button class="restore-btn-cancel" id="updateCloseBtn" onclick="closeUpdate()">✖ Cerrar</button></div>
</div>
</div>

<!-- Modal Restore -->
<div id="restoreModal" class="restore-modal">
<div class="restore-box">
<div class="restore-title">📂 Restaurar configuración</div>
<label class="restore-label" for="restoreFile">Selecciona fichero Copia_PHPPLUS.zip</label>
<input type="file" id="restoreFile" accept=".zip" class="restore-file">
<div class="restore-btns">
<button class="restore-btn-ok" onclick="doRestore()">▶ Restaurar</button>
<button class="restore-btn-cancel" onclick="closeRestore()">✖ Cancelar</button>
</div>
<div id="restoreMsg" class="restore-msg"></div>
</div>
</div>

<!-- Modal Instalar Display Driver -->
<div id="installModal" class="install-modal">
<div class="install-box">
<div class="install-title">⚙ Instalar Display Driver</div>
<div id="installOutput" class="install-output"></div>
<div class="restore-btns">
<button class="restore-btn-ok" id="btnInstalarOk" onclick="confirmarInstalacion()">▶ Confirmar instalación</button>
<button class="restore-btn-cancel" onclick="closeInstalar()">✖ Cancelar</button>
</div>
<div id="installMsg" class="restore-msg"></div>
</div>
</div>

<script>
let refreshTimer=null,txTimer=null,vuTimer=null,ysfTimer=null,mmdvmYsfTimer=null,ysfTxTimer=null,ysfVuTimer=null,dstarTimer=null;
let running=false,ysfRunning=false,mmdvmYsfRunning=false,dstarRunning=false,currentlyActive=false,ysfCurrentlyActive=false;
let dmrLastActiveTs=0,ysfLastActiveTs=0;
const DMR_IDLE_TIMEOUT=12000,YSF_IDLE_TIMEOUT=12000;

async function fetchStationInfo(){try{const r=await fetch('?action=station-info');const d=await r.json();document.getElementById('scCallsign').textContent='📡 '+d.callsign;const nxPort=document.getElementById('nxPort');if(nxPort)nxPort.textContent=d.port||'—';const nxFrx=document.getElementById('nxFrx');if(nxFrx)nxFrx.textContent=d.freqRX||'—';const nxFtx=document.getElementById('nxFtx');if(nxFtx)nxFtx.textContent=d.freq||'—';const nxIp=document.getElementById('nxIp');if(nxIp)nxIp.textContent=d.ip||'—';const yNxPort=document.getElementById('ysfNxPort');if(yNxPort)yNxPort.textContent=d.ysfPort||'—';const yNxFrx=document.getElementById('ysfNxFrx');if(yNxFrx)yNxFrx.textContent=d.ysfFreqRX||'—';const yNxFtx=document.getElementById('ysfNxFtx');if(yNxFtx)yNxFtx.textContent=d.ysfFreqTX||'—';const yNxIp=document.getElementById('ysfNxIp');if(yNxIp)yNxIp.textContent=d.ysfIp||'—';const label=d.callsign+' · ADER';const nx=document.getElementById('nxStationLabel');if(nx)nx.textContent=label;const yx=document.getElementById('ysfStationLabel');if(yx)yx.textContent=label;const dx=document.getElementById('dstarStationLabel');if(dx)dx.textContent=label;const nxdnLbl=document.getElementById('nxdnStationLabel');if(nxdnLbl)nxdnLbl.textContent=label;const dNxPort=document.getElementById('dstarNxPort');if(dNxPort)dNxPort.textContent=d.dstarPort||'—';const dNxFrx=document.getElementById('dstarNxFrx');if(dNxFrx)dNxFrx.textContent=d.dstarFreqRX||'—';const dNxFtx=document.getElementById('dstarNxFtx');if(dNxFtx)dNxFtx.textContent=d.dstarFreqTX||'—';const dNxIp=document.getElementById('dstarNxIp');if(dNxIp)dNxIp.textContent=d.dstarIp||'—';const nNxPort=document.getElementById('nxdnNxPort');if(nNxPort)nNxPort.textContent=d.nxdnPort||'—';const nNxFrx=document.getElementById('nxdnNxFrx');if(nNxFrx)nNxFrx.textContent=d.nxdnFreqRX||'—';const nNxFtx=document.getElementById('nxdnNxFtx');if(nNxFtx)nNxFtx.textContent=d.nxdnFreqTX||'—';const nNxIp=document.getElementById('nxdnNxIp');if(nNxIp)nNxIp.textContent=d.nxdnIp||'—';}catch(e){console.warn('station-info error:',e);}}

const _winOS = /Windows/i.test(navigator.userAgent);
const _TBASE = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/';
const _FLAGS = [
    {re:/^E[ABCDEFGH][1-9]/,  e:'🇪🇸', t:'1f1ea-1f1f8'},
    {re:/^C[TUQ]/,            e:'🇵🇹', t:'1f1f5-1f1f9'},
    {re:/^F[A-Z]/,            e:'🇫🇷', t:'1f1eb-1f1f7'},
    {re:/^I[0-9]|^IK|^IW|^IZ/,e:'🇮🇹',t:'1f1ee-1f1f9'},
    {re:/^G[0-9]|^M[0-9]|^2E|^GB|^MJ|^MU/,e:'🇬🇧',t:'1f1ec-1f1e7'},
    {re:/^D[A-R]|^Y[2-9]/,   e:'🇩🇪', t:'1f1e9-1f1ea'},
    {re:/^[KWN][0-9]|^AA|^AB|^AC|^AD|^AE|^AF/,e:'🇺🇸',t:'1f1fa-1f1f8'},
    {re:/^VE|^VA|^VO|^VY/,   e:'🇨🇦', t:'1f1e8-1f1e6'},
    {re:/^PY|^PU|^PV|^PW|^PX/,e:'🇧🇷',t:'1f1e7-1f1f7'},
    {re:/^LU|^LV|^LW|^LX/,   e:'🇦🇷', t:'1f1e6-1f1f7'},
    {re:/^JA|^JE|^JF|^JG|^JH|^JI|^JJ|^JK|^JL|^JR/,e:'🇯🇵',t:'1f1ef-1f1f5'},
    {re:/^VK/,                e:'🇦🇺', t:'1f1e6-1f1fa'},
    {re:/^ZS|^ZT|^ZU/,       e:'🇿🇦', t:'1f1ff-1f1e6'},
    {re:/^OH|^OG/,            e:'🇫🇮', t:'1f1eb-1f1ee'},
    {re:/^PA|^PB|^PC|^PD|^PE|^PF|^PG|^PH/,e:'🇳🇱',t:'1f1f3-1f1f1'},
    {re:/^HB/,                e:'🇨🇭', t:'1f1e8-1f1ed'},
    {re:/^OE/,                e:'🇦🇹', t:'1f1e6-1f1f9'},
    {re:/^SP|^SQ|^SR|^HF/,   e:'🇵🇱', t:'1f1f5-1f1f1'},
    {re:/^UA|^UB|^UC|^UD|^UE|^UF|^RA|^RB|^RC/,e:'🇷🇺',t:'1f1f7-1f1fa'},
    {re:/^SV|^SW|^SX|^SY|^SZ/,e:'🇬🇷',t:'1f1ec-1f1f7'},
    {re:/^LY/,                e:'🇱🇹', t:'1f1f1-1f1f9'},
    {re:/^9A/,                e:'🇭🇷', t:'1f1ed-1f1f7'},
];
function getFlagByCall(callsign){
    if(!callsign)return'';
    const cs=callsign.toUpperCase().trim();
    for(const p of _FLAGS){
        if(p.re.test(cs)){
            if(_winOS)
                return'<img class="flag-emoji-img" src="'+_TBASE+p.t+'.png" alt="">';
            return'<span class="flag-emoji">'+p.e+'</span>';
        }
    }
    return'';
}

function buildVU(id){const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}}
buildVU('vuLeft');buildVU('vuRight');buildVU('ysfVuLeft');buildVU('ysfVuRight');buildVU('nxdnVuLeft');buildVU('nxdnVuRight');

function animateVU(on,prefix){clearInterval(prefix==='ysf'?ysfVuTimer:vuTimer);const ids=prefix==='ysf'?['ysfVuLeft','ysfVuRight']:['vuLeft','vuRight'];ids.forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;const timer=setInterval(()=>{ids.forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=prefix==='ysf'?(i<10?' lit-v':i<14?' lit-vd':' lit-r'):(i<10?' lit-g':i<14?' lit-a':' lit-r');document.getElementById(`${id}-${i}`).className=cls;}});},80);if(prefix==='ysf')ysfVuTimer=timer;else vuTimer=timer;}

let nxdnVuTimerAnim=null;
function animateNXDNVU(on){clearInterval(nxdnVuTimerAnim);['nxdnVuLeft','nxdnVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;nxdnVuTimerAnim=setInterval(()=>{['nxdnVuLeft','nxdnVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-y':i<14?' lit-ya':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}

function updateClock(){const now=new Date();const hms=now.toLocaleTimeString('es-ES');const date=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();if(!currentlyActive){const clk=document.getElementById('nxClock');if(clk){clk.textContent=hms;document.getElementById('nxDate').textContent=date;}}if(!ysfCurrentlyActive){const yClk=document.getElementById('ysfNxClock');if(yClk){yClk.textContent=hms;document.getElementById('ysfNxDate').textContent=date;}}}
setInterval(updateClock,1000);updateClock();

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function setDMRToggle(on){const chk=document.getElementById('chkDMR'),lbl=document.getElementById('dmrToggleLabel'),sta=document.getElementById('dmrToggleStatus');chk.checked=on;lbl.className='toggle-label'+(on?' on-dmr':'');sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('autoRefreshBadge').style.display=on?'flex':'none';document.getElementById('dmrLogPanels').style.display=on?'contents':'none';document.getElementById('dmrLastHeardPanel').style.display=on?'':'none';document.getElementById('dmrDisplayPanel').style.display=on?'':'none';}
function setYSFToggle(on){const chk=document.getElementById('chkYSF'),lbl=document.getElementById('ysfToggleLabel'),sta=document.getElementById('ysfToggleStatus');chk.checked=on;lbl.className='toggle-label'+(on?' on-ysf':'');sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('ysfRefreshBadge').style.display=on?'flex':'none';document.getElementById('ysfLogPanels').style.display=on?'contents':'none';document.getElementById('ysfLastHeardPanel').style.display=on?'':'none';document.getElementById('ysfDisplayPanel').style.display=on?'':'none';}

function showIdle(){currentlyActive=false;animateVU(false,'dmr');document.getElementById('nxTxBar').classList.remove('active');document.getElementById('nxTG').textContent='—';document.getElementById('nxSlot').textContent='—';document.getElementById('nxDmrid').textContent='—';const src=document.getElementById('nxSource');src.textContent='';src.className='nx-source';document.getElementById('nxCenter').innerHTML='<div class="nx-clock" id="nxClock">00:00:00</div><div class="nx-date" id="nxDate">—</div>';updateClock();}
function showActive(d){currentlyActive=true;animateVU(true,'dmr');document.getElementById('nxTxBar').classList.add('active');document.getElementById('nxTG').textContent=d.tg?'TG '+d.tg:'—';document.getElementById('nxSlot').textContent=d.slot||'—';document.getElementById('nxDmrid').textContent=d.dmrid||'—';const src=document.getElementById('nxSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else if(d.source==='NETWORK'){src.textContent='NET';src.className='nx-source net';}else{src.textContent='';src.className='nx-source';}const flag=getFlagByCall(d.callsign);document.getElementById('nxCenter').innerHTML=`<div class="nx-callsign">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name">${esc(d.name)}</div>`:'');}
function showYSFIdle(){ysfCurrentlyActive=false;animateVU(false,'ysf');document.getElementById('ysfTxBar').className='nx-txbar';document.getElementById('ysfDest').textContent='—';document.getElementById('ysfProto').textContent='C4FM . DIGITAL VOICE';const src=document.getElementById('ysfSource');src.textContent='';src.className='nx-source';document.getElementById('ysfNxCenter').innerHTML='<div class="nx-clock" id="ysfNxClock" style="color:#c084ff;">00:00:00</div><div class="nx-date" id="ysfNxDate" style="color:#9b59d4;">—</div>';updateClock();}
function showYSFActive(d){ysfCurrentlyActive=true;animateVU(true,'ysf');document.getElementById('ysfTxBar').className='nx-txbar active-ysf';document.getElementById('ysfDest').textContent=d.dest?d.dest:'ALL';const src=document.getElementById('ysfSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else if(d.source==='NETWORK'){src.textContent='NET';src.className='nx-source net';}else{src.textContent='';src.className='nx-source';}const flag=getFlagByCall(d.callsign);document.getElementById('ysfNxCenter').innerHTML=`<div class="nx-callsign ysf">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name ysf">${esc(d.name)}</div>`:'');}

function renderLastHeard(list,activeCall){const body=document.getElementById('lhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad reciente</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-tg">${esc(r.tg||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}

function renderYSFLastHeard(list,activeCall){const body=document.getElementById('ysfLhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad C4FM</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot-ysf"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row-ysf${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call-ysf">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}

async function fetchTransmission(){try{const r=await fetch('?action=transmission');const d=await r.json();if(d.active){showActive(d);}else{showIdle();}renderLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){}}

async function fetchYSFTransmission(){try{const r=await fetch('?action=ysf-transmission');const d=await r.json();if(d.active){ysfLastActiveTs=Date.now();showYSFActive(d);}else{if(ysfCurrentlyActive)showYSFIdle();}renderYSFLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){if(ysfCurrentlyActive&&(Date.now()-ysfLastActiveTs)>YSF_IDLE_TIMEOUT)showYSFIdle();}}

async function checkStatus(){try{const r=await fetch('?action=status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';setDot('dot-gateway',gw?'active':'off');setDot('dot-mmdvm',mmd?'active':'off');setDot('dot-mosquitto',gw?'active':'off');running=gw||mmd;setDMRToggle(running);if(running)startRefresh();}catch(e){}}
async function checkYSFStatus(){try{const r=await fetch('?action=ysf-status');const d=await r.json();ysfRunning=d.ysf==='active';setDot('dot-ysf',ysfRunning?'active':'off');setYSFToggle(ysfRunning||mmdvmYsfRunning);}catch(e){}}
async function checkMMDVMYSFStatus(){try{const r=await fetch('?action=mmdvmysf-status');const d=await r.json();mmdvmYsfRunning=d.mmdvmysf==='active';setDot('dot-mmdvmysf',mmdvmYsfRunning?'active':'off');setYSFToggle(ysfRunning||mmdvmYsfRunning);}catch(e){}}
function setDot(id,state){document.getElementById(id).className='dot'+(state==='active'?' active':state==='error'?' error':'');}

function setDSTARToggle(on){const chk=document.getElementById('chkDSTAR'),lbl=document.getElementById('dstarToggleLabel'),sta=document.getElementById('dstarToggleStatus');chk.checked=on;lbl.style.color=on?'#00e5ff':'';sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';document.getElementById('dstarRefreshBadge').style.display=on?'flex':'none';document.getElementById('dstarPanelMmd').style.display=on?'':'none';document.getElementById('dstarPanelGw').style.display=on?'':'none';document.getElementById('dstarDisplayPanel').style.display=on?'':'none';document.getElementById('dstarLastHeardPanel').style.display=on?'':'none';}

let dstarVuTimer=null,dstarCurrentlyActive=false,dstarTxTimer2=null;
function buildDStarVU(){['dstarVuLeft','dstarVuRight'].forEach(id=>{const el=document.getElementById(id);for(let i=0;i<18;i++){const d=document.createElement('div');d.className='nx-vu-bar';d.id=`${id}-${i}`;el.appendChild(d);}});}
buildDStarVU();
function animateDStarVU(on){clearInterval(dstarVuTimer);['dstarVuLeft','dstarVuRight'].forEach(id=>{for(let i=0;i<18;i++)document.getElementById(`${id}-${i}`).className='nx-vu-bar';});if(!on)return;dstarVuTimer=setInterval(()=>{['dstarVuLeft','dstarVuRight'].forEach(id=>{const lvl=Math.floor(Math.random()*16)+1;for(let i=0;i<18;i++){let cls='nx-vu-bar';if(i<lvl)cls+=i<10?' lit-g':i<14?' lit-a':' lit-r';document.getElementById(`${id}-${i}`).className=cls;}});},80);}
function updateDStarClock(){if(!dstarCurrentlyActive){const now=new Date();const clk=document.getElementById('dstarNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('dstarNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateDStarClock,1000);updateDStarClock();
function showDStarIdle(){dstarCurrentlyActive=false;animateDStarVU(false);document.getElementById('dstarTxBar').className='nx-txbar';const src=document.getElementById('dstarSource');src.textContent='';src.className='nx-source';document.getElementById('dstarNxCenter').innerHTML='<div class="nx-clock" id="dstarNxClock" style="color:#00e5ff;">00:00:00</div><div class="nx-date" id="dstarNxDate" style="color:#009090;">—</div>';updateDStarClock();}
function showDStarActive(d){dstarCurrentlyActive=true;animateDStarVU(true);document.getElementById('dstarTxBar').className='nx-txbar active';document.getElementById('dstarTxBar').style.background='linear-gradient(90deg,transparent,#00e5ff,transparent)';const src=document.getElementById('dstarSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else{src.textContent='NET';src.className='nx-source net';}const flag=getFlagByCall(d.callsign.replace(/\/.*$/,''));document.getElementById('dstarNxCenter').innerHTML=`<div class="nx-callsign dstar">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name dstar">${esc(d.name)}</div>`:'');}
function renderDStarLastHeard(list,activeCall){const body=document.getElementById('dstarLhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad D-STAR</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot" style="background:#00e5ff;box-shadow:0 0 6px #00e5ff;"></span>':'';const flag=getFlagByCall(r.callsign.replace(/\/.*$/,''));return`<div class="lh-row${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call" style="color:#00e5ff;">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}
async function fetchDStarTransmission(){try{const r=await fetch('?action=dstar-transmission');const d=await r.json();if(d.active)showDStarActive(d);else showDStarIdle();renderDStarLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){}}
function startDStarTransmissionPoll(){fetchDStarTransmission();dstarTxTimer2=setInterval(fetchDStarTransmission,4000);}
function stopDStarTransmissionPoll(){clearInterval(dstarTxTimer2);dstarTxTimer2=null;}
async function checkDStarStatus(){try{const r=await fetch('?action=dstar-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';setDot('dot-dstargw',gw?'active':'off');setDot('dot-dstarmmd',mmd?'active':'off');dstarRunning=(gw||mmd)&&!d.stopped;setDSTARToggle(dstarRunning);if(dstarRunning){startDStarLogs();startDStarTransmissionPoll();}}catch(e){}}
async function toggleDStar(chk){const wasOn=!chk.checked;const sw=document.getElementById('swDSTAR');chk.checked=wasOn;sw.classList.add('busy');try{await fetch(wasOn?'?action=dstar-stop':'?action=dstar-start');let ok=false;for(let i=0;i<15;i++){await new Promise(r=>setTimeout(r,1000));const r=await fetch('?action=dstar-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';const isOn=(gw||mmd)&&!d.stopped;if(wasOn&&!isOn){ok=true;setDot('dot-dstargw','off');setDot('dot-dstarmmd','off');dstarRunning=false;setDSTARToggle(false);stopDStarLogs();stopDStarTransmissionPoll();showDStarIdle();clearLog('logDstarGw');clearLog('logDstarMmd');break;}if(!wasOn&&isOn){ok=true;setDot('dot-dstargw',gw?'active':'off');setDot('dot-dstarmmd',mmd?'active':'off');dstarRunning=true;setDSTARToggle(true);startDStarLogs();startDStarTransmissionPoll();break;}}if(!ok){const r=await fetch('?action=dstar-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';dstarRunning=(gw||mmd)&&!d.stopped;setDot('dot-dstargw',gw?'active':'off');setDot('dot-dstarmmd',mmd?'active':'off');setDSTARToggle(dstarRunning);}}catch(e){console.warn('toggleDStar error:',e);}finally{sw.classList.remove('busy');}}
async function fetchDStarLogs(){try{const r=await fetch('?action=dstar-logs&lines=15');const d=await r.json();['logDstarGw:gateway','logDstarMmd:mmdvm'].forEach(pair=>{const[id,key]=pair.split(':');const el=document.getElementById(id);const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d[key]);if(atBot)el.scrollTop=el.scrollHeight;});}catch(e){}}
function startDStarLogs(){fetchDStarLogs();dstarTimer=setInterval(fetchDStarLogs,5000);}
function stopDStarLogs(){clearInterval(dstarTimer);dstarTimer=null;}

let nxdnRunning=false,nxdnTimer=null,nxdnTxTimer=null,nxdnCurrentlyActive=false,nxdnLastActiveTs=0;
const NXDN_IDLE_TIMEOUT=12000;

function setNXDNToggle(on){
    const chk=document.getElementById('chkNXDN'),lbl=document.getElementById('nxdnToggleLabel'),sta=document.getElementById('nxdnToggleStatus');
    chk.checked=on;lbl.style.color=on?'#ffd700':'';
    sta.className='toggle-status'+(on?' on':'');sta.textContent=on?'ON':'OFF';
    document.getElementById('nxdnRefreshBadge').style.display=on?'flex':'none';
    document.getElementById('nxdnPanelMmd').style.display=on?'':'none';
    document.getElementById('nxdnPanelGw').style.display=on?'':'none';
    document.getElementById('nxdnDisplayPanel').style.display=on?'':'none';
    document.getElementById('nxdnLastHeardPanel').style.display=on?'':'none';
}

function updateNXDNClock(){if(!nxdnCurrentlyActive){const now=new Date();const clk=document.getElementById('nxdnNxClock');if(clk){clk.textContent=now.toLocaleTimeString('es-ES');document.getElementById('nxdnNxDate').textContent=now.toLocaleDateString('es-ES',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}).toUpperCase();}}}
setInterval(updateNXDNClock,1000);updateNXDNClock();

function showNXDNIdle(){nxdnCurrentlyActive=false;animateNXDNVU(false);document.getElementById('nxdnTxBar').className='nx-txbar';document.getElementById('nxdnTGLabel').textContent='—';const src=document.getElementById('nxdnSource');src.textContent='';src.className='nx-source';document.getElementById('nxdnNxCenter').innerHTML='<div class="nx-clock" id="nxdnNxClock" style="color:#ffd700;">00:00:00</div><div class="nx-date" id="nxdnNxDate" style="color:#b8a000;">—</div>';updateNXDNClock();}
function showNXDNActive(d){nxdnCurrentlyActive=true;animateNXDNVU(true);document.getElementById('nxdnTxBar').className='nx-txbar active-nxdn';document.getElementById('nxdnTGLabel').textContent=d.tg?'TG '+d.tg:'—';const src=document.getElementById('nxdnSource');if(d.source==='RF'){src.textContent='RF';src.className='nx-source rf';}else{src.textContent='NET';src.className='nx-source net';}const flag=getFlagByCall(d.callsign);document.getElementById('nxdnNxCenter').innerHTML=`<div class="nx-callsign nxdn">${flag} ${esc(d.callsign)}</div>`+(d.name?`<div class="nx-name nxdn">${esc(d.name)}</div>`:'');}

function renderNXDNLastHeard(list,activeCall){const body=document.getElementById('nxdnLhBody');if(!list||list.length===0){body.innerHTML='<div class="lh-empty">Sin actividad NXDN</div>';return;}body.innerHTML=list.map(r=>{const isActive=activeCall&&r.callsign===activeCall;const srcCls=r.source==='RF'?'rf':'net',srcLbl=r.source==='RF'?'RF':'NET';const dot=isActive?'<span class="lh-tx-dot-nxdn"></span>':'';const flag=getFlagByCall(r.callsign);return`<div class="lh-row-nxdn${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call-nxdn">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-tg">${esc(r.tg||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;}).join('');}

async function fetchNXDNTransmission(){try{const r=await fetch('?action=nxdn-transmission');const d=await r.json();if(d.active){nxdnLastActiveTs=Date.now();showNXDNActive(d);}else{if(nxdnCurrentlyActive&&(Date.now()-nxdnLastActiveTs)>NXDN_IDLE_TIMEOUT)showNXDNIdle();}renderNXDNLastHeard(d.lastHeard||[],d.active?d.callsign:null);}catch(e){if(nxdnCurrentlyActive&&(Date.now()-nxdnLastActiveTs)>NXDN_IDLE_TIMEOUT)showNXDNIdle();}}

async function checkNXDNStatus(){try{const r=await fetch('?action=nxdn-status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';setDot('dot-nxdngw',gw?'active':'off');setDot('dot-nxdnmmd',mmd?'active':'off');nxdnRunning=gw||mmd;setNXDNToggle(nxdnRunning);if(nxdnRunning){startNXDNLogs();startNXDNTxPoll();}}catch(e){}}

async function toggleNXDN(chk){const wasOn=!chk.checked;const sw=document.getElementById('swNXDN');chk.checked=wasOn;sw.classList.add('busy');
try{
    await fetch(wasOn?'?action=nxdn-stop':'?action=nxdn-start');
    let ok=false;
    for(let i=0;i<15;i++){
        await new Promise(r=>setTimeout(r,1000));
        const r=await fetch('?action=nxdn-status');
        const d=await r.json();
        const gw=d.gateway==='active',mmd=d.mmdvm==='active';
        const isOn=gw||mmd;
        if(wasOn&&!isOn){ok=true;setDot('dot-nxdngw','off');setDot('dot-nxdnmmd','off');nxdnRunning=false;setNXDNToggle(false);stopNXDNLogs();stopNXDNTxPoll();showNXDNIdle();clearLog('logNxdnGw');clearLog('logNxdnMmd');break;}
        if(!wasOn&&isOn){ok=true;setDot('dot-nxdngw',gw?'active':'off');setDot('dot-nxdnmmd',mmd?'active':'off');nxdnRunning=true;setNXDNToggle(true);startNXDNLogs();startNXDNTxPoll();break;}
    }
    if(!ok)await checkNXDNStatus();
}catch(e){console.warn('toggleNXDN error:',e);}
finally{sw.classList.remove('busy');}}

async function fetchNXDNLogs(){try{const r=await fetch('?action=nxdn-logs&lines=15');const d=await r.json();[['logNxdnGw','gateway'],['logNxdnMmd','mmdvm']].forEach(([id,key])=>{const el=document.getElementById(id);const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d[key]);if(atBot)el.scrollTop=el.scrollHeight;});}catch(e){}}
function startNXDNLogs(){fetchNXDNLogs();nxdnTimer=setInterval(fetchNXDNLogs,5000);}
function stopNXDNLogs(){clearInterval(nxdnTimer);nxdnTimer=null;}
function startNXDNTxPoll(){fetchNXDNTransmission();nxdnTxTimer=setInterval(fetchNXDNTransmission,4000);}
function stopNXDNTxPoll(){clearInterval(nxdnTxTimer);nxdnTxTimer=null;}

async function toggleServices(chk){const wasOn=!chk.checked;const sw=document.getElementById('swDMR');chk.checked=wasOn;sw.classList.add('busy');try{await fetch(wasOn?'?action=stop':'?action=start');await new Promise(r=>setTimeout(r,2200));const r=await fetch('?action=status');const d=await r.json();const gw=d.gateway==='active',mmd=d.mmdvm==='active';running=gw||mmd;setDot('dot-gateway',gw?'active':'off');setDot('dot-mmdvm',mmd?'active':'off');setDot('dot-mosquitto',gw?'active':'off');setDMRToggle(running);if(wasOn){stopRefresh();clearLog('logGw');clearLog('logMmd');showIdle();document.getElementById('lhBody').innerHTML='<div class="lh-empty">Sin actividad reciente</div>';}else startRefresh();}finally{sw.classList.remove('busy');}}
async function toggleYSF(chk){const wasOn=!chk.checked;const sw=document.getElementById('swYSF');chk.checked=wasOn;sw.classList.add('busy');try{if(wasOn){await fetch('?action=ysf-stop');await new Promise(r=>setTimeout(r,1000));await fetch('?action=mmdvmysf-stop');await new Promise(r=>setTimeout(r,2000));clearLog('logYsf');clearLog('logMmdvmYsf');stopYSFLogs();stopMMDVMYSFLogs();showYSFIdle();document.getElementById('ysfLhBody').innerHTML='<div class="lh-empty">Sin actividad C4FM</div>';}else{await fetch('?action=mmdvmysf-start');await new Promise(r=>setTimeout(r,2000));await fetch('?action=ysf-start');await new Promise(r=>setTimeout(r,1500));startYSFLogs();startMMDVMYSFLogs();}await checkYSFStatus();await checkMMDVMYSFStatus();}finally{sw.classList.remove('busy');}}

function toggleDropdown(e){e.stopPropagation();document.getElementById('dropActualizaciones').classList.toggle('open');}
document.addEventListener('click',()=>document.getElementById('dropActualizaciones').classList.remove('open'));
function closeUpdate(){document.getElementById('updateModal').classList.remove('open');}
const UPDATE_TITLES={imagen:'🖼 Actualizar Imagen',ids:'📋 Actualizar IDs',ysf:'📡 Actualizar Reflectores YSF'};
const UPDATE_ACTIONS={imagen:'?action=update-imagen',ids:'?action=update-ids',ysf:'?action=update-ysf'};
async function runUpdate(type){document.getElementById('dropActualizaciones').classList.remove('open');document.getElementById('updateTitle').textContent=UPDATE_TITLES[type];const con=document.getElementById('updateConsole');con.textContent='⏳ Ejecutando, espera…';document.getElementById('updateCloseBtn').disabled=true;document.getElementById('updateModal').classList.add('open');try{const r=await fetch(UPDATE_ACTIONS[type]);const d=await r.json();con.textContent=d.output||'(sin salida)';con.scrollTop=con.scrollHeight;}catch(e){con.textContent='✖ Error de red: '+e.message;}finally{document.getElementById('updateCloseBtn').disabled=false;}}
async function rebootPi(){if(!confirm('¿Seguro que quieres reiniciar la Raspberry Pi?'))return;const btn=document.getElementById('btnReboot');btn.textContent='⏻ Reiniciando…';btn.disabled=true;await fetch('?action=reboot');}
function closeInstalar(){document.getElementById('installModal').classList.remove('open');}
async function confirmarInstalacion(){const btn=document.getElementById('btnInstalarOk');const msg=document.getElementById('installMsg');const out=document.getElementById('installOutput');btn.disabled=true;btn.textContent='⏳ Instalando…';msg.className='restore-msg loading';msg.style.display='block';msg.textContent='⏳ Ejecutando instalador, espera…';out.className='install-output visible';out.textContent='';try{const r=await fetch('?action=install-display');const d=await r.json();out.textContent=d.output||'(sin salida)';out.scrollTop=out.scrollHeight;msg.className='restore-msg ok';msg.textContent='✔ Instalación completada.';btn.textContent='✔ Cerrar';btn.disabled=false;btn.onclick=function(){closeInstalar();};}catch(e){msg.className='restore-msg err';msg.textContent='✖ Error durante la instalación.';btn.textContent='▶ Confirmar instalación';btn.disabled=false;}}
function openRestore(){document.getElementById('restoreModal').classList.add('open');document.getElementById('restoreFile').value='';const msg=document.getElementById('restoreMsg');msg.style.display='none';msg.className='restore-msg';}
function closeRestore(){document.getElementById('restoreModal').classList.remove('open');}
async function doRestore(){const file=document.getElementById('restoreFile').files[0];if(!file){alert('Selecciona un fichero ZIP primero.');return;}const msg=document.getElementById('restoreMsg');if(!file.name.startsWith('Copia_PHP')){msg.className='restore-msg err';msg.style.display='block';msg.textContent='✖ Fichero no válido. El nombre debe empezar por "Copia_PHP".';return;}msg.className='restore-msg loading';msg.style.display='block';msg.textContent='⏳ Restaurando…';try{const form=new FormData();form.append('zipfile',file);const r=await fetch('?action=restore-configs',{method:'POST',body:form});const text=await r.text();let d;try{d=JSON.parse(text);}catch(parseErr){msg.className='restore-msg err';msg.textContent='✖ Respuesta inesperada: '+text.substring(0,200);return;}msg.className='restore-msg '+(d.ok?'ok':'err');msg.textContent=(d.ok?'✔ ':'✖ ')+d.msg;if(d.ok)setTimeout(closeRestore,2500);}catch(e){msg.className='restore-msg err';msg.textContent='✖ Error de red: '+e.message;}}

function colorize(text){return text.split('\n').map(l=>{const ll=l.toLowerCase();if(/error|fail|abort|assert/.test(ll))return`<span class="ln-err">${l}</span>`;if(/warn/.test(ll))return`<span class="ln-warn">${l}</span>`;if(/connect|start|open|loaded|success/.test(ll))return`<span class="ln-ok">${l}</span>`;return`<span class="ln-info">${l}</span>`;}).join('\n');}
function clearLog(id){document.getElementById(id).innerHTML='';}
async function fetchLogs(){try{const r=await fetch('?action=logs&lines=15');const d=await r.json();['logGw:gateway','logMmd:mmdvm'].forEach(pair=>{const[id,key]=pair.split(':');const el=document.getElementById(id);const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d[key]);if(atBot)el.scrollTop=el.scrollHeight;});}catch(e){}}
async function fetchYSFLogs(){try{const r=await fetch('?action=ysf-logs&lines=15');const d=await r.json();const el=document.getElementById('logYsf');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.ysf);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}}
async function fetchMMDVMYSFLogs(){try{const r=await fetch('?action=mmdvmysf-logs&lines=15');const d=await r.json();const el=document.getElementById('logMmdvmYsf');const atBot=el.scrollHeight-el.clientHeight<=el.scrollTop+10;el.innerHTML=colorize(d.mmdvmysf);if(atBot)el.scrollTop=el.scrollHeight;}catch(e){}}
function startRefresh(){fetchLogs();fetchTransmission();refreshTimer=setInterval(fetchLogs,5000);txTimer=setInterval(fetchTransmission,3000);}
function stopRefresh(){clearInterval(refreshTimer);clearInterval(txTimer);refreshTimer=txTimer=null;}
function startYSFLogs(){fetchYSFLogs();ysfTimer=setInterval(fetchYSFLogs,4000);}
function stopYSFLogs(){clearInterval(ysfTimer);ysfTimer=null;}
function startMMDVMYSFLogs(){fetchMMDVMYSFLogs();mmdvmYsfTimer=setInterval(fetchMMDVMYSFLogs,4000);}
function stopMMDVMYSFLogs(){clearInterval(mmdvmYsfTimer);mmdvmYsfTimer=null;}
function startYSFTransmissionPoll(){fetchYSFTransmission();ysfTxTimer=setInterval(fetchYSFTransmission,4000);}
async function fetchSysInfo(){try{const r=await fetch('?action=sysinfo');const d=await r.json();const cpuEl=document.getElementById('siCpu');cpuEl.textContent=d.cpu+' %';cpuEl.style.color=d.cpu>80?'var(--red)':d.cpu>50?'var(--amber)':'var(--green)';const tempEl=document.getElementById('siTemp');tempEl.textContent=d.temp||'—';const t=parseFloat(d.temp);tempEl.style.color=t>75?'var(--red)':t>60?'var(--amber)':'var(--green)';document.getElementById('siRam').textContent=d.ramUsed+' GB / '+d.ramTotal+' GB';document.getElementById('siRamFree').textContent=d.ramFree+' GB';document.getElementById('siDisk').textContent=d.diskUsed+' GB / '+d.diskTotal+' GB';document.getElementById('siDiskFree').textContent=d.diskFree+' GB';}catch(e){}}
fetchSysInfo();setInterval(fetchSysInfo,2000);

async function feditOpen(path){
    const msg=document.getElementById('feditMsg');
    msg.style.display='none';
    document.getElementById('feditPath').textContent=path;
    document.getElementById('feditArea').value='Cargando…';
    document.getElementById('feditModal').classList.add('open');
    try{
        const r=await fetch('?action=read-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)});
        const d=await r.json();
        if(d.ok){document.getElementById('feditArea').value=d.content;document.getElementById('feditArea').focus();}
        else{document.getElementById('feditArea').value='';msg.className='fedit-msg err';msg.textContent='✖ '+d.msg;msg.style.display='block';}
    }catch(e){document.getElementById('feditArea').value='';msg.className='fedit-msg err';msg.textContent='✖ Error: '+e.message;msg.style.display='block';}
}
async function feditSave(){
    const path=document.getElementById('feditPath').textContent;
    const content=document.getElementById('feditArea').value;
    const msg=document.getElementById('feditMsg');
    try{
        const r=await fetch('?action=save-file',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'path='+encodeURIComponent(path)+'&content='+encodeURIComponent(content)});
        const d=await r.json();
        msg.className='fedit-msg '+(d.ok?'ok':'err');
        msg.textContent=(d.ok?'✔ ':'✖ ')+d.msg;
        msg.style.display='block';
        if(d.ok)setTimeout(()=>{msg.style.display='none';},3000);
    }catch(e){msg.className='fedit-msg err';msg.textContent='✖ Error: '+e.message;msg.style.display='block';}
}
function feditClose(){document.getElementById('feditModal').classList.remove('open');}

function xtTtydOpen(){
    var url='http://'+window.location.hostname+':7681';
    document.getElementById('xtTtydFrame').src=url;
    document.getElementById('xtTtydModal').style.display='flex';
}
function xtTtydClose(){
    document.getElementById('xtTtydModal').style.display='none';
    document.getElementById('xtTtydFrame').src='';
}

(function(){
var xtHist=[],xtHidx=-1,xtCwd='/home/pi';
function xtPr(){return 'pi@raspberry:'+xtCwd.replace('/home/pi','~')+'$';}
function xtApp(html){var o=document.getElementById('xtOut');o.innerHTML+=html+'\n';o.scrollTop=o.scrollHeight;}
function xtEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
window.xtOpen=function(){document.getElementById('xtModal').classList.add('open');setTimeout(function(){document.getElementById('xtInp').focus();},80);};
window.xtClose=function(){document.getElementById('xtModal').classList.remove('open');};
document.getElementById('xtInp').addEventListener('keydown',async function(e){
    if(e.key==='Escape'){xtClose();return;}
    if(e.key==='ArrowUp'){e.preventDefault();if(xtHidx<xtHist.length-1)this.value=xtHist[++xtHidx]||'';return;}
    if(e.key==='ArrowDown'){e.preventDefault();xtHidx>0?this.value=xtHist[--xtHidx]:(xtHidx=-1,this.value='');return;}
    if(e.key!=='Enter')return;
    var cmd=this.value.trim();if(!cmd)return;
    xtHist.unshift(cmd);xtHidx=-1;this.value='';
    xtApp('<span class="xt-cmd">'+xtEsc(xtPr())+' '+xtEsc(cmd)+'</span>');
    if(/^\s*clear\s*$/.test(cmd)){document.getElementById('xtOut').innerHTML='';return;}
    if(/^\s*(edit|nano)(\s+\S+)?\s*$/.test(cmd)){
        var fpath=cmd.replace(/^\s*(edit|nano)\s*/,'').trim();
        if(!fpath)fpath=xtCwd.replace(/\/$/,'')+'/nuevo.txt';
        if(!fpath.startsWith('/'))fpath=xtCwd.replace(/\/$/,'')+'/'+fpath;
        xtApp('<span class="xt-out">Abriendo editor: '+xtEsc(fpath)+'</span>');
        feditOpen(fpath);return;
    }
    if(/^\s*(sudo\s+su|su\s*$|top|htop|vim|vi|less|more)\s*/.test(cmd)){xtApp('<span class="xt-err">Comando interactivo no soportado. Usa: nano /ruta/fichero</span>');return;}
    if(/^\s*cd(\s|$)/.test(cmd)){
        var t=cmd.replace(/^\s*cd\s*/,'').trim()||'~';
        if(t==='~'||t===''){xtCwd='/home/pi';}
        else if(t.startsWith('/')){xtCwd=t;}
        else if(t==='..'){var parts=xtCwd.split('/').filter(Boolean);parts.pop();xtCwd='/'+parts.join('/')||'/';}
        else{xtCwd=xtCwd.replace(/\/$/,'')+'/'+t;}
        document.getElementById('xtPr').textContent=xtPr();return;
    }
    try{
        var resp=await fetch('?action=terminal',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'cmd='+encodeURIComponent('cd '+xtCwd+' && '+cmd)});
        var dat=await resp.json();
        if(dat.output){xtApp('<span class="xt-out">'+dat.output+'</span>');}
    }catch(err){xtApp('<span class="xt-err">Error: '+xtEsc(err.message)+'</span>');}
});
})();

(async()=>{
    await fetchStationInfo();
    setInterval(fetchStationInfo,60000);
    await checkStatus();
    await checkYSFStatus();
    await checkMMDVMYSFStatus();
    await checkDStarStatus();
    await checkNXDNStatus();
    setInterval(checkStatus,10000);
    setInterval(checkYSFStatus,8000);
    setInterval(checkMMDVMYSFStatus,8000);
    setInterval(checkDStarStatus,10000);
    setInterval(checkNXDNStatus,10000);
    if(!running){showIdle();fetchTransmission();}
    showYSFIdle();
    showNXDNIdle();
    startYSFLogs();
    startMMDVMYSFLogs();
    startYSFTransmissionPoll();
})();
</script>
</body>
</html>
