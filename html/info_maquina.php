<?php
// Configuración de la ruta del archivo en la carpeta web con permisos correctos
$file_path = '/var/www/html/maquina.json';
$message = '';
$message_type = ''; // 'success' o 'error'

// 1. Intentar obtener la IP de la máquina automáticamente
$auto_ip = trim(shell_exec('hostname -I | awk \'{print $1}\''));
if (empty($auto_ip)) {
    // Fallback por si el comando de shell falla
    $auto_ip = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
}

// 2. Inicializar datos por defecto con el nombre solicitado: Raspberry Casa
$data = [
    'nombre' => 'Raspberry Casa',
    'ip' => $auto_ip
];

// 3. Si el archivo existe, leer su contenido
if (file_exists($file_path)) {
    $json_content = file_get_contents($file_path);
    $decoded_data = json_decode($json_content, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data)) {
        $data = $decoded_data;
        // Asegurarnos de que si no hay IP guardada, se use la auto-detectada
        if (empty($data['ip'])) {
            $data['ip'] = $auto_ip;
        }
    }
}

// 4. Procesar el formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizar entradas para seguridad básica
    $nombre_input = htmlspecialchars(trim($_POST['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
    $ip_input = htmlspecialchars(trim($_POST['ip'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Si la IP está vacía (porque se ha borrado), volvemos a asignar la auto-detectada
    $ip_recuperada = false;
    if (empty($ip_input)) {
        $ip_input = $auto_ip;
        $ip_recuperada = true;
    }

    $new_data = [
        'nombre' => $nombre_input,
        'ip' => $ip_input
    ];

    // Intentar guardar en el archivo
    $result = file_put_contents($file_path, json_encode($new_data, JSON_PRETTY_PRINT));

    if ($result !== false) {
        if ($ip_recuperada) {
            $message = '¡Detectada IP! Se ha restaurado automáticamente a: ' . $ip_input . ' y se ha guardado con éxito. Redirigiendo...';
        } else {
            $message = '¡Información guardada correctamente en /var/www/html/maquina.json! Redirigiendo...';
        }
        $message_type = 'success';
        $data = $new_data; // Actualizar la vista con los datos guardados
    } else {
        $message = 'Error: No se pudo guardar el archivo. Verifica los permisos de la carpeta.';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Máquina</title>
    <style>
        :root {
            --bg-color: #121212;
            --card-bg: #1e1e2f;
            --input-bg: #2a2a3c;
            --text-primary: #ffffff;
            --text-secondary: #a0a0b0;
            --accent-color: #4f46e5;
            --accent-hover: #4338ca;
            --border-color: #3d3d5c;
            --success-bg: rgba(16, 185, 129, 0.15);
            --success-text: #34d399;
            --error-bg: rgba(239, 68, 68, 0.15);
            --error-text: #f87171;
            --panel-cyan: #00d4ff; 
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 450px;
            border: 1px solid var(--border-color);
            position: relative;
        }

        h2 {
            text-align: center;
            color: var(--text-primary);
            margin-top: 0.5rem;
            margin-bottom: 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        input[type="text"] {
            width: 100%;
            padding: 0.85rem 1rem;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        .info {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            opacity: 0.8;
        }

        button[type="submit"] {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--accent-color) 0%, #3b82f6 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
            background: linear-gradient(135deg, var(--accent-hover) 0%, #2563eb 100%);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .error {
            background-color: var(--error-bg);
            color: var(--error-text);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>

<div class="container">

    <h2>Configuración del Equipo</h2>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="nombre">Nombre de la máquina</label>
            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($data['nombre']); ?>" required placeholder="Ej: Raspberry Casa">
        </div>

        <div class="form-group">
            <label for="ip">Dirección IP</label>
            <input type="text" id="ip" name="ip" value="<?php echo htmlspecialchars($data['ip']); ?>" placeholder="Ej: 192.168.1.50">
            <div class="info">Si la borras, se restaurará la IP del sistema automáticamente al guardar.</div>
        </div>

        <button type="submit">Guardar Configuración</button>
    </form>
</div>

<?php if ($message_type === 'success'): ?>
<script>
    setTimeout(function() {
        window.location.href = 'mmdvm.php';
    }, 4000); // 4 segundos
</script>
<?php endif; ?>

</body>
</html>
