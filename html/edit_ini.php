<?php
require_once __DIR__ . '/auth.php';


$files = [
    'mmdvm'        => '/home/pi/MMDVMHost/MMDVMHost.ini',
    'dmrgateway'   => '/home/pi/DMRGateway/DMRGateway.ini',
    'mmdvmysf'     => '/home/pi/MMDVMHost/MMDVMYSF.ini',
    'ysfgateway'   => '/home/pi/YSFClients/YSFGateway/YSFGateway.ini',
    'displaydriver'=> '/home/pi/Display-Driver/DisplayDriver.ini',
    'dstargateway' => '/home/pi/DStarGateway/DStarGateway.ini',
    'mmdvmdstar'   => '/home/pi/MMDVMHost/MMDVMDSTAR.ini',
    'mmdvmnxdn'    => '/home/pi/MMDVMHost/MMDVMNXDN.ini',
    'nxdngateway'  => '/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini',
];

$key  = $_GET['file'] ?? '';
$path = $files[$key] ?? '';
$msg  = ''; $type = '';

if (!$path) { die('Fichero no válido'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents($path, $_POST['content'] ?? '');
    $msg = 'Guardado correctamente. Reinicia los servicios para aplicar los cambios.';
    $type = 'success';
}

$content = file_exists($path) ? file_get_contents($path) : '';
$title   = basename($path);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $title ?> · Editor</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@700&display=swap" rel="stylesheet">
<style>
  :root { --bg:#0a0e14; --surface:#111720; --border:#1e2d3d; --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --text:#a8b9cc; --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); min-height: 100vh; display: flex; flex-direction: column; }
  .ctrl-header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
  .ctrl-header img { height: 40px; width: auto; }
  .ctrl-header h1 { font-weight: 700; font-size: 1.3rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
  .ctrl-header .filepath { font-family: var(--font-mono); font-size: .75rem; color: var(--amber); }
  .btn-back { margin-left: auto; background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none; transition: background .2s; }
  .btn-back:hover { background: #218838; color: #fff; }
  .alert { font-family: var(--font-mono); font-size: .82rem; padding: .6rem 1.5rem; border-bottom: 1px solid; }
  .alert.success { background: rgba(0,255,159,.08); border-color: var(--green); color: var(--green); }
  .alert.error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }
  form { flex: 1; display: flex; flex-direction: column; }
  textarea {
    flex: 1; width: 100%; background: #060c10; color: var(--green);
    font-family: var(--font-mono); font-size: .85rem; line-height: 1.6;
    border: none; outline: none; padding: 1.2rem 1.5rem;
    resize: none; min-height: calc(100vh - 140px);
    tab-size: 4;
  }
  .btn-bar { background: var(--surface); border-top: 1px solid var(--border); padding: .8rem 1.5rem; display: flex; gap: 1rem; align-items: center; }
  .btn-save { background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-ui); font-weight: 700; font-size: 1rem; letter-spacing: .1em; text-transform: uppercase; padding: .65rem 2rem; cursor: pointer; transition: background .2s; }
  .btn-save:hover { background: #218838; }
  .note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); }
</style>
</head>
<body>
<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <h1>Editor · <?= htmlspecialchars($title) ?></h1>
  <span class="filepath"><?= htmlspecialchars($path) ?></span>
  <a href="mmdvm.php" class="btn-back">← Volver</a>
</header>

<?php if ($msg): ?>
<div class="alert <?= $type ?>">✔ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="POST">
  <textarea name="content" spellcheck="false"><?= htmlspecialchars($content) ?></textarea>
  <div class="btn-bar">
    <button type="submit" class="btn-save">💾 Guardar</button>
    <span class="note">Los cambios requieren reiniciar los servicios</span>
  </div>
</form>
</body>
</html>
