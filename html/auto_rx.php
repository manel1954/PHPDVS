<?php
$config_file = "/home/pi/radiosonde_auto_rx/auto_rx/station.cfg";
$message = "";
$message_type = "";

/*
🧠 ESTADO ÚNICO DE VISTA:
none | terminal | editor
*/
$view = isset($_POST['view']) ? $_POST['view'] : "none";

/* 🔁 LOGS AJAX */
if (isset($_GET['logs'])) {
    echo shell_exec("journalctl -u auto_rx.service -n 50 --no-pager 2>&1");
    exit;
}

/* ⚙️ ACCIONES SERVICIO */
if (isset($_POST['action'])) {
    switch ($_POST['action']) {

        /* 🔥 SERVICIO (toggle único) */
        case 'toggle_service':
            $current = trim(shell_exec("systemctl is-active auto_rx.service"));
            if ($current === 'active') {
                shell_exec("sudo systemctl stop auto_rx.service");
                $message = "Servicio detenido";
                $message_type = "warning";
            } else {
                shell_exec("sudo systemctl start auto_rx.service");
                $message = "Servicio arrancado correctamente";
                $message_type = "success";
            }
            break;

        case 'restart':
            shell_exec("sudo systemctl restart auto_rx.service");
            $message = "Servicio reiniciado";
            $message_type = "info";
            break;

        /* 🔥 AUTOARRANQUE (toggle único) */
        case 'toggle_autostart':
            $current = trim(shell_exec("systemctl is-enabled auto_rx.service"));
            if ($current === 'enabled') {
                shell_exec("sudo systemctl disable auto_rx.service");
                $message = "Autoarranque DESACTIVADO";
                $message_type = "warning";
            } else {
                shell_exec("sudo systemctl enable auto_rx.service");
                $message = "Autoarranque ACTIVADO";
                $message_type = "success";
            }
            break;
    }
}

/* 💾 GUARDAR CONFIG */
if (isset($_POST['save_config'])) {
    file_put_contents($config_file, $_POST['config_content']);
    $message = "Configuración guardada correctamente";
    $message_type = "success";
    $view = "editor";
}

/* 📊 ESTADO SERVICIO */
$status = trim(shell_exec("systemctl is-active auto_rx.service"));
$autostart = trim(shell_exec("systemctl is-enabled auto_rx.service"));

/* 📄 CONFIG */
$config_content = file_exists($config_file) ? file_get_contents($config_file) : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Radiosonde · Control Panel</title>

<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎈</text></svg>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --bg-primary: #0b1220;
    --bg-secondary: #111827;
    --bg-tertiary: #1f2937;
    --border: #1f2937;
    --border-light: #374151;
    --text-primary: #f3f4f6;
    --text-secondary: #9ca3af;
    --accent: #3b82f6;
    --accent-hover: #2563eb;
    --success: #10b981;
    --success-hover: #059669;
    --danger: #ef4444;
    --warning: #f59e0b;
}

* { box-sizing: border-box; }

body {
    background: var(--bg-primary);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Inter", Roboto, sans-serif;
    min-height: 100vh;
    margin: 0;
    font-size: 14px;
    background-image:
        radial-gradient(circle at 15% 10%, rgba(59,130,246,0.08), transparent 40%),
        radial-gradient(circle at 85% 90%, rgba(139,92,246,0.06), transparent 40%);
    background-attachment: fixed;
}

/* NAVBAR */
.topbar {
    background: rgba(17, 24, 39, 0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 14px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 100;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 16px;
    letter-spacing: 0.3px;
}

.brand-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
}

.topbar-right {
    display: flex;
    align-items: center;
}

/* LAYOUT */
.container-main {
    max-width: 1200px;
    margin: 0 auto;
    padding: 28px;
}

/* CARDS */
.card-panel {
    background: rgba(17, 24, 39, 0.7);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 22px;
    overflow: hidden;
    transition: border-color 0.2s;
}

.card-panel:hover {
    border-color: var(--border-light);
}

.card-header {
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(31, 41, 55, 0.3);
}

.card-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.card-header h3 i {
    color: var(--accent);
    font-size: 16px;
}

.card-body {
    padding: 22px;
}

/* BOTONES BASE */
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: var(--bg-tertiary);
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}

.btn-action:hover {
    background: #374151;
    border-color: #4b5563;
    color: var(--text-primary);
    transform: translateY(-1px);
}

.btn-action i { font-size: 14px; }

/* BOTONES SÓLIDOS DE ACCIÓN */
.btn-primary-act {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}
.btn-primary-act:hover {
    background: var(--accent-hover);
    border-color: var(--accent-hover);
    color: white;
}

/* BOTÓN SONDEHUB (VERDE SÓLIDO, LETRA BLANCA) */
.btn-sondehub {
    background: var(--success);
    border-color: var(--success);
    color: white;
}
.btn-sondehub:hover {
    background: var(--success-hover);
    border-color: var(--success-hover);
    color: white;
}

.btn-restart {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.3);
    color: #fbbf24;
}
.btn-restart:hover {
    background: rgba(245, 158, 11, 0.2);
    border-color: #f59e0b;
    color: #fcd34d;
}

/* BOTÓN SERVICIO (toggle) */
.btn-service-on {
    background: rgba(239, 68, 68, 0.12);
    border-color: rgba(239, 68, 68, 0.35);
    color: #f87171;
}
.btn-service-on:hover {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.4);
    color: #34d399;
}

.btn-service-off {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.35);
    color: #34d399;
}
.btn-service-off:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
}

/* BOTÓN AUTOARRANQUE (toggle) */
.btn-autostart-on {
    background: rgba(16, 185, 129, 0.12);
    border-color: rgba(16, 185, 129, 0.35);
    color: #34d399;
}
.btn-autostart-on:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.4);
    color: #f87171;
}

.btn-autostart-off {
    background: rgba(107, 114, 128, 0.15);
    border-color: rgba(107, 114, 128, 0.35);
    color: #d1d5db;
}
.btn-autostart-off:hover {
    background: rgba(16, 185, 129, 0.15);
    border-color: rgba(16, 185, 129, 0.4);
    color: #34d399;
}

/* BUTTON GROUPS */
.btn-group-custom {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.section-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-secondary);
    margin-bottom: 10px;
    font-weight: 600;
}

/* TERMINAL */
.terminal-window {
    background: #0a0f1a;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    font-family: "SF Mono", "Monaco", "Menlo", "Consolas", monospace;
}

.terminal-header {
    background: #111827;
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: var(--text-secondary);
}

.terminal-dots {
    display: flex;
    gap: 6px;
}

.terminal-dots span {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #374151;
}
.terminal-dots span:nth-child(1) { background: #ef4444; }
.terminal-dots span:nth-child(2) { background: #f59e0b; }
.terminal-dots span:nth-child(3) { background: #10b981; }

.terminal-body {
    padding: 16px;
    color: #10b981;
    font-size: 12.5px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
    height: 420px;
    overflow-y: auto;
}

.terminal-body::-webkit-scrollbar { width: 8px; }
.terminal-body::-webkit-scrollbar-track { background: #0a0f1a; }
.terminal-body::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }

/* EDITOR */
.config-editor {
    width: 100%;
    min-height: 440px;
    background: #0a0f1a;
    color: #e5e7eb;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    font-family: "SF Mono", "Monaco", "Menlo", monospace;
    font-size: 13px;
    line-height: 1.55;
    resize: vertical;
}

.config-editor:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* ALERTS */
.alert-custom {
    padding: 12px 16px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
    color: #fcd34d;
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #93c5fd;
}

/* INFO GRID */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
}

.info-item {
    background: var(--bg-tertiary);
    padding: 14px 16px;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.info-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-secondary);
    margin-bottom: 6px;
    font-weight: 600;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 640px) {
    .container-main { padding: 16px; }
    .topbar { padding: 12px 16px; }
    .card-body { padding: 16px; }
}
</style>

<script>
function actualizarTerminal() {
    fetch("?logs=1")
    .then(res => res.text())
    .then(data => {
        let term = document.getElementById("terminal");
        if (term) {
            term.innerText = data;
            term.scrollTop = term.scrollHeight;
        }
    });
}

setInterval(() => {
    if (document.getElementById("terminal")) {
        actualizarTerminal();
    }
}, 2000);
</script>

</head>

<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-broadcast"></i></div>
        <div>
            Radiosonde <span style="color: var(--text-secondary); font-weight: 400;">· auto_rx</span>
        </div>
    </div>
    
    <div class="topbar-right">
        <!-- BOTÓN PHPPLUS -->
        <a href="mmdvm.php" class="btn-action" style="padding: 7px 14px; font-size: 12px;">
            <i class="bi bi-house-door-fill"></i> Panel PHPPLUS
        </a>
    </div>
</div>

<div class="container-main">

    <!-- MENSAJES -->
    <?php if ($message): ?>
    <div class="alert-custom alert-<?php echo $message_type ?: 'info'; ?>">
        <i class="bi <?php
            echo $message_type === 'success' ? 'bi-check-circle-fill' :
                ($message_type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
        ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- PANEL PRINCIPAL -->
    <div class="card-panel">
        <div class="card-header">
            <h3><i class="bi bi-sliders"></i> Panel de Control</h3>
            <span style="font-size: 12px; color: var(--text-secondary);">
                <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i'); ?>
            </span>
        </div>
        <div class="card-body">

            <!-- INFO ESTADO -->
            <div class="info-grid" style="margin-bottom: 22px;">
                <div class="info-item">
                    <div class="info-label">Servicio</div>
                    <div class="info-value" style="color: <?php echo ($status == 'active') ? '#34d399' : '#f87171'; ?>;">
                        <i class="bi <?php echo ($status == 'active') ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>"></i>
                        <?php echo ucfirst($status); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Autoarranque</div>
                    <div class="info-value" style="color: <?php echo ($autostart == 'enabled') ? '#34d399' : '#9ca3af'; ?>;">
                        <i class="bi <?php echo ($autostart == 'enabled') ? 'bi-lightning-charge-fill' : 'bi-lightning-charge'; ?>"></i>
                        <?php echo ucfirst($autostart); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Configuración</div>
                    <div class="info-value">
                        <i class="bi bi-file-earmark-code" style="color: var(--accent);"></i>
                        station.cfg
                    </div>
                </div>
            </div>

            <!-- CONTROL SERVICIO -->
            <div class="section-label">Control del servicio</div>
            <div class="btn-group-custom" style="margin-bottom: 22px;">
                
                <!-- SERVICIO (toggle único Arrancar/Detener) -->
                <form method="post" style="display:inline;">
                    <?php if ($status === 'active'): ?>
                        <button class="btn-action btn-service-on" name="action" value="toggle_service"
                                title="Pulsa para detener el servicio">
                            <i class="bi bi-stop-circle-fill"></i> Detener servicio
                            <i class="bi bi-toggle-on" style="font-size:16px; margin-left:4px;"></i>
                        </button>
                    <?php else: ?>
                        <button class="btn-action btn-service-off" name="action" value="toggle_service"
                                title="Pulsa para arrancar el servicio">
                            <i class="bi bi-play-circle-fill"></i> Arrancar servicio
                            <i class="bi bi-toggle-off" style="font-size:16px; margin-left:4px;"></i>
                        </button>
                    <?php endif; ?>
                </form>

                <!-- REINICIAR (acción puntual) -->
                <form method="post" style="display:inline;">
                    <button class="btn-action btn-restart" name="action" value="restart">
                        <i class="bi bi-arrow-clockwise"></i> Reiniciar
                    </button>
                </form>

                <!-- AUTOARRANQUE (toggle único) -->
                <form method="post" style="display:inline;">
                    <?php if ($autostart === 'enabled'): ?>
                        <button class="btn-action btn-autostart-on" name="action" value="toggle_autostart"
                                title="Pulsa para desactivar el autoarranque">
                            <i class="bi bi-lightning-charge-fill"></i> Autoarranque: ON
                            <i class="bi bi-toggle-on" style="font-size:16px; margin-left:4px;"></i>
                        </button>
                    <?php else: ?>
                        <button class="btn-action btn-autostart-off" name="action" value="toggle_autostart"
                                title="Pulsa para activar el autoarranque">
                            <i class="bi bi-lightning-charge"></i> Autoarranque: OFF
                            <i class="bi bi-toggle-off" style="font-size:16px; margin-left:4px;"></i>
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ENLACES EXTERNOS -->
            <div class="section-label">Accesos rápidos</div>
            <div class="btn-group-custom" style="margin-bottom: 22px;">
                <a href="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http'); ?>://<?php echo $_SERVER['HTTP_HOST']; ?>:5000"
                   target="_blank" class="btn-action btn-primary-act">
                    <i class="bi bi-broadcast-pin"></i> Interfaz Radiosonde
                </a>
                
                <!-- BOTÓN SONDEHUB (VERDE SÓLIDO, LETRA BLANCA) -->
                <a href="https://sondehub.org/" target="_blank" class="btn-action btn-sondehub">
                    <i class="bi bi-globe2"></i> SondeHub
                </a>
            </div>

            <!-- VISTAS -->
            <div class="section-label">Herramientas</div>
            <div class="btn-group-custom">
                <form method="post" style="display:inline;">
                    <button class="btn-action" name="view" value="<?php echo ($view == 'terminal') ? 'none' : 'terminal'; ?>">
                        <i class="bi bi-terminal<?php echo ($view == 'terminal') ? '-fill' : ''; ?>"></i>
                        <?php echo ($view == 'terminal') ? "Ocultar terminal" : "Ver terminal"; ?>
                    </button>
                </form>
                <form method="post" style="display:inline;">
                    <button class="btn-action" name="view" value="<?php echo ($view == 'editor') ? 'none' : 'editor'; ?>">
                        <i class="bi bi-pencil-<?php echo ($view == 'editor') ? 'fill' : 'square'; ?>"></i>
                        <?php echo ($view == 'editor') ? "Cerrar editor" : "Editar configuración"; ?>
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- TERMINAL -->
    <?php if ($view == "terminal"): ?>
    <div class="card-panel">
        <div class="card-header">
            <h3><i class="bi bi-terminal-fill"></i> Terminal en vivo</h3>
            <span style="font-size: 11px; color: var(--text-secondary);">
                <i class="bi bi-arrow-repeat"></i> Actualización cada 2s
            </span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="terminal-window" style="border-radius: 0; border: none;">
                <div class="terminal-header">
                    <div class="terminal-dots"><span></span><span></span><span></span></div>
                    <span style="margin-left: 8px;">journalctl · auto_rx.service</span>
                </div>
                <div id="terminal" class="terminal-body">Cargando logs...</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- EDITOR -->
    <?php if ($view == "editor"): ?>
    <div class="card-panel">
        <div class="card-header">
            <h3><i class="bi bi-file-earmark-code"></i> Editor · station.cfg</h3>
            <span style="font-size: 11px; color: var(--text-secondary);">
                <?php echo strlen($config_content); ?> bytes
            </span>
        </div>
        <div class="card-body">
            <form method="post">
                <textarea name="config_content" class="config-editor"><?php echo htmlspecialchars($config_content); ?></textarea>
                <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" name="save_config" class="btn-action btn-service-off">
                        <i class="bi bi-check2-circle"></i> Guardar cambios
                    </button>
                    <button type="submit" name="view" value="none" class="btn-action btn-service-on">
                        <i class="bi bi-x-circle"></i> Descartar y cerrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align: center; padding: 20px 0 10px; color: var(--text-secondary); font-size: 11px; letter-spacing: 0.5px;">
        RADIOSONDE AUTO_RX CONTROL PANEL · <?php echo date('Y'); ?>
    </div>

</div>

</body>
</html>
