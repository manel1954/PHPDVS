<?php
session_start();

if (!empty($_SESSION['authenticated'])) {
    header('Location: mmdvm.php');
    exit;
}

$error   = '';
$pwdFile = __DIR__ . '/password.json';

// ── Cargar usuarios ──────────────────────────────────────────────
function loadUsers($pwdFile) {
    if (!file_exists($pwdFile)) return [];
    $data = json_decode(file_get_contents($pwdFile), true);
    if (isset($data['hash'])) return [['user'=>'admin','hash'=>$data['hash']]];
    return $data['users'] ?? [];
}

function saveUsers($pwdFile, $users) {
    return file_put_contents($pwdFile, json_encode(['users'=>$users], JSON_PRETTY_PRINT)) !== false;
}

// ── Acción: Cambiar contraseña ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change') {
    header('Content-Type: application/json');
    $current = trim($_POST['current'] ?? '');
    $new1    = trim($_POST['new1']    ?? '');
    $new2    = trim($_POST['new2']    ?? '');

    if ($new1 === '' || strlen($new1) < 4) { echo json_encode(['ok'=>false,'msg'=>'La nueva contraseña debe tener al menos 6 caracteres.']); exit; }
    if ($new1 !== $new2)                   { echo json_encode(['ok'=>false,'msg'=>'Las contraseñas nuevas no coinciden.']); exit; }

    $users = loadUsers($pwdFile);
    $found = false;
    foreach ($users as &$u) {
        if (!empty($u['hash']) && password_verify($current, $u['hash'])) {
            $u['hash'] = password_hash($new1, PASSWORD_BCRYPT);
            $found = true;
            break;
        }
    }
    unset($u);
    if (!$found) { echo json_encode(['ok'=>false,'msg'=>'Contraseña actual incorrecta.']); exit; }
    if (!saveUsers($pwdFile, $users)) { echo json_encode(['ok'=>false,'msg'=>'Error al guardar. Comprueba permisos del fichero.']); exit; }
    echo json_encode(['ok'=>true,'msg'=>'Contraseña cambiada correctamente.']);
    exit;
}

// ── Acción: Restablecer contraseña (token de fichero) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset') {
    header('Content-Type: application/json');
    $token   = trim($_POST['token']   ?? '');
    $new1    = trim($_POST['new1']    ?? '');
    $new2    = trim($_POST['new2']    ?? '');
    $tokenFile = __DIR__ . '/reset_token.txt';

    if (!file_exists($tokenFile))           { echo json_encode(['ok'=>false,'msg'=>'No hay token de restablecimiento activo. Genera uno desde la terminal de la Pi.']); exit; }
    $saved = trim(file_get_contents($tokenFile));
    // Token expira en 30 minutos (timestamp:token)
    $parts = explode(':', $saved, 2); $ts = $parts[0] ?? ''; $tk = $parts[1] ?? '';
    if (time() - intval($ts) > 1800)        { unlink($tokenFile); echo json_encode(['ok'=>false,'msg'=>'El token ha expirado. Genera uno nuevo desde la terminal.']); exit; }
    if (!hash_equals($tk, $token))          { echo json_encode(['ok'=>false,'msg'=>'Token incorrecto.']); exit; }
    if ($new1 === '' || strlen($new1) < 4)  { echo json_encode(['ok'=>false,'msg'=>'La contraseña debe tener al menos 6 caracteres.']); exit; }
    if ($new1 !== $new2)                    { echo json_encode(['ok'=>false,'msg'=>'Las contraseñas no coinciden.']); exit; }

    $users = loadUsers($pwdFile);
    if (empty($users)) $users = [['user'=>'admin','hash'=>'']];
    $users[0]['hash'] = password_hash($new1, PASSWORD_BCRYPT);
    if (!saveUsers($pwdFile, $users))       { echo json_encode(['ok'=>false,'msg'=>'Error al guardar.']); exit; }
    unlink($tokenFile);
    echo json_encode(['ok'=>true,'msg'=>'Contraseña restablecida correctamente. Ya puedes iniciar sesión.']);
    exit;
}

// ── Login normal ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === '') {
    $input = trim($_POST['password'] ?? '');
    if (!file_exists($pwdFile)) {
        $error = 'Fichero de contraseña no encontrado.';
    } else {
        $users = loadUsers($pwdFile);
        $ok = false;
        foreach ($users as $u) {
            if (!empty($u['hash']) && password_verify($input, $u['hash'])) {
                $ok = true;
                $_SESSION['username'] = $u['user'];
                break;
            }
        }
        if ($ok) { $_SESSION['authenticated'] = true; header('Location: mmdvmcopia.php'); exit; }
        else $error = 'Contraseña incorrecta.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login · MMDVM Control</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
<style>
:root { --bg:#0a0e14; --surface:#111720; --border:#1e2d3d; --green:#00ff9f; --red:#ff4560; --amber:#ffb300; --cyan:#00d4ff; --text:#a8b9cc; --text-dim:#4a5568; --font-mono:'Share Tech Mono',monospace; --font-orb:'Orbitron',monospace; }
* { box-sizing:border-box; margin:0; padding:0; }
body { background:var(--bg); color:var(--text); font-family:var(--font-mono); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-box { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:2.5rem 2.5rem 2rem; width:100%; max-width:400px; box-shadow:0 0 40px rgba(0,212,255,.06); }
.login-logo { display:flex; justify-content:center; margin-bottom:1.5rem; }
.login-logo img { height:56px; width:auto; }
.login-title { font-family:var(--font-orb); font-size:1rem; font-weight:700; color:#e2eaf5; text-align:center; letter-spacing:.1em; text-transform:uppercase; margin-bottom:.4rem; }
.login-sub { font-size:.72rem; color:var(--text-dim); text-align:center; letter-spacing:.1em; text-transform:uppercase; margin-bottom:2rem; }
label { font-size:.75rem; color:var(--text-dim); letter-spacing:.1em; text-transform:uppercase; display:block; margin-bottom:.5rem; }
.input-wrap { position:relative; margin-bottom:1.2rem; }
.input-wrap input { width:100%; background:#0d1e2a; border:1px solid var(--border); border-radius:6px; color:var(--green); font-family:var(--font-mono); font-size:1rem; padding:.65rem 2.8rem .65rem .9rem; outline:none; letter-spacing:.15em; transition:border-color .2s; }
.input-wrap input:focus { border-color:var(--green); }
.eye-btn { position:absolute; right:.7rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-dim); font-size:1rem; padding:0; transition:color .2s; }
.eye-btn:hover { color:var(--green); }
.btn-login { width:100%; background:#28a745; color:#fff; border:none; border-radius:6px; font-family:var(--font-mono); font-size:.9rem; letter-spacing:.15em; text-transform:uppercase; padding:.75rem; cursor:pointer; transition:background .2s; }
.btn-login:hover { background:#218838; }
.error-msg { background:rgba(255,69,96,.1); border:1px solid var(--red); border-radius:6px; color:var(--red); font-size:.78rem; padding:.6rem .9rem; margin-bottom:1.2rem; text-align:center; letter-spacing:.05em; }
.lock-icon { text-align:center; font-size:2rem; margin-bottom:1rem; opacity:.3; }
.link-row { display:flex; justify-content:space-between; margin-top:1rem; }
.link-btn { background:none; border:none; cursor:pointer; font-family:var(--font-mono); font-size:.7rem; color:var(--text-dim); letter-spacing:.06em; text-decoration:underline; padding:0; transition:color .2s; }
.link-btn:hover { color:var(--cyan); }

/* ── Modales ──  */
.modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:9000; align-items:center; justify-content:center; }
.modal-bg.open { display:flex; }
.modal-box { background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:2rem; width:100%; max-width:400px; }
.modal-title { font-family:var(--font-orb); font-size:.85rem; color:var(--cyan); letter-spacing:.12em; text-transform:uppercase; margin-bottom:1.5rem; text-align:center; }
.modal-msg { font-size:.75rem; padding:.6rem .9rem; border-radius:4px; border:1px solid; margin-bottom:1rem; text-align:center; display:none; }
.modal-msg.ok  { color:var(--green); border-color:var(--green); background:rgba(0,255,159,.06); }
.modal-msg.err { color:var(--red);   border-color:var(--red);   background:rgba(255,69,96,.06); }
.modal-btns { display:flex; gap:.8rem; margin-top:1rem; }
.btn-ok  { flex:1; background:#28a745; color:#fff; border:none; border-radius:6px; font-family:var(--font-mono); font-size:.8rem; letter-spacing:.08em; text-transform:uppercase; padding:.6rem; cursor:pointer; transition:background .2s; }
.btn-ok:hover { background:#218838; }
.btn-cancel { flex:1; background:transparent; color:var(--text-dim); border:1px solid var(--border); border-radius:6px; font-family:var(--font-mono); font-size:.8rem; letter-spacing:.08em; text-transform:uppercase; padding:.6rem; cursor:pointer; transition:all .2s; }
.btn-cancel:hover { border-color:var(--text); color:var(--text); }

/* ── Info reset ── */
.reset-info { background:#0d1e2a; border:1px solid var(--border); border-radius:6px; padding:.8rem 1rem; margin-bottom:1.2rem; font-size:.72rem; color:var(--text-dim); line-height:1.6; }
.reset-info code { color:var(--amber); display:block; margin:.4rem 0; font-size:.78rem; word-break:break-all; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo"><img src="Logo_ea3eiz.png" alt="EA3EIZ" onerror="this.style.display='none'"></div>
  <div class="login-title">MMDVM Control</div>
  <div class="login-sub">EA3EIZ · Associació ADER</div>
  <div class="lock-icon">🔒</div>

  <?php if ($error): ?>
  <div class="error-msg">✖ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <input type="hidden" name="action" value="">
    <label for="password">Contraseña</label>
    <div class="input-wrap">
      <input type="password" id="password" name="password" placeholder="••••••" autofocus>
      <button type="button" class="eye-btn" onclick="togglePwd('password','eyeIcon')"><span id="eyeIcon">👁</span></button>
    </div>
    <button type="submit" class="btn-login">Entrar →</button>
  </form>

  <div class="link-row">
    <button class="link-btn" onclick="openModal('changeModal')">🔑 Cambiar contraseña</button>
    <button class="link-btn" onclick="openModal('resetModal')">❓ Olvidé la contraseña</button>
  </div>
</div>

<!-- ── Modal: Cambiar contraseña ── -->
<div id="changeModal" class="modal-bg" onclick="if(event.target===this)closeModal('changeModal')">
<div class="modal-box">
  <div class="modal-title">🔑 Cambiar contraseña</div>
  <div class="modal-msg" id="changeMsg"></div>
  <label>Contraseña actual</label>
  <div class="input-wrap"><input type="password" id="chgCurrent" placeholder="••••••"><button type="button" class="eye-btn" onclick="togglePwd('chgCurrent','eyeChg0')"><span id="eyeChg0">👁</span></button></div>
  <label>Nueva contraseña</label>
  <div class="input-wrap"><input type="password" id="chgNew1" placeholder="••••••"><button type="button" class="eye-btn" onclick="togglePwd('chgNew1','eyeChg1')"><span id="eyeChg1">👁</span></button></div>
  <label>Repetir nueva contraseña</label>
  <div class="input-wrap"><input type="password" id="chgNew2" placeholder="••••••"><button type="button" class="eye-btn" onclick="togglePwd('chgNew2','eyeChg2')"><span id="eyeChg2">👁</span></button></div>
  <div class="modal-btns">
    <button class="btn-ok" onclick="doChange()">💾 Guardar</button>
    <button class="btn-cancel" onclick="closeModal('changeModal')">✖ Cancelar</button>
  </div>
</div>
</div>

<!-- ── Modal: Olvidé la contraseña ── -->
<div id="resetModal" class="modal-bg" onclick="if(event.target===this)closeModal('resetModal')">
<div class="modal-box">
  <div class="modal-title">❓ Restablecer contraseña</div>
  <div class="modal-msg" id="resetMsg"></div>
  <div class="reset-info">
    Para restablecer la contraseña, ejecuta este comando en la terminal de la Raspberry Pi y copia el token generado:
    <code>php -r "echo time().':'.bin2hex(random_bytes(12)).PHP_EOL;" | tee /var/www/html/reset_token.txt</code>
    El token expira en <strong style="color:var(--amber)">30 minutos</strong>.
  </div>
  <label>Token generado</label>
  <div class="input-wrap"><input type="text" id="rstToken" placeholder="pega aquí el token" style="letter-spacing:.05em;color:var(--amber);"></div>
  <label>Nueva contraseña</label>
  <div class="input-wrap"><input type="password" id="rstNew1" placeholder="••••••"><button type="button" class="eye-btn" onclick="togglePwd('rstNew1','eyeRst1')"><span id="eyeRst1">👁</span></button></div>
  <label>Repetir nueva contraseña</label>
  <div class="input-wrap"><input type="password" id="rstNew2" placeholder="••••••"><button type="button" class="eye-btn" onclick="togglePwd('rstNew2','eyeRst2')"><span id="eyeRst2">👁</span></button></div>
  <div class="modal-btns">
    <button class="btn-ok" onclick="doReset()">🔓 Restablecer</button>
    <button class="btn-cancel" onclick="closeModal('resetModal')">✖ Cancelar</button>
  </div>
</div>
</div>

<script>
function togglePwd(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.textContent = inp.type === 'password' ? '👁' : '🙈';
}
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); const m=document.getElementById(id.replace('Modal','Msg')); if(m){m.style.display='none';} }

function showMsg(id, ok, text) {
    const el = document.getElementById(id);
    el.className = 'modal-msg ' + (ok ? 'ok' : 'err');
    el.textContent = (ok ? '✔ ' : '✖ ') + text;
    el.style.display = 'block';
}

async function doChange() {
    const current = document.getElementById('chgCurrent').value.trim();
    const new1    = document.getElementById('chgNew1').value.trim();
    const new2    = document.getElementById('chgNew2').value.trim();
    try {
        const r = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=change&current='+encodeURIComponent(current)+'&new1='+encodeURIComponent(new1)+'&new2='+encodeURIComponent(new2)});
        const d = await r.json();
        showMsg('changeMsg', d.ok, d.msg);
        if (d.ok) { document.getElementById('chgCurrent').value=''; document.getElementById('chgNew1').value=''; document.getElementById('chgNew2').value=''; }
    } catch(e) { showMsg('changeMsg', false, 'Error de red: '+e.message); }
}

async function doReset() {
    const token = document.getElementById('rstToken').value.trim();
    const new1  = document.getElementById('rstNew1').value.trim();
    const new2  = document.getElementById('rstNew2').value.trim();
    try {
        const r = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=reset&token='+encodeURIComponent(token)+'&new1='+encodeURIComponent(new1)+'&new2='+encodeURIComponent(new2)});
        const d = await r.json();
        showMsg('resetMsg', d.ok, d.msg);
        if (d.ok) { setTimeout(()=>closeModal('resetModal'), 2500); }
    } catch(e) { showMsg('resetMsg', false, 'Error de red: '+e.message); }
}
</script>
</body>
</html>
