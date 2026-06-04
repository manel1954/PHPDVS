
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Transcoding</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
<style>
body {
    background: #1a1a2e;
    font-family: 'Rajdhani', sans-serif;
    min-height: 100vh;
    padding: 0;
    margin: 0;
}
.page-header {
    background: #000000;
    border-bottom: 2px solid #fff;
    color: #fff;
}
.page-header-inner {
    max-width: 900px;
    width: 100%;
    margin: 0 auto;
    padding: .8rem 2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-sizing: border-box;
}
.page-header a {
    background: #1a2535;
    color: #b06090;
    border: 1px solid #b06090;
    font-size: .75rem;
    padding: .3rem .8rem;
    border-radius: 4px;
    text-decoration: none;
    font-family: 'Share Tech Mono', monospace;
    text-transform: uppercase;
    letter-spacing: .06em;
}
.page-header span {
    color: #ffffff;
    font-size: 1.2rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
}
.cards-row {
    display: flex;
    gap: 1.5rem;
    padding: 3rem 2rem;
    max-width: 900px;
    margin: 0 auto;
    flex-wrap: wrap;
}
.bridge-card {
    flex: 1;
    min-width: 260px;
    border-radius: 8px;
    padding: 2rem 1.8rem;
    cursor: pointer;
    text-decoration: none;
    display: block;
    position: relative;
    overflow: hidden;
    transition: transform .15s, box-shadow .15s;
    height: 140px;
}
.bridge-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 32px rgba(0,0,0,.4);
    text-decoration: none;
}
.bridge-card.dmr2ysf { background: #e74c3c; }
.bridge-card.ysf2dmr { background: #16a085; }
.bridge-card .card-number {
    font-size: 2.90rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    display: block;
}
.bridge-card .card-label {
    font-size: .90rem;
    font-weight: 500;
    color: rgba(255,255,255,.9);
    display: block;
    margin-top: .5rem;
    letter-spacing: .04em;
}
.bridge-card .card-icon {
    position: absolute;
    right: 1.5rem;
    bottom: 1.2rem;
    font-size: 2rem;
    color: #000;
    line-height: 1;
}
.bridge-card .card-subtitle {
    font-size: .85rem;
    color: rgba(255,255,255,.7);
    margin-top: .3rem;
    font-family: 'Share Tech Mono', monospace;
    letter-spacing: .04em;
}
.bridge-card.dmr2nxdn { background: #d4a017; cursor: not-allowed; }
.bridge-card.dmr2nxdn:hover { transform: none; box-shadow: none; }
.badge-soon { display: inline-block; background: rgba(0,0,0,.25); color: #fff; font-size: .65rem; font-family: 'Share Tech Mono', monospace; letter-spacing: .08em; text-transform: uppercase; padding: .15rem .5rem; border-radius: 3px; margin-top: .5rem; }
</style>

</head>
<body>

<div class="page-header">
<div class="page-header-inner">
  <a href="mmdvm.php" class="btn btn-primary btn-sm">← Panel PHPDVS</a>
  <span>🔗 Panel . Transcoding</span>
</div>
</div>

<div class="cards-row">

  <a href="dmr2ysf.php" class="bridge-card dmr2ysf">
    <span class="card-number">DMR</span>
    <span class="card-label">DMR2YSF</span>
    <span class="card-subtitle">Cross-mode Bridge</span>
    <span class="card-icon" style="color:#000">⇄</span>
  </a>

  <a href="ysf2dmr.php" class="bridge-card ysf2dmr">
    <span class="card-number">YSF</span>
    <span class="card-label">YSF2DMR</span>
    <span class="card-subtitle">Cross-mode Bridge</span>
    <span class="card-icon" style="color:#000">⇄</span>
  </a>

  <div class="bridge-card dmr2nxdn">
    <span class="card-number">DMR</span>
    <span class="card-label">DMR2NXDN</span>
    <span class="card-subtitle">Cross-mode Bridge</span>
    <span class="badge-soon">Próximamente</span>
    <span class="card-icon" style="color:#000">⇄</span>
  </div>

</div>

</body>
</html>
