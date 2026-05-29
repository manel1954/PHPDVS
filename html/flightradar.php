<?php
$config_file = "/etc/fr24feed.ini";
$message = "";
$message_type = "";

$view = isset($_POST['view']) ? $_POST['view'] : "none";

if (isset($_GET['logs'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo shell_exec("journalctl -u fr24feed.service -n 100 --no-pager 2>&1");
    exit;
}

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'start':
            shell_exec("sudo systemctl start fr24feed.service 2>&1");
            usleep(500000);
            $check = trim(shell_exec("systemctl is-active fr24feed.service"));
            if ($check === "active") {
                $message = "✅ Servicio iniciado correctamente";
                $message_type = "success";
            } else {
                $message = "❌ Error al iniciar. Revisa logs o configuración.";
                $message_type = "error";
            }
            break;
            
        case 'stop':
            shell_exec("sudo systemctl stop fr24feed.service 2>&1");
            $message = "⏹️ Servicio detenido";
            $message_type = "info";
            break;
            
        case 'restart':
            shell_exec("sudo systemctl restart fr24feed.service 2>&1");
            usleep(500000);
            $check = trim(shell_exec("systemctl is-active fr24feed.service"));
            if ($check === "active") {
                $message = "🔄 Servicio reiniciado correctamente";
                $message_type = "success";
            } else {
                $message = "❌ Error al reiniciar. Revisa logs o configuración.";
                $message_type = "error";
            }
            break;
            
        case 'toggle_power':
            $is_active = trim(shell_exec("systemctl is-active fr24feed.service")) === "active";
            if ($is_active) {
                shell_exec("sudo systemctl stop fr24feed.service 2>&1");
                shell_exec("sudo systemctl disable fr24feed.service 2>&1");
                $message = "🔌 Servicio desactivado y autoarranque OFF";
                $message_type = "info";
            } else {
                shell_exec("sudo systemctl enable fr24feed.service 2>&1");
                shell_exec("sudo systemctl start fr24feed.service 2>&1");
                usleep(500000);
                $check = trim(shell_exec("systemctl is-active fr24feed.service"));
                if ($check === "active") {
                    $message = "⚡ Servicio activado y autoarranque ON";
                    $message_type = "success";
                } else {
                    $message = "❌ Error al iniciar. Revisa logs o configuración.";
                    $message_type = "error";
                }
            }
            break;
    }
}

if (isset($_POST['save_config'])) {
    $content = $_POST['config_content'] ?? '';
    if (file_put_contents($config_file, $content) !== false) {
        $message = "💾 Configuración guardada correctamente";
        $message_type = "success";
    } else {
        $message = "❌ Error al guardar la configuración";
        $message_type = "error";
    }
    $view = "editor";
}

$status_raw = trim(shell_exec("systemctl is-active fr24feed.service"));
$enabled_raw = trim(shell_exec("systemctl is-enabled fr24feed.service"));
$is_active = ($status_raw === "active");
$is_enabled = ($enabled_raw === "enabled");

$config_content = file_exists($config_file) ? file_get_contents($config_file) : "// Archivo no encontrado: $config_file";
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlightRadar24</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✈️</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --accent-green: #10b981;
            --accent-red: #ef4444;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-yellow: #f59e0b;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        
        .card {
            background: var(--bg-secondary);
            border: 1px solid var(--bg-tertiary);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
            transition: box-shadow 0.2s;
        }
        
        .card:hover {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.35);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            border-bottom: 1px solid var(--bg-tertiary);
            border-radius: 16px 16px 0 0 !important;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
        }
        
        .btn {
            border-radius: 8px;
            padding: 0.4rem 0.9rem;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            border: none;
            line-height: 1.3;
        }
        
        .btn:hover { transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        
        .btn-start { 
            background: linear-gradient(135deg, var(--accent-green), #059669); 
            color: white;
        }
        .btn-start:hover { background: linear-gradient(135deg, #059669, #047857); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4); }
        
        .btn-stop { 
            background: linear-gradient(135deg, var(--accent-red), #dc2626); 
            color: white;
        }
        .btn-stop:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); }
        
        .btn-restart { 
            background: linear-gradient(135deg, var(--accent-yellow), #d97706); 
            color: white;
        }
        .btn-restart:hover { background: linear-gradient(135deg, #d97706, #b45309); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4); }
        
        .btn-toggle {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            color: white;
            min-width: 180px;
            font-weight: 600;
        }
        .btn-toggle:hover { 
            background: linear-gradient(135deg, #2563eb, #7c3aed); 
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
        }
        
        .btn-panel {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        .btn-panel:hover { 
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
        }
        
        .btn-web {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
        }
        .btn-web:hover {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
        }
        
        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .status-active { 
            background: rgba(16, 185, 129, 0.2); 
            color: var(--accent-green);
            border: 1px solid var(--accent-green);
        }
        .status-inactive { 
            background: rgba(239, 68, 68, 0.2); 
            color: var(--accent-red);
            border: 1px solid var(--accent-red);
        }
        
        .enabled-badge {
            background: rgba(59, 130, 246, 0.2);
            color: var(--accent-blue);
            border: 1px solid var(--accent-blue);
        }
        .disabled-badge {
            background: rgba(156, 163, 175, 0.2);
            color: var(--text-secondary);
            border: 1px solid var(--bg-tertiary);
        }
        
        .terminal {
            background: #0a0f1d;
            color: #22c55e;
            font-family: 'Fira Code', 'Consolas', monospace;
            padding: 1rem;
            border-radius: 12px;
            height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            border: 1px solid var(--bg-tertiary);
            font-size: 0.85rem;
            line-height: 1.5;
        }
        
        .config-editor {
            background: #050505;
            color: #ffffff;
            font-family: 'Fira Code', 'Consolas', 'Courier New', monospace;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 1rem;
            border-radius: 12px;
            height: 500px;
            width: 100%;
            border: 1px solid #334155;
            resize: vertical;
            line-height: 1.5;
            box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.4);
            box-sizing: border-box;
        }

        .config-editor:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 
                0 0 0 3px rgba(96, 165, 250, 0.25), 
                inset 0 0 15px rgba(0, 0, 0, 0.5);
        }

        .config-editor::-webkit-scrollbar { width: 8px; }
        .config-editor::-webkit-scrollbar-track { background: #0a0a0a; border-radius: 4px; }
        .config-editor::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
        .config-editor::-webkit-scrollbar-thumb:hover { background: #64748b; }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            animation: slideIn 0.3s ease;
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .action-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .info-row {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
            padding: 0.25rem 0;
        }
        
        .view-toggle-btn {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        .view-toggle-btn:hover {
            background: #475569;
            color: white;
        }
        
        /* ===== LED INDICATORS ===== */
        .led {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            box-shadow: 0 0 6px currentColor;
            vertical-align: middle;
        }
        
        .led-green-blink {
            background-color: var(--accent-green);
            color: var(--accent-green);
            animation: blink 1s infinite;
        }
        
        .led-red-solid {
            background-color: var(--accent-red);
            color: var(--accent-red);
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; box-shadow: 0 0 6px currentColor; }
            51%, 100% { opacity: 0.4; box-shadow: 0 0 2px currentColor; }
        }
        /* ===== END LED INDICATORS ===== */
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-primary); }
        ::-webkit-scrollbar-thumb { 
            background: var(--bg-tertiary); 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
</head>
<body>

<div class="container-fluid" style="max-width: 1200px;">
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-airplane me-2" style="font-size:1.1em;"></i>FlightRadar24
            </h4>
            <a href="mmdvm.php" class="btn btn-panel btn-sm">
                <i class="bi bi-house-fill me-1"></i>Panel PHPPLUS
            </a>
        </div>
        <div class="card-body">
            
            <div class="info-row mb-3">
                <!-- Badge de estado con LED personalizado reemplazando el círculo original -->
                <span class="status-badge <?= $is_active ? 'status-active' : 'status-inactive' ?>">
                    <span class="led <?= $is_active ? 'led-green-blink' : 'led-red-solid' ?>" title="<?= $is_active ? 'Servicio activo' : 'Servicio detenido' ?>"></span>
                    <?= $is_active ? 'Activo' : 'Inactivo' ?>
                </span>
                <span class="status-badge <?= $is_enabled ? 'enabled-badge' : 'disabled-badge' ?>">
                    <i class="bi bi-power me-1"></i>
                    Autoarranque: <?= $is_enabled ? 'ON' : 'OFF' ?>
                </span>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-custom alert-<?= $message_type === 'error' ? 'danger' : ($message_type === 'success' ? 'success' : 'info') ?> mb-3">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="action-group">
                
                <button type="submit" name="action" value="toggle_power" class="btn btn-toggle">
                    <?php if ($is_active): ?>
                        <i class="bi bi-power me-1"></i>Desactivar
                    <?php else: ?>
                        <i class="bi bi-lightning-charge me-1"></i>Activar
                    <?php endif; ?>
                </button>
                
                <button type="submit" name="action" value="restart" class="btn btn-restart">
                    <i class="bi bi-arrow-clockwise me-1"></i>Reiniciar
                </button>
                
                <div class="vr mx-1 d-none d-md-block"></div>
                
                <a href="http://<?php echo $_SERVER['HTTP_HOST']; ?>:8754" target="_blank" class="btn btn-web">
                    <i class="bi bi-globe me-1"></i>Web FR24
                </a>
                
                <button type="submit" name="view" value="<?= $view === 'terminal' ? 'none' : 'terminal' ?>" 
                        class="btn view-toggle-btn">
                    <i class="bi bi-terminal me-1"></i>
                    <?= $view === 'terminal' ? 'Ocultar Logs' : 'Ver Logs' ?>
                </button>
                
                <button type="submit" name="view" value="<?= $view === 'editor' ? 'none' : 'editor' ?>" 
                        class="btn view-toggle-btn">
                    <i class="bi bi-file-earmark-text me-1"></i>
                    <?= $view === 'editor' ? 'Cerrar' : 'Editar' ?>
                </button>
                
            </form>
            
        </div>
    </div>

    <?php if ($view === 'terminal'): ?>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-journal-text me-2"></i>📟 Logs - fr24feed.service
        </div>
        <div class="card-body">
            <div id="terminal" class="terminal">⏳ Cargando logs...</div>
            <small class="text-muted d-block mt-2">
                <i class="bi bi-arrow-repeat me-1"></i>Actualización cada 2s
            </small>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view === 'editor'): ?>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-pencil-square me-2"></i>📝 Editor: fr24feed.ini
        </div>
        <div class="card-body">
            <form method="post">
                <textarea name="config_content" class="config-editor" spellcheck="false"><?php 
                    echo htmlspecialchars($config_content); 
                ?></textarea>
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" name="save_config" class="btn btn-start">
                        <i class="bi bi-save me-1"></i>Guardar
                    </button>
                    <button type="submit" name="view" value="none" class="btn" style="background:var(--accent-red);color:white;">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function fetchLogs() {
    const terminal = document.getElementById('terminal');
    if (!terminal) return;
    
    fetch('?logs=1')
        .then(res => res.text())
        .then(data => {
            terminal.textContent = data || '⚠️ Sin logs disponibles';
            terminal.scrollTop = terminal.scrollHeight;
        })
        .catch(err => {
            terminal.textContent = '❌ Error cargando logs';
            console.error('Logs error:', err);
        });
}

setInterval(() => {
    if (document.getElementById('terminal')) {
        fetchLogs();
    }
}, 2000);

document.addEventListener('DOMContentLoaded', fetchLogs);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
