<?php
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$server_ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['HTTP_HOST'] ?? 'Local';
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$protocol = $is_https ? "HTTPS (Seguro)" : "HTTP (No seguro)";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ESP32 Web Flash Tool</title>
<style>
:root{ --primary:#2196F3; --success:#4CAF50; --error:#f44336; --warning:#FF9800; --bg:#1e1e2e; --card:#2a2a3e; --text:#e0e0e0; --mono:'Fira Code', monospace; }
*{ margin:0; padding:0; box-sizing:border-box; }
body{ background:var(--bg); color:var(--text); font-family:system-ui,sans-serif; max-width:1100px; margin:auto; padding:20px; }
header{ text-align:center; margin-bottom:25px; }
header h1{ margin-bottom: 15px; display: block; }
.env-info { display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; flex-wrap: wrap; }
.badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; border: 1px solid transparent; }
.badge-success { background: rgba(76, 175, 80, 0.15); color: #81c784; border-color: #4CAF50; }
.badge-warning { background: rgba(255, 152, 0, 0.15); color: #ffb74d; border-color: #FF9800; }
.badge-error { background: rgba(244, 67, 54, 0.15); color: #ef9a9a; border-color: #f44336; }

.card{ background:var(--card); border-radius:12px; padding:20px; margin-bottom:20px; }
.card h3{ margin-bottom:15px; color:var(--primary); }
.status{ background:#333; padding:14px; border-radius:8px; margin-bottom:15px; font-family:var(--mono); }
.btn-group{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
button, select{ border:none; border-radius:8px; padding:10px 16px; cursor:pointer; font-weight:600; }
button{ background:var(--primary); color:white; } button:hover{ opacity:.9; } button:disabled{ opacity:.5; cursor:not-allowed; }
.success{ background:var(--success); } .danger{ background:var(--error); } .warning{ background:var(--warning); color:black; }

.partition{ display:grid; grid-template-columns:120px 1fr 45px 100px 100px; gap:10px; align-items:center; background:#252538; padding:12px; border-radius:8px; margin-bottom:10px; }
.btn-clear{ background:#444; color:#ff6666; border:none; cursor:pointer; padding:5px; border-radius:4px; font-weight:bold; }
.btn-clear:hover{ background:#555; color:#ff4444; }

.offset{ color:var(--warning); font-family:var(--mono); } .filesize{ color:#999; text-align:right; font-size:.85rem; }
.progress-wrapper{ margin-top:18px; } .progress-bar{ width:100%; height:30px; background:#333; border-radius:8px; overflow:hidden; position:relative; }
.progress-fill{ height:100%; width:0%; background:linear-gradient(90deg,#4CAF50,#66BB6A); transition:width .2s; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; }
#log{ background:#111; border-radius:8px; padding:15px; font-family:var(--mono); font-size:.85rem; max-height:420px; overflow-y:auto; white-space:pre-wrap; }
.timestamp{ color:#888; } .hidden{ display:none; }
@media(max-width:850px){ .partition{ grid-template-columns: 1fr 1fr; } .btn-group{ flex-direction:column; } button, select{ width:100%; } }
</style>
</head>
<body>
<header>
    <h1>Programador WEB-ESP32 PHPPLUS</h1>
    <div class="env-info">
        <span class="badge badge-success">IP: <?php echo $server_ip; ?></span>
        <span class="badge <?php echo $is_https ? 'badge-success' : 'badge-warning'; ?>">Protocolo: <?php echo $protocol; ?></span>
        <span id="webserialBadge" class="badge">Chequeando WebSerial...</span>
        <span id="secureBadge" class="badge">Chequeando Seguridad...</span>
    </div>
</header>

<div class="card">
    <div id="status" class="status">Esperando conexión...</div>
    <div class="btn-group">
        <button id="connectBtn">🔌 Conectar</button>
        <button id="disconnectBtn" class="danger hidden">❌ Desconectar</button>
        <button id="eraseBtn" class="warning">🗑️ Borrar Flash</button>
        <button id="flashBtn" class="success" disabled>🚀 Programar</button>

        <select id="baudRate">
            <option value="115200">115200</option>
            <option value="460800" selected>460800 ⚡</option>
            <option value="921600">921600 🚀</option>
        </select>

        <a href="https://raw.githubusercontent.com/ea4aoj/PHPPLUS/main/esp32/firmware.zip" style="text-decoration: none;">
    <button type="button" style="background-color: #673AB7;">📥 Descarga firmware</button>
</a>

<a href="mmdvm.php" style="text-decoration:none; margin-left:auto;">
    <button type="button">
        🏠 Panel PHPPLUS
    </button>
</a>
    </div>
    <div id="progressContainer" class="progress-wrapper hidden">
        <div class="progress-bar"><div id="progressFill" class="progress-fill">0%</div></div>
        <div id="progressLabel" style="margin-top:8px; font-size:0.9rem; color:#bbb; text-align:center;">Esperando...</div>
    </div>
</div>

<div class="card">
    <h3>Archivos BIN</h3>
    <div class="partition" data-offset="0x1000">
        <label>Bootloader</label>
        <input type="file" class="binFile" data-type="bootloader" accept=".bin">
        <button class="btn-clear" onclick="clearFile('bootloader')" title="Quitar archivo">✖</button>
        <span class="offset">0x1000</span>
        <span class="filesize" id="size-bootloader"></span>
    </div>
    <div class="partition" data-offset="0x8000">
        <label>Partitions</label>
        <input type="file" class="binFile" data-type="partitions" accept=".bin">
        <button class="btn-clear" onclick="clearFile('partitions')" title="Quitar archivo">✖</button>
        <span class="offset">0x8000</span>
        <span class="filesize" id="size-partitions"></span>
    </div>
    <div class="partition" data-offset="0x10000">
        <label>Firmware</label>
        <input type="file" class="binFile" data-type="firmware" accept=".bin">
        <button class="btn-clear" onclick="clearFile('firmware')" title="Quitar archivo">✖</button>
        <span class="offset">0x10000</span>
        <span class="filesize" id="size-firmware"></span>
    </div>
    <div class="partition" data-offset="0x290000">
        <label>LittleFS</label>
        <input type="file" class="binFile" data-type="littlefs" accept=".bin">
        <button class="btn-clear" onclick="clearFile('littlefs')" title="Quitar archivo">✖</button>
        <span class="offset">0x290000</span>
        <span class="filesize" id="size-littlefs"></span>
    </div>
</div>

<div class="card"><h3>Consola</h3><div id="log"></div></div>

<script type="module">
import { ESPLoader, Transport } from 'https://unpkg.com/esptool-js@0.6.0/bundle.js';

const logDiv = document.getElementById('log');
function log(text, type=''){
    const line = document.createElement('div');
    line.innerHTML = `<span class="timestamp">[${new Date().toLocaleTimeString()}]</span> <span class="${type}">${text}</span>`;
    logDiv.appendChild(line);
    logDiv.scrollTop = logDiv.scrollHeight;
}

// DIAGNÓSTICO AL CARGAR
window.addEventListener('DOMContentLoaded', () => {
    if ('serial' in navigator) {
        document.getElementById('webserialBadge').textContent = "WebSerial: OK ✅";
        document.getElementById('webserialBadge').className = "badge badge-success";
    } else {
        document.getElementById('webserialBadge').textContent = "WebSerial: NO Soportado ❌";
        document.getElementById('webserialBadge').className = "badge badge-error";
    }
    if (window.isSecureContext) {
        document.getElementById('secureBadge').textContent = "Entorno: Seguro ✅";
        document.getElementById('secureBadge').className = "badge badge-success";
    } else {
        document.getElementById('secureBadge').textContent = "Entorno: NO Seguro ⚠️";
        document.getElementById('secureBadge').className = "badge badge-warning";
    }
});

let port = null, transport = null, loader = null;
const selectedFiles = {};

// HERRAMIENTAS DE PARSEO
function parseOffset(offset) {
    if (typeof offset === 'number') return offset;
    return parseInt(offset.trim(), 16);
}

// LIMPIAR ARCHIVOS
window.clearFile = function(type) {
    const input = document.querySelector(`input[data-type="${type}"]`);
    if(input) input.value = ''; 
    if(selectedFiles[type]) {
        log(`Archivo quitado: ${selectedFiles[type].file.name}`);
        delete selectedFiles[type]; 
    }
    const sizeEl = document.getElementById('size-' + type);
    if(sizeEl) sizeEl.textContent = '';
    updateFlashButton();
}

function updateFlashButton(){
    const hasFiles = Object.keys(selectedFiles).length > 0;
    document.getElementById('flashBtn').disabled = !(loader && hasFiles);
}

document.querySelectorAll('.binFile').forEach(input => {
    input.addEventListener('change', event => {
        const file = event.target.files[0];
        if(!file) return;
        const row = event.target.closest('.partition');
        const offset = row.dataset.offset;
        const type = event.target.dataset.type;
        selectedFiles[type] = { file, offset };
        
        const sizeEl = document.getElementById('size-' + type);
        if(sizeEl) sizeEl.textContent = (file.size / 1024).toFixed(1) + ' KB';
        
        log(`Cargado: ${file.name}`);
        updateFlashButton();
    });
});

// BOTONES Y LÓGICA
const connectBtn = document.getElementById('connectBtn');
const disconnectBtn = document.getElementById('disconnectBtn');
const flashBtn = document.getElementById('flashBtn');

async function connectESP32(){
    try {
        port = await navigator.serial.requestPort();
        transport = new Transport(port);
        loader = new ESPLoader({
            transport: transport,
            baudrate: parseInt(document.getElementById('baudRate').value),
            terminal: { clean(){}, writeLine(data){ log(data); }, write(data){ log(data); } }
        });
        await loader.main();
        log('Conectado correctamente', 'success');
        document.getElementById('status').textContent = 'Conectado';
        connectBtn.classList.add('hidden');
        disconnectBtn.classList.remove('hidden');
        updateFlashButton();
    } catch(e) { log(e.message, 'error'); }
}

async function flashESP32() {
    if (!loader) return;
    try {
        const files = Object.keys(selectedFiles)
            .map(k => selectedFiles[k])
            .sort((a,b) => parseOffset(a.offset) - parseOffset(b.offset));
        
        document.getElementById('progressContainer').classList.remove('hidden');
        
        for (let item of files) {
            const addr = parseOffset(item.offset);
            const buffer = await item.file.arrayBuffer();
            log(`Escribiendo ${item.file.name} en 0x${addr.toString(16)}...`);
            
            await loader.writeFlash({
                fileArray: [{ data: new Uint8Array(buffer), address: addr }],
                flashSize: 'keep',
                compress: true,
                reportProgress: (idx, written, total) => {
                    const p = Math.floor((written/total)*100);
                    document.getElementById('progressFill').style.width = p+'%';
                    document.getElementById('progressFill').textContent = p+'%';
                    document.getElementById('progressLabel').textContent = `${item.file.name}: ${p}%`;
                }
            });
            log(`✔ ${item.file.name} grabado.`);
        }
        log('¡GRABACIÓN COMPLETADA!', 'success');
        
        // Reset
        await transport.setDTR(false); 
        await new Promise(r => setTimeout(r, 200)); 
        await transport.setDTR(true);
    } catch(e) { 
        log(`ERROR: ${e.message}`, 'error'); 
    }
}

connectBtn.onclick = connectESP32;
disconnectBtn.onclick = () => { location.reload(); };
document.getElementById('eraseBtn').onclick = async () => {
    if(!loader) return;
    if(confirm('¿Borrar todo el chip?')) { 
        log('Borrando flash...');
        await loader.eraseFlash(); 
        log('Chip borrado', 'success'); 
    }
};
flashBtn.onclick = flashESP32;

log('ESP32 Web Flash Tool listo');
</script>
</body>
</html>

