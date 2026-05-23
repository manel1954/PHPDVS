<?php
require_once __DIR__ . '/auth.php';

$iniPath = '/home/pi/MMDVMHost/MMDVMHost.ini';
$msg = ''; $msgType = '';

// Campos bloqueados: siempre se guardan con este valor, no editables
$lockedFields = [
    'D-Star__Enable' => '0',
    'DMR__Enable'    => '1',
];

function parseIni($path) {
    $result = []; $section = '';
    if (!file_exists($path)) return $result;
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') {
            $result[$section][] = ['type'=>'comment','raw'=>$line];
            continue;
        }
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            $section = trim($m[1]);
            $result[$section][] = ['type'=>'section','name'=>$section];
            continue;
        }
        if (preg_match('/^([^=]+)=(.*)$/', $line, $m)) {
            $raw = trim($m[2]);
            $value = preg_replace('/#.*$/', '', $raw);
            preg_match('/#(.*)$/', $raw, $mc);
            $comment = isset($mc[1]) ? trim($mc[1]) : '';
            $result[$section][] = ['type'=>'kv','key'=>trim($m[1]),'value'=>trim($value),'comment'=>$comment];
        }
    }
    return $result;
}

function writeIni($path, $data) {
    $out = '';
    foreach ($data as $section => $lines) {
        foreach ($lines as $entry) {
            if ($entry['type'] === 'comment') { $out .= $entry['raw'] . "\n"; }
            elseif ($entry['type'] === 'section') { $out .= "[" . $entry['name'] . "]\n"; }
            elseif ($entry['type'] === 'kv') {
                $line = $entry['key'] . '=' . $entry['value'];
                if (!empty($entry['comment'])) $line .= "\t\t\t\t# " . $entry['comment'];
                $out .= $line . "\n";
            }
        }
    }
    return file_put_contents($path, ltrim($out));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parsed = parseIni($iniPath);
    foreach ($parsed as $section => &$lines) {
        foreach ($lines as &$entry) {
            if ($entry['type'] === 'kv') {
                $fieldKey = str_replace(' ', '_', $section) . '__' . $entry['key'];
                // Si está bloqueado, forzar valor fijo
                if (isset($GLOBALS['lockedFields'][$fieldKey])) {
                    $entry['value'] = $GLOBALS['lockedFields'][$fieldKey];
                } elseif (isset($_POST[$fieldKey])) {
                    $entry['value'] = $_POST[$fieldKey];
                }
            }
        }
    }
    unset($lines, $entry);
    if (writeIni($iniPath, $parsed) !== false) {
        $msg = '✔ MMDVMHost.ini guardado correctamente.'; $msgType = 'ok';
    } else {
        $msg = '✖ Error al guardar. Comprueba permisos del fichero.'; $msgType = 'err';
    }
}

$parsed = parseIni($iniPath);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MMDVMHost Config</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@500;700&family=Orbitron:wght@700&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#0a0e14; --surface:#111720; --border:#1e2d3d;
    --green:#00ff9f; --red:#ff4560; --amber:#ffb300;
    --cyan:#00d4ff; --mmdvm:#a78bfa; --text:#a8b9cc;
    --text-dim:#4a5568; --lock:#ffb300;
    --font-mono:'Share Tech Mono',monospace;
    --font-ui:'Rajdhani',sans-serif;
    --font-orb:'Orbitron',monospace;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--bg);color:var(--text);font-family:var(--font-ui);min-height:100vh;}
.ctrl-header{background:var(--surface);border-bottom:1px solid var(--border);padding:1rem 2rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
.ctrl-header img{height:40px;width:auto;}
.ctrl-header h1{font-weight:700;font-size:1.3rem;letter-spacing:.08em;color:#e2eaf5;text-transform:uppercase;}
.ctrl-header .path{font-family:var(--font-mono);font-size:.72rem;color:var(--mmdvm);}
.btn-back{margin-left:auto;background:transparent;color:var(--mmdvm);border:1px solid var(--mmdvm);border-radius:4px;font-family:var(--font-mono);font-size:.78rem;letter-spacing:.08em;text-transform:uppercase;padding:.4rem 1.2rem;text-decoration:none;transition:background .2s;}
.btn-back:hover{background:rgba(167,139,250,.1);color:var(--mmdvm);}
.alert{font-family:var(--font-mono);font-size:.82rem;padding:.7rem 2rem;border-bottom:1px solid;}
.alert.ok{background:rgba(0,255,159,.07);border-color:var(--green);color:var(--green);}
.alert.err{background:rgba(255,69,96,.07);border-color:var(--red);color:var(--red);}
.body{padding:2rem;max-width:1100px;margin:0 auto;}
.section-block{background:var(--surface);border:1px solid var(--border);border-radius:8px;margin-bottom:1.5rem;overflow:hidden;}
.section-title{background:linear-gradient(90deg,#0d1520,#111720);border-bottom:1px solid var(--border);padding:.55rem 1.2rem;font-family:var(--font-mono);font-size:.72rem;letter-spacing:.15em;text-transform:uppercase;color:var(--mmdvm);display:flex;align-items:center;gap:.5rem;}
.section-title::before{content:'▸';}
.fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
@media(max-width:700px){.fields-grid{grid-template-columns:1fr;}}
.field-row{display:flex;flex-direction:column;gap:.3rem;padding:.75rem 1.2rem;border-bottom:1px solid rgba(30,45,61,.5);border-right:1px solid rgba(30,45,61,.5);}
.field-row:nth-child(even){border-right:none;}
.field-row:last-child,.field-row:nth-last-child(2):nth-child(odd){border-bottom:none;}
.field-label{font-family:var(--font-mono);font-size:.65rem;color:var(--text-dim);letter-spacing:.12em;text-transform:uppercase;display:flex;align-items:center;gap:.4rem;}
.field-hint{font-family:var(--font-mono);font-size:.6rem;color:#2a3a5a;letter-spacing:.05em;margin-top:.1rem;}
.field-input{background:#0a1018;border:1px solid #1e2d3d;border-radius:3px;color:var(--mmdvm);font-family:var(--font-mono);font-size:.85rem;padding:.35rem .6rem;width:100%;transition:border-color .2s;}
.field-input:focus{outline:none;border-color:var(--mmdvm);background:#0d1520;}
.field-input.bool{color:var(--amber);}
/* Campo bloqueado */
.field-row.locked .field-input{
    background:#0d0a00;
    border:1px solid rgba(255,179,0,.3);
    color:var(--lock);
    cursor:not-allowed;
    opacity:.85;
}
.lock-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.58rem;color:var(--lock);font-family:var(--font-mono);background:rgba(255,179,0,.08);border:1px solid rgba(255,179,0,.25);border-radius:3px;padding:.1rem .35rem;letter-spacing:.08em;text-transform:uppercase;}
.lock-note{font-family:var(--font-mono);font-size:.58rem;color:rgba(255,179,0,.5);margin-top:.15rem;}
.comment-line{font-family:var(--font-mono);font-size:.68rem;color:var(--text-dim);padding:.3rem 1.2rem;background:rgba(0,0,0,.2);opacity:.6;white-space:pre-wrap;}
.btn-bar{position:sticky;bottom:0;background:var(--surface);border-top:1px solid var(--border);padding:1rem 2rem;display:flex;gap:1rem;align-items:center;z-index:100;}
.btn-save{background:var(--mmdvm);color:#000;border:none;border-radius:6px;font-family:var(--font-ui);font-weight:700;font-size:1rem;letter-spacing:.1em;text-transform:uppercase;padding:.65rem 2.5rem;cursor:pointer;transition:opacity .2s;}
.btn-save:hover{opacity:.85;}
.btn-note{font-family:var(--font-mono);font-size:.72rem;color:var(--text-dim);}
</style>
</head>
<body>
<header class="ctrl-header">
  <img src="Logo_ea3eiz.png" alt="EA3EIZ">
  <div>
    <h1>⚙ MMDVMHost Config</h1>
    <div class="path"><?= htmlspecialchars($iniPath) ?></div>
  </div>
  <a href="mmdvm.php" class="btn-back">← Volver</a>
</header>

<?php if ($msg): ?>
<div class="alert <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="POST">
<div class="body">
<?php
$skipSections = [''];
foreach ($parsed as $section => $lines) {
    if (in_array($section, $skipSections)) continue;
    $kvLines    = array_filter($lines, fn($l) => $l['type'] === 'kv');
    $commentLines = array_filter($lines, fn($l) => $l['type'] === 'comment' && trim($l['raw']) !== '');
    if (empty($kvLines) && empty($commentLines)) continue;

    echo '<div class="section-block">';
    if ($section !== '') echo '<div class="section-title">' . htmlspecialchars($section) . '</div>';

    foreach ($commentLines as $c) {
        $raw = trim($c['raw']);
        if ($raw !== '' && $raw !== '#') {
            echo '<div class="comment-line">' . htmlspecialchars($c['raw']) . '</div>';
        }
    }

    if (!empty($kvLines)) {
        echo '<div class="fields-grid">';
        foreach ($kvLines as $entry) {
            $sectionKey = str_replace(' ', '_', $section);
            $fieldKey   = $sectionKey . '__' . $entry['key'];
            $fieldKeyHtml = htmlspecialchars($fieldKey);
            $hint       = $entry['comment'] ?? '';
            $isLocked   = isset($lockedFields[$fieldKey]);

            // Si bloqueado, usar el valor forzado; si no, el del fichero
            $displayVal = $isLocked ? $lockedFields[$fieldKey] : $entry['value'];
            $val = htmlspecialchars($displayVal);

            $isBool = !is_numeric($displayVal)
                && in_array(strtolower($displayVal), ['true','false','yes','no','enable','disable','enabled','disabled']);

            echo '<div class="field-row' . ($isLocked ? ' locked' : '') . '">';
            echo '<label class="field-label">' . htmlspecialchars($entry['key']);
            if ($isLocked) echo ' <span class="lock-badge">🔒 forzado</span>';
            echo '</label>';

            if ($isLocked) {
                // Input visual readonly + hidden con valor forzado
                echo '<input type="text" value="' . $val . '" class="field-input" readonly tabindex="-1">';
                echo '<input type="hidden" name="' . $fieldKeyHtml . '" value="' . $val . '">';
                $lockMsg = ($fieldKey === 'D-Star__Enable') ? 'D-Star desactivado (forzado)' : 'DMR activado (forzado)';
                echo '<div class="lock-note">' . $lockMsg . '</div>';
            } elseif ($isBool) {
                echo '<select name="' . $fieldKeyHtml . '" class="field-input bool">';
                $opts = ['0','1','true','false','yes','no','enable','disable','enabled','disabled'];
                foreach ($opts as $o) {
                    $sel = strtolower($displayVal) === $o ? ' selected' : '';
                    echo '<option value="'.$o.'"'.$sel.'>'.$o.'</option>';
                }
                echo '</select>';
            } else {
                echo '<input type="text" name="' . $fieldKeyHtml . '" value="' . $val . '" class="field-input">';
            }

            if ($hint) echo '<div class="field-hint">' . htmlspecialchars($hint) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}
?>
</div>
<div class="btn-bar">
  <button type="submit" class="btn-save">💾 Guardar MMDVMHost.ini</button>
  <span class="btn-note">Reinicia el servicio mmdvmhost para aplicar los cambios</span>
</div>
</form>
</body>
</html>
