<?php
/*
==================================================================
 ANALISIS.PHP - PANEL PHPHPLUS ULTIMATE
==================================================================

✔ Detección automática de servicios systemd
✔ Encender / apagar / reiniciar
✔ CPU | RAM | Disco | Temperatura | Red
✔ Diseño oscuro elegante con interruptores mejorados
✔ Auto detección desde /etc/systemd/system/
✔ Compatible con x11vnc y servicios personalizados
✔ Botón HOME hacia mmdvm.php

==================================================================
 CONFIGURAR SUDO
==================================================================

sudo visudo

Añadir:

www-data ALL=(ALL) NOPASSWD: /bin/systemctl
www-data ALL=(ALL) NOPASSWD: /bin/journalctl

==================================================================
*/

date_default_timezone_set('Europe/Madrid');

$systemdPath = '/etc/systemd/system/';

/*
==================================================================
 IGNORAR SERVICIOS DEL SISTEMA
==================================================================
*/

$ignoredServices = [
    'dbus', 'systemd', 'getty', 'apt', 'cron', 'rsyslog', 'ssh',
    'apache2', 'nginx', 'mysql', 'mariadb', 'redis', 'network',
    'wpa', 'cups', 'snap', 'ufw', 'polkit'
];

/*
==================================================================
 DETECTAR SERVICIOS
==================================================================
*/

$services = [];
$files = glob($systemdPath . '*.service');

foreach ($files as $file) {
    $service = basename($file, '.service');
    $skip = false;
    
    foreach ($ignoredServices as $ignore) {
        if (stripos($service, $ignore) !== false) {
            $skip = true;
            break;
        }
    }
    
    if (!$skip) {
        $services[] = $service;
    }
}
sort($services);

/*
==================================================================
 FUNCIONES SYSTEMD
==================================================================
*/

function serviceStatus($service) {
    $cmd = "sudo systemctl is-active " . escapeshellarg($service) . " 2>&1";
    $status = trim(shell_exec($cmd));
    return ($status === 'active');
}

function serviceAction($service, $action) {
    $allowed = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowed)) return false;
    shell_exec("sudo systemctl $action " . escapeshellarg($service));
    return true;
}

function getLogs($service) {
    return shell_exec("sudo journalctl -u " . escapeshellarg($service) . " -n 5 --no-pager 2>&1");
}

/*
==================================================================
 SISTEMA
==================================================================
*/

function cpuLoad() {
    $load = sys_getloadavg();
    return round($load[0], 2);
}

function ramUsage() {
    $data = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $data, $total);
    preg_match('/MemAvailable:\s+(\d+)/', $data, $available);
    $used = $total[1] - $available[1];
    return round(($used / $total[1]) * 100, 1);
}

function diskUsage() {
    $total = disk_total_space("/");
    $free = disk_free_space("/");
    $used = $total - $free;
    return round(($used / $total) * 100, 1);
}

function uptime() {
    return trim(shell_exec("uptime -p"));
}

function cpuTemp() {
    $tempFile = '/sys/class/thermal/thermal_zone0/temp';
    if (file_exists($tempFile)) {
        $temp = file_get_contents($tempFile);
        return round($temp / 1000, 1);
    }
    return 'N/A';
}

function networkTraffic() {
    $rx = 0; $tx = 0;
    $lines = file('/proc/net/dev');
    
    foreach ($lines as $line) {
        if (strpos($line, 'lo:') === false && strpos($line, ':') !== false) {
            $data = preg_split('/\s+/', trim($line));
            $rx += $data[1];
            $tx += $data[9];
        }
    }
    return ['rx' => round($rx / 1024 / 1024, 2), 'tx' => round($tx / 1024 / 1024, 2)];
}

function ipAddress() {
    return trim(shell_exec("hostname -I | awk '{print \$1}'"));
}

function hostnameServer() {
    return gethostname();
}

/*
==================================================================
 ACCIONES POST
==================================================================
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service = $_POST['service'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (in_array($service, $services)) {
        serviceAction($service, $action);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/*
==================================================================
 DATOS DEL SISTEMA
==================================================================
*/

$cpu = cpuLoad();
$ram = ramUsage();
$disk = diskUsage();
$temp = cpuTemp();
$uptime = uptime();
$network = networkTraffic();
$ip = ipAddress();
$host = hostnameServer();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis servicios</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #0b1118;
            color: #fff;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            padding: 30px;
            line-height: 1.5;
            background-image:
                radial-gradient(circle at 10% 10%, rgba(0,255,170,0.06) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(0,140,255,0.06) 0%, transparent 40%);
        }

        .container { max-width: 1600px; margin: auto; }

        /* ===== TOPBAR ===== */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .title {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #00e5ff, #00ff95);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: #7f96aa;
            margin-top: 6px;
            font-size: 14px;
            font-weight: 400;
        }

        .home-btn {
            background: linear-gradient(135deg, #00c853, #0091ea);
            color: white;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 14px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(0, 200, 83, 0.25);
        }

        .home-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 200, 83, 0.4);
        }

       /* ===== STATS ===== */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;🔽 reducido de 16px
    margin-bottom: 30px;
}

.stat {
    background: #131c26;
    border-radius: 18px;
    padding: 14px 16px;
    border: 1px solid rgba(255,255,255,0.05);
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    transition: transform 0.2s ease, border-color 0.2s ease;
}

.stat-title {
    color: #7f96aa;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}

        /* ===== GRID DE SERVICIOS ===== */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 20px;
        }

        .card {
            background: #131c26;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 12px 35px rgba(0,0,0,0.35);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #00e5ff, #00ff95, #00e5ff);
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
            opacity: 0.8;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .card:hover {
            transform: translateY(-3px);
            border-color: rgba(0,255,170,0.25);
            box-shadow: 0 20px 45px rgba(0,0,0,0.45);
        }

        /* ===== NOMBRE DEL SERVICIO ===== */
        .service-name {
            font-size: 18px; /* 🔽 REDUCIDO de 24px */
            font-weight: 600;
            margin-bottom: 14px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .service-name::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #00e5ff;
            box-shadow: 0 0 10px rgba(0,229,255,0.6);
        }

        /* ===== ESTADO ===== */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            margin-bottom: 16px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .online {
            background: rgba(0,255,120,0.12);
            color: #00ff95;
            border: 1px solid rgba(0,255,120,0.2);
        }

        .offline {
            background: rgba(255,70,70,0.12);
            color: #ff6b6b;
            border: 1px solid rgba(255,70,70,0.2);
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            position: relative;
        }

        .online .dot {
            background: #00ff95;
            box-shadow: 0 0 0 3px rgba(0,255,149,0.15), 0 0 15px #00ff95;
            animation: pulse 2s infinite;
        }

        .offline .dot {
            background: #ff4d4d;
            box-shadow: 0 0 0 3px rgba(255,77,77,0.15), 0 0 15px #ff4d4d;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(0.95); }
        }

        /* ===== INTERRUPTOR MEJORADO 🎚️ ===== */
        .switch-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px 0;
            border-top: 1px solid rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .switch-label {
            font-size: 13px;
            color: #91a4b7;
            font-weight: 500;
        }

        .switch {
            position: relative;
            width: 68px;
            height: 36px;
            display: inline-block;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #2d3748;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 50px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);
        }

        .slider::before {
            position: absolute;
            content: "";
            width: 28px;
            height: 28px;
            left: 4px;
            bottom: 4px;
            background: linear-gradient(145deg, #ffffff, #e6e6e6);
            border-radius: 50%;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 10px rgba(0,0,0,0.25), 0 0 0 1px rgba(0,0,0,0.1);
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #00c853, #00e676);
            box-shadow: 0 0 20px rgba(0,200,83,0.35);
        }

        input:checked + .slider::before {
            transform: translateX(32px);
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            box-shadow: 0 3px 12px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.3);
        }

        .switch:hover .slider::before {
            transform: scale(1.05);
        }

        input:checked + .slider:hover::before {
            transform: translateX(32px) scale(1.05);
        }

        /* ===== BOTONES ===== */
        .buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .btn {
            border: none;
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .restart {
            background: linear-gradient(135deg, #1565c0, #1e88e5);
            color: white;
            box-shadow: 0 4px 15px rgba(21,101,192,0.3);
        }

        .logs-btn {
            background: linear-gradient(135deg, #2e7d32, #43a047);
            color: white;
            box-shadow: 0 4px 15px rgba(46,125,50,0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.95;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* ===== LOGS ===== */
        .logs {
            background: #0a1017;
            border-radius: 14px;
            padding: 14px;
            font-size: 11px;
            font-family: 'Fira Code', 'Consolas', monospace;
            color: #90caf9;
            max-height: 160px;
            overflow: auto;
            border: 1px solid rgba(255,255,255,0.06);
            white-space: pre-wrap;
            line-height: 1.4;
        }

        .logs::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .logs::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
        }

        .logs::-webkit-scrollbar-thumb {
            background: rgba(0,229,255,0.4);
            border-radius: 3px;
        }

        .logs::-webkit-scrollbar-thumb:hover {
            background: rgba(0,229,255,0.7);
        }

        /* ===== FOOTER ===== */
        .footer {
            margin-top: 45px;
            text-align: center;
            color: #7f96aa;
            font-size: 13px;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .footer strong {
            color: #00ff95;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            body { padding: 20px; }
            
            .title { font-size: 28px; }
            .subtitle { font-size: 13px; }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-value { font-size: 22px; }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .card { padding: 18px; }
            .service-name { font-size: 17px; }
            
            .switch { width: 64px; height: 34px; }
            .slider::before { width: 26px; height: 26px; }
            input:checked + .slider::before { transform: translateX(30px); }
            
            .buttons { flex-wrap: wrap; }
            .btn { flex: 1; min-width: 120px; justify-content: center; }
        }

        @media (max-width: 480px) {
            .stats { grid-template-columns: 1fr 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .home-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>
<div class="container">

    <!-- ===== TOPBAR ===== -->
    <div class="topbar">
        <div>
            <div class="title">Análisis del sistema</div>
            <div class="subtitle">Panel inteligente PHPHPLUS</div>
        </div>
        <a href="mmdvm.php" class="home-btn">
            🏠 Panel PHPPLUS
        </a>
    </div>

    <!-- ===== ESTADÍSTICAS ===== -->
    <div class="stats">
        <div class="stat">
            <div class="stat-title">CPU Load</div>
            <div class="stat-value"><?php echo $cpu; ?>%</div>
        </div>
        <div class="stat">
            <div class="stat-title">RAM Usada</div>
            <div class="stat-value"><?php echo $ram; ?>%</div>
        </div>
        <div class="stat">
            <div class="stat-title">Disco</div>
            <div class="stat-value"><?php echo $disk; ?>%</div>
        </div>
        <div class="stat">
            <div class="stat-title">Temp CPU</div>
            <div class="stat-value"><?php echo $temp; ?>°C</div>
        </div>
        <div class="stat">
            <div class="stat-title">RX</div>
            <div class="stat-value"><?php echo $network['rx']; ?> MB</div>
        </div>
        <div class="stat">
            <div class="stat-title">TX</div>
            <div class="stat-value"><?php echo $network['tx']; ?> MB</div>
        </div>
        <div class="stat">
            <div class="stat-title">Host</div>
            <div class="stat-value"><?php echo htmlspecialchars($host); ?></div>
        </div>
        <div class="stat">
            <div class="stat-title">IP</div>
            <div class="stat-value"><?php echo htmlspecialchars($ip); ?></div>
        </div>
    </div>

    <!-- ===== SERVICIOS ===== -->
    <div class="grid">
        <?php foreach($services as $service): ?>
            <?php $running = serviceStatus($service); ?>
            
            <div class="card">
                <div class="service-name">
                    <?php echo htmlspecialchars($service); ?>
                </div>

                <div class="status <?php echo $running ? 'online' : 'offline'; ?>">
                    <div class="dot"></div>
                    <?php echo $running ? 'ACTIVO' : 'DETENIDO'; ?>
                </div>

                <div class="switch-container">
                    <span class="switch-label">Encender / Apagar</span>
                    
                    <form method="POST" id="form_<?php echo $service; ?>" style="display: contents;">
                        <input type="hidden" name="service" value="<?php echo $service; ?>">
                        <input type="hidden" name="action" id="action_<?php echo $service; ?>" 
                               value="<?php echo $running ? 'stop' : 'start'; ?>">
                        
                        <label class="switch">
                            <input type="checkbox" 
                                   <?php echo $running ? 'checked' : ''; ?> 
                                   onchange="toggleService('<?php echo $service; ?>', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </form>
                </div>

                <div class="buttons">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="service" value="<?php echo $service; ?>">
                        <input type="hidden" name="action" value="restart">
                        <button class="btn restart" type="submit">
                            🔄 Reiniciar
                        </button>
                    </form>
                    
                    <button class="btn logs-btn" type="button" 
                            onclick="toggleLogs('<?php echo $service; ?>')">
                        📜 Logs
                    </button>
                </div>

                <div class="logs" id="logs_<?php echo $service; ?>" style="display:none;">
                    <?php echo htmlspecialchars(getLogs($service)); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="footer">
        <strong>PHPHPLUS</strong> © <?php echo date('Y'); ?> 
        &nbsp;|&nbsp; 
        Uptime: <?php echo htmlspecialchars($uptime); ?>
    </div>

</div>

<script>
    function toggleService(service, enabled) {
        const action = document.getElementById('action_' + service);
        action.value = enabled ? 'start' : 'stop';
        document.getElementById('form_' + service).submit();
    }

    function toggleLogs(service) {
        const logs = document.getElementById('logs_' + service);
        logs.style.display = (logs.style.display === 'none' || !logs.style.display) ? 'block' : 'none';
    }

    // Auto-refresh cada 30 segundos
    setTimeout(() => { location.reload(); }, 30000);
</script>

</body>
</html>

