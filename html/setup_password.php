<?php
require_once __DIR__ . '/auth.php';

$pwdFile = __DIR__ . '/password.json';
$message = '';
$type    = '';

// ── Cargar usuarios existentes ────────────────────────────────────────────────
function loadUsers($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    // Compatibilidad con formato antiguo (solo un hash)
    if (isset($data['hash'])) return [['user' => 'admin', 'hash' => $data['hash']]];
    return $data['users'] ?? [];
}

function saveUsers($file, $users) {
    file_put_contents($file, json_encode(['users' => array_values($users)], JSON_PRETTY_PRINT));
}

$users = loadUsers($pwdFile);

// ── Procesar acciones ─────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $newUser = trim($_POST['new_user'] ?? '');
    $pwd1    = $_POST['pwd1'] ?? '';
    $pwd2    = $_POST['pwd2'] ?? '';

    if (empty($newUser)) {
        $message = 'El nombre de usuario no puede estar vacío.'; $type = 'error';
    } elseif (empty($pwd1)) {
        $message = 'La contraseña no puede estar vacía.'; $type = 'error';
    } elseif ($pwd1 !== $pwd2) {
        $message = 'Las contraseñas no coinciden.'; $type = 'error';
    } else {
        // Comprobar si ya existe
        foreach ($users as $u) {
            if (strtolower($u['user']) === strtolower($newUser)) {
                $message = "El usuario '{$newUser}' ya existe. Usa 'Cambiar contraseña'.";
                $type = 'error';
                break;
            }
        }
        if (!$message) {
            $users[] = ['user' => $newUser, 'hash' => password_hash($pwd1, PASSWORD_BCRYPT)];
            saveUsers($pwdFile, $users);
            $message = "Usuario '{$newUser}' añadido correctamente."; $type = 'success';
        }
    }
}

if ($action === 'change') {
    $idx  = intval($_POST['idx'] ?? -1);
    $pwd1 = $_POST['cpwd1'] ?? '';
    $pwd2 = $_POST['cpwd2'] ?? '';

    if (!isset($users[$idx])) {
        $message = 'Usuario no encontrado.'; $type = 'error';
    } elseif (empty($pwd1)) {
        $message = 'La contraseña no puede estar vacía.'; $type = 'error';
    } elseif ($pwd1 !== $pwd2) {
        $message = 'Las contraseñas no coinciden.'; $type = 'error';
    } else {
        $users[$idx]['hash'] = password_hash($pwd1, PASSWORD_BCRYPT);
        saveUsers($pwdFile, $users);
        $message = "Contraseña de '{$users[$idx]['user']}' actualizada."; $type = 'success';
    }
}

if ($action === 'delete') {
    $idx = intval($_POST['idx'] ?? -1);
    if (count($users) <= 1) {
        $message = 'Debe haber al menos un usuario.'; $type = 'error';
    } elseif (isset($users[$idx])) {
        $name = $users[$idx]['user'];
        array_splice($users, $idx, 1);
        saveUsers($pwdFile, $users);
        $message = "Usuario '{$name}' eliminado."; $type = 'success';
    }
}

// Recargar tras cambios
$users = loadUsers($pwdFile);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestión de contraseñas · MMDVM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:        #0a0e14;
    --surface:   #111720;
    --border:    #1e2d3d;
    --green:     #00ff9f;
    --red:       #ff4560;
    --amber:     #ffb300;
    --cyan:      #00d4ff;
    --text:      #a8b9cc;
    --text-dim:  #4a5568;
    --font-mono: 'Share Tech Mono', monospace;
    --font-ui:   'Rajdhani', sans-serif;
  }
  * { box-sizing: border-box; }
  body { background: var(--bg); color: var(--text); font-family: var(--font-ui); margin: 0; min-height: 100vh; }

  .ctrl-header {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem;
  }
  .ctrl-header img { height: 40px; width: auto; }
  .ctrl-header h1 {
    font-family: var(--font-ui); font-weight: 700; font-size: 1.4rem;
    letter-spacing: .08em; color: #e2eaf5; margin: 0; text-transform: uppercase;
  }
  .btn-back {
    margin-left: auto; background: #28a745; color: #fff; border: none;
    border-radius: 6px; font-family: var(--font-mono); font-size: .8rem;
    letter-spacing: .08em; text-transform: uppercase; padding: .4rem 1.2rem;
    text-decoration: none; transition: background .2s;
  }
  .btn-back:hover { background: #218838; color: #fff; }

  .page-body { padding: 2rem; max-width: 700px; margin: 0 auto; }

  .alert-custom {
    border-radius: 6px; padding: .8rem 1.2rem; margin-bottom: 1.5rem;
    font-family: var(--font-mono); font-size: .85rem; border: 1px solid;
  }
  .alert-success { background: rgba(0,255,159,.08); border-color: var(--green); color: var(--green); }
  .alert-error   { background: rgba(255,69,96,.08);  border-color: var(--red);   color: var(--red); }

  /* Cards */
  .sec-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 8px; margin-bottom: 1.5rem; overflow: hidden;
  }
  .sec-card-header {
    background: #0d1e2a; border-bottom: 1px solid var(--border);
    padding: .6rem 1.2rem; font-family: var(--font-mono); font-size: .8rem;
    letter-spacing: .12em; text-transform: uppercase;
  }
  .sec-card-body { padding: 1.2rem 1.5rem; }

  /* User table */
  .user-table { width: 100%; border-collapse: collapse; }
  .user-table th {
    font-family: var(--font-mono); font-size: .7rem; color: var(--text-dim);
    letter-spacing: .1em; text-transform: uppercase;
    padding: .5rem .8rem; border-bottom: 1px solid var(--border); text-align: left;
  }
  .user-table td {
    padding: .6rem .8rem; border-bottom: 1px solid rgba(30,45,61,.5);
    vertical-align: middle;
  }
  .user-table tr:last-child td { border-bottom: none; }
  .user-name { font-family: var(--font-mono); font-size: .9rem; color: var(--green); }

  /* Inline forms */
  .inline-form { display: flex; gap: .5rem; align-items: center; }
  .inline-form input[type=password] {
    background: #0d1e2a; border: 1px solid var(--border); border-radius: 4px;
    color: var(--green); font-family: var(--font-mono); font-size: .82rem;
    padding: .35rem .6rem; outline: none; width: 130px; transition: border-color .2s;
  }
  .inline-form input[type=password]:focus { border-color: var(--green); }

  /* Buttons */
  .btn-sm-green {
    background: #28a745; color: #fff; border: none; border-radius: 4px;
    font-family: var(--font-mono); font-size: .72rem; letter-spacing: .06em;
    text-transform: uppercase; padding: .35rem .7rem; cursor: pointer; transition: background .2s;
  }
  .btn-sm-green:hover { background: #218838; }
  .btn-sm-red {
    background: #dc3545; color: #fff; border: none; border-radius: 4px;
    font-family: var(--font-mono); font-size: .72rem; letter-spacing: .06em;
    text-transform: uppercase; padding: .35rem .7rem; cursor: pointer; transition: background .2s;
  }
  .btn-sm-red:hover { background: #c82333; }

  /* Add user form */
  label { font-family: var(--font-ui); font-size: .95rem; color: var(--text); display: block; margin-bottom: .3rem; }
  input[type=text], input[type=password].field {
    width: 100%; background: #0d1e2a; border: 1px solid var(--border);
    border-radius: 4px; color: var(--green); font-family: var(--font-mono);
    font-size: .95rem; padding: .5rem .8rem; outline: none; transition: border-color .2s;
  }
  input[type=text]:focus, input[type=password].field:focus { border-color: var(--green); }
  .field-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
  .btn-add {
    background: #28a745; color: #fff; border: none; border-radius: 6px;
    font-family: var(--font-ui); font-weight: 700; font-size: 1rem;
    letter-spacing: .1em; text-transform: uppercase;
    padding: .7rem 2rem; cursor: pointer; transition: background .2s;
  }
  .btn-add:hover { background: #218838; }

  .badge-count {
    background: rgba(0,255,159,.15); color: var(--green); border: 1px solid rgba(0,255,159,.3);
    border-radius: 3px; font-family: var(--font-mono); font-size: .65rem;
    padding: .1rem .4rem; margin-left: .5rem;
  }
</style>
</head>
<body>

<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <h1>Gestión de contraseñas</h1>
  <a href="mmdvm.php" class="btn-back">← Volver a MMDVM Control</a>
</header>

<div class="page-body">

  <?php if ($message): ?>
  <div class="alert-custom alert-<?= $type ?>">
    <?= $type === 'success' ? '✔ ' : '✖ ' ?><?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <!-- Lista de usuarios -->
  <div class="sec-card">
    <div class="sec-card-header" style="color: var(--cyan);">
      Usuarios activos
      <span class="badge-count"><?= count($users) ?></span>
    </div>
    <div class="sec-card-body">
      <?php if (empty($users)): ?>
        <p style="font-family:var(--font-mono);font-size:.8rem;color:var(--text-dim);">No hay usuarios. Añade uno abajo.</p>
      <?php else: ?>
      <table class="user-table">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Nueva contraseña</th>
            <th>Repetir</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $i => $u): ?>
          <tr>
            <td><span class="user-name"><?= htmlspecialchars($u['user']) ?></span></td>
            <td colspan="2">
              <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="change">
                <input type="hidden" name="idx" value="<?= $i ?>">
                <input type="password" name="cpwd1" placeholder="Nueva pwd" autocomplete="new-password">
                <input type="password" name="cpwd2" placeholder="Repetir" autocomplete="new-password">
                <button type="submit" class="btn-sm-green">💾 Cambiar</button>
              </form>
            </td>
            <td>
              <?php if (count($users) > 1): ?>
              <form method="POST" onsubmit="return confirm('¿Eliminar usuario <?= htmlspecialchars($u['user']) ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="idx" value="<?= $i ?>">
                <button type="submit" class="btn-sm-red">✖ Borrar</button>
              </form>
              <?php else: ?>
                <span style="font-family:var(--font-mono);font-size:.7rem;color:var(--text-dim);">único</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Añadir usuario -->
  <div class="sec-card">
    <div class="sec-card-header" style="color: var(--amber);">Añadir nuevo usuario</div>
    <div class="sec-card-body">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="field-row">
          <div>
            <label for="new_user">Usuario</label>
            <input type="text" id="new_user" name="new_user" placeholder="indicativo o nombre" autocomplete="off">
          </div>
          <div>
            <label for="pwd1">Contraseña</label>
            <input type="password" id="pwd1" name="pwd1" class="field" autocomplete="new-password">
          </div>
          <div>
            <label for="pwd2">Repetir</label>
            <input type="password" id="pwd2" name="pwd2" class="field" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="btn-add">➕ Añadir usuario</button>
      </form>
    </div>
  </div>

</div>
</body>
</html>
