<?php
// mis_enlaces.php  –  Panel de enlaces rápidos
// Posicionado explícito en CSS Grid usando fila/columna del JSON

define('JSON_FILE', '/home/pi/.local/enlaces.json');

$enlaces = [];
if (file_exists(JSON_FILE)) {
    $enlaces = json_decode(file_get_contents(JSON_FILE), true) ?? [];
}

// Calcular dimensiones del grid desde los datos
$maxCol  = 3;
$maxFila = 1;
foreach ($enlaces as $e) {
    $maxCol  = max($maxCol,  (int)($e['columna'] ?? 1));
    $maxFila = max($maxFila, (int)($e['fila']    ?? 1));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIS ENLACES</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:     #1e1e1e;
            --dim:    #888;
            --radius: 4px;
            --cols:   <?= $maxCol ?>;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: #e0e0e0;
            font-family: 'Share Tech Mono', monospace;
            min-height: 100vh;
            padding: 0 0 40px;
        }

        /* ── Header ── */
        header {
            background: linear-gradient(135deg, #111 0%, #1c1c1c 100%);
            border-bottom: 2px solid #444;
            padding: 20px 36px 16px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .logo-badge {
            font-family: 'Orbitron', sans-serif;
            font-size: 10px; font-weight: 700;
            color: #00e5ff;
            background: rgba(0,229,255,.08);
            border: 1px solid rgba(0,229,255,.3);
            border-radius: 3px;
            padding: 4px 10px; letter-spacing: 2px;
        }
        header h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px; font-weight: 900;
            letter-spacing: 6px; color: #fff;
        }
        .callsign {
            font-family: 'Orbitron', sans-serif;
            font-size: 12px; color: #00e5ff;
            letter-spacing: 3px; opacity: .65;
        }
        .btn-editor {
            margin-left: auto;
            font-family: 'Orbitron', sans-serif;
            font-size: 9px; letter-spacing: 2px;
            padding: 6px 14px; border-radius: var(--radius);
            border: 1px solid #444; background: #252525; color: #888;
            text-decoration: none; transition: all .2s; white-space: nowrap;
        }
        .btn-editor:hover { background: #333; color: #fff; border-color: #00e5ff55; }

        /* ── Buscador ── */
        .search-wrap {
            padding: 14px 28px 6px;
            display: flex; align-items: center; gap: 10px;
        }
        .search-wrap input {
            flex: 1; max-width: 400px;
            background: #222; border: 1px solid #444;
            border-radius: var(--radius); color: #ccc;
            font-family: 'Share Tech Mono', monospace; font-size: 13px;
            padding: 7px 13px; outline: none; transition: border-color .2s;
        }
        .search-wrap input:focus { border-color: #00e5ff66; }
        #contador { font-size: 11px; color: var(--dim); white-space: nowrap; }

        /* ── Grid con posicionado explícito ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(<?= $maxCol ?>, 1fr);
            grid-template-rows: repeat(<?= $maxFila ?>, auto);
            gap: 5px 8px;
            padding: 12px 28px;
        }

        /* ── Botones ── */
        .btn-link {
            display: block;
            padding: 7px 10px;
            border: none; border-radius: var(--radius);
            font-family: 'Share Tech Mono', monospace; font-size: 11.5px;
            text-align: center; text-decoration: none;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            cursor: pointer;
            transition: filter .15s, transform .1s, box-shadow .15s;
            position: relative;
        }
        .btn-link:hover {
            filter: brightness(1.35);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(0,0,0,.55);
            z-index: 2;
        }
        .btn-link:active { transform: translateY(0); filter: brightness(1.1); }

        .btn-local { cursor: not-allowed; opacity: .5; }
        .btn-local::after {
            content: '⚠ local';
            position: absolute; right: 6px; top: 50%;
            transform: translateY(-50%);
            font-size: 9px; opacity: .7;
        }

        /* Botón de comando local ejecutable */
        .btn-cmd { cursor: pointer; border: none; }
        .btn-cmd::after {
            content: '⚡ cmd';
            position: absolute; right: 6px; top: 50%;
            transform: translateY(-50%);
            font-size: 9px; opacity: .7;
        }
        .btn-cmd.running { opacity: .6; cursor: wait; }
        .btn-cmd.ok-flash { filter: brightness(1.6); }

        /* Celda vacía – ocupa el espacio pero no se ve */
        .cell-empty { visibility: hidden; }

        /* Ocultar al filtrar */
        .btn-wrap.hidden .btn-link { opacity: .1; pointer-events: none; }

        /* ── Toast ── */
        #toast {
            position: fixed; bottom: 22px; right: 22px; z-index: 9999;
            background: #1e1e1e; border: 1px solid #444; border-radius: 5px;
            font-family: 'Share Tech Mono', monospace; font-size: 12px;
            padding: 10px 18px; color: #ccc; max-width: 380px;
            transform: translateY(12px); opacity: 0;
            transition: all .3s; pointer-events: none; line-height: 1.5;
        }
        #toast.show { transform: translateY(0); opacity: 1; }
        #toast.ok   { border-color: #43a047; color: #81c784; }
        #toast.err  { border-color: #e53935; color: #ef9a9a; }

        /* ── Footer ── */
        footer { margin-top: 24px; display: flex; justify-content: center; }
        .btn-salir {
            font-family: 'Orbitron', sans-serif;
            font-size: 11px; letter-spacing: 3px;
            padding: 9px 40px; border-radius: var(--radius);
            border: none; cursor: pointer;
            background: #c0392b; color: white;
            transition: background .2s, transform .1s;
            text-decoration: none;
        }
        .btn-salir:hover { background: #27ae60; transform: scale(1.04); }

        /* ── Responsive ── */
        @media (max-width: 700px) {
            .grid { grid-template-columns: repeat(2, 1fr) !important; }
            header h1 { font-size: 15px; letter-spacing: 3px; }
        }
        @media (max-width: 440px) {
            .grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

<header>
    <div class="logo-badge">PANEL</div>
    <h1>MIS ENLACES</h1>
    <span class="callsign"></span>
    <a href="editor_enlaces.php" class="btn-editor">✏ EDITOR</a>
</header>

<div class="search-wrap">
    <span>🔍</span>
    <input type="text" id="buscar" placeholder="Filtrar enlaces..." autocomplete="off" oninput="filtrar(this.value)">
    <span id="contador"></span>
</div>

<div class="grid" id="grid">
<?php
// Construir mapa de posiciones para detectar celdas vacías
$ocupadas = [];
foreach ($enlaces as $e) {
    $f = (int)($e['fila']    ?? 1);
    $c = (int)($e['columna'] ?? 1);
    $ocupadas["$f,$c"] = true;
}

// Renderizar: primero los botones reales con posición explícita
foreach ($enlaces as $idx => $e):
    $rawUrl  = $e['url'] ?? '';
    $esCmd   = (strpos($rawUrl, 'cmd:') === 0) || (strpos($rawUrl, ' ') !== false);
    $cmdText = $esCmd ? htmlspecialchars(strpos($rawUrl,'cmd:')===0 ? trim(substr($rawUrl,4)) : $rawUrl) : '';
    $nombre  = htmlspecialchars($e['nombre'] ?? '');
    $url     = htmlspecialchars($rawUrl);
    $bg      = htmlspecialchars($e['bg']     ?? '#333');
    $fg      = htmlspecialchars($e['fg']     ?? '#fff');
    $local   = !empty($e['local']);
    $fila    = (int)($e['fila']    ?? 1);
    $col     = (int)($e['columna'] ?? 1);
    $key     = strtolower($e['nombre'] ?? '');
    $style   = "grid-row:$fila;grid-column:$col;";
?>
    <div class="btn-wrap" data-nombre="<?= htmlspecialchars($key) ?>" style="<?= $style ?>">
        <?php if ($esCmd): ?>
            <button class="btn-link btn-cmd"
                    style="background:<?= $bg ?>;color:<?= $fg ?>;"
                    title="Ejecutar: <?= $cmdText ?>"
                    onclick="ejecutarCmd(this, <?= json_encode(strpos($rawUrl,'cmd:')===0 ? trim(substr($rawUrl,4)) : $rawUrl) ?>)">
                <?= $nombre ?>
            </button>
        <?php elseif ($local || $rawUrl === ''): ?>
            <span class="btn-link btn-local"
                  style="background:<?= $bg ?>;color:<?= $fg ?>;"
                  title="Acción local – no disponible en web">
                <?= $nombre ?>
            </span>
        <?php else: ?>
            <a class="btn-link"
               href="<?= $url ?>"
               target="_blank" rel="noopener noreferrer"
               style="background:<?= $bg ?>;color:<?= $fg ?>;"
               title="<?= $url ?>">
                <?= $nombre ?>
            </a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php
// Rellenar celdas vacías para que el grid quede uniforme
for ($f = 1; $f <= $maxFila; $f++):
    for ($c = 1; $c <= $maxCol; $c++):
        if (!isset($ocupadas["$f,$c"])): ?>
    <div class="cell-empty" style="grid-row:<?= $f ?>;grid-column:<?= $c ?>"></div>
        <?php endif;
    endfor;
endfor;
?>
</div>

<footer>
    <a href="mmdvm.php" class="btn-salir btn-header red">✕ &nbsp;CERRAR</a>

</footer>

<script>
    const items  = document.querySelectorAll('.btn-wrap');
    const total  = <?= count($enlaces) ?>;

    function actualizar() {
        let visible = 0;
        items.forEach(el => { if (!el.classList.contains('hidden')) visible++; });
        document.getElementById('contador').textContent =
            (visible < total ? visible + ' de ' : '') + total + ' enlaces';
    }

    function filtrar(q) {
        q = q.toLowerCase().trim();
        items.forEach(el => {
            el.classList.toggle('hidden', q !== '' && !el.dataset.nombre.includes(q));
        });
        actualizar();
    }

    actualizar();

    let toastTimer;
    function showToast(msg, type) {
        const el = document.getElementById('toast');
        el.innerHTML = msg;
        el.className = 'show ' + (type || '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.className = '', 4000);
    }

    async function ejecutarCmd(btn, cmd) {
        btn.classList.add('running');
        showToast('⏳ Lanzando: <b>' + cmd.substring(0,60) + '</b>…', '');
        try {
            const r = await fetch('ejecutar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cmd })
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            btn.classList.remove('running');
            if (d.ok) {
                btn.classList.add('ok-flash');
                setTimeout(() => btn.classList.remove('ok-flash'), 800);
                const logInfo = d.log && d.log !== '(sin salida inmediata — proceso en marcha)'
                    ? '<br><small style="opacity:.7">' + d.log.substring(0,120) + '</small>'
                    : '';
                showToast('✅ ' + d.msg + logInfo, 'ok');
            } else {
                showToast('❌ Error: ' + d.msg, 'err');
            }
        } catch(e) {
            btn.classList.remove('running');
            showToast('❌ No se pudo conectar con ejecutar.php<br><small>' + e.message + '</small>', 'err');
        }
    }
</script>
<div id="toast"></div>
</body>
</html>
