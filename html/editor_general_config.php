<?php
// ============================================================
//  EDITOR GENERAL CONFIG – Panel ADER
// ============================================================
session_start();

$INI_FILES = [
    'MMDVMHost'    => '/home/pi/MMDVMHost/MMDVMHost.ini',
    'MMDVMYSF'     => '/home/pi/MMDVMHost/MMDVMYSF.ini',
    'MMDVMDSTAR'   => '/home/pi/MMDVMHost/MMDVMDSTAR.ini',
    'MMDVMNXDN'    => '/home/pi/MMDVMHost/MMDVMNXDN.ini',
];

// ── Mapa: campo => [ [file_key, section, ini_key], ... ]
// Usamos arrays de arrays para poder repetir el mismo fichero
// con distintas secciones (DStarGateway aparece 3 veces).
// Para cada campo se intentará escribir en todos los ficheros donde la
// clave ya exista (ini_write_batch respeta la flag $only_if_exists=true
// para los ficheros que no sean MMDVMHost).
$WRITE_MAP = [
    'Callsign'    => [
        ['MMDVMHost',  'General', 'Callsign'],
        ['MMDVMYSF',   'General', 'Callsign'],
        ['MMDVMDSTAR', 'General', 'Callsign'],
        ['MMDVMNXDN',  'General', 'Callsign'],
    ],
    'Id'          => [
        ['MMDVMHost',  'General', 'Id'],
        ['MMDVMYSF',   'General', 'Id'],
        ['MMDVMDSTAR', 'General', 'Id'],
        ['MMDVMNXDN',  'General', 'Id'],
    ],
    'RXFrequency' => [
        ['MMDVMHost',  'Info', 'RXFrequency'],
        ['MMDVMYSF',   'Info', 'RXFrequency'],
        ['MMDVMDSTAR', 'Info', 'RXFrequency'],
        ['MMDVMNXDN',  'Info', 'RXFrequency'],
    ],
    'TXFrequency' => [
        ['MMDVMHost',  'Info', 'TXFrequency'],
        ['MMDVMYSF',   'Info', 'TXFrequency'],
        ['MMDVMDSTAR', 'Info', 'TXFrequency'],
        ['MMDVMNXDN',  'Info', 'TXFrequency'],
    ],
    'Latitude'    => [
        ['MMDVMHost',  'Info', 'Latitude'],
        ['MMDVMYSF',   'Info', 'Latitude'],
        ['MMDVMDSTAR', 'Info', 'Latitude'],
        ['MMDVMNXDN',  'Info', 'Latitude'],
    ],
    'Longitude'   => [
        ['MMDVMHost',  'Info', 'Longitude'],
        ['MMDVMYSF',   'Info', 'Longitude'],
        ['MMDVMDSTAR', 'Info', 'Longitude'],
        ['MMDVMNXDN',  'Info', 'Longitude'],
    ],
    'Location'    => [
        ['MMDVMHost',  'Info', 'Location'],
        ['MMDVMYSF',   'Info', 'Location'],
        ['MMDVMDSTAR', 'Info', 'Location'],
        ['MMDVMNXDN',  'Info', 'Location'],
    ],
    'URL'         => [
        ['MMDVMHost',  'Info', 'URL'],
        ['MMDVMYSF',   'Info', 'URL'],
        ['MMDVMDSTAR', 'Info', 'URL'],
        ['MMDVMNXDN',  'Info', 'URL'],
    ],
];

// ── Para leer valores del formulario (primer fichero que tenga el campo)
$READ_MAP = [
    'Callsign'    => ['MMDVMHost',    'General',    'Callsign'],
    'Id'          => ['MMDVMHost',    'General',    'Id'],
    'RXFrequency' => ['MMDVMHost',    'Info',       'RXFrequency'],
    'TXFrequency' => ['MMDVMHost',    'Info',       'TXFrequency'],
    'Latitude'    => ['MMDVMHost',    'Info',       'Latitude'],
    'Longitude'   => ['MMDVMHost',    'Info',       'Longitude'],
    'Location'    => ['MMDVMHost',    'Info',       'Location'],
    'URL'         => ['MMDVMHost',    'Info',       'URL'],
];

// ============================================================
//  Funciones INI
// ============================================================

function ini_read_value(string $filepath, string $section, string $key): ?string {
    if (!file_exists($filepath)) return null;
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $in_section = ($section === ''); // sección vacía = antes del primer [header]
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            $in_section = (strcasecmp(trim($m[1]), $section) === 0);
            continue;
        }
        if ($in_section && $trimmed !== '' && $trimmed[0] !== ';' && $trimmed[0] !== '#') {
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*(.*)$/i', $trimmed, $m)) {
                return trim($m[1]);
            }
        }
    }
    return null;
}

/**
 * Escribe MÚLTIPLES (sección, clave, valor) en un solo fichero con
 * UNA ÚNICA lectura y escritura. Así no se sobreescriben los cambios.
 *
 * $changes = [ ['Gateway','Callsign','EA3EIY'], ['Repeater 1','Callsign','EA3EIY'], ... ]
 *
 * Retorna array de errores (vacío = todo OK).
 */
function ini_write_batch(string $filepath, array $changes, bool $only_if_exists = false): array {
    $errors = [];

    if (!file_exists($filepath))  { return ["❌ No encontrado: $filepath"]; }
    if (!is_writable($filepath))  {
        $owner = posix_getpwuid(fileowner($filepath))['name'] ?? '?';
        $proc  = posix_getpwuid(posix_geteuid())['name'] ?? '?';
        return ["❌ Sin escritura: $filepath (propietario=$owner proceso=$proc)"];
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return ["❌ No se pudo leer: $filepath"];

    // Construir índice: clave = "SECTION\tKEY" (minúsculas) => índice en $changes
    $pending = [];   // "section\tkey" => índice en $changes
    foreach ($changes as $i => [$sec, $key, $val]) {
        $pending[strtolower($sec) . "\t" . strtolower($key)] = $i;
    }

    $output       = [];
    $cur_section  = '';   // sección actual mientras recorremos
    $found        = [];   // índices ya escritos
    $section_line = [];   // índice en $output donde está cada [Section]

    foreach ($lines as $raw) {
        $trimmed = trim($raw);

        // Cabecera de sección
        if (preg_match('/^\[(.+)\]$/', $trimmed, $m)) {
            $cur_section = trim($m[1]);
            $output[] = $raw;
            $section_line[strtolower($cur_section)] = count($output) - 1;
            continue;
        }

        // ¿Es una clave que debemos reemplazar?
        if ($trimmed !== '' && $trimmed[0] !== ';' && $trimmed[0] !== '#') {
            $pkey = strtolower($cur_section) . "\t" . strtolower(preg_split('/\s*=/', $trimmed, 2)[0]);
            if (isset($pending[$pkey]) && !in_array($pending[$pkey], $found)) {
                $idx = $pending[$pkey];
                [, $key, $val] = $changes[$idx];
                $output[] = "$key=$val";
                $found[] = $idx;
                continue;  // descarta la línea original
            }
        }

        $output[] = $raw;
    }

    // Claves no encontradas → añadir al final de su sección (o crear sección nueva)
    foreach ($changes as $i => [$sec, $key, $val]) {
        if (in_array($i, $found)) continue;

        $skey = strtolower($sec);
        if (isset($section_line[$skey])) {
            // Si only_if_exists y la clave no estaba en el fichero → saltar
            if ($only_if_exists) continue;
            // Insertar justo después de la última línea de esa sección
            $insert_after = $section_line[$skey];
            for ($j = $insert_after + 1; $j < count($output); $j++) {
                $t = trim($output[$j]);
                if (preg_match('/^\[(.+)\]$/', $t)) break; // próxima sección
                $insert_after = $j;
            }
            array_splice($output, $insert_after + 1, 0, ["$key=$val"]);
            // Actualizar section_line para los siguientes inserts
            foreach ($section_line as &$sl) {
                if ($sl > $insert_after) $sl++;
            }
            unset($sl);
        } else {
            // La sección no existe → crearla al final
            $output[] = '';
            $output[] = "[$sec]";
            $output[] = "$key=$val";
            $section_line[$skey] = count($output) - 3;
        }
    }

    $bytes = file_put_contents($filepath, implode(PHP_EOL, $output) . PHP_EOL);
    if ($bytes === false) $errors[] = "❌ file_put_contents falló: $filepath";
    return $errors;
}

function read_form_values(array $read_map, array $ini_files): array {
    $values = [];
    foreach ($read_map as $field => [$file_key, $section, $key]) {
        $path = $ini_files[$file_key] ?? null;
        $values[$field] = ($path ? ini_read_value($path, $section, $key) : null) ?? '';
    }
    return $values;
}



// ============================================================
//  Procesar POST – guardar valores (escritura en batch por fichero)
// ============================================================
$messages    = [];
$form_values = read_form_values($READ_MAP, $INI_FILES);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $post_fields = [
        'Callsign',
        'Id', 'RXFrequency', 'TXFrequency',
        'Latitude', 'Longitude', 'Location', 'URL'
    ];

    // Agrupar cambios por fichero → UNA escritura por fichero
    // $batch[$filepath][] = [section, key, value]
    $batch = [];
    foreach ($post_fields as $field) {
        $new_val = trim($_POST[$field] ?? '');
        $form_values[$field] = $new_val;
        foreach ($WRITE_MAP[$field] ?? [] as [$file_key, $section, $key]) {
            $path = $INI_FILES[$file_key] ?? null;
            if (!$path) continue;
            $batch[$path][] = [$section, $key, $new_val];
        }
    }

    $errors  = [];
    $written = [];
    $mmdvmhost_path = $INI_FILES['MMDVMHost'];
    foreach ($batch as $path => $changes) {
        // Para MMDVMHost siempre crea la clave si no existe.
        // Para el resto, solo actualiza claves que ya estén en el fichero.
        $only_if_exists = ($path !== $mmdvmhost_path);
        $errs = ini_write_batch($path, $changes, $only_if_exists);
        if (empty($errs)) {
            $written[] = basename($path) . " (" . count($changes) . " campos)";
        } else {
            $errors = array_merge($errors, $errs);
        }
    }

    if (empty($errors)) {
        $messages[] = ['type' => 'success', 'text' => "✅ Guardado en " . count($written) . " fichero(s): " . implode(' · ', $written)];
    } else {
        foreach ($errors as $e) {
            $messages[] = ['type' => 'danger', 'text' => $e];
        }
        if (!empty($written)) {
            $messages[] = ['type' => 'warning', 'text' => "⚠️ Escrito parcialmente en: " . implode(', ', $written)];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editor General</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
:root {
    --bg-base:    #0d1117;
    --bg-card:    #161b22;
    --bg-input:   #1c2330;
    --cyan:       #00e5ff;
    --amber:      #ffb300;
    --violet:     #bf00ff;
    --green:      #00e676;
    --red:        #ff1744;
    --text-main:  #e0e6f0;
    --text-muted: #8892a4;
    --border:     #2a3447;
    --font-hud:   'Orbitron', sans-serif;
    --font-ui:    'Rajdhani', sans-serif;
    --font-mono:  'Share Tech Mono', monospace;
}
* { box-sizing: border-box; }
body { background: var(--bg-base); color: var(--text-main); font-family: var(--font-ui); min-height: 100vh; }

.page-header {
    background: linear-gradient(135deg,#0d1117,#161b22);
    border-bottom: 1px solid var(--cyan);
    padding: 1.25rem 2rem;
}
.page-header-inner {
    max-width: 960px;
    margin: 0 auto;
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
}
.page-header h1 { font-family: var(--font-hud); font-size: 1.4rem; color: var(--cyan); margin: 0; letter-spacing:.1em; text-shadow: 0 0 12px rgba(0,229,255,.45); }
.badge-subtitle { font-family: var(--font-mono); font-size:.7rem; color:var(--text-muted); background:rgba(0,229,255,.08); border:1px solid rgba(0,229,255,.2); border-radius:4px; padding:2px 8px; }
.btn-back { font-family:var(--font-hud); font-size:.7rem; letter-spacing:.05em; color:var(--text-muted); border:1px solid var(--border); background:transparent; border-radius:6px; padding:5px 12px; text-decoration:none; transition:all .2s; }
.btn-back:hover { color:var(--cyan); border-color:var(--cyan); }

.ader-card { background:var(--bg-card); border:1px solid var(--border); border-radius:10px; padding:1.5rem; margin-bottom:1.5rem; }
.ader-card-title { font-family:var(--font-hud); font-size:.85rem; letter-spacing:.12em; color:var(--cyan); margin-bottom:1.25rem; display:flex; align-items:center; gap:.5rem; }
.ader-card-title::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,var(--cyan),transparent); opacity:.35; margin-left:.5rem; }

.form-label { font-family:var(--font-mono); font-size:.78rem; color:var(--text-muted); margin-bottom:.3rem; }
.badge-ini { font-size:.62rem; background:rgba(0,229,255,.1); color:var(--cyan); border:1px solid rgba(0,229,255,.25); border-radius:3px; padding:1px 5px; margin-left:4px; vertical-align:middle; }
.badge-ini-dstar { font-size:.62rem; background:rgba(191,0,255,.1); color:var(--violet); border:1px solid rgba(191,0,255,.3); border-radius:3px; padding:1px 5px; margin-left:4px; vertical-align:middle; }
.form-control { background:var(--bg-input)!important; border:1px solid var(--border)!important; color:var(--text-main)!important; font-family:var(--font-mono); font-size:.9rem; border-radius:6px; transition:border-color .2s, box-shadow .2s; }
.form-control:focus { border-color:var(--cyan)!important; box-shadow:0 0 0 3px rgba(0,229,255,.15)!important; outline:none; }
.form-control.dstar-field:focus { border-color:var(--violet)!important; box-shadow:0 0 0 3px rgba(191,0,255,.15)!important; }
.form-control::placeholder { color:#4a5568; }
.form-hint { font-size:.72rem; color:var(--text-muted); font-family:var(--font-mono); margin-top:.25rem; }

.section-label { font-family:var(--font-hud); font-size:.65rem; letter-spacing:.15em; color:var(--text-muted); text-transform:uppercase; margin:1.5rem 0 .75rem; display:flex; align-items:center; gap:.5rem; }
.section-label::before { content:''; display:inline-block; width:3px; height:12px; background:var(--cyan); border-radius:2px; }
.section-label.dstar::before { background:var(--violet); }

.btn-save { font-family:var(--font-hud); font-size:.8rem; letter-spacing:.1em; background:var(--cyan); color:#000; border:none; border-radius:8px; padding:.6rem 2rem; cursor:pointer; transition:all .2s; box-shadow:0 0 16px rgba(0,229,255,.3); }
.btn-save:hover { background:#fff; box-shadow:0 0 24px rgba(0,229,255,.6); }

.ader-alert { border-radius:7px; padding:.75rem 1rem; margin-bottom:.75rem; font-family:var(--font-mono); font-size:.82rem; border-left:3px solid transparent; }
.ader-alert-success { background:rgba(0,230,118,.1); border-color:var(--green); color:var(--green); }
.ader-alert-danger  { background:rgba(255,23,68,.1);  border-color:var(--red);   color:var(--red); }
.ader-alert-warning { background:rgba(255,179,0,.1);  border-color:var(--amber); color:var(--amber); }

.fst { width:100%; border-collapse:collapse; font-family:var(--font-mono); font-size:.73rem; }
.fst th { color:var(--text-muted); border-bottom:1px solid var(--border); padding:.35rem .5rem; text-align:left; font-weight:normal; }
.fst td { padding:.35rem .5rem; border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; }
.pill { display:inline-block; border-radius:4px; padding:1px 7px; font-size:.62rem; font-weight:600; }
.pill-ok   { background:rgba(0,230,118,.15); color:var(--green); border:1px solid rgba(0,230,118,.3); }
.pill-warn { background:rgba(255,179,0,.12); color:var(--amber); border:1px solid rgba(255,179,0,.3); }
.pill-err  { background:rgba(255,23,68,.12);  color:var(--red);   border:1px solid rgba(255,23,68,.3); }

/* Diagnóstico */
.diag-box { background:#0d1117; border:1px solid var(--violet); border-radius:8px; padding:1rem; font-family:var(--font-mono); font-size:.72rem; color:var(--text-muted); margin-top:.75rem; white-space:pre-wrap; word-break:break-all; max-height:340px; overflow-y:auto; }
.btn-diag { font-family:var(--font-hud); font-size:.72rem; letter-spacing:.08em; background:transparent; color:var(--violet); border:1px solid var(--violet); border-radius:6px; padding:4px 14px; cursor:pointer; transition:all .2s; }
.btn-diag:hover { background:rgba(191,0,255,.15); }
.btn-diag.running { opacity:.5; cursor:wait; }
</style>
</head>
<body>

<div class="page-header">
    <div class="page-header-inner" style="display:flex; flex-direction:column; align-items:center; text-align:center; gap:12px;">
        
    <div style="display:flex; align-items:center; gap:16px;">
            <h1><i class="bi bi-sliders"></i> 🍓&nbsp;EDITOR GENERAL</h1>
            <a href="mmdvm.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
            </a>
        </div>
        <span class="badge-subtitle">MMDVMHost · YSF · D-STAR · NXDN</span>
    </div>
</div>

<div class="container py-4">
<div class="row g-4">

    <div class="col-lg-8 mx-auto">

        <?php foreach ($messages as $msg): ?>
        <div class="ader-alert ader-alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
        <?php endforeach; ?>

        <div class="ader-card">
            <div class="ader-card-title"><i class="bi bi-pencil-square"></i> CONFIGURACIÓN GENERAL</div>

            <form method="POST" autocomplete="off">

                <div class="section-label">Identificación de estación</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Callsign <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="Callsign" class="form-control"
                               value="<?= htmlspecialchars($form_values['Callsign']) ?>"
                               placeholder="Ej: EA3EIZ" maxlength="20">
                        <div class="form-hint">[General] Callsign= en los 4 ficheros</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">DMR Id <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="Id" class="form-control"
                               value="<?= htmlspecialchars($form_values['Id']) ?>"
                               placeholder="Ej: 214317526" pattern="[0-9]*">
                        <div class="form-hint">[General] Id= en los 4 ficheros (si existe)</div>
                    </div>
                </div>
                </div>

                <div class="section-label">Frecuencias</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">RX Frecuencia (Hz) <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="RXFrequency" class="form-control"
                               value="<?= htmlspecialchars($form_values['RXFrequency']) ?>"
                               placeholder="Ej: 430500000" pattern="[0-9]*">
                        <div class="form-hint">[Info] RXFrequency= en los 4 ficheros (si existe)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">TX Frecuencia (Hz) <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="TXFrequency" class="form-control"
                               value="<?= htmlspecialchars($form_values['TXFrequency']) ?>"
                               placeholder="Ej: 430500000" pattern="[0-9]*">
                        <div class="form-hint">[Info] TXFrequency= en los 4 ficheros (si existe)</div>
                    </div>
                </div>

                <div class="section-label">Posición geográfica</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Latitud <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="Latitude" class="form-control"
                               value="<?= htmlspecialchars($form_values['Latitude']) ?>"
                               placeholder="Ej: 41.3851">
                        <div class="form-hint">[Info] Latitude= en los 4 ficheros (si existe)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Longitud <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="Longitude" class="form-control"
                               value="<?= htmlspecialchars($form_values['Longitude']) ?>"
                               placeholder="Ej: 2.1734">
                        <div class="form-hint">[Info] Longitude= en los 4 ficheros (si existe)</div>
                    </div>
                </div>

                <div class="section-label">Información pública</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Location <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="Location" class="form-control"
                               value="<?= htmlspecialchars($form_values['Location']) ?>"
                               placeholder="Ej: Barcelona, Spain">
                        <div class="form-hint">[Info] Location= en los 4 ficheros (si existe)</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL <span class="badge-ini">Los 4 ficheros</span></label>
                        <input type="text" name="URL" class="form-control"
                               value="<?= htmlspecialchars($form_values['URL']) ?>"
                               placeholder="Ej: www.associacioader.com">
                        <div class="form-hint">[Info] URL= en los 4 ficheros (si existe)</div>
                    </div>
                </div>

                <hr style="border-color:var(--border);margin:1.5rem 0;">
                <div class="d-flex align-items-center gap-3">
                    <button type="submit" name="save_config" class="btn-save">
                        <i class="bi bi-floppy"></i> &nbsp;GUARDAR CONFIGURACIÓN
                    </button>
                    <span style="font-family:var(--font-mono);font-size:.72rem;color:var(--text-muted);">
                        Los ficheros se actualizan al instante
                    </span>
                </div>
            </form>
        </div>
    </div><!-- /col-lg-8 -->
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

            out += `🔍 [General]   Callsign = ${d.callsign_found !== null ? d.callsign_found : '⚠️ NO ENCONTRADO'}\n`;

            out += `✏️ Test escritura: ${d.write_test || '—'}\n\n`;

            if (d.raw_head) {
                out += `── Primeras 30 líneas del fichero ──\n${d.raw_head}`;
            }

            box.textContent = out;
            box.style.color = d.writable && d.write_test && d.write_test.startsWith('OK')
                ? 'var(--green)' : 'var(--amber)';
        })
        .catch(e => {
            box.textContent = '❌ Error al ejecutar diagnóstico: ' + e;
            box.style.color = 'var(--red)';
        })
        .finally(() => {
            btn.classList.remove('running');
            btn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> &nbsp;Repetir diagnóstico';
        });
}
</script>
</body>
</html>
