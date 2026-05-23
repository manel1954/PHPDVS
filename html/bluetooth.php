<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_time_limit(30);

class BluetoothManager {
    private string $scriptPath;
    private string $hcitoolBin;
    private string $rfcommBin;
    private string $hciconfigBin;
    private string $btctlBin;

    public function __construct(string $scriptPath = '/home/pi/.local/bluetooth.sh') {
        $this->scriptPath = $scriptPath;
        $this->hcitoolBin   = trim(shell_exec('which hcitool 2>/dev/null')) ?: '/usr/bin/hcitool';
        $this->rfcommBin    = trim(shell_exec('which rfcomm 2>/dev/null')) ?: '/usr/bin/rfcomm';
        $this->hciconfigBin = trim(shell_exec('which hciconfig 2>/dev/null')) ?: '/usr/bin/hciconfig';
        $this->btctlBin     = trim(shell_exec('which bluetoothctl 2>/dev/null')) ?: '/usr/bin/bluetoothctl';
        
        if (!is_writable(dirname($this->scriptPath))) {
            throw new RuntimeException("Directorio no escribible. Ejecuta: sudo chown -R www-data " . dirname($this->scriptPath));
        }
    }

    private function runSudo(string $cmd): array {
        $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open("sudo $cmd", $desc, $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $ret = proc_close($proc);
        return ['stdout' => trim($out), 'stderr' => trim($err), 'return' => $ret];
    }

    public function scanDevices(): array {
        $this->runSudo("{$this->hciconfigBin} hci0 up 2>/dev/null || true");
        usleep(300000);
        $res = $this->runSudo("{$this->hcitoolBin} scan 2>&1");
        $devices = [];
        if (!empty($res['stdout'])) {
            foreach (preg_split('/\r\n|\r|\n/', $res['stdout']) as $line) {
                if (preg_match('/([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}/', $line, $m)) {
                    $mac = $m[0];
                    $name = trim(preg_replace('/.*([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}\s*/', '', $line));
                    $devices[$mac] = $name ?: 'Dispositivo sin nombre';
                }
            }
        }
        return ['devices' => $devices, 'raw' => $res['stdout'], 'err' => $res['stderr'], 'ret' => $res['return']];
    }

    public function getBoundDevices(): array {
        if (!file_exists($this->scriptPath)) return [];
        $content = file_get_contents($this->scriptPath);
        $bound = [];
        if (preg_match_all('/sudo\s+rfcomm\s+bind\s+(\/dev\/rfcomm\d+)\s+([0-9A-Fa-f:]{17})/m', $content, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) $bound[$row[1]] = $row[2];
        }
        return $bound;
    }

    public function getDeviceStatus(string $rfcomm, string $mac): array {
        $rfcomm = str_starts_with($rfcomm, '/') ? $rfcomm : "/dev/$rfcomm";
        $bound = ($this->runSudo("test -e $rfcomm 2>/dev/null")['return'] === 0);
        
        $fuserRes = $this->runSudo("fuser $rfcomm 2>/dev/null");
        $in_use = ($fuserRes['return'] === 0 && trim($fuserRes['stdout']) !== '');
        
        $rssi = null;
        if ($mac && $bound) {
            $btRes = $this->runSudo("{$this->btctlBin} info $mac 2>&1");
            $out = $btRes['stdout'] . $btRes['stderr'];
            if (preg_match('/RSSI:\s+(-\d+)\s+dBm/i', $out, $m)) {
                $rssi = (int)$m[1];
            }
        }
        
        $signal = 0;
        if ($bound) {
            if ($rssi !== null) {
                $signal = $rssi > -60 ? 3 : ($rssi > -80 ? 2 : 1);
            } else {
                $signal = $in_use ? 2 : 1;
            }
        }
        
        return ['rfcomm' => $rfcomm, 'bound' => $bound, 'in_use' => $in_use, 'rssi' => $rssi, 'signal' => $signal];
    }

    public function addAndBind(string $mac, string $rfcomm): bool {
        if (!preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac)) throw new InvalidArgumentException("MAC inválido");
        $this->addToScript($rfcomm, $mac);
        $res = $this->runSudo("{$this->rfcommBin} bind $rfcomm $mac");
        if ($res['return'] !== 0) throw new RuntimeException("Error al vincular:\n" . ($res['stderr'] ?: $res['stdout']));
        usleep(800000);
        return true;
    }

    public function toggleBind(string $rfcomm, string $mac): bool {
        $status = $this->getDeviceStatus($rfcomm, $mac);
        if ($status['in_use']) {
            throw new RuntimeException("⚠️ El dispositivo está en uso. Cierra la aplicación que lo usa.");
        }
        if ($status['bound']) {
            $res = $this->runSudo("{$this->rfcommBin} release $rfcomm");
            if ($res['return'] !== 0) throw new RuntimeException("Error al desvincular:\n" . ($res['stderr'] ?: $res['stdout']));
        } else {
            $res = $this->runSudo("{$this->rfcommBin} bind $rfcomm $mac");
            if ($res['return'] !== 0) throw new RuntimeException("Error al vincular:\n" . ($res['stderr'] ?: $res['stdout']));
        }
        usleep(800000);
        return true;
    }

    public function unbindAndRemove(string $rfcomm): bool {
        if (!preg_match('/^\/dev\/rfcomm\d+$/', $rfcomm)) throw new InvalidArgumentException("rfcomm inválido");
        $this->runSudo("{$this->rfcommBin} release $rfcomm 2>/dev/null || true");
        usleep(1000000);
        $this->removeFromScript($rfcomm);
        return true;
    }

    private function addToScript(string $rfcomm, string $mac): void {
        $content = file_exists($this->scriptPath) ? file_get_contents($this->scriptPath) : "#!/bin/bash\n";
        if (strpos($content, '#!/bin/bash') === false) $content = "#!/bin/bash\n" . $content;
        $escaped = preg_quote($rfcomm, '/');
        $content = preg_replace("/sudo\s+rfcomm\s+bind\s+{$escaped}\s+[0-9A-Fa-f:]{17}\s*\n?/m", '', $content);
        $content = rtrim($content) . "\nsudo rfcomm bind $rfcomm $mac\n";
        $this->atomicWrite($content);
    }

    private function removeFromScript(string $rfcomm): void {
        if (!file_exists($this->scriptPath)) return;
        $content = file_get_contents($this->scriptPath);
        $escaped = preg_quote($rfcomm, '/');
        $content = preg_replace("/sudo\s+rfcomm\s+bind\s+{$escaped}\s+[0-9A-Fa-f:]{17}\s*\n?/m", '', $content);
        $content = preg_replace("/\n{3,}/", "\n\n", trim($content)) . "\n";
        $this->atomicWrite($content);
    }

    private function atomicWrite(string $content): void {
        $tmp = $this->scriptPath . '.tmp';
        file_put_contents($tmp, $content, LOCK_EX);
        chmod($tmp, 0755);
        rename($tmp, $this->scriptPath);
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $bt = new BluetoothManager('/home/pi/.local/bluetooth.sh');
        switch($_GET['action']) {
            case 'scan':     $data = $bt->scanDevices(); break;
            case 'list':     $data = $bt->getBoundDevices(); break;
            case 'status':   $data = $bt->getDeviceStatus($_GET['rfcomm'] ?? '', $_GET['mac'] ?? ''); break;
            case 'add_bind': $bt->addAndBind($_GET['mac'] ?? '', $_GET['rfcomm'] ?? ''); $data = ['ok' => true]; break;
            case 'toggle':   $bt->toggleBind($_GET['rfcomm'] ?? '', $_GET['mac'] ?? ''); $data = ['ok' => true]; break;
            case 'remove':   $bt->unbindAndRemove($_GET['rfcomm'] ?? ''); $data = ['ok' => true]; break;
            default: throw new Exception("Acción no válida");
        }
        exit(json_encode(['status' => 'success', 'data' => $data]));
    } catch (Throwable $e) {
        http_response_code(500);
        exit(json_encode(['status' => 'error', 'msg' => $e->getMessage()]));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📡 Gestor Bluetooth</title>
    <style>
        :root { --bg:#0f1115; --card:#181b21; --border:#2a2e36; --text:#e2e4e8; --muted:#8b909a; --accent:#00d4ff; --success:#2ecc71; --danger:#e74c3c; --warn:#f39c12; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:system-ui,-apple-system,sans-serif; background:var(--bg); color:var(--text); line-height:1.5; padding:20px; }
        .wrap { max-width:900px; margin:0 auto; }
        .header { text-align:center; margin-bottom:24px; }
        .header h1 { font-weight:600; margin-top:8px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.3); }
        .card h2 { font-size:1.1rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .btn { background:var(--accent); color:#000; border:none; padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer; transition:0.2s; display:inline-flex; align-items:center; gap:8px; }
        .btn:hover { filter:brightness(0.9); } .btn:disabled { opacity:0.4; cursor:not-allowed; filter:grayscale(0.8); }
        .btn-danger { background:var(--danger); color:#fff; } .btn-warn { background:var(--warn); color:#000; } .btn-sm { padding:6px 12px; font-size:0.85rem; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th,td { padding:12px 14px; text-align:left; border-bottom:1px solid var(--border); }
        th { background:#111318; font-weight:500; color:var(--muted); font-size:0.85rem; text-transform:uppercase; letter-spacing:0.5px; }
        tr:last-child td { border-bottom:none; }
        .mac { font-family:'SF Mono','Consolas',monospace; letter-spacing:0.5px; color:var(--accent); }
        .empty { text-align:center; padding:20px; color:var(--muted); font-style:italic; }
        .status-cell { display:flex; align-items:center; gap:8px; }
        .badge { padding:4px 8px; border-radius:6px; font-size:0.75rem; font-weight:600; white-space:nowrap; }
        .badge-ok { background:var(--success); color:#000; }
        .badge-warn { background:var(--warn); color:#000; }
        .badge-err { background:var(--danger); color:#fff; }
        .signal-bar { display:inline-flex; align-items:flex-end; gap:2px; height:16px; }
        .signal-bar span { display:block; width:4px; border-radius:1px; background:#444; transition:0.2s; }
        .signal-bar .active { background:var(--success); }
        .signal-bar .b1 { height:6px; } .signal-bar .b2 { height:10px; } .signal-bar .b3 { height:14px; }
        .rssi-tag { font-size:0.75rem; color:var(--muted); font-family:monospace; white-space:nowrap; }
        .debug-box { background:#0a0c10; border:1px dashed var(--muted); padding:12px; border-radius:8px; font-family:monospace; font-size:0.8rem; white-space:pre-wrap; margin-top:12px; max-height:150px; overflow:auto; color:#7a8299; }
        .toast { position:fixed; top:20px; right:20px; padding:12px 18px; border-radius:8px; background:#1e2026; border:1px solid var(--border); transform:translateX(120%); transition:0.3s; z-index:1000; font-size:0.9rem; max-width:350px; white-space:pre-wrap; }
        .toast.show { transform:translateX(0); } .toast.ok { border-left:4px solid var(--success); } .toast.err { border-left:4px solid var(--danger); }
        .loader { width:14px; height:14px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:spin 0.8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }
        .pulse { animation: pulse 2s infinite; }
        @media (max-width:600px) { th,td { padding:10px; font-size:0.9rem; } .btn { padding:8px 12px; } }
</style>
</head>
<body>
    <div class="wrap">

        <div class="header" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">

           <!-- ICONO BLUETOOTH CON FONDO BLANCO -->
<div style="
    width:48px;
    height:48px;
    background:#0f2a3a;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 2px 6px rgba(0,0,0,0.3);
">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none"
         xmlns="http://www.w3.org/2000/svg">
        <path d="M7 7L17 17L12 22V2L17 7L7 17"
              stroke="#00d4ff"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"/>
    </svg>
</div>

            <!-- TITULO -->
            <h1 style="margin:0; font-size:1.4rem;">
                Gestión de dispositivos Bluetooth
            </h1>

            <!-- BOTON A LA DERECHA -->
            <a href="mmdvm.php"
               style="margin-left:auto;
                      background:#2ecc71;
                      color:#000;
                      padding:8px 14px;
                      border-radius:8px;
                      text-decoration:none;
                      font-weight:600;
                      white-space:nowrap;">
                🏠 Panel PHPPLUS
            </a>

        </div>
        
        <div class="card">
            <h2>🔍 Escaneo</h2>
            <button id="scanBtn" class="btn" onclick="scanDevices()">Escanear Ahora</button>
            <div id="scanResult" style="display:none; margin-top:16px;">
                <table id="tblDiscovered"><thead><tr><th>MAC</th><th>Nombre</th><th>Acción</th></tr></thead><tbody></tbody></table>
                <div id="debugPanel" class="debug-box" style="display:none;"></div>
            </div>
        </div>

        <div class="card">
            <h2>🔗 Dispositivos en Autoarranque</h2>
            <p style="font-size:0.85rem; color:var(--muted); margin-bottom:12px;">
                ⚡ Estado actualizado automáticamente cada 2s. Botón central gestiona el puerto sin borrar la configuración de reinicio.<br>
                ❌ Solo "Eliminar" borra permanentemente de `bluetooth.sh`.
            </p>
            <table id="tblBound"><thead><tr><th>Puerto</th><th>MAC</th><th>Estado</th><th>Acción</th></tr></thead><tbody></tbody></table>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        const toast = (msg, type='ok') => {
            const el = document.getElementById('toast');
            el.textContent = msg;
            el.className = `toast show ${type}`;
            setTimeout(() => el.className = 'toast', 4500);
        };

        const api = async (action, params={}) => {
            const qs = new URLSearchParams({action, ...params}).toString();
            const res = await fetch(`?${qs}`);
            const data = await res.json();
            if (!res.ok || data.status === 'error') throw new Error(data.msg);
            return data;
        };

        const safeId = str => str.replace(/[\/\-]/g, '_');

        async function updateRow(rowEl) {
            const rfcomm = rowEl.dataset.rfcomm;
            const mac = rowEl.dataset.mac;
            const statusCell = rowEl.querySelector('.status-cell');
            const mainBtn = rowEl.querySelector('.btn-main');
            const deleteBtn = rowEl.querySelector('.btn-danger');
            
            try {
                const res = await api('status', {rfcomm, mac});
                const { bound, in_use, signal, rssi } = res.data;
                
                statusCell.innerHTML = '';
                const badge = document.createElement('span');
                badge.className = 'badge';
                
                if (bound) {
                    if (in_use) {
                        badge.className += ' badge-warn pulse';
                        badge.textContent = '🟡 En Uso';
                        mainBtn.textContent = '⚠️ Ocupado';
                        mainBtn.disabled = true;
                        mainBtn.onclick = null;
                        if(deleteBtn) deleteBtn.disabled = true;
                    } else {
                        badge.className += ' badge-ok';
                        badge.textContent = '🟢 Vinculado';
                        mainBtn.textContent = '🔌 Desvincular';
                        mainBtn.disabled = false;
                        mainBtn.onclick = () => toggleBind(rfcomm, mac, rowEl);
                        if(deleteBtn) deleteBtn.disabled = false;
                    }
                    statusCell.appendChild(badge);
                    
                    if (in_use) {
                        const barWrap = document.createElement('span');
                        barWrap.className = 'signal-bar';
                        [1,2,3].forEach(i => {
                            const bar = document.createElement('span');
                            bar.className = `b${i} ${i <= signal ? 'active' : ''}`;
                            barWrap.appendChild(bar);
                        });
                        statusCell.appendChild(barWrap);
                        
                        if (rssi !== null) {
                            const rssiTag = document.createElement('span');
                            rssiTag.className = 'rssi-tag';
                            rssiTag.textContent = `${rssi} dBm`;
                            statusCell.appendChild(rssiTag);
                        }
                    }
                } else {
                    badge.className += ' badge-err';
                    badge.textContent = '🔴 Desvinculado';
                    statusCell.appendChild(badge);
                    
                    mainBtn.textContent = '⚡ Vincular';
                    mainBtn.disabled = false;
                    mainBtn.onclick = () => toggleBind(rfcomm, mac, rowEl);
                    if(deleteBtn) deleteBtn.disabled = false;
                }
            } catch(e) {
                // Mantiene último estado válido ante fallos transitorios
            }
        }

        async function toggleBind(rfcomm, mac, rowEl) {
            const btn = rowEl.querySelector('.btn-main');
            btn.disabled = true; btn.textContent = '⏳ Procesando...';
            try {
                await api('toggle', {rfcomm, mac});
                await new Promise(r => setTimeout(r, 1000));
                await updateRow(rowEl);
                const st = await api('status', {rfcomm, mac});
                toast(`✅ ${rfcomm} ${st.data.bound ? 'vinculado' : 'desvinculado'}`, 'ok');
            } catch(e) { 
                toast('❌ ' + e.message, 'err'); 
                await updateRow(rowEl);
            }
        }

        async function scanDevices() {
            const btn = document.getElementById('scanBtn');
            btn.disabled = true; btn.innerHTML = '<span class="loader"></span> Escaneando...';
            try {
                const res = await api('scan');
                const tbody = document.querySelector('#tblDiscovered tbody');
                tbody.innerHTML = '';
                const devices = res.data.devices;
                const debug = document.getElementById('debugPanel');

                if (!Object.keys(devices).length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="empty">No se detectaron dispositivos</td></tr>';
                    debug.style.display = 'block';
                    debug.textContent = `Salida cruda:\n${res.data.raw || '(vacío)'}\nError:\n${res.data.err || '(ninguno)'}\nRetorno: ${res.data.ret}`;
                } else {
                    debug.style.display = 'none';
                    for (const [mac, name] of Object.entries(devices)) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td class="mac">${mac}</td><td>${name}</td>
                            <td><button class="btn btn-sm" onclick="bindNew('${mac}')">Vincular</button></td>`;
                        tbody.appendChild(tr);
                    }
                }
                document.getElementById('scanResult').style.display = 'block';
                toast(Object.keys(devices).length ? '✅ Escaneo completado' : 'Revisa el panel de debug', Object.keys(devices).length ? 'ok' : 'warn');
            } catch(e) { toast('❌ ' + e.message, 'err'); }
            finally { btn.disabled = false; btn.innerHTML = 'Escanear Ahora'; }
        }

        async function bindNew(mac) {
            try {
                const list = await api('list');
                const next = Object.keys(list.data).length;
                const rfcomm = `/dev/rfcomm${next}`;
                await api('add_bind', {rfcomm, mac});
                toast(`✅ ${mac} añadido y vinculado en ${rfcomm}`, 'ok');
                loadBound();
            } catch(e) { toast('❌ ' + e.message, 'err'); }
        }

        async function loadBound() {
            try {
                const res = await api('list');
                const tbody = document.querySelector('#tblBound tbody');
                tbody.innerHTML = '';
                const bound = res.data;
                if (!Object.keys(bound).length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="empty">No hay dispositivos en autoarranque</td></tr>';
                    return;
                }
                
                for (const [dev, mac] of Object.entries(bound)) {
                    const tr = document.createElement('tr');
                    tr.id = `row_${safeId(dev)}`;
                    tr.dataset.rfcomm = dev;
                    tr.dataset.mac = mac;
                    tr.innerHTML = `
                        <td class="mac">${dev}</td>
                        <td class="mac">${mac}</td>
                        <td class="status-cell"><span class="badge badge-ok">⏳ Verificando...</span></td>
                        <td class="btn-cell">
                            <button class="btn-main btn btn-sm" disabled>⏳</button>
                            <button class="btn btn-danger btn-sm" style="margin-left:6px;" onclick="removeDev('${dev}')">❌ Eliminar</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                }
                await Promise.all(Array.from(tbody.children).map(row => updateRow(row)));
            } catch(e) { toast('❌ ' + e.message, 'err'); }
        }

        async function removeDev(dev) {
            if (!confirm(`¿Eliminar ${dev} permanentemente del autoarranque y liberar el puerto?`)) return;
            try {
                await api('remove', {rfcomm: dev});
                toast(`✅ ${dev} eliminado correctamente`, 'ok');
                loadBound();
            } catch(e) { toast('❌ ' + e.message, 'err'); }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadBound();
            setInterval(() => {
                document.querySelectorAll('#tblBound tr[data-rfcomm]').forEach(row => updateRow(row));
            }, 2000);
        });
    </script>
</body>
</html>


