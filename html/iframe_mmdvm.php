<?php
require_once __DIR__ . '/auth.php';
header('X-Content-Type-Options: nosniff');
$action = $_GET['action'] ?? '';

// ── DMR status ───────────────────────────────────────────────────────
if ($action === 'status') {
    $gw = trim(shell_exec('systemctl is-active dmrgateway 2>/dev/null'));
    $mmd = trim(shell_exec('systemctl is-active mmdvmhost 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['gateway' => $gw, 'mmdvm' => $mmd]);
    exit;
}

if ($action === 'start') {
    shell_exec('sudo systemctl start dmrgateway 2>/dev/null');
    sleep(2);
    shell_exec('sudo systemctl start mmdvmhost 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'stop') {
    shell_exec('sudo systemctl stop mmdvmhost 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop dmrgateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── YSF status ───────────────────────────────────────────────────────
if ($action === 'ysf-status') {
    $st = trim(shell_exec('sudo /usr/local/bin/ysf_status.sh 2>/dev/null'));
    if ($st === 'active') {
        header('Content-Type: application/json');
        echo json_encode(['ysf' => 'active']);
        exit;
    }
    $pid = trim(@file_get_contents('/tmp/ysfgateway.pid'));
    $active = ($pid && is_numeric($pid) && file_exists('/proc/' . $pid)) ? 'active' : 'inactive';
    header('Content-Type: application/json');
    echo json_encode(['ysf' => $active]);
    exit;
}

if ($action === 'ysf-start') {
    shell_exec('sudo systemctl start ysfgateway 2>/dev/null');
    sleep(1);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'ysf-stop') {
    shell_exec('sudo systemctl stop ysfgateway 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── MMDVMHost YSF status ─────────────────────────────────────────────
if ($action === 'mmdvmysf-status') {
    $st = trim(shell_exec('systemctl is-active mmdvmysf 2>/dev/null'));
    header('Content-Type: application/json');
    echo json_encode(['mmdvmysf' => $st]);
    exit;
}

if ($action === 'mmdvmysf-start') {
    shell_exec('sudo systemctl start mmdvmysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mmdvmysf-stop') {
    shell_exec('sudo systemctl stop ysfgateway 2>/dev/null');
    sleep(1);
    shell_exec('sudo systemctl stop mmdvmysf 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mmdvmysf-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $log = shell_exec("sudo journalctl -u mmdvmysf -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['mmdvmysf' => htmlspecialchars($log ?? '')]);
    exit;
}

// ── Reboot ───────────────────────────────────────────────────────────
if ($action === 'reboot') {
    shell_exec('sudo /usr/bin/systemctl reboot 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Display Driver restart ───────────────────────────────────────────
if ($action === 'display-restart') {
    shell_exec('sudo systemctl daemon-reload 2>/dev/null');
    shell_exec('sudo systemctl enable displaydriver 2>/dev/null');
    shell_exec('sudo systemctl restart displaydriver 2>/dev/null');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Instalar Display Driver ──────────────────────────────────────────
if ($action === 'install-display') {
    $output = shell_exec('sudo /home/pi/A108/instalar_displaydriver.sh 2>&1');
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'output' => htmlspecialchars($output ?? '')]);
    exit;
}

// ── Backup configuraciones ───────────────────────────────────────────
if ($action === 'backup-configs') {
    $zipName = 'Copia_A108_' . date('Ymd_His') . '.zip';
    $zipPath = '/tmp/' . $zipName;
    $files = [
        '/home/pi/MMDVMHost/MMDVMHost.ini',
        '/home/pi/MMDVMHost/MMDVMYSF.ini',
        '/home/pi/Display-Driver/DisplayDriver.ini',
        '/home/pi/YSFClients/YSFGateway/YSFGateway.ini',
        '/home/pi/DMRGateway/DMRGateway.ini',
    ];
    $fileList = implode(' ', array_map('escapeshellarg', $files));
    shell_exec("zip -j " . escapeshellarg($zipPath) . " {$fileList} 2>/dev/null");
    if (file_exists($zipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zipPath);
        unlink($zipPath);
    } else {
        header('Content-Type: text/plain');
        echo 'Error: No se pudo crear el ZIP. Verifica que zip está instalado: sudo apt-get install -y zip';
    }
    exit;
}

// ── Restore configuraciones ──────────────────────────────────────────
if ($action === 'restore-configs') {
    if (!isset($_FILES['zipfile']) || $_FILES['zipfile']['error'] !== 0) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'No se recibió el fichero.']);
        exit;
    }
    $tmpZip = $_FILES['zipfile']['tmp_name'];
    $destMap = [
        'MMDVMHost.ini' => '/home/pi/MMDVMHost/MMDVMHost.ini',
        'MMDVMYSF.ini' => '/home/pi/MMDVMHost/MMDVMYSF.ini',
        'DisplayDriver.ini' => '/home/pi/Display-Driver/DisplayDriver.ini',
        'YSFGateway.ini' => '/home/pi/YSFClients/YSFGateway/YSFGateway.ini',
        'DMRGateway.ini' => '/home/pi/DMRGateway/DMRGateway.ini',
    ];
    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'No se pudo abrir el ZIP.']);
        exit;
    }
    $restored = [];
    $errors = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = basename($zip->getNameIndex($i));
        if (isset($destMap[$name])) {
            $result = file_put_contents($destMap[$name], $zip->getFromIndex($i));
            if ($result !== false) $restored[] = $name;
            else $errors[] = $name;
        }
    }
    $zip->close();
    if (empty($restored)) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'No se encontraron ficheros compatibles en el ZIP.']);
        exit;
    }
    $msg = 'Restaurados: ' . implode(', ', $restored);
    if ($errors) $msg .= ' | Errores: ' . implode(', ', $errors);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'msg' => $msg]);
    exit;
}

// ── logs ─────────────────────────────────────────────────────────────
if ($action === 'logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $gw = shell_exec("sudo journalctl -u dmrgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    $mmd = shell_exec("sudo journalctl -u mmdvmhost -n {$lines} --no-pager --output=short 2>/dev/null");
    header('Content-Type: application/json');
    echo json_encode(['gateway' => htmlspecialchars($gw ?? ''), 'mmdvm' => htmlspecialchars($mmd ?? '')]);
    exit;
}

// ── YSF logs ─────────────────────────────────────────────────────────
if ($action === 'ysf-logs') {
    $lines = intval($_GET['lines'] ?? 15);
    $log = shell_exec("sudo journalctl -u ysfgateway -n {$lines} --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) {
        $log = shell_exec("tail -n {$lines} /tmp/ysfgateway.log 2>/dev/null");
    }
    if (empty(trim($log))) {
        $logFile = glob('/home/pi/YSFClients/YSFGateway/YSFGateway-*.log');
        if ($logFile) {
            $latest = end($logFile);
            $log = shell_exec("tail -n {$lines} " . escapeshellarg($latest) . " 2>/dev/null");
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ysf' => htmlspecialchars($log ?? '')]);
    exit;
}

// ── DMR Id lookup ────────────────────────────────────────────────────
function lookupCall($callsign) {
    $datFiles = [
        '/home/pi/MMDVMHost/DMRIds.dat',
        '/etc/DMRIds.dat',
        '/usr/local/etc/DMRIds.dat',
    ];
    $cs = strtoupper(trim($callsign));
    foreach ($datFiles as $f) {
        if (!file_exists($f)) continue;
        $cmd = "awk -F'\t' '{if (toupper(\$2)==\"" . $cs . "\") {print \$1\"\t\"\$2\"\t\"\$3; exit}}' " . escapeshellarg($f) . " 2>/dev/null";
        $row = trim(shell_exec($cmd));
        if ($row !== '') {
            $parts = explode("\t", $row);
            return ['dmrid' => trim($parts[0] ?? ''), 'name' => trim($parts[2] ?? '')];
        }
    }
    return ['dmrid' => '', 'name' => ''];
}

if ($action === 'transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmhost -n 200 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));
    $active = false; $callsign = ''; $dmrid = ''; $name = ''; $tg = ''; $slot = ''; $source = '';
    foreach ($lines as $line) {
        if (preg_match('/DMR Slot \d.*(end of voice|lost RF|watchdog)/i', $line)) { $active = false; break; }
        if (preg_match('/DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $active = true; $slot = $m[1]; $source = strtoupper($m[2]);
            $callsign = strtoupper(rtrim($m[3], ',')); $tg = $m[4]; break;
        }
    }
    if ($callsign) { $info = lookupCall($callsign); $dmrid = $info['dmrid']; $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        if (preg_match('/(\d{2}:\d{2}:\d{2})\.\d+\s+DMR Slot (\d), received (RF|network) voice header from (\S+) to TG (\d+)/i', $line, $m)) {
            $cs = strtoupper(rtrim($m[4], ','));
            if (!in_array($cs, $seen)) {
                $inf = lookupCall($cs);
                $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dmrid'=>$inf['dmrid'],'tg'=>$m[5],'slot'=>$m[2],'source'=>strtoupper($m[3]),'time'=>$m[1]];
                $seen[] = $cs; if (count($lastHeard) >= 5) break;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dmrid'=>$dmrid,'tg'=>$tg,'slot'=>$slot,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}

if ($action === 'ysf-transmission') {
    $log = shell_exec("sudo journalctl -u mmdvmysf -n 300 --no-pager --output=short 2>/dev/null");
    if (empty(trim($log))) $log = shell_exec("sudo journalctl -u ysfgateway -n 300 --no-pager --output=short 2>/dev/null");
    $lines = array_reverse(explode("\n", $log));
    $active = false; $callsign = ''; $name = ''; $dest = ''; $source = '';
    foreach ($lines as $line) {
        if (preg_match('/YSF.*(end of|lost RF|watchdog)/i', $line)) { $active = false; break; }
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i', $line, $m)) {
            $active = true; $source = strtoupper($m[2]); $callsign = strtoupper(trim($m[3])); break;
        }
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i', $line, $m)) {
            $active = true; $source = 'RF'; $callsign = strtoupper(trim($m[2])); $dest = trim($m[3]); break;
        }
    }
    if ($callsign) { $info = lookupCall($callsign); $name = $info['name']; }
    $lastHeard = []; $seen = [];
    foreach ($lines as $line) {
        $cs = ''; $src = ''; $time = ''; $dst = '';
        if (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*received (RF|network) voice.*from\s+(\S+)/i', $line, $m)) {
            $time = $m[1]; $src = strtoupper($m[2]); $cs = strtoupper(trim($m[3]));
        } elseif (preg_match('/(\d{2}:\d{2}:\d{2}).*YSF.*from\s+(\S+)\s+to\s+(\S+)/i', $line, $m)) {
            $time = $m[1]; $src = 'RF'; $cs = strtoupper(trim($m[2])); $dst = trim($m[3]);
        }
        if ($cs && !in_array($cs, $seen)) {
            $inf = lookupCall($cs);
            $lastHeard[] = ['callsign'=>$cs,'name'=>$inf['name'],'dest'=>$dst,'source'=>$src,'time'=>$time];
            $seen[] = $cs; if (count($lastHeard) >= 5) break;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['active'=>$active,'callsign'=>$callsign,'name'=>$name,'dest'=>$dest,'source'=>$source,'lastHeard'=>$lastHeard]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVM Control</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg: #0a0e14; --surface: #111720; --border: #1e2d3d; --green: #00ff9f; --green-dim: #00cc7a; --red: #ff4560; --amber: #ffb300; --cyan: #00d4ff; --violet: #b57aff; --violet-dim:#8a4dff; --text: #a8b9cc; --text-dim: #4a5568; --font-mono: 'Share Tech Mono', monospace; --font-ui: 'Rajdhani', sans-serif; --font-orb: 'Orbitron', monospace; }
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); font-size: 1rem; min-height: 100vh; padding: 0; margin: 0; }
.ctrl-header { border-bottom: 1px solid var(--border); padding: 1.2rem 2rem; display: flex; align-items: center; gap: .8rem; background: var(--surface); flex-wrap: wrap; }
.ctrl-header h1 { font-family: var(--font-ui); font-weight: 700; font-size: 1.5rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
.ctrl-header .uptime { margin-left: auto; font-family: var(--font-mono); font-size: .8rem; color: var(--text-dim); }
.btn-header { font-family: var(--font-mono); font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; background: transparent; border-radius: 4px; padding: .35rem .9rem; cursor: pointer; transition: background .2s; text-decoration: none; display: inline-block; }
.btn-header.cyan { color: var(--cyan); border: 1px solid var(--cyan); }
.btn-header.cyan:hover { background: rgba(0,212,255,.1); color: var(--cyan); }
.btn-header.green { color: var(--green); border: 1px solid var(--green); }
.btn-header.green:hover { background: rgba(0,255,159,.1); color: var(--green); }
.btn-header.amber { color: var(--amber); border: 1px solid var(--amber); }
.btn-header.amber:hover { background: rgba(255,179,0,.1); color: var(--amber); }
.btn-header.red { color: var(--red); border: 1px solid var(--red); }
.btn-header.red:hover { background: rgba(255,69,96,.15); color: var(--red); }
button.btn-header { font-family: var(--font-mono); }
.btn-header:disabled { opacity: .5; pointer-events: none; }
.ctrl-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.status-bar { display: flex; gap: 2rem; margin-bottom: 1.8rem; flex-wrap: wrap; align-items: center; }
.status-item { display: flex; align-items: center; gap: .5rem; font-family: var(--font-mono); font-size: .85rem; text-transform: uppercase; letter-spacing: .08em; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: var(--text-dim); transition: background .4s, box-shadow .4s; }
.dot.active { background: var(--green); box-shadow: 0 0 8px var(--green); animation: pulse 2s infinite; }
.dot.error { background: var(--red); box-shadow: 0 0 8px var(--red); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
.section-divider { width: 1px; height: 20px; background: var(--border); margin: 0 .5rem; }
.controls-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; margin-bottom: 2rem; }
@media (max-width: 800px) { .controls-section { grid-template-columns: 1fr; } }
.service-card { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 1.2rem 1.4rem; }
.service-card-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; text-transform: uppercase; margin-bottom: .8rem; }
.service-card-label.dmr { color: var(--amber); }
.service-card-label.ysf { color: var(--violet); }
.service-card-btns { display: flex; gap: .6rem; flex-wrap: nowrap; margin-top: .8rem; }
.btn-mmdvm { font-family: var(--font-ui); font-weight: 700; font-size: 1rem; letter-spacing: .12em; text-transform: uppercase; padding: .75rem 2rem; border-radius: 6px; border: none; color: #fff; background: #c82333; width: 100%; transition: background .2s, box-shadow .2s; cursor: pointer; }
.btn-mmdvm:hover { background: #218838; box-shadow: 0 4px 15px rgba(40,167,69,.4); }
.btn-mmdvm.running { background: #218838; }
.btn-mmdvm.running:hover { background: #c82333; }
.btn-mmdvm.busy { background: #6c757d; pointer-events: none; }
.btn-ysf { font-family: var(--font-ui); font-weight: 700; font-size: 1rem; letter-spacing: .12em; text-transform: uppercase; padding: .75rem 2rem; border-radius: 6px; border: none; color: #fff; background: #5a2d82; width: 100%; transition: background .2s, box-shadow .2s; cursor: pointer; }
.btn-ysf:hover { background: #218838; box-shadow: 0 4px 15px rgba(40,167,69,.4); }
.btn-ysf.running { background: #218838; }
.btn-ysf.running:hover { background: #5a2d82; }
.btn-ysf.busy { background: #6c757d; pointer-events: none; }
.auto-badge { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); display: flex; align-items: center; gap: .4rem; margin-top: .5rem; }
.auto-badge .dot-sm { width: 6px; height: 6px; border-radius: 50%; background: var(--green); animation: pulse 2s infinite; }
.auto-badge.ysf .dot-sm { background: var(--violet); }
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
@media (max-width: 900px) { .display-row { grid-template-columns: 1fr; } }
.panel-label { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .15em; color: var(--amber); text-transform: uppercase; margin-bottom: .5rem; }
.panel-label.ysf-label { color: var(--violet); }
.nextion { background: #060c10; border: 2px solid #1a3a4a; border-radius: 6px; box-shadow: 0 0 0 1px #0d2030, inset 0 0 40px rgba(0,212,255,.04), 0 0 30px rgba(0,212,255,.08); position: relative; overflow: hidden; height: 210px; display: flex; align-items: center; justify-content: center; }
.nextion::before, .nextion::after { content: '◈'; position: absolute; font-size: .6rem; color: #1a3a4a; }
.nextion::before { top: .5rem; left: .7rem; }
.nextion::after { bottom: .5rem; right: .7rem; }
.nextion-ysf { background: #08060e; border: 2px solid #2d1a4a; border-radius: 6px; box-shadow: 0 0 0 1px #1a0d30, inset 0 0 40px rgba(181,122,255,.04), 0 0 30px rgba(181,122,255,.1); position: relative; overflow: hidden; height: 210px; display: flex; align-items: center; justify-content: center; }
.nextion-ysf::before, .nextion-ysf::after { content: '◈'; position: absolute; font-size: .6rem; color: #2d1a4a; }
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
.nx-vu { position: absolute; left: 1rem; top: 38px; bottom: 32px; width: 6px; display: flex; flex-direction: column-reverse; gap: 2px; }
.nx-vu.right { left: auto; right: 1rem; }
.nx-vu-bar { height: 5px; border-radius: 1px; background: #0d2030; transition: background .08s; }
.nx-vu-bar.lit-g { background: var(--green); box-shadow: 0 0 4px var(--green); }
.nx-vu-bar.lit-a { background: var(--amber); box-shadow: 0 0 4px var(--amber); }
.nx-vu-bar.lit-r { background: var(--red); box-shadow: 0 0 4px var(--red); }
.nx-vu-bar.lit-v { background: var(--violet); box-shadow: 0 0 4px var(--violet); }
.nx-vu-bar.lit-vd { background: #d4a8ff; box-shadow: 0 0 4px #d4a8ff; }
.nx-txbar { position: absolute; bottom: 28px; left: 0; right: 0; height: 3px; }
.nx-txbar.active { background: linear-gradient(90deg, transparent 0%, var(--green) 50%, transparent 100%); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
.nx-txbar.active-ysf { background: linear-gradient(90deg, transparent 0%, var(--violet) 50%, transparent 100%); background-size: 200% 100%; animation: scan 1.4s linear infinite; }
@keyframes scan { from{background-position:200% 0} to{background-position:-200% 0} }
.nx-center { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .15rem; z-index: 1; }
.nx-clock { font-family: var(--font-orb); font-size: 4rem; font-weight: 700; color: #f5bd06; letter-spacing: .06em; line-height: 1; }
.nx-date { font-family: var(--font-mono); font-size: .7rem; color: #ff0; letter-spacing: .12em; text-transform: uppercase; margin-top: .2rem; }
.nx-callsign { font-family: var(--font-orb); font-size: 3.4rem; font-weight: 900; letter-spacing: .04em; line-height: 1; color: var(--green); text-shadow: 0 0 20px rgba(0,255,159,.55), 0 0 60px rgba(0,255,159,.2); animation: glow-in .3s ease; }
.nx-callsign.ysf { color: var(--violet); text-shadow: 0 0 20px rgba(181,122,255,.6), 0 0 60px rgba(181,122,255,.25); }
.nx-name { font-family: var(--font-ui); font-weight: 500; font-size: 1.2rem; color: var(--cyan); letter-spacing: .18em; text-transform: uppercase; opacity: .9; margin-top: .15rem; }
.nx-name.ysf { color: #d4a8ff; }
@keyframes glow-in { from{opacity:0;transform:scale(.96)} to{opacity:1;transform:scale(1)} }
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
.lh-row.lh-active .lh-call { text-shadow: 0 0 8px rgba(0,255,159,.5); }
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
.lh-row-ysf.lh-active .lh-call-ysf { text-shadow: 0 0 8px rgba(181,122,255,.5); }
.lh-src-ysf.rf { color: var(--green); font-family: var(--font-mono); font-size: .6rem; }
.lh-src-ysf.net { color: var(--cyan); font-family: var(--font-mono); font-size: .6rem; }
.log-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
@media (max-width: 900px) { .log-grid { grid-template-columns: 1fr; } }
.log-panel { background: var(--surface); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
.log-panel-header { display: flex; align-items: center; justify-content: space-between; padding: .5rem 1rem; border-bottom: 1px solid var(--border); background: rgba(0,0,0,.3); }
.log-panel-header .svc-name { font-family: var(--font-mono); font-size: .8rem; letter-spacing: .1em; color: var(--green); text-transform: uppercase; }
.log-panel-header .svc-name.gw { color: var(--amber); }
.log-panel-header .svc-name.ysf { color: var(--violet); }
.log-panel-header .btn-clear { font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim); background: none; border: none; cursor: pointer; padding: 0; transition: color .2s; }
.log-panel-header .btn-clear:hover { color: var(--text); }
.log-output { font-family: var(--font-mono); font-size: .72rem; line-height: 1.55; color: #7a9ab5; padding: .8rem 1rem; height: 190px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
.log-output::-webkit-scrollbar { width: 4px; }
.log-output::-webkit-scrollbar-track { background: transparent; }
.log-output::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
.ln-info { color: #7a9ab5; }
.ln-warn { color: var(--amber); }
.ln-err { color: var(--red); }
.ln-ok { color: var(--green-dim); }
.ysf-display-section { margin-top: 2rem; }
.ysf-section-title { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: var(--violet); margin-bottom: 1rem; display: flex; align-items: center; gap: .8rem; }
.ysf-section-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, rgba(181,122,255,.4), transparent); }
.restore-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.75); z-index: 9000; align-items: center; justify-content: center; }
.restore-modal.open { display: flex; }
.restore-box { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 2rem; min-width: 380px; max-width: 90vw; }
.restore-title { font-family: var(--font-mono); font-size: .8rem; color: var(--amber); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1.2rem; }
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
.install-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.75); z-index: 9000; align-items: center; justify-content: center; }
.install-modal.open { display: flex; }
.install-box { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 2rem; min-width: 480px; max-width: 90vw; }
.install-title { font-family: var(--font-mono); font-size: .8rem; color: var(--green); letter-spacing: .12em; text-transform: uppercase; margin-bottom: 1.2rem; }
.install-output { font-family: var(--font-mono); font-size: .72rem; color: #7a9ab5; background: #060c10; border: 1px solid var(--border); border-radius: 4px; padding: .8rem; height: 200px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin-bottom: 1rem; display: none; }
.install-output.visible { display: block; }
</style>
</head>
<body>
<header class="ctrl-header">
<img src="Logo_ea3eiz.png" alt="EA3EIZ" style="height:40px; width:auto;">
<h1>MMDVM &amp; YSF Control</h1>
<span class="uptime" id="clock">--:--:--</span>
<a href="edit_ini.php?file=displaydriver" target="_blank" class="btn-header cyan"> 📄 Config Display-Driver </a>
<!-- <button onclick="activarDisplay()" class="btn-header green"> ▶ Activar Display Driver </button> -->
<button onclick="instalarDisplay()" class="btn-header amber" id="btnInstalar"> ⚙ Instalar Display-Driver </button>
<a href="?action=backup-configs" class="btn-header amber"> 💾 Backup Configs </a>
<button onclick="openRestore()" class="btn-header cyan"> 📂 Restore Configs </button>
<button id="btnReboot" class="btn-header red" onclick="rebootPi()">⏻ Reiniciar Pi</button>
</header>
<main class="ctrl-body">
<div class="status-bar">
<div class="status-item"><div class="dot" id="dot-mosquitto"></div><span>Mosquitto</span></div>
<div class="status-item"><div class="dot" id="dot-gateway"></div><span>DMRGateway</span></div>
<div class="status-item"><div class="dot" id="dot-mmdvm"></div><span>MMDVMHost</span></div>
<div class="section-divider"></div>
<div class="status-item"><div class="dot" id="dot-ysf"></div><span style="color:var(--violet)">YSFGateway</span></div>
<div class="status-item"><div class="dot" id="dot-mmdvmysf"></div><span style="color:#26c6da">MMDVMHost YSF</span></div>
</div>
<div class="controls-section">
<div class="service-card">
<div class="service-card-label dmr">▸ DMR · MMDVMHost + DMRGateway</div>
<button class="btn-mmdvm" id="btnToggle" onclick="toggleServices()"><span id="btnLabel">Abrir MMDVM Host</span></button>
<div class="auto-badge" id="autoRefreshBadge" style="display:none"><div class="dot-sm"></div> auto-refresh 3s</div>
<div class="service-card-btns">
<a href="mmdvm_config.php" target="_blank" class="ini-btn edit" style="flex:1;justify-content:center;">⚙ MMDVM Config</a>
<a href="dmrgateway_config.php" target="_blank" class="ini-btn edit" style="flex:1;justify-content:center;">⚙ Gateway Config</a>
</div>
<div class="service-card-btns" style="margin-top:.4rem;">
<a href="edit_ini.php?file=mmdvm" target="_blank" class="ini-btn view" style="flex:1;justify-content:center;">📄 MMDVM.ini</a>
<a href="edit_ini.php?file=dmrgateway" target="_blank" class="ini-btn view" style="flex:1;justify-content:center;">📄 Gateway.ini</a>
</div>
</div>
<div class="service-card">
<div class="service-card-label ysf">▸ C4FM · YSFGateway + MMDVMHost YSF</div>
<button class="btn-ysf" id="btnYSF" onclick="toggleYSF()"><span id="btnYSFLabel">Abrir C4FM</span></button>
<div class="auto-badge ysf" id="ysfRefreshBadge" style="display:none"><div class="dot-sm"></div> C4FM activo</div>
<div class="service-card-btns">
<a href="ysfgateway_config.php" target="_blank" class="ini-btn edit ysf" style="flex:1;justify-content:center;">⚙ YSFGATEWAY CONFIG</a>
<a href="mmdvmysf_config.php" target="_blank" class="ini-btn edit" style="flex:1;justify-content:center;color:#26c6da;border-color:rgba(38,198,218,.3);">⚙ MMDVMYSF CONFIG</a>
</div>
<div class="service-card-btns" style="margin-top:.4rem;">
<a href="edit_ini.php?file=ysfgateway" target="_blank" class="ini-btn view ysf" style="flex:1;justify-content:center;">📄 YSFGateway.ini</a>
<a href="edit_ini.php?file=mmdvmysf" target="_blank" class="ini-btn view" style="flex:1;justify-content:center;color:#80deea;border-color:rgba(38,198,218,.2);">📄 MMDVMYSF.ini</a>
</div>
</div>
</div>
<!-- DMR Display row -->
<div class="display-row">
<div>
<div class="panel-label">▸ DMR Display</div>
<div class="nextion">
<div class="nx-topbar">
<span class="nx-mode">DMR · SIMPLEX</span>
<span>EA3EIZ · ADER</span>
<span class="nx-tg" id="nxTG">—</span>
</div>
<div class="nx-vu" id="vuLeft"></div>
<div class="nx-vu right" id="vuRight"></div>
<div class="nx-center" id="nxCenter">
<div class="nx-clock" id="nxClock">00:00:00</div>
<div class="nx-date" id="nxDate">—</div>
</div>
<div class="nx-txbar" id="nxTxBar"></div>
<div class="nx-botbar">
<span class="nx-dmrid" id="nxDmrid">—</span>
<span>SLOT <span id="nxSlot">—</span></span>
<span class="nx-source" id="nxSource"></span>
</div>
</div>
</div>
<div>
<div class="panel-label">▸ Últimos escuchados DMR</div>
<div class="lh-panel">
<div class="lh-header"><span>Indicativo</span><span>Nombre</span><span>TG</span><span>Hora</span><span>Src</span></div>
<div class="lh-body" id="lhBody"><div class="lh-empty">Sin actividad reciente</div></div>
</div>
</div>
</div>
<!-- Logs -->
<div class="log-grid">
<div class="log-panel">
<div class="log-panel-header"><span class="svc-name gw">▸ DMRGateway</span><button class="btn-clear" onclick="clearLog('logGw')">limpiar</button></div>
<div class="log-output" id="logGw">Esperando servicios…</div>
</div>
<div class="log-panel">
<div class="log-panel-header"><span class="svc-name">▸ MMDVMHost</span><button class="btn-clear" onclick="clearLog('logMmd')">limpiar</button></div>
<div class="log-output" id="logMmd">Esperando servicios…</div>
</div>
<div class="log-panel">
<div class="log-panel-header"><span class="svc-name ysf">▸ YSFGateway</span><button class="btn-clear" onclick="clearLog('logYsf')">limpiar</button></div>
<div class="log-output" id="logYsf">Esperando YSFGateway…</div>
</div>
<div class="log-panel">
<div class="log-panel-header"><span class="svc-name" style="color:#26c6da">▸ MMDVMHost YSF</span><button class="btn-clear" onclick="clearLog('logMmdvmYsf')">limpiar</button></div>
<div class="log-output" id="logMmdvmYsf">Esperando MMDVMHost YSF…</div>
</div>
</div>
<!-- YSF Display -->
<div class="ysf-display-section">
<div class="ysf-section-title">▸ C4FM · YSF Monitor</div>
<div class="display-row">
<div>
<div class="panel-label ysf-label">▸ C4FM Display</div>
<div class="nextion-ysf">
<div class="nx-topbar ysf-bar">
<span class="nx-mode">C4FM · YSF</span>
<span style="color:#6a3a9a">EA3EIZ · ADER</span>
<span class="nx-dest" id="ysfDest">—</span>
</div>
<div class="nx-vu" id="ysfVuLeft"></div>
<div class="nx-vu right" id="ysfVuRight"></div>
<div class="nx-center" id="ysfNxCenter">
<div class="nx-clock" id="ysfNxClock" style="color:#c084ff;">00:00:00</div>
<div class="nx-date" id="ysfNxDate" style="color:#9b59d4;">—</div>
</div>
<div class="nx-txbar" id="ysfTxBar"></div>
<div class="nx-botbar ysf-bar">
<span style="color:#5a3a8a;font-family:var(--font-mono);font-size:.65rem;" id="ysfProto">YSF</span>
<span style="color:#5a3a8a;font-family:var(--font-mono);font-size:.65rem;">C4FM · DIGITAL VOICE</span>
<span class="nx-source" id="ysfSource"></span>
</div>
</div>
</div>
<div>
<div class="panel-label ysf-label">▸ Últimos escuchados C4FM</div>
<div class="lh-panel-ysf">
<div class="lh-header-ysf"><span>Indicativo</span><span>Nombre</span><span>Hora</span><span>Src</span></div>
<div class="lh-body" id="ysfLhBody"><div class="lh-empty">Sin actividad C4FM</div></div>
</div>
</div>
</div>
</div>
</main>
<!-- Modal Restore -->
<div id="restoreModal" class="restore-modal">
<div class="restore-box">
<div class="restore-title">📂 Restaurar configuración</div>
<label class="restore-label" for="restoreFile">Selecciona fichero Copia_A108_*.zip</label>
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
let refreshTimer = null, txTimer = null, vuTimer = null, ysfTimer = null, mmdvmYsfTimer = null, ysfTxTimer = null, ysfVuTimer = null;
let running = false, ysfRunning = false, mmdvmYsfRunning = false, currentlyActive = false, ysfCurrentlyActive = false;

// 🔽 FUNCIÓN PARA OBTENER BANDERA POR CALLSIGN
function getFlagByCall(callsign) {
    if (!callsign) return '';
    const cs = callsign.toUpperCase().trim();
    const prefixes = [
        {re: /^EA[0-9]|EB|EC|ED|EE|EF|EG|EH/, flag:'🇪🇸'},
        {re: /^CT|CU|CV|CQ/, flag:'🇵🇹'},
        {re: /^F[A-Z]|FT[0-9A-Z]|FM|FO|FH|FJ|FK|FL|FP|FR|FS/, flag:'🇫🇷'},
        {re: /^I[0-9]|IK|IW|IZ/, flag:'🇮🇹'},
        {re: /^G[0-9]|M[0-9]|2E[0-9]|2[0-9]|GB|MJ|MU/, flag:'🇬🇧'},
        {re: /^D[ALM]|DA|DB|DC|DD|DE|DF|DG|DH|DI|DJ|DK|DL|DM|DN|DO|DP|DQ|DR/, flag:'🇩🇪'},
        {re: /^K[0-9]|W[0-9]|N[0-9]|AA|AB|AC|AD|AE|AF/, flag:'🇺🇸'},
        {re: /^VE[0-9]|VA[0-9]|VO[0-9]|VY[0-9]|XJ|XK|XL|XM|XN|XO/, flag:'🇨🇦'},
        {re: /^PY[0-9]|PU|PV|PW|PX/, flag:'🇧🇷'},
        {re: /^LU[0-9]|LV|LW|LX/, flag:'🇦🇷'},
        {re: /^JA[0-9]|JB|JC|JD|JE|JF|JG|JH|JI|JJ|JK|JL|JM|JN|JO|JP|JQ|JR|JS|JT|JU|JV|JW|JX|JY|JZ/, flag:'🇯🇵'},
        {re: /^VK[0-9]|VL|VM|VN|VO|VP|VQ|VR|VS|VT|VU|VV|VW|VX|VY|VZ/, flag:'🇦🇺'},
        {re: /^ZS[0-9]|ZT|ZU|ZV|ZW|ZX|ZY|ZZ/, flag:'🇿🇦'},
        {re: /^OH[0-9]|OG|OI|OJ|OK|OL|OM|ON|OO|OP|OQ|OR|OS|OT|OU|OV|OW|OX|OY|OZ/, flag:'🇫🇮'},
        {re: /^PA[0-9]|PB|PC|PD|PE|PF|PG|PH|PI|PJ|PK|PL|PM|PN|PO|PP|PQ|PR|PS|PT|PU|PV|PW|PX|PY|PZ/, flag:'🇳🇱'},
        {re: /^HB[0-9]|HB9|HE|HF|HG|HH|HI|HJ|HK|HL|HM|HN|HO|HP|HQ|HR|HS|HT|HU|HV|HW|HX|HY|HZ/, flag:'🇨🇭'},
        {re: /^OE[0-9]|OF|OG|OH|OI|OJ|OK|OL|OM|ON|OO|OP|OQ|OR|OS|OT|OU|OV|OW|OX|OY|OZ/, flag:'🇦🇹'},
        {re: /^SP[0-9]|SQ|SR|SS|ST|SU|SV|SW|SX|SY|SZ/, flag:'🇵🇱'},
        {re: /^UA[0-9]|UB|UC|UD|UE|UF|UG|UH|UI|UJ|UK|UL|UM|UN|UO|UP|UQ|UR|US|UT|UU|UV|UW|UX|UY|UZ/, flag:'🇷🇺'},
        {re: /^SV[0-9]|SW|SX|SY|SZ/, flag:'🇬🇷'},
        {re: /^LY[0-9]|LZ/, flag:'🇱🇹'},
        {re: /^9A[0-9]|9B|9C|9D|9E|9F|9G|9H|9I|9J|9K|9L|9M|9N|9O|9P|9Q|9R|9S|9T|9U|9V|9W|9X|9Y|9Z/, flag:'🇭🇷'},
    ];
    for (const p of prefixes) { if (p.re.test(cs)) return p.flag; }
    return '🌐';
}

function buildVU(id) { const el = document.getElementById(id); for (let i = 0; i < 18; i++) { const d = document.createElement('div'); d.className = 'nx-vu-bar'; d.id = `${id}-${i}`; el.appendChild(d); } }
buildVU('vuLeft'); buildVU('vuRight'); buildVU('ysfVuLeft'); buildVU('ysfVuRight');

function animateVU(on, prefix) {
    clearInterval(prefix === 'ysf' ? ysfVuTimer : vuTimer);
    const ids = prefix === 'ysf' ? ['ysfVuLeft','ysfVuRight'] : ['vuLeft','vuRight'];
    ids.forEach(id => { for (let i = 0; i < 18; i++) document.getElementById(`${id}-${i}`).className = 'nx-vu-bar'; });
    if (!on) return;
    const timer = setInterval(() => {
        ids.forEach(id => {
            const lvl = Math.floor(Math.random() * 16) + 1;
            for (let i = 0; i < 18; i++) {
                let cls = 'nx-vu-bar';
                if (i < lvl) cls += prefix === 'ysf' ? (i < 10 ? ' lit-v' : i < 14 ? ' lit-vd' : ' lit-r') : (i < 10 ? ' lit-g' : i < 14 ? ' lit-a' : ' lit-r');
                document.getElementById(`${id}-${i}`).className = cls;
            }
        });
    }, 80);
    if (prefix === 'ysf') ysfVuTimer = timer; else vuTimer = timer;
}

function updateClock() {
    const now = new Date();
    const hms = now.toLocaleTimeString('es-ES');
    const date = now.toLocaleDateString('es-ES', {weekday:'short', day:'2-digit', month:'short', year:'numeric'}).toUpperCase();
    document.getElementById('clock').textContent = hms;
    if (!currentlyActive) { const clk = document.getElementById('nxClock'); if (clk) { clk.textContent = hms; document.getElementById('nxDate').textContent = date; } }
    if (!ysfCurrentlyActive) { const yClk = document.getElementById('ysfNxClock'); if (yClk) { yClk.textContent = hms; document.getElementById('ysfNxDate').textContent = date; } }
}
setInterval(updateClock, 1000); updateClock();

function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showIdle() {
    currentlyActive = false; animateVU(false, 'dmr');
    document.getElementById('nxTxBar').classList.remove('active');
    document.getElementById('nxTG').textContent = '—'; document.getElementById('nxSlot').textContent = '—';
    document.getElementById('nxDmrid').textContent = '—';
    const src = document.getElementById('nxSource'); src.textContent = ''; src.className = 'nx-source';
    document.getElementById('nxCenter').innerHTML = '<div class="nx-clock" id="nxClock">00:00:00</div><div class="nx-date" id="nxDate">—</div>';
    updateClock();
}

function showActive(d) {
    currentlyActive = true; animateVU(true, 'dmr');
    document.getElementById('nxTxBar').classList.add('active');
    document.getElementById('nxTG').textContent = d.tg ? 'TG ' + d.tg : '—';
    document.getElementById('nxSlot').textContent = d.slot || '—';
    document.getElementById('nxDmrid').textContent = d.dmrid || '—';
    const src = document.getElementById('nxSource');
    if (d.source === 'RF') { src.textContent = 'RF'; src.className = 'nx-source rf'; }
    else if (d.source === 'NETWORK') { src.textContent = 'NET'; src.className = 'nx-source net'; }
    else { src.textContent = ''; src.className = 'nx-source'; }
    const flag = getFlagByCall(d.callsign);
    document.getElementById('nxCenter').innerHTML = `<div class="nx-callsign">${flag} ${esc(d.callsign)}</div>` + (d.name ? `<div class="nx-name">${esc(d.name)}</div>` : '');
}

function showYSFIdle() {
    ysfCurrentlyActive = false; animateVU(false, 'ysf');
    document.getElementById('ysfTxBar').className = 'nx-txbar';
    document.getElementById('ysfDest').textContent = '—';
    document.getElementById('ysfProto').textContent = 'YSF';
    const src = document.getElementById('ysfSource'); src.textContent = ''; src.className = 'nx-source';
    document.getElementById('ysfNxCenter').innerHTML = '<div class="nx-clock" id="ysfNxClock" style="color:#c084ff;">00:00:00</div><div class="nx-date" id="ysfNxDate" style="color:#9b59d4;">—</div>';
    updateClock();
}

function showYSFActive(d) {
    ysfCurrentlyActive = true; animateVU(true, 'ysf');
    document.getElementById('ysfTxBar').className = 'nx-txbar active-ysf';
    document.getElementById('ysfDest').textContent = d.dest ? d.dest : 'ALL';
    const src = document.getElementById('ysfSource');
    if (d.source === 'RF') { src.textContent = 'RF'; src.className = 'nx-source rf'; }
    else if (d.source === 'NETWORK') { src.textContent = 'NET'; src.className = 'nx-source net'; }
    else { src.textContent = ''; src.className = 'nx-source'; }
    const flag = getFlagByCall(d.callsign);
    document.getElementById('ysfNxCenter').innerHTML = `<div class="nx-callsign ysf">${flag} ${esc(d.callsign)}</div>` + (d.name ? `<div class="nx-name ysf">${esc(d.name)}</div>` : '');
}

function renderLastHeard(list, activeCall) {
    const body = document.getElementById('lhBody');
    if (!list || list.length === 0) { body.innerHTML = '<div class="lh-empty">Sin actividad reciente</div>'; return; }
    body.innerHTML = list.map(r => {
        const isActive = activeCall && r.callsign === activeCall;
        const srcCls = r.source === 'RF' ? 'rf' : 'net', srcLbl = r.source === 'RF' ? 'RF' : 'NET';
        const dot = isActive ? '<span class="lh-tx-dot"></span>' : '';
        const flag = getFlagByCall(r.callsign);
        return `<div class="lh-row${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-tg">${esc(r.tg||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src ${srcCls}">${srcLbl}</span></div>`;
    }).join('');
}

function renderYSFLastHeard(list, activeCall) {
    const body = document.getElementById('ysfLhBody');
    if (!list || list.length === 0) { body.innerHTML = '<div class="lh-empty">Sin actividad C4FM</div>'; return; }
    body.innerHTML = list.map(r => {
        const isActive = activeCall && r.callsign === activeCall;
        const srcCls = r.source === 'RF' ? 'rf' : 'net', srcLbl = r.source === 'RF' ? 'RF' : 'NET';
        const dot = isActive ? '<span class="lh-tx-dot-ysf"></span>' : '';
        const flag = getFlagByCall(r.callsign);
        return `<div class="lh-row-ysf${isActive?' lh-active':''}"><div class="lh-call-wrap">${dot}<span class="lh-call-ysf">${flag} ${esc(r.callsign)}</span></div><span class="lh-name">${esc(r.name||'—')}</span><span class="lh-time">${esc(r.time||'—')}</span><span class="lh-src-ysf ${srcCls}">${srcLbl}</span></div>`;
    }).join('');
}

async function fetchTransmission() { try { const r = await fetch('?action=transmission'); const d = await r.json(); if (d.active) showActive(d); else showIdle(); renderLastHeard(d.lastHeard||[], d.active?d.callsign:null); } catch(e) { showIdle(); } }
async function fetchYSFTransmission() { try { const r = await fetch('?action=ysf-transmission'); const d = await r.json(); if (d.active) showYSFActive(d); else showYSFIdle(); renderYSFLastHeard(d.lastHeard||[], d.active?d.callsign:null); } catch(e) { showYSFIdle(); } }
async function checkStatus() { try { const r = await fetch('?action=status'); const d = await r.json(); const gw = d.gateway==='active', mmd = d.mmdvm==='active'; setDot('dot-gateway', gw?'active':'off'); setDot('dot-mmdvm', mmd?'active':'off'); setDot('dot-mosquitto', gw?'active':'off'); running = gw || mmd; updateDMRButton(running); } catch(e) {} }
async function checkYSFStatus() { try { const r = await fetch('?action=ysf-status'); const d = await r.json(); ysfRunning = d.ysf==='active'; setDot('dot-ysf', ysfRunning?'active':'off'); refreshYSFButton(); } catch(e) {} }
async function checkMMDVMYSFStatus() { try { const r = await fetch('?action=mmdvmysf-status'); const d = await r.json(); mmdvmYsfRunning = d.mmdvmysf==='active'; setDot('dot-mmdvmysf', mmdvmYsfRunning?'active':'off'); refreshYSFButton(); } catch(e) {} }
function setDot(id, state) { document.getElementById(id).className = 'dot' + (state==='active'?' active':state==='error'?' error':''); }
function updateDMRButton(on) { document.getElementById('btnToggle').classList.toggle('running',on); document.getElementById('btnLabel').textContent = on?'Cerrar MMDVM Host':'Abrir MMDVM Host'; }
function refreshYSFButton() { const on = ysfRunning || mmdvmYsfRunning; document.getElementById('btnYSF').classList.toggle('running',on); document.getElementById('btnYSFLabel').textContent = on?'Cerrar C4FM':'Abrir C4FM'; document.getElementById('ysfRefreshBadge').style.display = on?'flex':'none'; }
async function toggleServices() { const btn = document.getElementById('btnToggle'); const wasOpen = running; btn.classList.add('busy'); document.getElementById('btnLabel').textContent = wasOpen?'Deteniendo…':'Iniciando…'; try { await fetch(wasOpen?'?action=stop':'?action=start'); await new Promise(r => setTimeout(r,2200)); await checkStatus(); if (wasOpen) { stopRefresh(); clearLog('logGw'); clearLog('logMmd'); showIdle(); document.getElementById('lhBody').innerHTML='<div class="lh-empty">Sin actividad reciente</div>'; } else startRefresh(); } finally { btn.classList.remove('busy'); } }
async function toggleYSF() { const btn = document.getElementById('btnYSF'); const wasOpen = ysfRunning || mmdvmYsfRunning; btn.classList.add('busy'); document.getElementById('btnYSFLabel').textContent = wasOpen?'Deteniendo…':'Iniciando…'; try { if (wasOpen) { await fetch('?action=ysf-stop'); await new Promise(r => setTimeout(r,1000)); await fetch('?action=mmdvmysf-stop'); await new Promise(r => setTimeout(r,2000)); clearLog('logYsf'); clearLog('logMmdvmYsf'); stopYSFLogs(); stopMMDVMYSFLogs(); showYSFIdle(); document.getElementById('ysfLhBody').innerHTML='<div class="lh-empty">Sin actividad C4FM</div>'; } else { await fetch('?action=mmdvmysf-start'); await new Promise(r => setTimeout(r,2000)); await fetch('?action=ysf-start'); await new Promise(r => setTimeout(r,1500)); startYSFLogs(); startMMDVMYSFLogs(); } await checkYSFStatus(); await checkMMDVMYSFStatus(); } finally { btn.classList.remove('busy'); } }
async function rebootPi() { if (!confirm('¿Seguro que quieres reiniciar la Raspberry Pi?')) return; const btn = document.getElementById('btnReboot'); btn.textContent = '⏻ Reiniciando…'; btn.disabled = true; await fetch('?action=reboot'); }
async function activarDisplay() { if (!confirm('¿Activar el servicio Display Driver?')) return; await fetch('?action=display-restart'); alert('✔ Display Driver activado correctamente.'); }
function instalarDisplay() { document.getElementById('installModal').classList.add('open'); document.getElementById('installOutput').className = 'install-output'; document.getElementById('installOutput').textContent = ''; document.getElementById('installMsg').style.display = 'none'; document.getElementById('installMsg').className = 'restore-msg'; document.getElementById('btnInstalarOk').disabled = false; document.getElementById('btnInstalarOk').textContent = '▶ Confirmar instalación'; }
function closeInstalar() { document.getElementById('installModal').classList.remove('open'); }
async function confirmarInstalacion() { const btn = document.getElementById('btnInstalarOk'); const msg = document.getElementById('installMsg'); const out = document.getElementById('installOutput'); btn.disabled = true; btn.textContent = '⏳ Instalando…'; msg.className = 'restore-msg loading'; msg.style.display = 'block'; msg.textContent = '⏳ Ejecutando instalador, espera…'; out.className = 'install-output visible'; out.textContent = ''; try { const r = await fetch('?action=install-display'); const d = await r.json(); out.textContent = d.output || '(sin salida)'; out.scrollTop = out.scrollHeight; msg.className = 'restore-msg ok'; msg.textContent = '✔ Instalación completada.'; btn.textContent = '✔ Cerrar'; btn.disabled = false; btn.onclick = function() { closeInstalar(); }; } catch(e) { msg.className = 'restore-msg err'; msg.textContent = '✖ Error durante la instalación.'; btn.textContent = '▶ Confirmar instalación'; btn.disabled = false; } }
function openRestore() { document.getElementById('restoreModal').classList.add('open'); document.getElementById('restoreFile').value = ''; const msg = document.getElementById('restoreMsg'); msg.style.display = 'none'; msg.className = 'restore-msg'; }
function closeRestore() { document.getElementById('restoreModal').classList.remove('open'); }
async function doRestore() { const file = document.getElementById('restoreFile').files[0]; if (!file) { alert('Selecciona un fichero ZIP primero.'); return; } const msg = document.getElementById('restoreMsg'); msg.className = 'restore-msg loading'; msg.style.display = 'block'; msg.textContent = '⏳ Restaurando…'; try { const form = new FormData(); form.append('zipfile', file); const r = await fetch('?action=restore-configs', { method: 'POST', body: form }); const d = await r.json(); msg.className = 'restore-msg ' + (d.ok ? 'ok' : 'err'); msg.textContent = (d.ok ? '✔ ' : '✖ ') + d.msg; if (d.ok) setTimeout(closeRestore, 2500); } catch(e) { msg.className = 'restore-msg err'; msg.textContent = '✖ Error en la solicitud.'; } }
function colorize(text) { return text.split('\n').map(l => { const ll = l.toLowerCase(); if (/error|fail|abort|assert/.test(ll)) return `<span class="ln-err">${l}</span>`; if (/warn/.test(ll)) return `<span class="ln-warn">${l}</span>`; if (/connect|start|open|loaded|success/.test(ll)) return `<span class="ln-ok">${l}</span>`; return `<span class="ln-info">${l}</span>`; }).join('\n'); }
function clearLog(id) { document.getElementById(id).innerHTML = ''; }
async function fetchLogs() { try { const r = await fetch('?action=logs&lines=15'); const d = await r.json(); ['logGw:gateway','logMmd:mmdvm'].forEach(pair => { const [id,key] = pair.split(':'); const el = document.getElementById(id); const atBot = el.scrollHeight-el.clientHeight <= el.scrollTop+10; el.innerHTML = colorize(d[key]); if (atBot) el.scrollTop = el.scrollHeight; }); } catch(e) {} }
async function fetchYSFLogs() { try { const r = await fetch('?action=ysf-logs&lines=15'); const d = await r.json(); const el = document.getElementById('logYsf'); const atBot = el.scrollHeight-el.clientHeight<=el.scrollTop+10; el.innerHTML=colorize(d.ysf); if(atBot)el.scrollTop=el.scrollHeight; } catch(e) {} }
async function fetchMMDVMYSFLogs() { try { const r = await fetch('?action=mmdvmysf-logs&lines=15'); const d = await r.json(); const el = document.getElementById('logMmdvmYsf'); const atBot = el.scrollHeight-el.clientHeight<=el.scrollTop+10; el.innerHTML=colorize(d.mmdvmysf); if(atBot)el.scrollTop=el.scrollHeight; } catch(e) {} }
function startRefresh() { fetchLogs(); fetchTransmission(); refreshTimer=setInterval(fetchLogs,5000); txTimer=setInterval(fetchTransmission,3000); document.getElementById('autoRefreshBadge').style.display='flex'; }
function stopRefresh() { clearInterval(refreshTimer); clearInterval(txTimer); refreshTimer=txTimer=null; document.getElementById('autoRefreshBadge').style.display='none'; }
function startYSFLogs() { fetchYSFLogs(); ysfTimer = setInterval(fetchYSFLogs, 4000); }
function stopYSFLogs() { clearInterval(ysfTimer); ysfTimer = null; }
function startMMDVMYSFLogs() { fetchMMDVMYSFLogs(); mmdvmYsfTimer = setInterval(fetchMMDVMYSFLogs, 4000); }
function stopMMDVMYSFLogs() { clearInterval(mmdvmYsfTimer); mmdvmYsfTimer = null; }
function startYSFTransmissionPoll() { fetchYSFTransmission(); ysfTxTimer = setInterval(fetchYSFTransmission, 4000); }
function stopYSFTransmissionPoll() { clearInterval(ysfTxTimer); ysfTxTimer = null; }
(async () => { await checkStatus(); await checkYSFStatus(); await checkMMDVMYSFStatus(); setInterval(checkStatus,10000); setInterval(checkYSFStatus,8000); setInterval(checkMMDVMYSFStatus,8000); if (running) startRefresh(); else { showIdle(); fetchTransmission(); } showYSFIdle(); startYSFLogs(); startMMDVMYSFLogs(); startYSFTransmissionPoll(); })();
</script>



<?php
/*
 * ============================================================
 *  BLOQUE DVSwitch (iframe) — añade esto dentro de mmdvm.php
 *  Colócalo donde quieras el panel, por ejemplo al final
 *  antes del </div> principal o </body>
 * ============================================================
 */
?>

<!-- ===================== PANEL DVSWITCH IFRAME ===================== -->
<div class="row mt-4">
  <div class="col-10 offset-1">
    <div class="card dark-card">
      <div class="card-header d-flex align-items-center justify-content-between"
           style="background: linear-gradient(90deg,#1a2540,#1e2d50); border-bottom:1px solid #7eb8f733;">
        <span style="color:#7eb8f7; font-weight:700; letter-spacing:2px; font-size:1.05rem;">
          <i class="bi bi-broadcast me-2"></i>DVSwitch Dashboard
        </span>
        <div class="d-flex align-items-center gap-2">
          <!-- Botón abrir en ventana nueva -->
          <a href="http://<?= $_SERVER['SERVER_ADDR'] ?>:8080"
             target="_blank"
             class="btn btn-sm"
             style="background:#1e3a5f; border:1px solid #7eb8f755; color:#7eb8f7; font-size:.8rem;">
            <i class="bi bi-box-arrow-up-right me-1"></i>Abrir en nueva ventana
          </a>
          <!-- Botón recargar iframe -->
          <button class="btn btn-sm"
                  style="background:#1e3a5f; border:1px solid #7eb8f755; color:#7eb8f7; font-size:.8rem;"
                  onclick="document.getElementById('dvs-iframe').src = document.getElementById('dvs-iframe').src">
            <i class="bi bi-arrow-clockwise me-1"></i>Recargar
          </button>
        </div>
      </div>

      <div class="card-body p-0" style="background:#000;">
        <iframe
          id="dvs-iframe"
          src="http://192.168.1.126/dvswitch/"
          style="width:100%; height:680px; border:none; display:block;"
          scrolling="auto"
          allowfullscreen>
        </iframe>
      </div>

    </div>
  </div>
</div>
<!-- ============= FIN PANEL DVSWITCH IFRAME ============= -->







</body>
</html>