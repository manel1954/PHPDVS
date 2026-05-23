<?php
header('Content-Type: text/plain');
$log = '/tmp/dump1090.log';
if (file_exists($log)) {
    $lines = file($log);
    $last = array_slice($lines, -100); // últimas 100 líneas
    echo implode('', $last);
} else {
    echo '(log vacío o dump1090 no iniciado)';
}