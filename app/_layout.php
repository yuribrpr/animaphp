<?php
if (!isset($user) || !is_array($user)) {
    throw new RuntimeException('Layout precisa de $user');
}

$pageTitle = isset($pageTitle) ? (string)$pageTitle : '';
$bodyClass = isset($bodyClass) ? (string)$bodyClass : 'hold-transition text-sm sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed';
$extraCss = isset($extraCss) && is_array($extraCss) ? $extraCss : [];
$extraJs = isset($extraJs) && is_array($extraJs) ? $extraJs : [];
$inlineJs = isset($inlineJs) ? (string)$inlineJs : '';
$renderContent = isset($renderContent) && is_callable($renderContent) ? $renderContent : null;
$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');

if (!$renderContent) {
    throw new RuntimeException('Layout precisa de $renderContent callable');
}

$activeHome = str_starts_with($scriptName, '/app/home.php');
$activeAdminAnimas = str_starts_with($scriptName, '/app/admin/animas.php');
$activeAdmin = $activeAdminAnimas;

$flashError = get_flash('error');
$flashSuccess = get_flash('success');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?><?= $pageTitle !== '' ? ' | ' . e($pageTitle) : '' ?></title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="/dist/css/adminlte.min.css">
  <?php foreach ($extraCss as $href): ?>
    <link rel="stylesheet" href="<?= e((string)$href) ?>">
  <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="/app/home.php" class="nav-link">Home</a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <!-- Theme Toggle -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#" role="button" title="Tema">
          <i id="theme-icon" class="fas fa-adjust"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-sm">
          <a class="dropdown-item theme-opt" href="#" data-theme="light">
            <i class="fas fa-sun mr-2 text-warning"></i> Claro
          </a>
          <a class="dropdown-item theme-opt" href="#" data-theme="dark">
            <i class="fas fa-moon mr-2 text-info"></i> Escuro
          </a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item theme-opt" href="#" data-theme="system">
            <i class="fas fa-desktop mr-2 text-secondary"></i> Sistema
          </a>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/app/logout.php" role="button" title="Sair">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="/app/home.php" class="brand-link">
      <img src="/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light"><?= e(APP_NAME) ?></span>
    </a>

    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="#" class="d-block"><?= e((string)$user['name']) ?></a>
        </div>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-legacy nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="/app/home.php" class="nav-link <?= $activeHome ? 'active' : '' ?>">
              <i class="nav-icon fas fa-home"></i>
              <p>Homepage</p>
            </a>
          </li>

          <li class="nav-item <?= $activeAdmin ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link <?= $activeAdmin ? 'active' : '' ?>">
              <i class="nav-icon fas fa-tools"></i>
              <p>
                Administração
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="/app/admin/animas.php" class="nav-link <?= str_starts_with($scriptName, '/app/admin/animas.php') ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Animas</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="/app/admin/enemies.php" class="nav-link <?= str_starts_with($scriptName, '/app/admin/enemies.php') ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Inimigos</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="/app/admin/battle_prototype.php" class="nav-link <?= str_starts_with($scriptName, '/app/admin/battle_prototype.php') ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Batalha (Protótipo)</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-header">CENTRO ANIMA</li>
          <li class="nav-item">
            <a href="/app/animas/adoption.php" class="nav-link <?= str_starts_with($scriptName, '/app/animas/adoption.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-paw"></i>
              <p>Centro de Adoção</p>
            </a>
          </li>

          <li class="nav-header">ANIMALINK</li>
          <li class="nav-item">
            <a href="/app/animas/my_animas.php" class="nav-link <?= str_starts_with($scriptName, '/app/animas/my_animas.php') ? 'active' : '' ?>">
              <i class="nav-icon fas fa-mobile-alt"></i>
              <p>Meus Animas</p>
            </a>
          </li>

          <li class="nav-item">
            <a href="/app/logout.php" class="nav-link">
              <i class="nav-icon fas fa-sign-out-alt"></i>
              <p>Sair</p>
            </a>
          </li>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1><?= e($pageTitle) ?></h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <?php if ($flashError): ?>
          <div class="alert alert-danger"><?= e($flashError) ?></div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
          <div class="alert alert-success"><?= e($flashSuccess) ?></div>
        <?php endif; ?>

        <?php $renderContent(); ?>
      </div>
    </section>
  </div>

  <footer class="main-footer">
    <strong><?= e(APP_NAME) ?></strong>
  </footer>
</div>

<script src="/plugins/jquery/jquery.min.js"></script>
<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/dist/js/adminlte.min.js"></script>
<?php foreach ($extraJs as $src): ?>
  <script src="<?= e((string)$src) ?>"></script>
<?php endforeach; ?>
<script>
(function() {
    function getSystemPref() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    function applyTheme(mode) {
        var actual = mode === 'system' ? getSystemPref() : mode;
        var body = document.body;
        var nav = document.querySelector('.main-header.navbar');
        if (actual === 'dark') {
            body.classList.add('dark-mode');
            if (nav) { nav.classList.remove('navbar-white', 'navbar-light'); nav.classList.add('navbar-dark'); }
        } else {
            body.classList.remove('dark-mode');
            if (nav) { nav.classList.remove('navbar-dark'); nav.classList.add('navbar-white', 'navbar-light'); }
        }
        var icon = document.getElementById('theme-icon');
        if (icon) {
            icon.className = mode === 'dark' ? 'fas fa-moon' : (mode === 'light' ? 'fas fa-sun' : 'fas fa-desktop');
        }
        document.querySelectorAll('.theme-opt').forEach(function(el) {
            el.classList.toggle('active', el.getAttribute('data-theme') === mode);
        });
    }
    var saved = localStorage.getItem('theme') || 'system';
    applyTheme(saved);
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
            if ((localStorage.getItem('theme') || 'system') === 'system') applyTheme('system');
        });
    }
    document.addEventListener('click', function(e) {
        var opt = e.target.closest('.theme-opt');
        if (!opt) return;
        e.preventDefault();
        var mode = opt.getAttribute('data-theme');
        localStorage.setItem('theme', mode);
        applyTheme(mode);
    });
})();
</script>
<?php if ($inlineJs !== ''): ?>
  <script><?= $inlineJs ?></script>
<?php endif; ?>
</body>
</html>
