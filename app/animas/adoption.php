<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Centro de Adoção';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada.');
        redirect('/app/animas/adoption.php');
    }

    if ($action === 'adopt') {
        $animaId = (int)($_POST['anima_id'] ?? 0);
        $nickname = trim((string)($_POST['nickname'] ?? ''));

        if ($animaId <= 0) {
            set_flash('error', 'Anima inválido.');
            redirect('/app/animas/adoption.php');
        }

        $stmt = $pdo->prepare("SELECT * FROM animas WHERE id = ? AND level = 'Rookie'");
        $stmt->execute([$animaId]);
        $anima = $stmt->fetch();

        if (!$anima) {
            set_flash('error', 'Anima não disponível para adoção.');
            redirect('/app/animas/adoption.php');
        }

        if (mb_strlen($nickname) < 3 || mb_strlen($nickname) > 20) {
            set_flash('error', 'O apelido deve ter entre 3 e 20 caracteres.');
            redirect('/app/animas/adoption.php');
        }

        $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM user_animas WHERE user_id = ?');
        $stmtCount->execute([$user['id']]);
        $count = (int)$stmtCount->fetchColumn();
        $isMain = ($count === 0) ? 1 : 0;

        try {
            $stmtInsert = $pdo->prepare(
                'INSERT INTO user_animas (
                    user_id, anima_id, nickname, level,
                    current_exp, next_level_exp, current_health,
                    bonus_attack, bonus_defense, reduction_attack_speed, bonus_crit_chance,
                    is_main
                ) VALUES (
                    ?, ?, ?, 1,
                    0, 1000, ?,
                    0, 0, 0, 0.00,
                    ?
                )'
            );

            $initialHealth = (int)$anima['max_health'];

            $stmtInsert->execute([
                $user['id'],
                $anima['id'],
                $nickname,
                $initialHealth,
                $isMain,
            ]);

            set_flash('success', "Você adotou {$nickname} com sucesso.");
            redirect('/app/animas/my_animas.php');
        } catch (PDOException $e) {
            set_flash('error', 'Erro ao adotar: ' . $e->getMessage());
            redirect('/app/animas/adoption.php');
        }
    }
}

$rookies = $pdo->query("SELECT * FROM animas WHERE level = 'Rookie' ORDER BY species")->fetchAll();

$extraCss = [
    '/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css',
    '/plugins/toastr/toastr.min.css',
];

$extraJs = [
    '/plugins/sweetalert2/sweetalert2.min.js',
    '/plugins/toastr/toastr.min.js',
];

$inlineJs = <<<'JS'
$(function () {
  $('.btn-adopt').on('click', function () {
    var id = $(this).data('id');
    var species = $(this).data('species');

    Swal.fire({
      title: 'Adotar ' + species,
      text: 'Escolha um apelido para seu novo parceiro:',
      input: 'text',
      inputPlaceholder: 'Apelido',
      showCancelButton: true,
      confirmButtonText: 'Confirmar adoção',
      cancelButtonText: 'Cancelar',
      inputValidator: function (value) {
        if (!value) {
          return 'Você precisa escolher um nome.';
        }
        if (value.length < 3 || value.length > 20) {
          return 'O nome deve ter entre 3 e 20 caracteres.';
        }
      }
    }).then(function (result) {
      if (result.isConfirmed) {
        $('#adopt-id').val(id);
        $('#adopt-nickname').val(result.value);
        $('#form-adopt').trigger('submit');
      }
    });
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

$renderContent = function () use ($rookies): void {
    ?>
    <div class="row">
      <?php foreach ($rookies as $anima): ?>
        <?php
        $img = (string)($anima['image_path'] ?? '');
        if ($img === '') {
            $img = '/dist/img/default-150x150.png';
        }

        $attrInfo = match ((string)$anima['attribute']) {
            'virus' => ['class' => 'badge-danger', 'text' => 'Virus'],
            'vacina' => ['class' => 'badge-success', 'text' => 'Vacina'],
            'data' => ['class' => 'badge-info', 'text' => 'Data'],
            default => ['class' => 'badge-secondary', 'text' => 'Unknown'],
        };
        ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="card card-outline card-primary">
            <div class="card-header">
              <h3 class="card-title"><?= e((string)$anima['species']) ?></h3>
              <div class="card-tools">
                <span class="badge <?= $attrInfo['class'] ?>"><?= e($attrInfo['text']) ?></span>
              </div>
            </div>
            <div class="card-body text-center">
              <img src="<?= e($img) ?>" class="img-fluid img-size-64 mb-3" alt="<?= e((string)$anima['species']) ?>">
              <button
                type="button"
                class="btn btn-primary btn-sm btn-block btn-adopt"
                data-id="<?= e((string)$anima['id']) ?>"
                data-species="<?= e((string)$anima['species']) ?>"
              >
                Adotar
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($rookies)): ?>
        <div class="col-12">
          <div class="alert alert-info">
            <i class="fas fa-info-circle mr-2"></i>Nenhum Anima disponível para adoção no momento.
          </div>
        </div>
      <?php endif; ?>
    </div>

    <form id="form-adopt" action="/app/animas/adoption.php" method="post" class="d-none">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="adopt">
      <input type="hidden" name="anima_id" id="adopt-id">
      <input type="hidden" name="nickname" id="adopt-nickname">
    </form>
    <?php
};

require __DIR__ . '/../_layout.php';
