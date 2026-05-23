<?php
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    
    $url = $_GET['url'] ?? 'http://localhost/dump1090/aircraft.json';
    $rx_lat = floatval($_GET['rx_lat'] ?? 40.324938);
    $rx_lon = floatval($_GET['rx_lon'] ?? -3.868996);
    
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) { echo json_encode(['error' => 'No se pudo conectar al receptor']); exit; }
    
    $data = json_decode($json, true);
    if (!$data || !isset($data['aircraft'])) { echo json_encode(['error' => 'JSON inválido o vacío']); exit; }

    $list = array_map(function($a) use ($rx_lat, $rx_lon) {
        $lat = $a['lat'] ?? null;
        $lon = $a['lon'] ?? null;
        
        $dst = $a['dst'] ?? null;
        $dir = $a['dir'] ?? null;
        if (!$dst && $lat && $lon) {
            $dlat = deg2rad($lat - $rx_lat);
            $dlon = deg2rad($lon - $rx_lon);
            $hav = sin($dlat/2)**2 + cos(deg2rad($rx_lat)) * cos(deg2rad($lat)) * sin($dlon/2)**2;
            $dst = 6371000 * 2 * atan2(sqrt($hav), sqrt(1-$hav));
        }
        if (!$dir && $lat && $lon) {
            $y = sin($dlon) * cos(deg2rad($lat));
            $x = cos(deg2rad($rx_lat)) * sin(deg2rad($lat)) - sin(deg2rad($rx_lat)) * cos(deg2rad($lat)) * cos($dlon);
            $dir = fmod(rad2deg(atan2($y, $x)) + 360, 360);
        }

        return [
            'hex'       => strtoupper($a['hex'] ?? ''),
            'flight'    => trim($a['flight'] ?? ''),
            'reg'       => $a['r'] ?? '',
            'type'      => $a['t'] ?? '',
            'cat'       => $a['category'] ?? '',
            'alt'       => $a['alt_baro'] ?? $a['alt_geom'] ?? null,
            'on_ground' => ($a['alt_baro'] === 'ground' || $a['altitude'] === 'ground'),
            'gs'        => $a['gs'] ?? null,
            'track'     => $a['track'] ?? null,
            'sqk'       => $a['squawk'] ?? '',
            'emerg'     => $a['emergency'] ?? '',
            'rssi'      => round($a['rssi'] ?? -99, 1),
            'msgs'      => (int)($a['messages'] ?? 0),
            'seen'      => round($a['seen'] ?? 999, 1),
            'dst_m'     => $dst !== null ? round($dst) : null,
            'dir'       => $dir !== null ? round($dir) : null,
            'lat'       => $lat,
            'lon'       => $lon,
            'mlat'      => !empty($a['mlat']),
            'tisb'      => !empty($a['tisb'])
        ];
    }, $data['aircraft']);

    usort($list, fn($a,$b) => $a['seen'] <=> $b['seen']);
    
    $rssiList = array_column($list, 'rssi');
    $histogram = [];
    for($i=-50; $i<=-10; $i+=5) {
        $histogram[$i] = count(array_filter($rssiList, fn($r) => $r >= $i && $r < $i+5));
    }
    
    echo json_encode([
        'now'        => date('H:i:s'),
        'total'      => count($list),
        'with_pos'   => count(array_filter($list, fn($a)=>$a['lat']!==null)),
        'with_alt'   => count(array_filter($list, fn($a)=>$a['alt']!==null)),
        'aircraft'   => array_slice($list, 0, 300),
        'histogram'  => $histogram
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>✈ ADS-B Ultimate Monitor</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
:root{--bg:#0a0e14;--surface:#111720;--border:#1e2d3d;--green:#00ff9f;--red:#ff4560;--cyan:#00d4ff;--amber:#ffb300;--text:#a8b9cc;--text-dim:#4a5568;--purple:#b794f4;--font-mono:'Share Tech Mono',monospace;--font-ui:system-ui,-apple-system,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}body{background:var(--bg);color:var(--text);font-family:var(--font-ui);font-size:.85rem;height:100vh;display:flex;flex-direction:column;overflow:hidden}
.config{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;padding:.6rem 1rem;background:var(--surface);border-bottom:1px solid var(--border)}
.config label{font-size:.7rem;color:var(--text-dim);display:flex;align-items:center;gap:.3rem}
.config input{background:#0d1520;border:1px solid var(--border);color:var(--cyan);padding:.3rem .5rem;border-radius:4px;font-family:var(--font-mono);min-width:280px}
.config input:focus{outline:none;border-color:var(--cyan)}
.btn{background:transparent;border:1px solid var(--cyan);color:var(--cyan);padding:.3rem .7rem;border-radius:4px;cursor:pointer;font-family:var(--font-mono);font-size:.7rem;text-transform:uppercase;transition:.2s}
.btn:hover{background:rgba(0,212,255,.15)}.btn-save{border-color:var(--green);color:var(--green);background:rgba(0,255,159,.1)}
.btn-alert{border-color:var(--red);color:var(--red);background:rgba(255,69,96,.1)}.btn-map{border-color:var(--purple);color:var(--purple)}
.btn-csv{border-color:var(--amber);color:var(--amber)}.btn-unit{border-color:var(--text-dim);color:var(--text-dim)}
.btn-unit.active{border-color:var(--cyan);color:var(--cyan);background:rgba(0,212,255,.15);font-weight:700}
.btn-stats{border-color:var(--amber);color:var(--amber)}
.header{display:flex;justify-content:space-between;padding:.6rem 1rem;background:rgba(0,0,0,.25);border-bottom:1px solid var(--border);font-size:.8rem}
.title{color:var(--cyan);font-weight:700;letter-spacing:.05em}.status{display:flex;gap:1rem;align-items:center}
.dot{width:8px;height:8px;border-radius:50%;background:var(--text-dim)}.dot.on{background:var(--green);box-shadow:0 0 8px var(--green);animation:pulse 2s infinite}
.dot.err{background:var(--red)}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.toolbar{display:flex;gap:.6rem;padding:.5rem 1rem;background:var(--surface);border-bottom:1px solid var(--border);flex-wrap:wrap;align-items:center}
.toolbar input{flex:1;min-width:250px;background:#0d1520;border:1px solid var(--border);color:var(--text);padding:.35rem .6rem;border-radius:4px;font-family:var(--font-mono)}
.toolbar input:focus{outline:none;border-color:var(--cyan)}
.stats-bar{display:flex;gap:1rem;padding:.3rem 1rem;background:rgba(0,0,0,.2);font-size:.75rem;color:var(--text-dim);border-bottom:1px solid var(--border);flex-wrap:wrap}
.stats-bar b{color:var(--amber)}
.tabs{display:flex;gap:.4rem;padding:.4rem 1rem;background:var(--surface);border-bottom:1px solid var(--border);align-items:center}
.tab{padding:.3rem .8rem;border-radius:4px 4px 0 0;background:transparent;border:none;color:var(--text-dim);cursor:pointer;font-family:var(--font-mono);font-size:.75rem;text-transform:uppercase;transition:.2s}
.tab:hover{color:var(--cyan)}.tab.active{background:var(--bg);color:var(--cyan);border-bottom:2px solid var(--cyan)}
.tab-pane{display:none;flex:1;flex-direction:column;overflow:hidden}.tab-pane.active{display:flex}
#mapPane{background:#000}#map{flex:1;background:#000}
.wrap{flex:1;overflow-y:auto;padding:.5rem}
table{width:100%;border-collapse:collapse;font-size:.78rem;font-family:var(--font-mono)}
thead{position:sticky;top:0;background:#0d1520;z-index:2}th{padding:.5rem .6rem;border-bottom:2px solid var(--border);color:var(--cyan);font-weight:700;font-size:.7rem;text-align:left;cursor:pointer;user-select:none;white-space:nowrap}
th:hover{color:var(--green)}th .arrow{font-size:.6rem;margin-left:4px;opacity:.5}
td{padding:.45rem .6rem;border-bottom:1px solid rgba(30,45,61,.4);vertical-align:middle}tbody tr:hover{background:rgba(0,212,255,.04)}tbody tr:active{background:rgba(0,255,159,.06)}tbody tr.highlight{background:rgba(255,179,0,0.15)}
.hex{color:var(--amber);font-weight:700;letter-spacing:.05em;cursor:pointer}.hex:hover{color:var(--cyan)}
.flight{color:var(--cyan);font-weight:500;cursor:pointer}.flight:hover{color:var(--green)}
.reg{color:var(--text-dim);font-size:.7rem}
.alt{color:var(--amber);font-weight:600}
.alt.ground{color:var(--green);font-size:.7rem}
.speed{color:var(--green)}
.track{display:inline-block;transform:rotate(var(--deg,0deg));font-size:.85rem}
.squawk{color:var(--purple);font-weight:600;letter-spacing:.1em}
.squawk.emerg{color:var(--red)}
.rssi-bar{height:6px;background:#1a2535;border-radius:3px;overflow:hidden;width:60px;display:inline-block;vertical-align:middle}
.rssi-fill{height:100%;background:var(--green);border-radius:3px;transition:width .3s}
.rssi-fill.med{background:var(--amber)}.rssi-fill.low{background:var(--red)}
.rssi-txt{font-size:.7rem;color:var(--text-dim);margin-left:6px}
.dist{color:var(--amber);font-weight:600}
.dir{color:var(--text-dim);font-size:.7rem}
.badge{display:inline-block;padding:.1rem .35rem;border-radius:3px;font-size:.65rem;font-weight:700;text-transform:uppercase;margin-left:4px}
.badge.emerg{background:rgba(255,69,96,.2);color:var(--red)}
.badge.mlat{background:rgba(0,212,255,.2);color:var(--cyan)}
.badge.tisb{background:rgba(255,179,0,.2);color:var(--amber)}
.badge.stale{background:rgba(74,85,104,.2);color:var(--text-dim)}
.seen{color:var(--text-dim);font-size:.7rem}
.seen.fresh{color:var(--green)}
.empty{text-align:center;padding:4rem;color:var(--text-dim);font-style:italic}
.footer{padding:.4rem 1rem;background:var(--surface);border-top:1px solid var(--border);font-size:.7rem;color:var(--text-dim);display:flex;justify-content:space-between}
.alert-indicator{position:fixed;top:1rem;right:1rem;background:rgba(255,69,96,.9);color:#fff;padding:.8rem 1.2rem;border-radius:6px;font-weight:700;z-index:9999;display:none}
.alert-indicator.show{display:block}
.leaflet-popup-content-wrapper{background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:4px}
.leaflet-popup-content{margin:8px;font-family:var(--font-mono);font-size:.8rem}
.leaflet-popup-tip{background:var(--surface)}

/* 🔹 MARCADORES SIMPLES Y COMPATIBLES */
.aircraft-marker{background:transparent;pointer-events:auto}
.ac-icon{font-size:16px;line-height:1;display:inline-block;user-select:none;text-shadow:0 0 3px #000,0 0 6px #000}
.ac-label{font-size:10px;color:#fff;text-shadow:0 0 3px #000,0 0 5px #000;white-space:nowrap;font-weight:600;line-height:1;user-select:none;pointer-events:none;background:rgba(10,14,20,0.5);padding:1px 3px;border-radius:2px;margin-left:3px}

/* 🗺️ Controles de capa */
.map-controls{display:flex;gap:.4rem;margin-left:auto;padding:0 1rem 0.4rem;background:var(--surface);border-top:1px solid var(--border)}
.map-btn{background:#0d1520;border:1px solid var(--border);color:var(--text-dim);padding:.25rem .6rem;border-radius:3px;cursor:pointer;font-size:.7rem;font-family:var(--font-mono)}
.map-btn:hover{color:var(--cyan);border-color:var(--cyan)}
.map-btn.active{background:rgba(0,212,255,.15);color:var(--cyan);border-color:var(--cyan);font-weight:700}

/* 📊 Panel de Estadísticas */
.stats-panel{position:fixed;bottom:2rem;right:2rem;width:340px;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.6);z-index:1000;opacity:0;transform:translateY(10px);transition:all .3s ease;pointer-events:none}
.stats-panel.visible{opacity:1;transform:translateY(0);pointer-events:auto}
.stats-header{display:flex;justify-content:space-between;align-items:center;padding:.6rem .8rem;border-bottom:1px solid var(--border);color:var(--cyan);font-weight:700;font-family:var(--font-mono)}
.btn-close{background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:1rem;padding:0 4px}
.btn-close:hover{color:var(--red)}
.hist-panel{display:flex;gap:2px;padding:.6rem .8rem;height:60px;align-items:flex-end}
.hist-bar{flex:1;background:var(--green);border-radius:2px 2px 0 0;transition:height .3s;min-height:2px;position:relative}
.hist-bar.med{background:var(--amber)}.hist-bar.low{background:var(--red)}
.hist-bar:hover::after{content:attr(data-count);position:absolute;top:-18px;left:50%;transform:translateX(-50%);background:#0d1520;padding:2px 4px;border-radius:2px;font-size:.65rem;border:1px solid var(--border);white-space:nowrap}
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;padding:.6rem .8rem}
.stat-box{background:rgba(0,0,0,.2);padding:.5rem;border-radius:4px;text-align:center}
.stat-val{font-size:1rem;font-weight:700;color:var(--amber);font-family:var(--font-mono)}
.stat-label{font-size:.65rem;color:var(--text-dim);text-transform:uppercase;margin-top:2px}

/* 🔹 BANDERAS COMO IMÁGENES - Solución definitiva para Chrome/Win11 */
.flag-img {
    width: 20px;
    height: 14px;
    object-fit: cover;
    border-radius: 2px;
    vertical-align: middle;
    display: inline-block;
    background: #2a3545;
    border: 1px solid rgba(255,255,255,0.1);
    image-rendering: -webkit-optimize-contrast;
    image-rendering: crisp-edges;
}
.flag-img.small { width: 16px; height: 12px; }
.flag-img.large { width: 24px; height: 18px; }
</style>
</head>
<body>
<div class="config">
    <label>🌐 JSON URL: <input type="text" id="cfgUrl" value="http://localhost/dump1090/aircraft.json"></label>
    <label>📍 Lat RX: <input type="number" id="cfgLat" value="40.324938" step="0.0001" style="min-width:100px"></label>
    <label>📍 Lon RX: <input type="number" id="cfgLon" value="-3.868996" step="0.0001" style="min-width:100px"></label>
    <span style="border-right:1px solid var(--border);height:20px"></span>
    <button class="btn btn-unit active" id="btnMetric" onclick="setUnits('metric')">🇪🇺 Métrico</button>
    <button class="btn btn-unit" id="btnImperial" onclick="setUnits('imperial')">🇺🇸 Imperial</button>
    <span style="border-right:1px solid var(--border);height:20px"></span>
    <button class="btn btn-save" onclick="saveCfg()">💾 Aplicar</button>
    <button class="btn btn-alert" id="btnAlert" onclick="toggleAlerts()">🔔 Alertas: OFF</button>
    <button class="btn btn-map" onclick="showTab('map')">🗺 Mapa</button>
    <button class="btn btn-csv" onclick="exportCSV()">📥 CSV</button>
        <button class="btn btn-alert" onclick="window.location.href='mmdvm.php'">✖ Cerrar</button>
    <!-- <button class="btn btn-stats" onclick="toggleStats()">📊 Estadísticas</button> -->
</div>
<div class="header">
    <div class="title">✈ ADS-B Ultimate Monitor <span style="font-size:.7rem;color:var(--text-dim);font-weight:400" id="unitLabel">(m / km/h)</span></div>
    <div class="status">
        <span><span class="dot" id="dot"></span><span id="statusTxt">Conectando…</span></span>
        <span>✈ Activos: <b id="acCount">0</b></span>
        <span>📍 Con Pos: <b id="posCount">0</b></span>
        <span>📏 Con Alt: <b id="altCount">0</b></span>
    </div>
</div>
<div class="stats-bar">
    <span>🕐 Actualizado: <b id="lastUpdate">—</b></span>
    <span>📡 Fuente: <b id="srcInfo">localhost</b></span>
    <span style="color:var(--text-dim);font-size:.65rem;margin-left:auto">ⓘ ADS-B Monitor By REM-ESP @ ADER</span>
</div>
<div class="tabs">
    <button class="tab active" onclick="showTab('table')">📋 Tabla</button>
    <button class="tab" onclick="showTab('map')">🗺 Mapa</button>
    <div class="map-controls" id="mapControls" style="display:none">
        <button class="map-btn active" onclick="setMapLayer('day')">☀️ Día</button>
        <button class="map-btn" onclick="setMapLayer('night')">🌙 Noche</button>
        <button class="map-btn" onclick="setMapLayer('sat')">🛰 Satélite</button>
    </div>
</div>
<div class="toolbar">
    <input type="text" id="filter" placeholder="🔍 Filtrar por ICAO, Vuelo, Registro, Squawk o Tipo…" oninput="applyFilter()">
</div>
<div class="tab-pane active" id="tablePane">
    <div class="wrap">
        <table>
            <thead><tr>
                <th onclick="sortTable(0)">ICAO <span class="arrow">▼</span></th>
                <th>País</th>
                <th onclick="sortTable(2)">Vuelo / Reg</th>
                <th onclick="sortTable(3)">Altitud</th>
                <th onclick="sortTable(4)">Velocidad</th>
                <th onclick="sortTable(5)">Rumbo</th>
                <th onclick="sortTable(6)">Squawk</th>
                <th onclick="sortTable(7)">Distancia</th>
                <th onclick="sortTable(8)">RSSI</th>
                <th onclick="sortTable(9)">Visto</th>
                <th onclick="sortTable(10)">Estado</th>
            </tr></thead>
            <tbody id="tbody"><tr><td colspan="11" class="empty">Esperando datos de dump1090-fa…</td></tr></tbody>
        </table>
    </div>
</div>
<div class="tab-pane" id="mapPane">
    <div id="map"></div>
</div>
<div class="footer">
    <span>🛰️ Ultimate Monitor | dump1090-fa | CSV Export | Sound Alerts | Live Map</span>
    <span>🔄 Auto-refresh: 2.0s</span>
</div>
<div class="alert-indicator" id="alertBox">🚨 EMERGENCIA DETECTADA</div>

<div class="stats-panel" id="statsPanel">
    <div class="stats-header">📊 Análisis de Señal <button class="btn-close" onclick="toggleStats()">✕</button></div>
    <div class="hist-panel" id="histogramPanel"></div>
    <div class="stats-grid">
        <div class="stat-box"><div class="stat-val" id="statAvgRssi">—</div><div class="stat-label">RSSI Promedio</div></div>
        <div class="stat-box"><div class="stat-val" id="statMaxRssi">—</div><div class="stat-label">RSSI Máximo</div></div>
        <div class="stat-box"><div class="stat-val" id="statMinRssi">—</div><div class="stat-label">RSSI Mínimo</div></div>
        <div class="stat-box"><div class="stat-val" id="statAvgDst">—</div><div class="stat-label">Dist. Media</div></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const CFG_KEY = 'adsb_ultimate_cfg_v9';
let cfg = { url:'http://localhost/dump1090/aircraft.json', lat:40.324938, lon:-3.868996, units:'metric', alerts:false, sort:{col:9,asc:true}, filter:'' };
let rawData = [], map = null, markers = {}, sortCol = 9, sortAsc = true, audioCtx = null, lastEmergencies = new Set(), currentLayer = 'day';

function isCritical(a){
    const badSq = ['7500','7600','7700'];
    const badEm = ['life_guard','waypoint','turning','no_response','no_acknowledge'];
    return badSq.includes(a.sqk) || (a.emerg && badEm.includes(String(a.emerg).toLowerCase()));
}

// 🏳️ MAPEO DE PREFIJOS ICAO → CÓDIGO ISO 3166-1 alpha-2 (para banderas como imagen)
const FLAG_CODE = {
  '34':'ES','35':'ES','30':'IT','31':'IT','32':'IT','33':'IT','38':'FR','39':'FR','3A':'FR','3B':'FR',
  '3C':'DE','3D':'DE','3E':'DE','3F':'DE','40':'GB','41':'GB','42':'GR','43':'GB','44':'BE','45':'DK','46':'FI','47':'HU',
  '48':'NL','49':'PT','4A':'RO','4B':'CH','4C':'RS','4D':'LU','4E':'NO','4F':'CZ','50':'HU','51':'PL',
  '52':'LV','53':'LT','54':'SK','55':'IE','56':'PL','57':'PL','58':'SI','59':'BG','5A':'SE','5B':'CY','5C':'MK','5D':'AL','5E':'HR',
  'A0':'US','A1':'US','A2':'US','A3':'US','A4':'US','A5':'US','A6':'US','A7':'US','A8':'US','A9':'US','AA':'US','AB':'US','AC':'US','AD':'US','AE':'US','AF':'US',
  'C0':'CA','C1':'CA','C2':'CA','C3':'CA','C4':'CA','C5':'CA','C6':'CA','C7':'CA',
  '04':'RU','05':'RU','06':'RU','07':'RU','08':'RU','09':'RU','0A':'RU','0B':'RU','0C':'RU','0D':'RU','0E':'RU','0F':'RU',
  '14':'UA','15':'UA','16':'UA','17':'UA','70':'JP','71':'JP','72':'JP','73':'JP','74':'JP','75':'JP','76':'JP','77':'JP',
  '78':'CN','79':'CN','7A':'CN','7B':'CN','80':'IN','81':'IN','82':'IN','83':'IN','84':'IN','85':'IN','86':'IN','87':'IN',
  'E0':'AR','E1':'AR','E2':'BR','E3':'BR','E4':'BR','E5':'BR','E6':'BR','E7':'BR','E8':'CL','E9':'CO','EA':'MX','EB':'VE','EC':'PE','ED':'UY','EE':'BO','EF':'PY'
};

// 🔹 FUNCIÓN CLAVE: Devuelve bandera como IMAGEN (no emoji) para compatibilidad total
function getFlagImg(hex, sizeClass = '') {
    if (!hex || hex.length < 2) return '<span class="flag-img ' + sizeClass + '" style="background:#334">🌍</span>';
    const prefix = hex.substring(0,2).toUpperCase();
    const code = FLAG_CODE[prefix];
    if (!code) return '<span class="flag-img ' + sizeClass + '" style="background:#334">🌍</span>';
    // flagcdn.com: w20 = 20px ancho, formato PNG, CDN global, sin API key
    return `<img src="https://flagcdn.com/w20/${code.toLowerCase()}.png" 
                 alt="${code}" 
                 class="flag-img ${sizeClass}" 
                 loading="lazy" 
                 onerror="this.outerHTML='<span class=\\'flag-img ${sizeClass}\\' style=\\'background:#334\\'>🌍</span>'">`;
}

// 🔹 Función auxiliar para CSV (texto plano, sin HTML)
function getFlagCode(hex) {
    if (!hex || hex.length < 2) return 'XX';
    const prefix = hex.substring(0,2).toUpperCase();
    return FLAG_CODE[prefix] || 'XX';
}

const baseLayers = {
    day: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }),
    night: L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { attribution: '© CartoDB', maxZoom: 19 }),
    sat: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: '© Esri', maxZoom: 19 })
};

function setMapLayer(type) {
    if(!map) return;
    map.removeLayer(baseLayers[currentLayer]);
    map.addLayer(baseLayers[type]);
    currentLayer = type;
    document.querySelectorAll('.map-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.map-btn[onclick="setMapLayer('${type}')"]`).classList.add('active');
}

function getAircraftIconData(a) {
    const t = (a.type || '').toUpperCase();
    const c = (a.cat || '').toUpperCase();
    
    if (/^H|HELICOPTER|ROTOR|EC1|EC3|EC4|EC5|AS3|AS5|AW1|AW0|R44|R22|BELL|SIK|S76|S92|UH6|AH6|CH4|MI8|MI1|KA5|KA2|NH9|TIG|EC6|EC7/.test(t)) {
        return { emoji: '🚁', scale: 0.8, rotationOffset: 0 };
    }
    if (c === 'A7') return { emoji: '🚁', scale: 0.8, rotationOffset: 0 };
    
    if (/GLID|ULAC|SF25|ASK|DG|LS|SZD|VENT|DISC|ANT|NIM|JAN|PEG|K8|K13|K21|ARC|TWIN|MONI|SWIF|SALI|FALKE|TOUR|PA25|AG/.test(t)) {
        return { emoji: '🪂', scale: 0.6, rotationOffset: 0 };
    }
    if (c === 'B1' || c === 'B2' || c === 'B3' || c === 'B4' || c === 'B5' || c === 'B6') return { emoji: '🪂', scale: 0.6, rotationOffset: 0 };
    
    if (/(A38|B74|B77|B78|A33|A34|A35|B76|MD11|C5M|A400|IL7|AN1|VC1|C13|B75|A30|A31)/.test(t)) {
        return { emoji: '✈️', scale: 1.3, rotationOffset: 0 };
    }
    if (c === 'A4' || c === 'A5') return { emoji: '✈️', scale: 1.3, rotationOffset: 0 };
    
    if (/(C17|PA2|BE5|PC1|DA4|C20|LJ|PH|CL6|TBM|C15|C18|C42|C68|B4|C90|BEECH|CESSNA|PIPER|SR2|CIRRUS|DIAMOND)/.test(t)) {
        return { emoji: '✈️', scale: 0.8, rotationOffset: 0 };
    }
    if (c === 'A1' || c === 'A2') return { emoji: '✈️', scale: 0.8, rotationOffset: 0 };
    
    return { emoji: '✈️', scale: 1.0, rotationOffset: 0 };
}

function getMarkerIcon(a, zoom) {
    const z = zoom || 10;
    const iconData = getAircraftIconData(a);
    
    const zoomFactor = Math.max(0.4, Math.min(1.8, 0.5 + (z - 7) * 0.12));
    const baseSize = 14;
    const finalSize = Math.round(baseSize * iconData.scale * zoomFactor);
    
    const color = isCritical(a) ? '#FF3B30' : '#FFFFFF';
    const rotation = (a.track || 0) + iconData.rotationOffset;
    
    // 🔹 Bandera como imagen en marcador del mapa
    const flagImg = getFlagImg(a.hex, 'small');
    const label = a.flight || a.hex;
    
    const iconWidth = finalSize + 60;
    const iconHeight = finalSize + 12;
    
    return L.divIcon({
        className: 'aircraft-marker',
        html: `<span class="ac-icon" style="font-size:${finalSize}px;color:${color};transform:rotate(${rotation}deg)">${iconData.emoji}</span><span class="ac-label">${flagImg} ${label}</span>`,
        iconSize: [iconWidth, iconHeight],
        iconAnchor: [finalSize/2 + 30, finalSize/2 + 6],
        popupAnchor: [finalSize/2 + 30, -finalSize/2 - 8]
    });
}

const receiverIcon = L.divIcon({
    className: 'aircraft-marker',
    html: `<span style="font-size:14px;color:#00FF00;text-shadow:0 0 3px #000,0 0 5px #000;display:inline-block;line-height:1;user-select:none;">📡</span>`,
    iconSize: [14, 14],
    iconAnchor: [7, 7]
});

function loadCfg(){
    try{ const s=localStorage.getItem(CFG_KEY); if(s) Object.assign(cfg,JSON.parse(s)); }catch(e){}
    document.getElementById('cfgUrl').value=cfg.url;
    document.getElementById('cfgLat').value=cfg.lat;
    document.getElementById('cfgLon').value=cfg.lon;
    document.getElementById('filter').value=cfg.filter;
    setUnits(cfg.units, true);
    try{ document.getElementById('srcInfo').textContent=new URL(cfg.url).hostname; }catch(e){}
}
function saveCfg(){
    cfg.url=document.getElementById('cfgUrl').value.trim();
    cfg.lat=parseFloat(document.getElementById('cfgLat').value)||40.324938;
    cfg.lon=parseFloat(document.getElementById('cfgLon').value)||-3.868996;
    cfg.filter=document.getElementById('filter').value;
    localStorage.setItem(CFG_KEY,JSON.stringify(cfg));
    try{ document.getElementById('srcInfo').textContent=new URL(cfg.url).hostname; }catch(e){}
    poll(true);
}
function setUnits(sys, skipRender=false){
    cfg.units = sys;
    localStorage.setItem(CFG_KEY, JSON.stringify(cfg));
    document.getElementById('btnMetric').className = `btn btn-unit ${sys==='metric'?'active':''}`;
    document.getElementById('btnImperial').className = `btn btn-unit ${sys==='imperial'?'active':''}`;
    document.getElementById('unitLabel').textContent = sys==='metric' ? '(m / km/h / km)' : '(ft / kt / nm)';
    if(!skipRender){ renderTable(); if(rawData.length) updateMap(rawData); }
}
function toggleAlerts(){
    cfg.alerts = !cfg.alerts;
    localStorage.setItem(CFG_KEY, JSON.stringify(cfg));
    document.getElementById('btnAlert').textContent = `🔔 Alertas: ${cfg.alerts?'ON':'OFF'}`;
    document.getElementById('btnAlert').className = `btn ${cfg.alerts?'btn-alert':''}`;
    if(cfg.alerts && !audioCtx) initAudio();
}
function toggleStats(){ document.getElementById('statsPanel').classList.toggle('visible'); }
function initAudio(){ try{ audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }catch(e){} }
function playAlert(type){
    if(!cfg.alerts || !audioCtx) return;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.frequency.value = 880; osc.type = 'sine';
    gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
    osc.start(); osc.stop(audioCtx.currentTime + 0.5);
    setTimeout(()=>playAlert('emergency'), 600);
}
function showTab(tab){
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.getElementById(tab+'Pane').classList.add('active');
    event.target.classList.add('active');
    document.getElementById('mapControls').style.display = tab==='map' ? 'flex' : 'none';
    if(tab==='map' && map) setTimeout(()=>map.invalidateSize(), 100);
}

function focusOnAircraft(hex) {
    const a = rawData.find(x => x.hex === hex);
    if (!a || !a.lat || !a.lon || !map) return;
    
    if (!document.getElementById('mapPane').classList.contains('active')) {
        showTab('map');
    }
    
    map.flyTo([a.lat, a.lon], Math.max(9, map.getZoom()), { duration: 1.2 });
    
    document.querySelectorAll('#tbody tr').forEach(tr => tr.classList.remove('highlight'));
    const row = document.querySelector(`#tbody tr[data-search*="${hex}"]`);
    if (row) {
        row.classList.add('highlight');
        setTimeout(() => row.classList.remove('highlight'), 2500);
    }
    
    setTimeout(() => {
        if (markers[hex] && map.hasLayer(markers[hex])) {
            markers[hex].openPopup();
        }
    }, 1300);
}

function initMap(){
    if(map) return;
    map = L.map('map', { zoomControl: true, attributionControl: true }).setView([cfg.lat, cfg.lon], 8);
    baseLayers.day.addTo(map);
    L.marker([cfg.lat, cfg.lon], { icon: receiverIcon }).addTo(map)
     .bindPopup('<strong>📡 Receptor</strong><br>Lat: '+cfg.lat.toFixed(6)+'<br>Lon: '+cfg.lon.toFixed(6));
    map.on('zoomend', () => { Object.keys(markers).forEach(hex => { const a = rawData.find(x => x.hex === hex); if(a) markers[hex].setIcon(getMarkerIcon(a, map.getZoom())); }); });
}

function updateMap(aircraft){
    initMap();
    const currentHex = new Set();
    aircraft.forEach(a=>{
        if(!a.lat || !a.lon) return;
        currentHex.add(a.hex);
        if(markers[a.hex]){
            markers[a.hex].setLatLng([a.lat, a.lon]);
            markers[a.hex].setPopupContent(getPopupContent(a));
            markers[a.hex].setIcon(getMarkerIcon(a, map.getZoom()));
        }else{
            const m = L.marker([a.lat, a.lon], { icon: getMarkerIcon(a, map.getZoom()) }).addTo(map);
            m.bindPopup(getPopupContent(a));
            markers[a.hex] = m;
        }
    });
    Object.keys(markers).forEach(hex=>{ if(!currentHex.has(hex)){ map.removeLayer(markers[hex]); delete markers[hex]; } });
}

function getPopupContent(a){
    // 🔹 Bandera como imagen en popup del mapa
    const flagImg = getFlagImg(a.hex, 'small');
    return `<strong>${flagImg} ${a.flight||a.hex}</strong><br>`+
           `ICAO: ${a.hex}<br>`+(a.reg?`Reg: ${a.reg}<br>`:'')+
           `Alt: ${formatAlt(a.alt)}<br>`+`Vel: ${formatSpeed(a.gs)}<br>`+
           (a.track?`Rumbo: ${Math.round(a.track)}°<br>`:'')+
           (a.sqk?`Squawk: ${a.sqk}<br>`:'')+
           `Dist: ${formatDist(a.dst_m)}<br>`+
           `<small>RSSI: ${a.rssi} dB | Mensajes: ${a.msgs}</small>`;
}

function renderHistogram(hist){
    const container = document.getElementById('histogramPanel'); container.innerHTML = '';
    const max = Math.max(...Object.values(hist), 1);
    for(let rssi=-50; rssi<=-10; rssi+=5){
        const count = hist[rssi] || 0;
        const pct = (count/max)*100;
        const bar = document.createElement('div');
        bar.className = 'hist-bar '+(pct>60?'':pct>30?'med':'low');
        bar.style.height = Math.max(pct, 5)+'%';
        bar.setAttribute('data-count', count);
        bar.title = `${rssi} a ${rssi+5} dBm: ${count} aviones`;
        container.appendChild(bar);
    }
}

function updateStats(data){
    const rssiList = data.aircraft.map(a=>a.rssi).filter(v=>v>-99);
    const dstList = data.aircraft.map(a=>a.dst_m).filter(v=>v!==null);
    if(rssiList.length){
        document.getElementById('statAvgRssi').textContent = (rssiList.reduce((a,b)=>a+b,0)/rssiList.length).toFixed(1)+' dB';
        document.getElementById('statMaxRssi').textContent = Math.max(...rssiList).toFixed(1)+' dB';
        document.getElementById('statMinRssi').textContent = Math.min(...rssiList).toFixed(1)+' dB';
    }else{ document.getElementById('statAvgRssi').textContent='—'; }
    if(dstList.length){
        const avg = dstList.reduce((a,b)=>a+b,0)/dstList.length;
        document.getElementById('statAvgDst').textContent = (avg/1000).toFixed(1)+' km';
    }else{ document.getElementById('statAvgDst').textContent='—'; }
}

function formatAlt(ft){ if(ft===null) return '—'; return cfg.units==='metric' ? Math.round(ft*0.3048).toLocaleString()+' m' : ft.toLocaleString()+' ft'; }
function formatSpeed(kt){ if(kt===null) return '—'; return cfg.units==='metric' ? Math.round(kt*1.852)+' km/h' : Math.round(kt)+' kt'; }
function formatDist(m){ if(m===null) return '—'; return cfg.units==='metric' ? (m/1000).toFixed(1)+' km' : (m/1852).toFixed(1)+' nm'; }
function hdgArrow(deg){ return deg===null?'—':`<span class="track" style="--deg:${deg}deg">▲</span> ${Math.round(deg)}°`; }
function rssiBar(val){
    const pct = Math.max(0, Math.min(100, ((val+50)/35)*100));
    const cls = pct>65?'':pct>35?'med':'low';
    return `<div class="rssi-bar"><div class="rssi-fill ${cls}" style="width:${pct}%"></div></div><span class="rssi-txt">${val}dB</span>`;
}
function fmtSeen(s){ return s===null?'—':(s<1?'<span class="seen fresh">Ahora</span>':`<span class="seen">+${Math.round(s)}s</span>`); }
function badgeStatus(a){
    let b='';
    if(isCritical(a)) b+=`<span class="badge emerg">🚨 CRÍTICO</span>`;
    if(a.mlat) b+=`<span class="badge mlat">MLAT</span>`;
    if(a.tisb) b+=`<span class="badge tisb">TIS-B</span>`;
    if(a.seen>15) b+=`<span class="badge stale">STALE</span>`;
    return b||'—';
}
function renderRow(a){
    const crit = isCritical(a);
    // 🔹 Bandera como imagen en tabla
    const flagImg = getFlagImg(a.hex, '');
    const alt = a.alt===null?'—':(a.on_ground?'<span class="alt ground">🟢 SUELO</span>':`<span class="alt">${formatAlt(a.alt)}</span>`);
    const spd = a.gs===null?'—':`<span class="speed">${formatSpeed(a.gs)}</span>`;
    const sqk = a.sqk?`<span class="squawk ${crit?'emerg':''}">${a.sqk}</span>`:'—';
    const dist = a.dst_m!==null?`<span class="dist">${formatDist(a.dst_m)}</span><br><span class="dir">${a.dir!==null?Math.round(a.dir)+'°':''}</span>`:'—';
    
    return `<tr data-search="${a.hex} ${a.flight} ${a.reg} ${a.sqk} ${a.cat} ${a.type}">
        <td class="hex" onclick="focusOnAircraft('${a.hex}')">${a.hex}</td>
        <td style="font-size:1.1rem;line-height:1;">${flagImg}</td>
        <td><div class="flight" onclick="focusOnAircraft('${a.hex}')">${a.flight||'—'}</div><div class="reg">${a.reg||''} ${a.type?`[${a.type}]`:''}</div></td>
        <td>${alt}</td><td>${spd}</td><td>${hdgArrow(a.track)}</td><td>${sqk}</td>
        <td style="text-align:center">${dist}</td><td>${rssiBar(a.rssi)}</td>
        <td style="text-align:center">${fmtSeen(a.seen)}</td><td>${badgeStatus(a)}</td></tr>`;
}
function applyFilter(){
    const term = document.getElementById('filter').value.toLowerCase();
    document.querySelectorAll('#tbody tr').forEach(tr => { tr.style.display = tr.dataset.search.toLowerCase().includes(term) ? '' : 'none'; });
}
function sortTable(col){
    sortCol=col; sortAsc=!sortAsc;
    document.querySelectorAll('thead th .arrow').forEach(el=>el.textContent='▼');
    document.querySelectorAll('thead th')[col].querySelector('.arrow').textContent=sortAsc?'▲':'▼';
    renderTable();
}
function renderTable(){
    if(!rawData.length) return;
    let list=[...rawData];
    const keys=['hex','hex','flight','alt','gs','track','sqk','dst_m','rssi','seen','emerg'];
    const k=keys[sortCol];
    list.sort((a,b)=>{ let va=a[k],vb=b[k]; if(va===null||va===undefined) return 1; if(vb===null||vb===undefined) return -1; if(typeof va==='number'&&typeof vb==='number') return sortAsc?va-vb:vb-va; va=String(va);vb=String(vb); return sortAsc?va.localeCompare(vb,undefined,{numeric:true}):vb.localeCompare(va,undefined,{numeric:true}); });
    document.getElementById('tbody').innerHTML = list.map(renderRow).join('');
    applyFilter();
}
function exportCSV(){
    if(!rawData.length){alert('No hay datos');return;}
    const u = cfg.units==='metric';
    const headers = ['ICAO','País','Vuelo','Registro','Tipo', `Altitud(${u?'m':'ft'})`, `Velocidad(${u?'km/h':'kt'})`, 'Rumbo', 'Squawk', 'Categoría', `Distancia(${u?'km':'nm'})`, 'Dirección', 'RSSI', 'Mensajes', 'Visto', 'Estado'];
    const rows = rawData.map(a => [
        a.hex, getFlagCode(a.hex), a.flight, a.reg, a.type, a.alt?formatAlt(a.alt):'', a.gs?formatSpeed(a.gs):'', a.track||'', a.sqk, a.cat,
        a.dst_m?formatDist(a.dst_m):'', a.dir||'', a.rssi, a.msgs, a.seen, isCritical(a)?'EMERGENCIA':'NORMAL'
    ]);
    const csv = [headers,...rows].map(r=>r.map(c=>`"${c}"`).join(',')).join('\n');
    const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'}));
    link.download = `adsb_export_${Date.now()}.csv`; link.click();
}
async function poll(force=false){
    const dot=document.getElementById('dot'), txt=document.getElementById('statusTxt');
    if(!force){ dot.className='dot'; txt.textContent='Sincronizando…'; }
    try{
        const r=await fetch(`?api=1&url=${encodeURIComponent(cfg.url)}&rx_lat=${cfg.lat}&rx_lon=${cfg.lon}&t=${Date.now()}`,{cache:'no-store'});
        const d=await r.json();
        if(d.error){ dot.className='dot err'; txt.textContent='Error: '+d.error; return; }
        dot.className='dot on'; txt.textContent='En vivo';
        rawData=d.aircraft;
        document.getElementById('acCount').textContent=d.total;
        document.getElementById('posCount').textContent=d.with_pos;
        document.getElementById('altCount').textContent=d.with_alt;
        document.getElementById('lastUpdate').textContent=d.now;
        renderHistogram(d.histogram);
        updateStats(d);
        renderTable(); updateMap(d.aircraft); checkEmergencies(d.aircraft);
    }catch(e){ dot.className='dot err'; txt.textContent='Error: '+e.message; }
}
function checkEmergencies(aircraft){
    if(!cfg.alerts) return;
    const current=new Set();
    aircraft.forEach(a=>{
        if(isCritical(a)){
            current.add(a.hex);
            if(!lastEmergencies.has(a.hex)){
                playAlert('emergency');
                const box=document.getElementById('alertBox');
                box.classList.add('show'); box.textContent=`🚨 ${a.flight||a.hex} - ${a.emerg||'SQUAWK '+a.sqk}`;
                setTimeout(()=>box.classList.remove('show'), 5000);
            }
        }
    }); lastEmergencies=current;
}
loadCfg(); poll(); setInterval(poll, 2000);
document.addEventListener('visibilitychange', ()=>{ if(!document.hidden)poll(true); });
document.querySelectorAll('.config input').forEach(el=>el.addEventListener('keydown',e=>{if(e.key==='Enter')saveCfg();}));
</script>
</body>
</html>