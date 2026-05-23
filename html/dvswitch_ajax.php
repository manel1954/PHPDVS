<?php
// dvswitch_ajax.php — Backend DVSwitch para mmdvm.php
// Coloca en /home/pi/IMAGEN_PHP/html/dvswitch_ajax.php

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$logDir  = '/var/log/mmdvm/';
$logFile = $logDir . 'MMDVM_Bridge-' . date('Y-m-d') . '.log';
if (!file_exists($logFile)) {
    $logFile = $logDir . 'MMDVM_Bridge.log';
}

$result = [
    'connected'  => false,
    'server'     => '',
    'callsign'   => '',
    'tg'         => '',
    'mode'       => 'DMR',
    'last_heard' => [],
    'timestamp'  => date('H:i:s'),
];

// --- Leer Analog_Bridge.ini para callsign y TG ---
$iniFile = '/opt/Analog_Bridge/Analog_Bridge.ini';
if (file_exists($iniFile)) {
    $ini = parse_ini_file($iniFile, true);
    if (!empty($ini['GENERAL']['callsign']))  $result['callsign'] = strtoupper(trim($ini['GENERAL']['callsign']));
    if (!empty($ini['AMBE_AUDIO']['TXTalkGroup'])) $result['tg'] = trim($ini['AMBE_AUDIO']['TXTalkGroup']);
    if (!empty($ini['AMBE_AUDIO']['mode']))   $result['mode']     = strtoupper(trim($ini['AMBE_AUDIO']['mode']));
}

if (!file_exists($logFile)) {
    echo json_encode($result);
    exit;
}

// --- Parsear log ---
// Solo las últimas 2000 líneas para eficiencia
$lines = [];
$fp = fopen($logFile, 'r');
if ($fp) {
    $buffer = [];
    while (($line = fgets($fp)) !== false) {
        $buffer[] = rtrim($line);
        if (count($buffer) > 2000) array_shift($buffer);
    }
    fclose($fp);
    $lines = $buffer;
}

$lastHeard   = [];   // array de entradas last-heard
$openCalls   = [];   // callsign => [time, tg, slot]
$connected   = false;
$server      = '';

foreach ($lines as $line) {
    // Conexión al master
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}).*Logged into the master successfully:\s*(.+)/', $line, $m)) {
        $connected = true;
        $server    = trim($m[2]);
    }

    // Desconexión
    if (strpos($line, 'Closing DMR network') !== false || strpos($line, 'login failed') !== false) {
        $connected = false;
    }

    // Inicio de transmisión: voice header
    // Formato: DMR Slot 2, received network voice header from EA4AOJ to TG 9
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+)\s+DMR Slot (\d), received network (?:voice header|late entry) from (\S+) to TG (\d+)/', $line, $m)) {
        $ts       = $m[1];
        $slot     = $m[2];
        $callsign = $m[3];
        $tg       = $m[4];
        $openCalls[$callsign] = ['time' => $ts, 'tg' => $tg, 'slot' => $slot];
    }

    // Fin de transmisión
    // Formato: DMR Slot 2, received network end of voice transmission, 50.9 seconds, 0% packet loss, BER: 0.0%
    if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+)\s+DMR Slot (\d), received network end of voice transmission,\s*([\d.]+) seconds,\s*(\d+)% packet loss, BER:\s*([\d.]+)%/', $line, $m)) {
        $slot = $m[2];
        $dur  = $m[3];
        $loss = $m[4];
        $ber  = $m[5];
        // Buscar el callsign que tenía ese slot abierto
        foreach ($openCalls as $cs => $data) {
            if ($data['slot'] === $slot) {
                $lastHeard[] = [
                    'time'     => substr($data['time'], 11, 8), // HH:MM:SS
                    'callsign' => $cs,
                    'tg'       => $data['tg'],
                    'slot'     => $slot,
                    'dur'      => $dur,
                    'loss'     => $loss,
                    'ber'      => $ber,
                    'mode'     => 'DMR',
                ];
                unset($openCalls[$cs]);
                break;
            }
        }
    }
}

// Las 10 últimas entradas en orden inverso
$result['connected']  = $connected;
$result['server']     = $server;
$result['last_heard'] = array_slice(array_reverse($lastHeard), 0, 10);

echo json_encode($result);
