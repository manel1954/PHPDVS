
<?php
header('Content-Type: application/json');

$script = '/home/pi/A108/ejecutar_dump1090.sh';

if (!file_exists($script)) {
    echo json_encode(['ok' => false, 'error' => 'Script no encontrado: ' . $script]);
    exit;
}
if (!is_executable($script)) {
    echo json_encode(['ok' => false, 'error' => 'Script sin permiso de ejecución']);
    exit;
}

$output = shell_exec("sudo bash $script 2>&1");

if ($output === null) {
    echo json_encode(['ok' => false, 'error' => 'shell_exec devolvió null (sudoers o shell_exec deshabilitado)']);
    exit;
}

echo json_encode(['ok' => true, 'output' => $output]);



