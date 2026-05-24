<?php
// ── Acción AJAX: restaurar imagen de fábrica ─────────────────────────────────
if (isset($_POST['accion']) && $_POST['accion'] === 'restaurar_fabrica') {
    header('Content-Type: application/json');
    $scripts = [
        '/home/pi/A108/crear_fabrica.sh',
        '/home/pi/A108/restaurar_de_fabrica.sh',
    ];
    $salida  = [];
    $ok      = true;
    foreach ($scripts as $script) {
        if (!file_exists($script)) {
            $salida[] = "ERROR: No existe $script";
            $ok = false;
            break;
        }
        $cmd    = "bash " . escapeshellarg($script) . " 2>&1";
        $output = shell_exec($cmd);
        $salida[] = "▶ " . basename($script) . "\n" . trim($output);
        if (strpos($output, 'ERROR:') !== false) {
            $ok = false;
            break;
        }
    }
    echo json_encode([
        'ok'     => $ok,
        'output' => implode("\n\n", $salida),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MENU EXTRA</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        .navbar-granate {
            background-color: #6b0f1a;
            min-height: 60px;
            padding-top: 0;
            padding-bottom: 0;
        }

        .navbar-granate .nav-link {
            color: #fff;
            font-size: 0.85rem;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .navbar-granate .nav-link:hover {
            color: #ffcccc;
        }

        .navbar-granate .navbar-brand {
            padding-top: 0;
            padding-bottom: 0;
        }

        .navbar-granate .navbar-brand img {
            height: 45px;
        }

        .card {
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: scale(1.02);
        }

        #fabrica-output {
            background: #111;
            color: #00ff99;
            font-family: monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
            border-radius: 6px;
            padding: 10px;
        }
    </style>
</head>

<body class="bg-dark text-white">

<!-- HEADER -->
<nav class="navbar navbar-expand-md navbar-granate">
    <div class="container">
        <a class="navbar-brand" target="_blank" href="https://associacioader.com">
          <img src="Logo_Ader.png" alt="Logo">
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>

        <a href="mmdvm.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
        </a>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="container py-4">

    <h1 class="mb-4 text-center">
        <i class="bi bi-grid-3x3-gap-fill me-2" style="color: #ff6600;"></i>
         🍓&nbsp;MENU EXTRA
    </h1>

    <div class="row g-3 justify-content-start">

        <!-- DUMP1090 CONTROL -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-airplane-fill me-2" style="color: #00ff15;"></i>Dump1090 Control
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Lanzador configurador Dump1090
                    </p>
                    <a href="/dump1090.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- DUMP1090 MONITOR -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-airplane-fill me-2" style="color: #00ff15;"></i>Dump1090 Monitor
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Seguimiento de aeronaves en tiempo real
                    </p>
                    <a href="/dump1090monitor.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- AMBE SERVER -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-cpu-fill me-2" style="color:#ff4dff;"></i>AMBE SERVER
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Servidor AMBE · Control de voz digital DMR
                    </p>
                    <a href="/ambeserver.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- RADARBOX -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-airplane-engines-fill me-2" style="color:#ff6600;"></i>RADARBOX
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Feeder Radarbox · Tracking ADS-B global.
                    </p>
                    <a href="/radarbox.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- FLIGHTRADAR24 -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-airplane-engines-fill me-2" style="color:#ffcc00;"></i>FLIGHTRADAR24
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Feeder FR24 · Seguimiento de vuelos en tiempo real.
                    </p>
                    <a href="/flightradar.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- RADIOSONDE (AUTO_RX) -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-balloon-fill me-2" style="color:#66ffcc;"></i>RADIOSONDE
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Seguimiento de sondas meteorológicas en tiempo real.
                    </p>
                    <a href="/auto_rx.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- SVXLINK -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-broadcast me-2" style="color:#00d4ff;"></i>SVXLINK
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Control de repetidor · EchoLink · Configuración y logs
                    </p>
                    <a href="/svxlink.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- BLUETOOTH -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-bluetooth me-2" style="color:#00d4ff;"></i>Bluetooth
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Gestión de dispositivos Bluetooth
                    </p>
                    <a href="/bluetooth.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

        <!-- PROGRAMADOR ESP32 -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card bg-secondary border-0 h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">
                        <i class="bi bi-cpu me-2" style="color:#00ffff;"></i>Programador ESP32
                    </h5>
                    <p class="card-text text-white-50 small flex-grow-1">
                        Grabador de Firmware para módulos ESP32 vía WebSerial
                    </p>
                    <a href="/esp32.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
                    </a>
                </div>
            </div>
        </div>

<!-- FUSION 2X -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-broadcast-pin me-2" style="color:#ff3b3b;"></i>Fusion 2X
            </h5>
            <p class="card-text text-white-50 small flex-grow-1">
                Servidor Fusion 2X · Interfaz web en tiempo real para equipos Yaesu
            </p>
            <a href="/fusion2x.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>
        </div>
    </div>
</div>

<!-- OPENWEBRX -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">

            <h5 class="card-title">
                <i class="bi bi-broadcast me-2" style="color:#00ff99;"></i>OpenWebRX
            </h5>

            <p class="card-text text-white-50 small flex-grow-1">
                Receptor SDR en tiempo real · Web interface para RTL-SDR y decodificación digital.
            </p>

            <a href="/openwebrx_control.php"
               class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>

        </div>
    </div>
</div>
        
 
<!-- MIS ENLACES PREFERIDOS -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-bookmarks-fill me-2" style="color:#00e5ff;"></i>Mis Enlaces Preferidos
            </h5>
            <p class="card-text text-white-50 small flex-grow-1">
                Panel de acceso rápido a enlaces de radioafición y servicios web.
            </p>
            <a href="/mis_enlaces.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>
        </div>
    </div>
</div>

<!-- LIMPIEZA DEL SISTEMA -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-trash3-fill me-2" style="color:#ff6666;"></i>
                Limpieza del sistema
            </h5>

            <p class="card-text text-white-50 small flex-grow-1">
                Limpieza de logs, temporales y mantenimiento básico del sistema para liberar espacio.
            </p>

            <a href="/limpieza.php"
               class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>
        </div>
    </div>
</div>

<!-- ANALISIS.PHP -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-speedometer2 me-2" style="color:#00e5ff;"></i>Análisis Servicios
            </h5>
            <p class="card-text text-white-50 small flex-grow-1">
                Panel de monitoreo · CPU/RAM/Disco · Control de servicios con interruptores
            </p>
            <a href="/analisis.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>
        </div>
    </div>
</div>

<!-- SEGURIDAD / CAMBIO DE CONTRASEÑAS -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-shield-lock-fill me-2" style="color:#ff6600;"></i>Seguridad
            </h5>
            <p class="card-text text-white-50 small flex-grow-1">
                Cambio de contraseñas · Gestión segura de usuarios pi y root
            </p>
            <a href="/changepassword.php" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>
        </div>
    </div>
</div>


<!-- DVSWITCH DASHBOARD -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-diagram-3-fill me-2" style="color:#00d4ff;"></i>DVSwitch Dashboard
            </h5>
            <p class="card-text text-white-50 small flex-grow-1">
                Monitor de actividad DVSwitch · Gateway DMR, YSF, D-Star, NXDN
            </p>
            <a href="/dvswitch" class="btn btn-info btn-sm mt-2 text-dark fw-bold">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir
            </a>
        </div>
    </div>
</div>

<!-- RESTAURAR IMAGEN DE FÁBRICA -->
<div class="col-12 col-sm-6 col-lg-3">
    <div class="card bg-secondary border-0 h-100">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">
                <i class="bi bi-arrow-counterclockwise me-2" style="color:#ffaa00;"></i>
                Restaurar de fábrica
            </h5>
            <p class="card-text text-white-50 small flex-grow-1">
                Restaura la imagen y borra todos los parámetros de usuario dejándola como cuando la descargas.
            </p>
            <button class="btn btn-warning btn-sm mt-2 fw-bold text-dark"
                    onclick="confirmarFabrica()">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
            </button>
        </div>
    </div>
</div>
        
    </div>
</div>

<!-- ── MODAL CONFIRMACIÓN ─────────────────────────────────────────────────── -->
<div class="modal fade" id="modalConfirm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-warning">
            <div class="modal-header border-warning">
                <h5 class="modal-title text-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>¿Restaurar imagen de fábrica?
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-white-50 small">
                Se ejecutarán en orden:<br>
                <code class="text-warning">1. crear_fabrica.sh</code><br>
                <code class="text-warning">2. restaurar_de_fabrica.sh</code><br><br>
                Los ficheros de configuración actuales serán sobreescritos. ¿Continuar?
            </div>
            <div class="modal-footer border-warning">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning btn-sm fw-bold text-dark" onclick="ejecutarFabrica()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Sí, restaurar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL RESULTADO ────────────────────────────────────────────────────── -->
<div class="modal fade" id="modalResultado" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border" id="modalResultadoBorder">
            <div class="modal-header">
                <h5 class="modal-title" id="modalResultadoTitulo"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fabrica-output"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmarFabrica() {
    new bootstrap.Modal(document.getElementById('modalConfirm')).show();
}

function ejecutarFabrica() {
    bootstrap.Modal.getInstance(document.getElementById('modalConfirm')).hide();

    const output   = document.getElementById('fabrica-output');
    const titulo   = document.getElementById('modalResultadoTitulo');
    const border   = document.getElementById('modalResultadoBorder');

    output.textContent = '⏳ Ejecutando scripts, espera...';
    titulo.innerHTML   = '<i class="bi bi-hourglass-split me-2"></i>Procesando...';
    titulo.className   = 'modal-title text-warning';
    border.className   = 'modal-content bg-dark border border-warning';

    new bootstrap.Modal(document.getElementById('modalResultado')).show();

    const fd = new FormData();
    fd.append('accion', 'restaurar_fabrica');

    fetch(window.location.pathname, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            output.textContent = data.output;
            if (data.ok) {
                titulo.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Restauración completada';
                titulo.className = 'modal-title text-success';
                border.className = 'modal-content bg-dark border border-success';
            } else {
                titulo.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Error en la restauración';
                titulo.className = 'modal-title text-danger';
                border.className = 'modal-content bg-dark border border-danger';
            }
        })
        .catch(err => {
            output.textContent = 'Error de comunicación: ' + err;
            titulo.innerHTML   = '<i class="bi bi-x-circle-fill me-2"></i>Error';
            titulo.className   = 'modal-title text-danger';
            border.className   = 'modal-content bg-dark border border-danger';
        });
}
</script>
</body>
</html>
