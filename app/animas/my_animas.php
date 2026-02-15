<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Meus Animas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada.');
        redirect('/app/animas/my_animas.php');
    }

    if ($action === 'set_main') {
        $userAnimaId = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT id FROM user_animas WHERE id = ? AND user_id = ?');
        $stmt->execute([$userAnimaId, $user['id']]);

        if ($stmt->fetch()) {
            $pdo->prepare('UPDATE user_animas SET is_main = 0 WHERE user_id = ?')->execute([$user['id']]);
            $pdo->prepare('UPDATE user_animas SET is_main = 1 WHERE id = ?')->execute([$userAnimaId]);
            set_flash('success', 'Anima principal atualizado.');
        } else {
            set_flash('error', 'Anima não encontrado.');
        }

        redirect('/app/animas/my_animas.php');
    }
}

$sql = '
    SELECT ua.*, a.species, a.image_path, a.attribute
    FROM user_animas ua
    JOIN animas a ON ua.anima_id = a.id
    WHERE ua.user_id = ?
    ORDER BY ua.is_main DESC, ua.level DESC, ua.created_at DESC
';
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$myAnimas = $stmt->fetchAll();

$extraCss = [
    '/plugins/toastr/toastr.min.css',
];

$extraJs = [
    '/plugins/toastr/toastr.min.js',
];

$inlineJs = <<<'JS'
$(function () {
  $('.btn-set-main').on('click', function () {
    var id = $(this).data('id');
    $('#main-id').val(id);
    $('#form-main').trigger('submit');
  });

  $('.alert-success').each(function () {
    toastr.success($(this).text());
    $(this).hide();
  });

  $('.alert-danger').each(function () {
    toastr.error($(this).text());
    $(this).hide();
  });
});
JS;

$renderContent = function () use ($myAnimas): void {
    ?>
    <div class="row">
      <?php foreach ($myAnimas as $row): ?>
        <?php
        $img = (string)($row['image_path'] ?? '');
        if ($img === '') {
            $img = '/dist/img/default-150x150.png';
        }

        $isMain = (bool)$row['is_main'];
        $headerClass = $isMain ? 'card-primary' : 'card-secondary';
        $statusText = $isMain ? 'Principal' : 'Reserva';

        $currentExp = (int)($row['current_exp'] ?? 0);
        $nextExp = max(1, (int)($row['next_level_exp'] ?? 1));
        $expPercent = min(100, (int)round(($currentExp / $nextExp) * 100));
        ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card <?= $headerClass ?> card-outline">
            <div class="card-header">
              <h3 class="card-title"><?= e((string)$row['nickname']) ?></h3>
              <div class="card-tools">
                <span class="badge badge-light"><?= e($statusText) ?></span>
              </div>
            </div>
            <div class="card-body text-center">
              <img src="<?= e($img) ?>" alt="<?= e((string)$row['species']) ?>" class="img-circle img-size-64 mb-2">
              <h5 class="mb-0"><?= e((string)$row['species']) ?></h5>
              <small class="text-muted">Nível <?= e((string)$row['level']) ?></small>

              <div class="mt-3 text-left">
                <small class="d-block mb-1 text-muted">Experiência</small>
                <div class="progress progress-sm">
                  <div class="progress-bar bg-success" role="progressbar" style="width: <?= $expPercent ?>%" aria-valuenow="<?= $expPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted"><?= e((string)$currentExp) ?> / <?= e((string)$nextExp) ?></small>
              </div>
            </div>
            <div class="card-footer">
              <?php if ($isMain): ?>
                <button type="button" class="btn btn-primary btn-sm btn-block" disabled>
                  <i class="fas fa-check mr-1"></i>Anima principal
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-outline-dark btn-sm btn-block btn-set-main" data-id="<?= e((string)$row['id']) ?>">
                  <i class="fas fa-star mr-1"></i>Tornar principal
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($myAnimas)): ?>
        <div class="col-12">
          <div class="card">
            <div class="card-body text-center py-5">
              <i class="fas fa-paw fa-3x text-muted mb-3"></i>
              <h4 class="text-muted">Você ainda não tem parceiros.</h4>
              <a href="/app/animas/adoption.php" class="btn btn-primary mt-2">Ir para o Centro de Adoção</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <form id="form-main" action="/app/animas/my_animas.php" method="post" class="d-none">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="set_main">
      <input type="hidden" name="id" id="main-id">
    </form>
    <?php
};

require __DIR__ . '/../_layout.php';
