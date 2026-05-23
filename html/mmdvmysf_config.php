<?php
require_once __DIR__ . '/auth.php';

$INI_PATH = '/home/pi/MMDVMHost/MMDVMYSF.ini';

function postKey($sec, $key) { return str_replace([' ','-'], '_', $sec) . '_' . $key; }

function readIniValues($path, $sections) {
    $values = [];
    if (!file_exists($path)) return $values;
    $lines = file($path);
    $currentSec = '';
    foreach ($lines as $line) {
        $stripped = trim($line);
        if (preg_match('/^\[(.+)\]/', $stripped, $m)) { $currentSec = $m[1]; continue; }
        if ($stripped === '' || $stripped[0] === '#' || $stripped[0] === ';') continue;
        if (strpos($stripped, '=') !== false) {
            [$k, $v] = explode('=', $stripped, 2);
            $k = trim($k); $v = trim($v);
            if (isset($sections[$currentSec])) {
                foreach ($sections[$currentSec] as $field) {
                    if (strtolower($field['key']) === strtolower($k)) {
                        $values[$currentSec][$field['key']] = $v;
                    }
                }
            }
        }
    }
    return $values;
}

function writeIniValues($path, $sections, $newValues) {
    if (!file_exists($path)) return ['ok' => false, 'msg' => 'Fichero no encontrado'];
    $lines = file($path);
    $currentSec = ''; $result = [];
    foreach ($lines as $line) {
        $s = trim($line);
        if (preg_match('/^\[(.+)\]/', $s, $m)) { $currentSec = $m[1]; $result[] = $line; continue; }
        if ($s === '' || $s[0] === '#' || $s[0] === ';') { $result[] = $line; continue; }
        if (strpos($s, '=') !== false && isset($sections[$currentSec])) {
            [$k] = explode('=', $s, 2); $k = trim($k); $matched = false;
            foreach ($sections[$currentSec] as $field) {
                if (strtolower($field['key']) === strtolower($k)) {
                    if (isset($newValues[$currentSec][$field['key']])) {
                        $result[] = $k . '=' . $newValues[$currentSec][$field['key']] . "\n";
                        $matched = true;
                    }
                    break;
                }
            }
            if (!$matched) $result[] = $line;
        } else { $result[] = $line; }
    }
    file_put_contents($path, implode('', $result));
    return ['ok' => true, 'msg' => 'Configuración guardada correctamente'];
}

$SECTIONS = [
    'General' => [
        ['key'=>'Callsign',     'label'=>'Indicativo',           'type'=>'str'],
        ['key'=>'Id',           'label'=>'DMR ID',                'type'=>'int'],
        ['key'=>'Timeout',      'label'=>'Timeout (s)',           'type'=>'int'],
        ['key'=>'Duplex',       'label'=>'Duplex (0=simplex)',    'type'=>'int'],
        ['key'=>'ModeHang',     'label'=>'Mode Hang (s)',         'type'=>'int'],
        ['key'=>'RFModeHang',   'label'=>'RF Mode Hang (s)',      'type'=>'int'],
        ['key'=>'NetModeHang',  'label'=>'Net Mode Hang (s)',     'type'=>'int'],
        ['key'=>'Display',      'label'=>'Display',               'type'=>'str'],
        ['key'=>'Daemon',       'label'=>'Daemon (0/1)',          'type'=>'int'],
    ],
    'Info' => [
        ['key'=>'RXFrequency',  'label'=>'Frecuencia RX (Hz)',   'type'=>'int'],
        ['key'=>'TXFrequency',  'label'=>'Frecuencia TX (Hz)',   'type'=>'int'],
        ['key'=>'Power',        'label'=>'Potencia (W)',          'type'=>'int'],
        ['key'=>'Latitude',     'label'=>'Latitud',              'type'=>'str'],
        ['key'=>'Longitude',    'label'=>'Longitud',             'type'=>'str'],
        ['key'=>'Height',       'label'=>'Altura (m)',           'type'=>'int'],
        ['key'=>'Location',     'label'=>'Localización',         'type'=>'str'],
        ['key'=>'Description',  'label'=>'Descripción',          'type'=>'str'],
        ['key'=>'URL',          'label'=>'URL',                  'type'=>'str'],
    ],
    'Modem' => [
        ['key'=>'Protocol',     'label'=>'Protocolo',            'type'=>'str'],
        ['key'=>'UARTPort',     'label'=>'Puerto UART',          'type'=>'select','options'=>[
            ['label'=>'/dev/ttyAMA0','value'=>'/dev/ttyAMA0'],
            ['label'=>'/dev/ttyACM0','value'=>'/dev/ttyACM0'],
            ['label'=>'/dev/ttyACM1','value'=>'/dev/ttyACM1'],
            ['label'=>'/dev/ttyACM2','value'=>'/dev/ttyACM2'],
            ['label'=>'/dev/ttyUSB0','value'=>'/dev/ttyUSB0'],
            ['label'=>'/dev/ttyUSB1','value'=>'/dev/ttyUSB1'],
            ['label'=>'/dev/ttyUSB2','value'=>'/dev/ttyUSB2'],
        ]],
        ['key'=>'UARTSpeed',    'label'=>'Velocidad UART',       'type'=>'select','options'=>[
            ['label'=>'115200','value'=>'115200'],
            ['label'=>'460800','value'=>'460800'],
        ]],
        ['key'=>'TXInvert',     'label'=>'TX Invertido (0/1)',   'type'=>'int'],
        ['key'=>'RXInvert',     'label'=>'RX Invertido (0/1)',   'type'=>'int'],
        ['key'=>'PTTInvert',    'label'=>'PTT Invertido (0/1)',  'type'=>'int'],
        ['key'=>'TXDelay',      'label'=>'TX Delay (ms)',        'type'=>'int'],
        ['key'=>'RXOffset',     'label'=>'RX Offset',            'type'=>'int','signed'=>true],
        ['key'=>'TXOffset',     'label'=>'TX Offset',            'type'=>'int','signed'=>true],
        ['key'=>'DMRDelay',     'label'=>'DMR Delay (ms)',       'type'=>'int'],
        ['key'=>'RXLevel',      'label'=>'Nivel RX (%)',         'type'=>'int'],
        ['key'=>'TXLevel',      'label'=>'Nivel TX (%)',         'type'=>'int'],
        ['key'=>'RFLevel',      'label'=>'Nivel RF (%)',         'type'=>'int'],
        ['key'=>'Trace',        'label'=>'Trace (0/1)',          'type'=>'int'],
        ['key'=>'Debug',        'label'=>'Debug (0/1)',          'type'=>'int'],
    ],
    'Transparent Data' => [
        ['key'=>'Enable',           'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'RemoteAddress',    'label'=>'IP Remota',             'type'=>'str'],
        ['key'=>'RemotePort',       'label'=>'Puerto Remoto',         'type'=>'int'],
        ['key'=>'LocalPort',        'label'=>'Puerto Local',          'type'=>'int'],
        ['key'=>'SendFrameType',    'label'=>'Send Frame Type (0/1)', 'type'=>'int'],
    ],
    'System Fusion' => [
        ['key'=>'Enable',           'label'=>'Activar (0/1)',         'type'=>'int'],
        ['key'=>'LowDeviation',     'label'=>'Low Deviation (0/1)',   'type'=>'int'],
        ['key'=>'SelfOnly',         'label'=>'Solo propio (0/1)',     'type'=>'int'],
        ['key'=>'TXHang',           'label'=>'TX Hang (s)',           'type'=>'int'],
        ['key'=>'RemoteGateway',    'label'=>'Remote Gateway (0/1)', 'type'=>'int'],
        ['key'=>'ModeHang',         'label'=>'Mode Hang (s)',         'type'=>'int'],
    ],
    'System Fusion Network' => [
        ['key'=>'Enable',           'label'=>'Activar (0/1)',          'type'=>'int'],
        ['key'=>'LocalAddress',     'label'=>'IP Local',               'type'=>'str'],
        ['key'=>'LocalPort',        'label'=>'Puerto Local',           'type'=>'int'],
        ['key'=>'GatewayAddress',   'label'=>'IP YSFGateway',         'type'=>'str'],
        ['key'=>'GatewayPort',      'label'=>'Puerto YSFGateway',     'type'=>'int'],
        ['key'=>'ModeHang',         'label'=>'Mode Hang (s)',          'type'=>'int'],
        ['key'=>'Debug',            'label'=>'Debug (0/1)',            'type'=>'int'],
    ],
    'MQTT' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',          'type'=>'int'],
        ['key'=>'Host',         'label'=>'Broker MQTT',            'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto MQTT',            'type'=>'int'],
        ['key'=>'Auth',         'label'=>'Autenticación (0/1)',    'type'=>'int'],
        ['key'=>'Username',     'label'=>'Usuario',                'type'=>'str'],
        ['key'=>'Password',     'label'=>'Contraseña',             'type'=>'str'],
        ['key'=>'Keepalive',    'label'=>'Keepalive (s)',          'type'=>'int'],
        ['key'=>'Name',         'label'=>'Nombre cliente',         'type'=>'str'],
    ],
    'Log' => [
        ['key'=>'DisplayLevel', 'label'=>'Nivel Display',          'type'=>'int'],
        ['key'=>'FileLevel',    'label'=>'Nivel Fichero',          'type'=>'int'],
        ['key'=>'FilePath',     'label'=>'Ruta Log',               'type'=>'str'],
        ['key'=>'FileRoot',     'label'=>'Nombre Log',             'type'=>'str'],
        ['key'=>'FileRotate',   'label'=>'Rotación (0/1)',         'type'=>'int'],
    ],
    'Nextion' => [
        ['key'=>'Port',             'label'=>'Puerto',               'type'=>'str'],
        ['key'=>'Brightness',       'label'=>'Brillo',               'type'=>'int'],
        ['key'=>'DisplayClock',     'label'=>'Mostrar Reloj (0/1)', 'type'=>'int'],
        ['key'=>'UTC',              'label'=>'UTC (0/1)',            'type'=>'int'],
        ['key'=>'IdleBrightness',   'label'=>'Brillo Idle',         'type'=>'int'],
        ['key'=>'ScreenLayout',     'label'=>'Layout Pantalla',      'type'=>'int'],
        ['key'=>'TEMPDisplayTime',  'label'=>'Temp Display Time (s)','type'=>'int'],
        ['key'=>'ScrollCharTime',   'label'=>'Scroll Char Time (ms)','type'=>'int'],
        ['key'=>'ScrollDelay',      'label'=>'Scroll Delay (ms)',    'type'=>'int'],
    ],
    'OLED' => [
        ['key'=>'Type',             'label'=>'Tipo (3/6)',            'type'=>'int'],
        ['key'=>'Brightness',       'label'=>'Brillo (0-255)',        'type'=>'int'],
        ['key'=>'Invert',           'label'=>'Invertir (0/1)',        'type'=>'int'],
        ['key'=>'Scroll',           'label'=>'Scroll (0/1)',          'type'=>'int'],
        ['key'=>'Rotate',           'label'=>'Rotar (0/1)',           'type'=>'int'],
        ['key'=>'Cast',             'label'=>'Cast (0/1)',            'type'=>'int'],
        ['key'=>'LogoScreensaver',  'label'=>'Logo Screensaver (0/1)','type'=>'int'],
    ],
    'LCDproc' => [
        ['key'=>'Address',      'label'=>'IP LCDproc',             'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto LCDproc',         'type'=>'int'],
        ['key'=>'LocalPort',    'label'=>'Puerto Local',           'type'=>'int'],
        ['key'=>'DisplayClock', 'label'=>'Mostrar Reloj (0/1)',    'type'=>'int'],
        ['key'=>'UTC',          'label'=>'UTC (0/1)',              'type'=>'int'],
        ['key'=>'DimOnIdle',    'label'=>'Dim On Idle (0/1)',      'type'=>'int'],
    ],
    'HD44780' => [
        ['key'=>'Rows',         'label'=>'Filas',                  'type'=>'int'],
        ['key'=>'Columns',      'label'=>'Columnas',               'type'=>'int'],
        ['key'=>'I2CAddress',   'label'=>'I2C Address',            'type'=>'str'],
        ['key'=>'PWMBrightness','label'=>'PWM Brillo',             'type'=>'int'],
        ['key'=>'DisplayClock', 'label'=>'Mostrar Reloj (0/1)',    'type'=>'int'],
        ['key'=>'UTC',          'label'=>'UTC (0/1)',              'type'=>'int'],
    ],
    'CW Id' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',          'type'=>'int'],
        ['key'=>'Time',         'label'=>'Tiempo (min)',           'type'=>'int'],
        ['key'=>'Message',      'label'=>'Mensaje',                'type'=>'str'],
    ],
    'Remote Control' => [
        ['key'=>'Enable',       'label'=>'Activar (0/1)',          'type'=>'int'],
        ['key'=>'Address',      'label'=>'IP Local',               'type'=>'str'],
        ['key'=>'Port',         'label'=>'Puerto',                 'type'=>'int'],
    ],
];

$SEC_COLORS = [
    'General'               => '#00ff9f',
    'Info'                  => '#ffb300',
    'Modem'                 => '#00d4ff',
    'Transparent Data'      => '#a8b9cc',
    'System Fusion'         => '#ff7043',
    'System Fusion Network' => '#26c6da',
    'MQTT'                  => '#b57aff',
    'Log'                   => '#00ff9f',
    'Nextion'               => '#00e5ff',
    'OLED'                  => '#ff4560',
    'LCDproc'               => '#ffb300',
    'HD44780'               => '#00d4ff',
    'CW Id'                 => '#ffd700',
    'Remote Control'        => '#ff4560',
];

$message = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = []; $newValues = [];
    foreach ($SECTIONS as $sec => $fields) {
        foreach ($fields as $field) {
            $val = trim($_POST[postKey($sec, $field['key'])] ?? '');
            if ($field['type'] === 'int' && $val !== '' && !is_numeric($val))
                $errors[] = "[{$sec}] '{$field['label']}' debe ser un número.";
            else $newValues[$sec][$field['key']] = $val;
        }
    }
    if ($errors) { $message = implode('<br>', $errors); $msgType = 'error'; }
    else { $res = writeIniValues($INI_PATH, $SECTIONS, $newValues); $message = $res['msg']; $msgType = $res['ok'] ? 'success' : 'error'; }
}
$values = readIniValues($INI_PATH, $SECTIONS);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVMYSF Config · EA3EIZ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#0a0e14; --surface:#111720; --border:#1e2d3d;
    --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --violet:#b57aff;
    --text:#a8b9cc; --text-dim:#4a5568;
    --font-mono:'Share Tech Mono',monospace; --font-ui:'Rajdhani',sans-serif;
}
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }
.ctrl-header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem; }
.ctrl-header img { height: 40px; }
.ctrl-header h1 { font-weight: 700; font-size: 1.4rem; letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase; }
.page-body { padding: 2rem; max-width: 1400px; margin: 0 auto; }
.ini-path { font-family: var(--font-mono); font-size: .75rem; color: var(--text-dim); background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; padding: .4rem .8rem; margin-bottom: 1.5rem; }
.ini-path span { color: var(--amber); }
.alert-custom { border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem; font-family: var(--font-mono); font-size: .85rem; border: 1px solid; }
.alert-success { background: rgba(0,255,159,.08); border-color: var(--green); color: var(--green); }
.alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }
.sec-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden; }
.sec-card-header { background: #0d1e2a; border-bottom: 1px solid var(--border); padding: .6rem 1.2rem; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .12em; text-transform: uppercase; }
.sec-card-body { padding: 1.2rem 1.5rem; }
.field-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .field-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 750px)  { .field-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px)  { .field-grid { grid-template-columns: 1fr; } }
.field-item { display: flex; flex-direction: column; }
label { font-family: var(--font-ui); font-size: .9rem; color: var(--text); display: block; margin-bottom: .3rem; }
input[type=text], input[type=number] { width: 100%; background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; font-family: var(--font-mono); font-size: .88rem; padding: .45rem .7rem; outline: none; transition: border-color .2s; }
select.field-select { width: 100%; background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px; font-family: var(--font-mono); font-size: .88rem; padding: .45rem .7rem; outline: none; cursor: pointer; transition: border-color .2s; }
select.field-select option { background: var(--surface); }
.field-hint { font-family: var(--font-mono); font-size: .65rem; color: var(--text-dim); margin-top: .2rem; }
.btn-row { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; }
.btn-save { background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-ui); font-weight: 700; font-size: 1rem; letter-spacing: .1em; text-transform: uppercase; padding: .75rem 2.5rem; cursor: pointer; transition: background .2s; }
.btn-save:hover { background: #218838; }
.btn-back { background: #28a745; color: #fff; border: none; border-radius: 6px; font-family: var(--font-mono); font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; padding: .4rem 1.2rem; text-decoration: none; margin-left: auto; transition: background .2s; display: inline-block; }
.btn-back:hover { background: #218838; color: #fff; }
.btn-reload { background: var(--surface); color: var(--text-dim); border: 1px solid var(--border); border-radius: 6px; font-family: var(--font-ui); font-size: 1rem; text-transform: uppercase; padding: .75rem 2rem; cursor: pointer; text-decoration: none; display: inline-block; }
.note { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); }
</style>
</head>
<body>

<header class="ctrl-header">
    <img src="Logo_ea3eiz.png" alt="EA3EIZ">
    <h1>MMDVMYSF Config Editor</h1>
    <a href="mmdvm.php" class="btn-back">← Volver</a>
</header>

<div class="page-body">
    <div class="ini-path">📄 Fichero: <span><?= htmlspecialchars($INI_PATH) ?></span></div>

    <?php if ($message): ?>
    <div class="alert-custom alert-<?= $msgType ?>">
        <?= $msgType === 'success' ? '✔ ' : '✖ ' ?><?= $message ?>
        <?php if ($msgType === 'success'): ?><br><small>Reinicia MMDVMHost YSF para aplicar los cambios.</small><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!file_exists($INI_PATH)): ?>
    <div class="alert-custom alert-error">✖ Fichero no encontrado: <?= htmlspecialchars($INI_PATH) ?></div>
    <?php else: ?>

    <form method="POST">
    <?php foreach ($SECTIONS as $sec => $fields):
        $color = $SEC_COLORS[$sec] ?? '#a8b9cc';
    ?>
    <div class="sec-card" style="border-top: 3px solid <?= $color ?>;">
        <div class="sec-card-header" style="color:<?= $color ?>;">[<?= htmlspecialchars($sec) ?>]</div>
        <div class="sec-card-body">
            <div class="field-grid">
            <?php foreach ($fields as $field):
                $pKey   = postKey($sec, $field['key']);
                $val    = $values[$sec][$field['key']] ?? '';
                $signed = !empty($field['signed']);
            ?>
            <div class="field-item">
                <label for="<?= $pKey ?>"><?= htmlspecialchars($field['label']) ?></label>
                <?php if ($field['type'] === 'select'): ?>
                    <select id="<?= $pKey ?>" name="<?= $pKey ?>" class="field-select" style="color:<?= $color ?>">
                        <?php foreach ($field['options'] as $opt): ?>
                        <option value="<?= htmlspecialchars($opt['value']) ?>" <?= $val == $opt['value'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input
                        type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>"
                        id="<?= $pKey ?>"
                        name="<?= $pKey ?>"
                        value="<?= htmlspecialchars($val) ?>"
                        style="color:<?= $color ?>"
                        <?= $field['type'] === 'int' ? ($signed ? 'min="-9999"' : 'min="0"') : '' ?>
                    >
                <?php endif; ?>
                <div class="field-hint"><?= htmlspecialchars($field['key']) ?></div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="btn-row">
        <button type="submit" class="btn-save">💾 Guardar</button>
        <a href="mmdvmysf_config.php" class="btn-reload">🔄 Recargar</a>
        <span class="note">Los cambios requieren reiniciar MMDVMHost YSF</span>
    </div>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
