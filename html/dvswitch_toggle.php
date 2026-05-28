<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$action = $_GET['action'] ?? 'status';

function svcActive($svc) {
    return trim(shell_exec("systemctl is-active $svc 2>/dev/null")) === 'active';
}

if ($action === 'status') {
    $ab = svcActive('analog_bridge');
    $mb = svcActive('mmdvm_bridge');
    echo json_encode([
        'active'  => $ab || $mb,
        'ab'      => $ab,
        'mb'      => $mb,
    ]);
    exit;
}

if ($action === 'toggle') {
    $ab = svcActive('analog_bridge');
    $mb = svcActive('mmdvm_bridge');
    $on = $ab || $mb;

    if ($on) {
        shell_exec('sudo systemctl stop mmdvm_bridge 2>/dev/null');
        shell_exec('sudo systemctl stop analog_bridge 2>/dev/null');
        shell_exec('sudo systemctl disable analog_bridge mmdvm_bridge 2>/dev/null');
        sleep(1);
        echo json_encode(['active' => false, 'msg' => 'Servicios detenidos']);
    } else {
        shell_exec('sudo systemctl enable analog_bridge mmdvm_bridge 2>/dev/null');
        shell_exec('sudo systemctl start analog_bridge 2>/dev/null');
        sleep(1);
        shell_exec('sudo systemctl start mmdvm_bridge 2>/dev/null');
        sleep(2);
        $newAb = svcActive('analog_bridge');
        $newMb = svcActive('mmdvm_bridge');
        echo json_encode(['active' => $newAb || $newMb, 'msg' => 'Servicios iniciados']);
    }
    exit;
}

echo json_encode(['error' => 'Acción no válida']);
