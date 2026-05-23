<?php
/**
 * changepassword.php
 * Interfaz web segura para cambiar contraseñas de pi y root.
 * Requiere: PHP 7.4+, servidor web con permisos sudo controlados.
 */
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 0); // No mostrar errores crudos en producción

$msg = '';
$msgType = ''; // success, error, warning

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitización básica
    $user       = trim($_POST['user'] ?? '');
    $current    = $_POST['current_pass'] ?? '';
    $new        = $_POST['new_pass'] ?? '';
    $confirm    = $_POST['confirm_pass'] ?? '';

    // Validaciones iniciales
    if (!in_array($user, ['pi', 'root'])) {
        $msg = 'Usuario no reconocido.';
        $msgType = 'error';
    } elseif ($new !== $confirm) {
        $msg = 'Las contraseñas nuevas no coinciden. Por favor, revísalas.';
        $msgType = 'error';
    } elseif (strlen($new) < 6) {
        $msg = 'La nueva contraseña debe tener al menos 6 caracteres.';
        $msgType = 'error';
    } else {
        // Verificar contraseña actual (usa su sin TTY)
        $checkCmd = 'echo ' . escapeshellarg($current) . ' | su -c "exit 0" ' . escapeshellarg($user) . ' 2>/dev/null && echo "OK"';
        $checkRes = trim(shell_exec($checkCmd));

        if ($checkRes !== 'OK') {
            $msg = 'La contraseña actual es incorrecta. Inténtalo de nuevo.';
            $msgType = 'error';
        } else {
            // Cambio de contraseña (chpasswd es el método estándar y seguro para scripts)
            $changeCmd = 'echo ' . escapeshellarg("$user:$new") . ' | sudo chpasswd';
            $output = [];
            $return = 0;
            exec($changeCmd . ' 2>&1', $output, $return);

            if ($return === 0) {
                $msg = '✅ ¡Operación exitosa! La contraseña de <strong>' . htmlspecialchars($user) . '</strong> ha sido actualizada correctamente.';
                $msgType = 'success';
            } else {
                $msg = '❌ Error al aplicar el cambio: ' . htmlspecialchars(implode(' ', $output));
                $msgType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambio de Contraseñas</title>
    <style>
        :root {
            --bg: #060810;
            --card: #0a0e18;
            --text: #1a7ac5;
            --text-light: #50a0e0;
            --border: #102038;
            --input-bg: #0c121e;
            --success: #4caf50;
            --error: #ef5350;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 2rem; }
        
        /* Header: título a la izquierda, botón a la derecha */
        .header { 
            width: 100%; 
            max-width: 960px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1.5rem; 
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .header-left {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        h1 { 
            font-size: 1.6rem; 
            font-weight: 600; 
            letter-spacing: 0.5px; 
            margin: 0; 
        }
        
        /* Nota de contraseña por defecto - pequeña y discreta */
        .default-pass-note {
            font-size: 0.7rem;
            color: var(--text);
            opacity: 0.85;
            font-style: italic;
        }
        .default-pass-note strong {
            font-weight: 600;
            opacity: 1;
        }
        
        .btn-home { 
            background: var(--card); 
            color: var(--text); 
            padding: 0.5rem 1rem; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            text-decoration: none; 
            transition: all 0.2s; 
            white-space: nowrap;
        }
        .btn-home:hover { 
            background: var(--text); 
            color: #fff; 
            transform: translateY(-1px); 
        }

        .container { display: flex; gap: 2rem; width: 100%; max-width: 960px; flex-wrap: wrap; }
        .card { flex: 1; min-width: 300px; background: var(--card); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(26,122,197,0.15); }
        .card h2 { margin-bottom: 1.2rem; color: var(--text-light); text-align: center; font-weight: 500; }

        .form-group { margin-bottom: 1rem; position: relative; }
        label { display: block; margin-bottom: 0.4rem; font-size: 0.85rem; color: var(--text-light); }
        input[type="password"], input[type="text"] { width: 100%; padding: 0.65rem 2.5rem 0.65rem 0.8rem; background: var(--input-bg); border: 1px solid var(--border); color: var(--text-light); border-radius: 8px; font-size: 0.95rem; transition: border 0.2s; }
        input:focus { outline: none; border-color: var(--text); box-shadow: 0 0 0 2px rgba(26, 122, 197, 0.2); }
        
        .toggle-pass { position: absolute; right: 12px; top: 34px; cursor: pointer; color: var(--text-light); font-size: 1.1rem; user-select: none; opacity: 0.8; transition: opacity 0.2s; }
        .toggle-pass:hover { opacity: 1; }

        button[type="submit"] { width: 100%; padding: 0.75rem; background: var(--text); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 0.5rem; }
        button[type="submit"]:hover { background: var(--text-light); color: #000; transform: translateY(-1px); }
        button[type="submit"]:active { transform: translateY(0); }

        .msg { margin-top: 1.5rem; padding: 1rem; border-radius: 8px; text-align: center; font-size: 0.95rem; display: none; width: 100%; max-width: 960px; }
        .msg.show { display: block; animation: fadeIn 0.3s ease; }
        .success { background: rgba(76, 175, 80, 0.15); color: #81c784; border: 1px solid rgba(76, 175, 80, 0.3); }
        .error { background: rgba(239, 83, 80, 0.15); color: #ef9a9a; border: 1px solid rgba(239, 83, 80, 0.3); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 720px) { 
            .container { flex-direction: column; } 
            .header { flex-direction: column; align-items: flex-start; }
            .header-left { width: 100%; }
            .btn-home { align-self: flex-end; }
        }
    </style>
</head>
<body>
    <div class="header">
        <!-- Lado izquierdo: Título + nota -->
        <div class="header-left">
            <h1>🔐 Gestión de Contraseñas</h1>
            <!-- 🔹 Nota de contraseña por defecto debajo del título -->
<small class="default-pass-note">
    Contraseña por defecto: <strong style="color:#fff;font-weight:bold;">aderphp</strong><strong> (Estas claves son independientes de la clave login)</strong>
</small>
        </div>
        
        <!-- Lado derecho: Botón Panel PHPPLUS -->
        <a href="mmdvm.php" class="btn-home">🏠 Panel PHPPLUS</a>
    </div>

    <div class="container">
        <!-- USUARIO PI -->
        <div class="card">
            <h2>👤 Usuario: pi</h2>
            <form method="POST" onsubmit="return validateForm('pi')">
                <input type="hidden" name="user" value="pi">
                <div class="form-group">
                    <label>Contraseña actual:</label>
                    <input type="password" name="current_pass" id="pi_current" required autocomplete="off">
                    <span class="toggle-pass" onclick="togglePass('pi_current')">👁️</span>
                </div>
                <div class="form-group">
                    <label>Nueva contraseña:</label>
                    <input type="password" name="new_pass" id="pi_new" required autocomplete="new-password">
                    <span class="toggle-pass" onclick="togglePass('pi_new')">👁️</span>
                </div>
                <div class="form-group">
                    <label>Confirmar nueva contraseña:</label>
                    <input type="password" name="confirm_pass" id="pi_confirm" required autocomplete="new-password">
                    <span class="toggle-pass" onclick="togglePass('pi_confirm')">👁️</span>
                </div>
                <button type="submit">Cambiar Contraseña pi</button>
            </form>
        </div>

        <!-- USUARIO ROOT -->
        <div class="card">
            <h2>🛡️ Usuario: root</h2>
            <form method="POST" onsubmit="return validateForm('root')">
                <input type="hidden" name="user" value="root">
                <div class="form-group">
                    <label>Contraseña actual:</label>
                    <input type="password" name="current_pass" id="root_current" required autocomplete="off">
                    <span class="toggle-pass" onclick="togglePass('root_current')">👁️</span>
                </div>
                <div class="form-group">
                    <label>Nueva contraseña:</label>
                    <input type="password" name="new_pass" id="root_new" required autocomplete="new-password">
                    <span class="toggle-pass" onclick="togglePass('root_new')">👁️</span>
                </div>
                <div class="form-group">
                    <label>Confirmar nueva contraseña:</label>
                    <input type="password" name="confirm_pass" id="root_confirm" required autocomplete="new-password">
                    <span class="toggle-pass" onclick="togglePass('root_confirm')">👁️</span>
                </div>
                <button type="submit">Cambiar Contraseña root</button>
            </form>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="msg <?= $msgType ?> show"><?= $msg ?></div>
    <?php endif; ?>

    <script>
        function togglePass(id) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function validateForm(user) {
            const newP = document.getElementById(user + '_new').value;
            const confP = document.getElementById(user + '_confirm').value;
            
            if (newP !== confP) {
                alert('⚠️ Las contraseñas nuevas no coinciden. Por favor, revísalas.');
                return false;
            }
            if (newP.length < 6) {
                alert('⚠️ La contraseña debe tener al menos 6 caracteres.');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
