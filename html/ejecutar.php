<?php
// ejecutar.php  –  Ejecuta comandos locales
// Solo accesible desde red local

header('Content-Type: application/json; charset=utf-8');

// Seguridad: solo red local
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$esLocal = ($ip === '127.0.0.1' || $ip === '::1'
         || strpos($ip, '192.168.') === 0
         || strpos($ip, '10.')      === 0
         || strpos($ip, '172.')     === 0);
if (!$esLocal) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado desde ' . $ip]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$cmd  = trim($body['cmd'] ?? '');

if ($cmd === '') {
    echo json_encode(['ok' => false, 'msg' => 'Comando vacío']);
    exit;
}

// Quitar prefijo cmd: si viene con él
if (strpos($cmd, 'cmd:') === 0) {
    $cmd = trim(substr($cmd, 4));
}

// Forzar DISPLAY=:0 si el comando abre ventana gráfica (ffplay, vlc, xterm...)
$cmdsGraficos = ['ffplay', 'vlc', 'mpv', 'xterm', 'xdg-open', 'eog', 'feh'];
$necesitaDisplay = false;
foreach ($cmdsGraficos as $cg) {
    if (strpos($cmd, $cg) !== false) {
        $necesitaDisplay = true;
        break;
    }
}

// Construir comando completo
if ($necesitaDisplay) {
    // DISPLAY=:0 para que abra en la pantalla local de la Pi
    $cmdFull = 'DISPLAY=:0 PATH=/usr/bin:/usr/local/bin:/bin nohup ' . $cmd . ' > /tmp/cmd_last.log 2>&1 &';
} else {
    $cmdFull = 'PATH=/usr/bin:/usr/local/bin:/bin nohup ' . $cmd . ' > /tmp/cmd_last.log 2>&1 &';
}

// Ejecutar
exec($cmdFull, $output, $ret);

// Pequeña pausa para capturar errores inmediatos
usleep(300000); // 0.3s

// Leer log por si hay error inmediato
$log = '';
if (file_exists('/tmp/cmd_last.log')) {
    $log = trim(file_get_contents('/tmp/cmd_last.log'));
}

// Si el log tiene contenido en menos de 0.3s, probablemente es un error
$hayError = ($log !== '' && $ret !== 0);

echo json_encode([
    'ok'      => true,   // El exec se lanzó (aunque el proceso pueda fallar después)
    'msg'     => $hayError
                    ? 'Lanzado con advertencia'
                    : 'Comando ejecutado correctamente',
    'cmd'     => $cmd,
    'display' => $necesitaDisplay,
    'log'     => $log ?: '(sin salida inmediata — proceso en marcha)',
]);
