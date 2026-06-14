<?php
// ═══ VERIFICACIÓN DE ESTADO DE BRIDGES (Método robusto por PID) ═══
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    
    function isProcessRunningByPid($pidFile, $expectedString) {
        if (!file_exists($pidFile)) return false;
        $pid = trim(file_get_contents($pidFile));
        if (!is_numeric($pid) || $pid <= 0) return false;
        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline === false) return false;
        $cmdline = str_replace("\0", " ", $cmdline);
        return strpos($cmdline, $expectedString) !== false;
    }
    
    $status = [
        'dmr2ysf' => (
            isProcessRunningByPid('/tmp/MMDVMDMR2YSF.pid', 'MMDVMDMR2YSF') &&
            isProcessRunningByPid('/tmp/DMR2YSF.pid', 'DMR2YSF') &&
            isProcessRunningByPid('/tmp/YSFGateway.pid', 'YSFGateway')
        ),
        'ysf2dmr' => (
            isProcessRunningByPid('/tmp/MMDVMYSF2DMR.pid', 'MMDVMYSF2DMR') &&
            isProcessRunningByPid('/tmp/YSF2DMR.pid', 'YSF2DMR')
        ),
        'dmr2nxdn' => (
            isProcessRunningByPid('/tmp/MMDVMDMR2NXDN.pid', 'MMDVMDMR2NXDN') &&
            isProcessRunningByPid('/tmp/DMR2NXDN.pid', 'DMR2NXDN') &&
            isProcessRunningByPid('/tmp/NXDNGateway.pid', 'NXDNGateway')
        )
    ];
    
    echo json_encode($status);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BRIDGES</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23C0C0C0' viewBox='0 0 16 16'%3E%3Cpath d='M4.715 6.542 3.343 7.914a3 3 0 1 0 4.243 4.243l1.828-1.829A3 3 0 0 0 8.586 5.5L8 6.086a1 1 0 0 0-.154.199 2 2 0 0 1 .861 3.337L6.88 11.45a2 2 0 1 1-2.83-2.83l.793-.792a4 4 0 0 1-.128-1.287z'/%3E%3Cpath d='M6.586 4.672A3 3 0 0 0 7.414 9.5l.775-.776a2 2 0 0 1-.896-3.346L9.12 3.55a2 2 0 1 1 2.83 2.83l-.793.792c.112.42.155.855.128 1.287l1.372-1.372a3 3 0 1 0-4.243-4.243z'/%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark:   #0a0e14;
            --bg-surface:#111720;
            --border:    #1e2d3d;
            --cyan:      #00d4ff;
            --red:       #ff3b3b;
            --orange:    #ffa500;
            --granate:   #6b0f1a;
            --granate-light: #8b1a2a;
            --text:      #a8b9cc;
            --text-dim:  #4a5568;
            --green:     #00ff9f;
        }

        body {
            background: var(--bg-dark);
            color: var(--text);
            font-family: 'Rajdhani', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        
        @keyframes pulseWaves {
            0%,100% { opacity:.4; transform:translate(-50%,-50%) scale(1); }
            50%      { opacity:.8; transform:translate(-50%,-50%) scale(1.1); }
        }

        /* ── Navbar ── */
        .navbar-granate {
            background: linear-gradient(135deg, var(--granate) 0%, var(--granate-light) 100%);
            min-height: 60px;
            box-shadow: 0 2px 20px rgba(107,15,26,.5);
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .navbar-granate::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,204,204,.5), transparent);
        }
        .navbar-granate .navbar-brand img { height: 45px; transition: transform .3s; }
        .navbar-granate .navbar-brand img:hover { transform: scale(1.05); }

        /* ── Btn panel ── */
        .btn-panel {
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.3);
            color: #fff;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 600;
            letter-spacing: .05em;
            transition: all .3s;
        }
        .btn-panel:hover {
            background: rgba(255,255,255,.2);
            border-color: rgba(255,255,255,.5);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,255,255,.2);
        }

        .title-text {
            font-family: 'Orbitron', sans-serif;
            font-size: 3rem; font-weight: 900; letter-spacing: .3em;
            background: linear-gradient(135deg, #fff 0%, var(--cyan) 50%, #fff 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .title-sub {
            font-size: .95rem; color: var(--text-dim);
            letter-spacing: .4em; text-transform: uppercase;
        }

        /* ── Cards Bootstrap ── */
        .card {
            background-color: var(--bg-surface);
            border: 1px solid var(--border) !important;
        }
        .card-header { border-bottom: 1px solid rgba(255,255,255,.1); }
        .bridge-header-cyan   { background: linear-gradient(135deg, #0a4a5a 0%, #0d2d3d 100%); border-left: 4px solid var(--cyan); }
        .bridge-header-red    { background: linear-gradient(135deg, #4a0a0a 0%, #2d0d0d 100%); border-left: 4px solid var(--red); }
        .bridge-header-orange { background: linear-gradient(135deg, #4a2a00 0%, #2d1800 100%); border-left: 4px solid var(--orange); }
        .font-orbitron { font-family: 'Orbitron', sans-serif; letter-spacing: .08em; }
        .ls-wide { letter-spacing: .12em; }

        /* hover lift */
        /* .bridge-card { transition: transform .3s, box-shadow .3s; }
        .bridge-card:hover { transform: translateY(-6px); box-shadow: 0 12px 35px rgba(0,0,0,.5) !important; } */
        .bridge-card-cyan:hover   { border-color: var(--cyan) !important; }
        .bridge-card-red:hover    { border-color: var(--red) !important; }
        .bridge-card-orange:hover { border-color: var(--orange) !important; }

        /* badge estado */
        .status-badge {
            font-family: 'Orbitron', sans-serif;
            font-size: .62rem; font-weight: 700; letter-spacing: .1em;
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .25rem .75rem; border-radius: 20px;
            transition: all .3s;
        }
        .status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .status-badge.active  { background: rgba(0,255,159,.15); color: var(--green); border: 1px solid rgba(0,255,159,.3); }
        .status-badge.active::before  { background: var(--green); animation: statusPulse 2s infinite; }
        .status-badge.inactive{ background: rgba(255,59,59,.1); color: var(--red); border: 1px solid rgba(255,59,59,.2); }
        .status-badge.inactive::before{ background: var(--red); opacity: .6; }
        @keyframes statusPulse {
            0%,100% { opacity:1; box-shadow: 0 0 5px var(--green); }
            50%      { opacity:.5; box-shadow: 0 0 10px var(--green); }
        }

        /* footer */
        .footer {
            color: var(--text-dim);
            font-size: .85rem; letter-spacing: .1em;
            border-top: 1px solid var(--border);
        }
        .footer a { color: var(--cyan); text-decoration: none; transition: all .3s; }
        .footer a:hover { text-shadow: 0 0 10px var(--cyan); }

        @media (max-width: 768px) {
            .title-text { font-size: 2rem; letter-spacing: .2em; }
            .title-antenna i { font-size: 2.2rem; }
        }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar navbar-expand-md navbar-granate position-relative">
    <div class="container">
        <a class="navbar-brand" target="_blank" href="https://associacioader.com">
            <img src="Logo_Ader.png" alt="Logo">
        </a>
        <a href="mmdvm.php" class="btn btn-panel btn-sm ms-auto">
            <i class="bi bi-house-fill me-1"></i> Panel PHPPLUS
        </a>
    </div>
</nav>

<!-- ── Título ── -->
<div class="container py-5">
    <div class="text-center mb-5">
        <!-- <div class="title-antenna mb-4">
            <i class="bi bi-broadcast"></i>
        </div> -->
        <h1 class="title-text mb-2">BRIDGES</h1>
        <p class="title-sub mb-0">Transcoding · Digital Voice · Multi-Protocol</p>
    </div>

    <!-- ── Cards ── -->
    <div class="row g-4 justify-content-center">

        <!-- DMR2YSF -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 text-white shadow-lg bridge-card bridge-card-cyan">
                <!-- Header -->
                <div class="card-header d-flex align-items-center justify-content-between py-3 px-4 bridge-header-cyan">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="background:rgba(0,212,255,.15);border:1px solid rgba(0,212,255,.4);width:52px;height:52px;">
                            <i class="bi bi-broadcast-pin fs-4 text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 font-orbitron text-info"style="color:#fff;">DMR2YSF</div>
                            <div class="small text-uppercase ls-wide" style="color:#fff;letter-spacing:.12em;">DMR ↔ System Fusion</div>
                        </div>
                    </div>
                    <span class="badge rounded-pill status-badge inactive" id="badge-dmr2ysf">INACTIV</span>
                </div>
                <!-- Body -->
                <div class="card-body px-4 py-3 d-flex flex-column">
                    <p class="card-text mb-3" style="color:var(--text);">
                        Puente bidireccional entre la red <strong class="text-info">DMR</strong> y <strong class="text-info">YSF (Yaesu System Fusion)</strong>. Permite la comunicación entre ambos modos digitales.
                    </p>
                    <ul class="list-unstyled mb-0 mt-auto small" style="color:var(--text-dim);">
                        <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-info"></i> Transcoding bidireccional</li>
                        <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-info"></i> Compatible con reflectores YSF</li>
                        <li class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-info"></i> Mapeo de TalkGroups DMR</li>
                    </ul>
                </div>
                <!-- Footer -->
                <div class="card-footer bg-transparent border-top border-secondary px-4 pb-4 pt-3">
                    <a href="/dmr2ysf.php" class="btn btn-info w-100 fw-bold text-dark text-uppercase">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Acceder al Bridge
                    </a>
                </div>
            </div>
        </div>

        <!-- YSF2DMR -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 text-white shadow-lg bridge-card bridge-card-red">
                <!-- Header -->
                <div class="card-header d-flex align-items-center justify-content-between py-3 px-4 bridge-header-red">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="background:rgba(211, 200, 43, 0.15);border:1px solid rgba(255,59,59,.4);width:52px;height:52px;">
                            <i class="bi bi-broadcast-pin fs-4 text-danger"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 font-orbitron text-danger">YSF2DMR</div>
                            <div class="small text-uppercase ls-wide" style="color:#fff;letter-spacing:.12em;">System Fusion ↔ DMR</div>
                        </div>
                    </div>
                    <span class="badge rounded-pill status-badge inactive" id="badge-ysf2dmr">INACTIVO</span>
                </div>
                <!-- Body -->
                <div class="card-body px-4 py-3 d-flex flex-column">
                    <p class="card-text mb-3" style="color:var(--text);">
                        Puente bidireccional entre <strong class="text-danger">YSF (Yaesu System Fusion)</strong> y la red <strong class="text-danger">DMR</strong>. Conecta reflectores Fusion con talkgroups DMR.
                    </p>
                    <ul class="list-unstyled mb-0 mt-auto small" style="color:var(--text-dim);">
                        <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-danger"></i> Transcoding bidireccional</li>
                        <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-danger"></i> Compatible con reflectores DMR+</li>
                        <li class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-danger"></i> Soporte BrandMeister</li>
                    </ul>
                </div>
                <!-- Footer -->
                <div class="card-footer bg-transparent border-top border-secondary px-4 pb-4 pt-3">
                    <a href="/ysf2dmr.php" class="btn btn-danger w-100 fw-bold text-uppercase">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Acceder al Bridge
                    </a>
                </div>
            </div>
        </div>

        <!-- DMR2NXDN -->
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100 text-white shadow-lg bridge-card bridge-card-orange">
                <!-- Header -->
                <div class="card-header d-flex align-items-center justify-content-between py-3 px-4 bridge-header-orange">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center" style="background:rgba(255,165,0,.15);border:1px solid rgba(255,165,0,.4);width:52px;height:52px;">
                            <i class="bi bi-broadcast-pin fs-4 text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 font-orbitron text-warning">DMR2NXDN</div>
                            <div class="small text-uppercase ls-wide" style="color:#fff;letter-spacing:.12em;">DMR ↔ NXDN</div>
                        </div>
                    </div>
                    <span class="badge rounded-pill status-badge inactive" id="badge-dmr2nxdn">INACTIVO</span>
                </div>
                <!-- Body -->
                <div class="card-body px-4 py-3 d-flex flex-column">
                    <p class="card-text mb-3" style="color:var(--text);">
                        Conversor directo entre <strong class="text-warning">DMR</strong> y <strong class="text-warning">NXDN</strong>. Permite enlazar con reflectores NXDN de todo el mundo de forma sencilla.
                    </p>
                    <ul class="list-unstyled mb-0 mt-auto small" style="color:var(--text-dim);">
                        <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-warning"></i> Conversor directo NXDN</li>
                        <li class="d-flex align-items-center gap-2 mb-1"><i class="bi bi-check-circle-fill text-warning"></i> Reflectores NXDN mundiales</li>
                        <li class="d-flex align-items-center gap-2"><i class="bi bi-check-circle-fill text-warning"></i> Mapeo TG DMR ↔ Sala NXDN</li>
                    </ul>
                </div>
                <!-- Footer -->
                <div class="card-footer bg-transparent border-top border-secondary px-4 pb-4 pt-3">
                    <a href="/dmr2nxdn.php" class="btn btn-warning w-100 fw-bold text-dark text-uppercase">
                        <i class="bi bi-box-arrow-up-right me-2"></i>Acceder al Bridge
                    </a>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div><!-- /container -->

<!-- ── Footer ── -->
<footer class="footer text-center py-3 mt-5">
    <i class="bi bi-radio me-2"></i>
    Bridge Cards ADER 2026 | <a href="https://associacioader.com" target="_blank">Associacioader</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    async function checkStatus() {
        try {
            const response = await fetch('?action=check_status');
            const data = await response.json();
            const bridges = ['dmr2ysf', 'ysf2dmr', 'dmr2nxdn'];
            bridges.forEach(bridge => {
                const badge = document.getElementById(`badge-${bridge}`);
                if (data[bridge]) {
                    badge.className = 'status-badge active';
                    badge.textContent = 'ACTIVO';
                } else {
                    badge.className = 'status-badge inactive';
                    badge.textContent = 'INACTIVO';
                }
            });
        } catch (error) {
            console.error('Error al comprobar estado:', error);
        }
    }
    checkStatus();
    setInterval(checkStatus, 3000);
</script>
</body>
</html>
