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
$currentMapId = (int)($_GET['map_id'] ?? 0);
$isDebugBattle = str_starts_with($scriptName, '/app/admin/battle_prototype.php') && isset($_GET['debug']);

if (!$renderContent) {
    throw new RuntimeException('Layout precisa de $renderContent callable');
}

$activeHome = str_starts_with($scriptName, '/app/home.php');
$activeAdminAnimas = str_starts_with($scriptName, '/app/admin/animas.php');
$activeAdminEnemies = str_starts_with($scriptName, '/app/admin/enemies.php');
$activeAdminMaps = str_starts_with($scriptName, '/app/admin/maps.php');
$activeAdoption = str_starts_with($scriptName, '/app/animas/adoption.php');
$activeMyAnimas = str_starts_with($scriptName, '/app/animas/my_animas.php');
$activeBattlePage = str_starts_with($scriptName, '/app/admin/battle_prototype.php');
$activeAdminDebugBattle = $activeBattlePage && $isDebugBattle;
$activeAnimaworldBattle = $activeBattlePage && !$isDebugBattle;

$menuOpenCenterAnima = $activeAdoption || $activeMyAnimas;
$menuOpenAnimaworld = $activeAnimaworldBattle;
$menuOpenAdmin = $activeAdminAnimas || $activeAdminEnemies || $activeAdminMaps || $activeAdminDebugBattle;

$animaworldMaps = [];
try {
    $stmtMaps = db()->query(
        'SELECT m.id, m.name
         FROM maps m
         WHERE EXISTS (
             SELECT 1
             FROM map_enemies me
             INNER JOIN enemies e ON e.id = me.enemy_id
             WHERE me.map_id = m.id
         )
         ORDER BY m.name ASC'
    );
    $animaworldMaps = $stmtMaps->fetchAll();
} catch (Throwable $t) {
    $animaworldMaps = [];
}

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
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Alternar menu"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="/app/home.php" class="nav-link">Home</a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <a class="nav-link" href="/app/logout.php" role="button" title="Sair" aria-label="Sair">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </li>
    </ul>
  </nav>

  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="/app/home.php" class="brand-link">
      <img src="/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3">
      <span class="brand-text font-weight-light"><?= e(APP_NAME) ?></span>
    </a>

    <div class="sidebar">
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="#" class="d-block"><?= e((string)$user['name']) ?></a>
          <small class="text-muted d-block">
            Bits: <?= e(number_format((int)($user['bits'] ?? 0), 0, ',', '.')) ?>
          </small>
        </div>
      </div>

      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="/app/home.php" class="nav-link <?= $activeHome ? 'active' : '' ?>">
              <i class="nav-icon fas fa-home"></i>
              <p>Inicio</p>
            </a>
          </li>

          <li class="nav-item <?= $menuOpenCenterAnima ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link <?= $menuOpenCenterAnima ? 'active' : '' ?>">
              <i class="nav-icon fas fa-paw"></i>
              <p>
                Centro Anima
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="/app/animas/adoption.php" class="nav-link <?= $activeAdoption ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Centro de Adocao</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="/app/animas/my_animas.php" class="nav-link <?= $activeMyAnimas ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Meus Animas</p>
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item <?= $menuOpenAnimaworld ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link <?= $menuOpenAnimaworld ? 'active' : '' ?>">
              <i class="nav-icon fas fa-globe-americas"></i>
              <p>
                Animaworld
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <?php if (!empty($animaworldMaps)): ?>
                <?php foreach ($animaworldMaps as $worldMap): ?>
                  <?php $mapId = (int)($worldMap['id'] ?? 0); ?>
                  <li class="nav-item">
                    <a href="/app/admin/battle_prototype.php?map_id=<?= e((string)$mapId) ?>" class="nav-link <?= ($activeAnimaworldBattle && $currentMapId === $mapId) ? 'active' : '' ?>">
                      <i class="far fa-circle nav-icon"></i>
                      <p><?= e((string)($worldMap['name'] ?? 'Mapa')) ?></p>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="nav-item">
                  <a href="#" class="nav-link disabled">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Nenhum mapa disponivel</p>
                  </a>
                </li>
              <?php endif; ?>
            </ul>
          </li>

          <li class="nav-item <?= $menuOpenAdmin ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link <?= $menuOpenAdmin ? 'active' : '' ?>">
              <i class="nav-icon fas fa-tools"></i>
              <p>
                Administracao
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="/app/admin/animas.php" class="nav-link <?= $activeAdminAnimas ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Animas</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="/app/admin/enemies.php" class="nav-link <?= $activeAdminEnemies ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Inimigos</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="/app/admin/maps.php" class="nav-link <?= $activeAdminMaps ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Mapas</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="/app/admin/battle_prototype.php?debug=1" class="nav-link <?= $activeAdminDebugBattle ? 'active' : '' ?>">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Batalha (Debug)</p>
                </a>
              </li>
            </ul>
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
          <div class="col-sm-12">
            <h1><?= e($pageTitle) ?></h1>
          </div>
        </div>
      </div>
    </section>

    <section class="content">
      <div class="container-fluid">
        <?php if ($flashError): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= nl2br(e(str_replace('<br>', "\n", $flashError))) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>
        <?php if ($flashSuccess): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= nl2br(e(str_replace('<br>', "\n", $flashSuccess))) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <?php $renderContent(); ?>
      </div>
    </section>
  </div>

  <footer class="main-footer">
    <strong><?= e(APP_NAME) ?></strong>
    <div class="float-right d-none d-sm-inline-block">AdminLTE</div>
  </footer>
</div>

<script src="/plugins/jquery/jquery.min.js"></script>
<script src="/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/dist/js/adminlte.min.js"></script>
<?php foreach ($extraJs as $src): ?>
  <script src="<?= e((string)$src) ?>"></script>
<?php endforeach; ?>
<?php if ($inlineJs !== ''): ?>
  <script><?= $inlineJs ?></script>
<?php endif; ?>
</body>
</html>
