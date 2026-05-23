<?php
require_once __DIR__ . '/auth.php';

$iniPath = '/usr/local/share/dstargateway.d/DStar_Hosts.json';

// ── Conversión de TXT a array de reflectores ──────────────────────────────────
function parseTxt($content, $type) {
    $reflectors = [];
    foreach (explode("\n", $content) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = preg_split('/[\t ]+/', $line, 2);
        if (count($parts) < 2) continue;
        $name = strtoupper(trim($parts[0]));
        $ip   = trim($parts[1]);
        if ($name === '' || $ip === '') continue;
        $reflectors[] = ['name' => $name, 'reflector_type' => $type, 'ipv4' => $ip];
    }
    return $reflectors;
}

function buildJson($all) {
    $unique = [];
    foreach ($all as $r) $unique[$r['name']] = $r;
    ksort($unique);
    return json_encode(['reflectors' => array_values($unique)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// ── Descarga desde pistar.uk y convierte ─────────────────────────────────────
function downloadAndConvert() {
    $sources = [
        ['url' => 'http://www.pistar.uk/downloads/DExtra_Hosts.txt', 'type' => 'XRF'],
        ['url' => 'http://www.pistar.uk/downloads/DCS_Hosts.txt',    'type' => 'DCS'],
        ['url' => 'http://www.pistar.uk/downloads/XLXHosts.txt',     'type' => 'XRF'],
    ];
    $all = []; $errors = [];
    foreach ($sources as $src) {
        $content = @file_get_contents($src['url']);
        if ($content === false) {
            $errors[] = 'No se pudo descargar: ' . $src['url'];
            continue;
        }
        $all = array_merge($all, parseTxt($content, $src['type']));
    }
    return ['json' => buildJson($all), 'errors' => $errors];
}

$jsonOut = ''; $errors = []; $saveMsg = ''; $saveMsgType = '';

// ── Acción: descarga automática ───────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'download') {
    $result  = downloadAndConvert();
    $errors  = $result['errors'];
    $jsonOut = $result['json'];
}



// ── Acción: guardar en el servidor ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $json = $_POST['json_content'] ?? '';
    if ($json === '') {
        $saveMsg = 'No hay JSON que guardar.'; $saveMsgType = 'err';
    } else {
        $jsonOut = $json;
        $dir = dirname($iniPath);
        if (!is_dir($dir)) {
            $saveMsg = 'El directorio ' . $dir . ' no existe.'; $saveMsgType = 'err';
        } elseif (file_put_contents($iniPath, $json) !== false) {
            $saveMsg = '✔ Guardado correctamente en ' . $iniPath; $saveMsgType = 'ok';
        } else {
            $saveMsg = '✖ Error al escribir en ' . $iniPath . '. Comprueba permisos.'; $saveMsgType = 'err';
        }
    }
}

// ── Descarga directa del JSON generado ───────────────────────────────────────
if (isset($_GET['dl']) && $_GET['dl'] === '1' && isset($_POST['json_content'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="DStar_Hosts.json"');
    echo $_POST['json_content'];
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>D-Star Hosts Converter</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0e14;--surface:#111720;--border:#1e2d3d;--green:#00ff9f;--red:#ff4560;--amber:#ffb300;--cyan:#00d4ff;--dstar:#00e5ff;--violet:#b57aff;--text:#a8b9cc;--text-dim:#4a5568;--font-mono:'Share Tech Mono',monospace;--font-ui:'Rajdhani',sans-serif;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:var(--font-ui);min-height:100vh;}
.ctrl-header{background:var(--surface);border-bottom:1px solid var(--border);padding:1rem 2rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
.ctrl-header img{height:40px;width:auto;}
.ctrl-header h1{font-weight:700;font-size:1.3rem;letter-spacing:.08em;color:#e2eaf5;text-transform:uppercase;}
.btn-back{margin-left:auto;background:transparent;color:var(--dstar);border:1px solid var(--dstar);border-radius:4px;font-family:var(--font-mono);font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;padding:.4rem 1.2rem;text-decoration:none;transition:background .2s;}
.btn-back:hover{background:rgba(0,229,255,.1);}
.body{padding:2rem;max-width:1000px;margin:0 auto;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:8px;margin-bottom:1.5rem;overflow:hidden;}
.card-title{background:linear-gradient(90deg,#0d1e2a,#111720);border-bottom:1px solid var(--border);padding:.6rem 1.2rem;font-family:var(--font-mono);font-size:.72rem;letter-spacing:.15em;text-transform:uppercase;color:var(--dstar);}
.card-title::before{content:'▸ ';}
.card-body{padding:1.2rem;}
.btn{font-family:var(--font-mono);font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;border:none;border-radius:6px;padding:.6rem 1.8rem;cursor:pointer;transition:opacity .2s;display:inline-block;}
.btn-green{background:var(--green);color:#000;}
.btn-cyan{background:var(--dstar);color:#000;}
.btn-amber{background:var(--amber);color:#000;}
.btn-red{background:var(--red);color:#fff;}
.btn-violet{background:var(--violet);color:#000;}
.btn:hover{opacity:.85;}
.desc{font-family:var(--font-mono);font-size:.75rem;color:var(--text-dim);margin-bottom:1rem;line-height:1.6;}

.badge{display:inline-block;font-family:var(--font-mono);font-size:.6rem;padding:.15rem .4rem;border-radius:2px;margin-left:.4rem;vertical-align:middle;}
.badge-xrf{background:rgba(0,229,255,.15);color:var(--dstar);border:1px solid rgba(0,229,255,.3);}
.badge-dcs{background:rgba(0,255,159,.15);color:var(--green);border:1px solid rgba(0,255,159,.3);}
.alert{font-family:var(--font-mono);font-size:.78rem;padding:.6rem 1rem;border-radius:4px;border:1px solid;margin-bottom:.8rem;}
.alert-err{background:rgba(255,69,96,.07);border-color:var(--red);color:var(--red);}
.alert-ok{background:rgba(0,255,159,.07);border-color:var(--green);color:var(--green);}
.json-output{font-family:var(--font-mono);font-size:.72rem;color:#7a9ab5;background:#060c10;border:1px solid var(--border);border-radius:4px;padding:.8rem;height:320px;overflow-y:auto;white-space:pre;margin-bottom:1rem;}
.json-output::-webkit-scrollbar{width:4px;}
.json-output::-webkit-scrollbar-thumb{background:var(--border);}
.stats{display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1rem;}
.stat{font-family:var(--font-mono);font-size:.75rem;}
.stat span{font-weight:bold;}
.stat-xrf span{color:var(--dstar);}
.stat-dcs span{color:var(--green);}
.stat-total span{color:#fff;}
.btn-row{display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;}
.path-note{font-family:var(--font-mono);font-size:.65rem;color:var(--text-dim);margin-top:.5rem;}
/* Modal confirmación */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:2rem;min-width:380px;max-width:90vw;}
.modal-title{font-family:var(--font-mono);font-size:.8rem;color:var(--amber);letter-spacing:.12em;text-transform:uppercase;margin-bottom:1rem;}
.modal-body{font-family:var(--font-mono);font-size:.78rem;color:var(--text);margin-bottom:1.5rem;line-height:1.6;}
.modal-body strong{color:var(--dstar);}
.modal-btns{display:flex;gap:.8rem;}
</style>
</head>
<body>
<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <div>
    <h1>📡 D-Star Hosts Converter</h1>
  </div>
  <a href="mmdvm.php" class="btn-back">← Volver</a>
</header>

<div class="body">

  <!-- OPCIÓN 1: Descarga automática -->
  <div class="card">
    <div class="card-title">Opción 1 · Descarga automática desde pistar.uk</div>
    <div class="card-body">
      <p class="desc">Descarga DExtra_Hosts.txt, DCS_Hosts.txt y XLXHosts.txt desde pistar.uk y los convierte al formato DStar_Hosts.json. La Pi necesita acceso a internet.</p>
      <form method="POST">
        <input type="hidden" name="action" value="download">
        <button type="submit" class="btn btn-green">⬇ Descargar y convertir ahora</button>
      </form>
    </div>
  </div>



  <!-- ERRORES -->
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-err">✖ <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>

  <!-- MENSAJE GUARDADO -->
  <?php if ($saveMsg !== ''): ?>
    <div class="alert alert-<?= $saveMsgType ?>"><?= htmlspecialchars($saveMsg) ?></div>
  <?php endif; ?>

  <!-- RESULTADO -->
  <?php if ($jsonOut !== ''): ?>
  <?php
    $decoded = json_decode($jsonOut, true);
    $total = count($decoded['reflectors'] ?? []);
    $cXRF  = count(array_filter($decoded['reflectors'] ?? [], fn($r) => $r['reflector_type'] === 'XRF'));
    $cDCS  = count(array_filter($decoded['reflectors'] ?? [], fn($r) => $r['reflector_type'] === 'DCS'));
  ?>
  <div class="card">
    <div class="card-title">Resultado · DStar_Hosts.json generado</div>
    <div class="card-body">
      <div class="alert alert-ok">✔ JSON generado correctamente con <?= $total ?> reflectores.</div>
      <div class="stats">
        <div class="stat stat-total">Total: <span><?= $total ?></span></div>
        <div class="stat stat-xrf">XRF/XLX: <span><?= $cXRF ?></span></div>
        <div class="stat stat-dcs">DCS: <span><?= $cDCS ?></span></div>
      </div>
      <div class="json-output"><?= htmlspecialchars($jsonOut) ?></div>
      <div class="btn-row">
        <!-- Descargar en el Mac -->
        <form method="POST" action="?dl=1" style="display:inline;">
          <input type="hidden" name="json_content" value="<?= htmlspecialchars($jsonOut) ?>">
          <button type="submit" class="btn btn-amber">💾 Descargar DStar_Hosts.json</button>
        </form>
        <!-- Guardar en el servidor -->
        <button class="btn btn-violet" onclick="openModal()">📂 Guardar en el servidor</button>
      </div>
      <p class="path-note">Ruta servidor: <?= htmlspecialchars($iniPath) ?></p>
    </div>
  </div>

  <!-- Modal confirmación guardar -->
  <div id="modalOverlay" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-title">⚠ Confirmar guardado</div>
      <div class="modal-body">
        ¿Quieres guardar el JSON generado en el servidor?<br><br>
        Ruta: <strong><?= htmlspecialchars($iniPath) ?></strong><br><br>
        Esto <strong>sobreescribirá</strong> el fichero actual si existe.
      </div>
      <div class="modal-btns">
        <form method="POST">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="json_content" value="<?= htmlspecialchars($jsonOut) ?>">
          <button type="submit" class="btn btn-green">✔ Sí, guardar</button>
        </form>
        <button class="btn btn-red" onclick="closeModal()">✖ Cancelar</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
function openModal(){document.getElementById('modalOverlay').classList.add('open');}
function closeModal(){document.getElementById('modalOverlay').classList.remove('open');}
document.getElementById('modalOverlay')?.addEventListener('click',function(e){if(e.target===this)closeModal();});
</script>
</body>
</html>
