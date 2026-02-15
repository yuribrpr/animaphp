<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$levels = ['Rookie', 'Champion', 'Ultimate', 'Mega', 'Burst Mode'];
$attributes = ['virus' => 'Virus', 'vacina' => 'Vacina', 'data' => 'Data', 'unknown' => 'Unknown'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada. Tente novamente.');
        redirect('/app/admin/enemies.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'Inimigo inválido.');
        } else {
            $stmt = $pdo->prepare('SELECT id, image_path FROM enemies WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $enemy = $stmt->fetch();

            if ($enemy) {
                $imagePath = (string)($enemy['image_path'] ?? '');
                if ($imagePath !== '') {
                    $fullPath = dirname(__DIR__, 2) . $imagePath;
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $stmt = $pdo->prepare('DELETE FROM enemies WHERE id = ?');
                $pdo->prepare('DELETE FROM map_enemies WHERE enemy_id = ?')->execute([$id]);
                $stmt->execute([$id]);
                set_flash('success', 'Inimigo removido com sucesso.');
            } else {
                set_flash('error', 'Inimigo não encontrado.');
            }
        }

        redirect('/app/admin/enemies.php');
    }

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $species = trim((string)($_POST['species'] ?? ''));
        $level = (string)($_POST['level'] ?? '');
        $attribute = (string)($_POST['attribute'] ?? '');
        $attack = (int)($_POST['attack'] ?? 0);
        $defense = (int)($_POST['defense'] ?? 0);
        $maxHealth = (int)($_POST['max_health'] ?? 0);
        $attackSpeed = (int)($_POST['attack_speed'] ?? 0);
        $critChance = (float)($_POST['crit_chance'] ?? 0);
        $rewardExp = (int)($_POST['reward_exp'] ?? 0);
        $rewardBits = (int)($_POST['reward_bits'] ?? 0);

        $errors = [];
        if ($species === '' || mb_strlen($species) < 2) {
            $errors[] = 'Informe uma espécie válida.';
        }
        if (!in_array($level, $levels, true)) {
            $errors[] = 'Selecione um nível válido.';
        }
        if (!array_key_exists($attribute, $attributes)) {
            $errors[] = 'Selecione um atributo válido.';
        }
        if ($attack < 0 || $defense < 0 || $maxHealth < 0 || $attackSpeed < 0) {
            $errors[] = 'Atributos não podem ser negativos.';
        }
        if ($critChance < 0 || $critChance > 100) {
            $errors[] = 'Chance de crítico deve estar entre 0 e 100.';
        }

        if ($rewardExp < 0 || $rewardBits < 0) {
            $errors[] = 'EXP e Bits nao podem ser negativos.';
        }

        if ($errors) {
            set_flash('error', implode("\n", $errors));
            redirect('/app/admin/enemies.php');
        }

        $imagePath = null;
        $file = $_FILES['image'] ?? null;

        if ($action === 'create' && (!isset($file) || $file['error'] !== UPLOAD_ERR_OK)) {
            set_flash('error', 'Imagem é obrigatória ao criar.');
            redirect('/app/admin/enemies.php');
        }

        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $tmpName = $file['tmp_name'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmpName);
            $ext = match ($mime) {
                'image/gif' => 'gif',
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => null,
            };

            if (!$ext) {
                set_flash('error', 'Formato inválido. Use JPG, PNG, GIF ou WEBP.');
                redirect('/app/admin/enemies.php');
            }

            $rootDir = dirname(__DIR__, 2);
            $uploadDir = $rootDir . '/uploads/enemies';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filename = 'enemy_' . bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($tmpName, $uploadDir . '/' . $filename)) {
                $imagePath = '/uploads/enemies/' . $filename;
            } else {
                set_flash('error', 'Erro ao salvar a imagem.');
                redirect('/app/admin/enemies.php');
            }
        }

        $name = $species;

        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO enemies (name, species, level, attribute, attack, defense, max_health, attack_speed, crit_chance, reward_exp, reward_bits, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $species, $level, $attribute, $attack, $defense, $maxHealth, $attackSpeed, number_format($critChance, 2, '.', ''), $rewardExp, $rewardBits, $imagePath]);
            set_flash('success', 'Inimigo criado com sucesso.');
        } else {
            $sql = 'UPDATE enemies SET name = ?, species = ?, level = ?, attribute = ?, attack = ?, defense = ?, max_health = ?, attack_speed = ?, crit_chance = ?, reward_exp = ?, reward_bits = ?';
            $params = [$name, $species, $level, $attribute, $attack, $defense, $maxHealth, $attackSpeed, number_format($critChance, 2, '.', ''), $rewardExp, $rewardBits];

            if ($imagePath) {
                $sql .= ', image_path = ?';
                $params[] = $imagePath;
            }

            $sql .= ' WHERE id = ?';
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
            set_flash('success', 'Inimigo atualizado com sucesso.');
        }

        redirect('/app/admin/enemies.php');
    }
}

$enemies = $pdo->query('SELECT * FROM enemies ORDER BY created_at DESC, id DESC')->fetchAll();

$pageTitle = 'Sistema de Inimigos';

$extraCss = [
    '/plugins/select2/css/select2.min.css',
    '/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    '/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    '/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    '/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css',
    '/plugins/toastr/toastr.min.css',
];

$extraJs = [
    '/plugins/select2/js/select2.full.min.js',
    '/plugins/select2/js/i18n/pt-BR.js',
    '/plugins/datatables/jquery.dataTables.min.js',
    '/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    '/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    '/plugins/datatables-responsive/js/responsive.bootstrap4.min.js',
    '/plugins/bs-custom-file-input/bs-custom-file-input.min.js',
    '/plugins/sweetalert2/sweetalert2.min.js',
    '/plugins/toastr/toastr.min.js',
];

$inlineJs = <<<'JS'
$(function () {
  $('.select2').select2({ theme: 'bootstrap4', language: 'pt-BR', allowClear: true });
  bsCustomFileInput.init();

  $('#table-enemies').DataTable({
    responsive: true,
    autoWidth: false,
    lengthChange: false,
    language: { url: 'https://cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json' },
    columnDefs: [
      { orderable: false, targets: [0, 4] },
      { className: 'align-middle', targets: '_all' }
    ]
  });

  function randomFactor() {
    return 0.9 + Math.random() * 0.2;
  }

  function varianceInt(value) {
    return Math.max(1, Math.round(value * randomFactor()));
  }

  function varianceDecimal(value) {
    return Math.max(0, value * randomFactor());
  }

  function calculatePowerScore(attack, defense, attackSpeed, critChance) {
    var normalizedSpeed = Math.max(500, attackSpeed);
    var speedPower = Math.max(1, Math.round(250000 / normalizedSpeed));
    var critPower = Math.round(Math.max(0, critChance) * 20);
    return Math.max(1, attack + defense + speedPower + critPower);
  }

  function calculateBaseReward(attack, defense, attackSpeed, critChance) {
    return Math.max(1, Math.round(calculatePowerScore(attack, defense, attackSpeed, critChance) * 0.1));
  }

  window.previewImage = function (input) {
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function (e) {
        $('#image-preview').attr('src', e.target.result);
      };
      reader.readAsDataURL(input.files[0]);
    }
  };

  window.resetForm = function () {
    $('#form-enemy')[0].reset();
    $('#form-action').val('create');
    $('#input-id').val('');
    $('#modal-title').text('Novo Inimigo');
    $('#image-preview').attr('src', '/dist/img/default-150x150.png');
    $('#select-level').val('Rookie').trigger('change');
    $('#select-attribute').val('virus').trigger('change');
  };

  $(document).on('click', '.btn-edit', function () {
    var btn = $(this);

    $('#form-action').val('update');
    $('#input-id').val(btn.data('id'));
    $('#modal-title').text('Editar Inimigo');

    $('#input-species').val(btn.data('species'));
    $('#select-level').val(btn.data('level')).trigger('change');
    $('#select-attribute').val(btn.data('attribute')).trigger('change');

    $('#input-attack').val(btn.data('attack'));
    $('#input-defense').val(btn.data('defense'));
    $('#input-max-health').val(btn.data('hp'));
    $('#input-attack-speed').val(btn.data('speed'));
    $('#input-crit-chance').val(btn.data('crit'));
    $('#input-reward-exp').val(btn.data('rewardExp'));
    $('#input-reward-bits').val(btn.data('rewardBits'));

    var img = btn.data('image');
    if (!img) {
      img = '/dist/img/default-150x150.png';
    }
    $('#image-preview').attr('src', img);

    $('#modal-manage-enemy').modal('show');
  });

  $(document).on('click', '.btn-delete', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');

    Swal.fire({
      title: 'Excluir inimigo?',
      text: name,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Excluir',
      cancelButtonText: 'Cancelar'
    }).then(function (result) {
      if (result.isConfirmed) {
        $('#delete-id').val(id);
        $('#form-delete').trigger('submit');
      }
    });
  });

  $('#select-level').on('change', function () {
    if ($('#form-action').val() !== 'create') {
      return;
    }

    var level = $(this).val();
    var presets = {
      'Rookie': { attack: 100, defense: 100, max_health: 1000, attack_speed: 2000, crit_chance: 5.00 },
      'Champion': { attack: 400, defense: 400, max_health: 3000, attack_speed: 1500, crit_chance: 7.50 },
      'Ultimate': { attack: 1500, defense: 1500, max_health: 7000, attack_speed: 1000, crit_chance: 10.00 },
      'Mega': { attack: 5000, defense: 5000, max_health: 12000, attack_speed: 700, crit_chance: 12.50 },
      'Burst Mode': { attack: 10000, defense: 10000, max_health: 20000, attack_speed: 500, crit_chance: 15.00 }
    };

    if (!presets[level]) {
      return;
    }

    var attack = varianceInt(presets[level].attack);
    var defense = varianceInt(presets[level].defense);
    var maxHealth = varianceInt(presets[level].max_health);
    var attackSpeed = varianceInt(presets[level].attack_speed);
    var critChance = varianceDecimal(presets[level].crit_chance);

    var baseReward = calculateBaseReward(attack, defense, attackSpeed, critChance);
    var rewardExp = varianceInt(baseReward);
    var rewardBits = varianceInt(baseReward);

    $('#input-attack').val(attack);
    $('#input-defense').val(defense);
    $('#input-max-health').val(maxHealth);
    $('#input-attack-speed').val(attackSpeed);
    $('#input-crit-chance').val(critChance.toFixed(2));
    $('#input-reward-exp').val(rewardExp);
    $('#input-reward-bits').val(rewardBits);
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

$renderContent = function () use ($levels, $attributes, $enemies): void {
    ?>
    <div class="row">
      <div class="col-12">
        <div class="card card-danger card-outline">
          <div class="card-header">
            <h3 class="card-title">Sistema de Inimigos</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#modal-manage-enemy" onclick="resetForm()">
                <i class="fas fa-plus mr-1"></i>Novo
              </button>
            </div>
          </div>
          <div class="card-body p-0">
            <table id="table-enemies" class="table table-striped table-hover mb-0">
              <thead>
              <tr>
                <th class="text-center">Imagem</th>
                <th>Espécie</th>
                <th>Atributo</th>
                <th>Status (HP • ATK • DEF)</th>
                <th class="text-right">Ações</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($enemies as $row): ?>
                <?php
                $img = (string)($row['image_path'] ?? '');
                if ($img === '') {
                    $img = '/dist/img/default-150x150.png';
                }

                $attrBadge = match ((string)$row['attribute']) {
                    'virus' => 'badge-danger',
                    'vacina' => 'badge-success',
                    'data' => 'badge-info',
                    default => 'badge-secondary'
                };
                ?>
                <tr>
                  <td class="text-center">
                    <img src="<?= e($img) ?>" alt="<?= e((string)$row['species']) ?>" class="img-circle img-size-32">
                  </td>
                  <td>
                    <div class="font-weight-bold"><?= e((string)$row['species']) ?></div>
                    <small class="text-muted"><?= e((string)$row['level']) ?></small>
                  </td>
                  <td>
                    <span class="badge <?= $attrBadge ?>"><?= e(ucfirst((string)$row['attribute'])) ?></span>
                  </td>
                  <td>
                    <small class="text-muted"><?= e((string)$row['max_health']) ?> • <?= e((string)$row['attack']) ?> • <?= e((string)$row['defense']) ?></small>
                    <br>
                    <small class="text-muted">EXP <?= e((string)($row['reward_exp'] ?? 0)) ?> • Bits <?= e((string)($row['reward_bits'] ?? 0)) ?></small>
                  </td>
                  <td class="text-right">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ações do inimigo">
                      <button
                        type="button"
                        class="btn btn-default btn-edit"
                        data-id="<?= e((string)$row['id']) ?>"
                        data-species="<?= e((string)$row['species']) ?>"
                        data-level="<?= e((string)$row['level']) ?>"
                        data-attribute="<?= e((string)$row['attribute']) ?>"
                        data-hp="<?= e((string)$row['max_health']) ?>"
                        data-attack="<?= e((string)$row['attack']) ?>"
                        data-defense="<?= e((string)$row['defense']) ?>"
                        data-speed="<?= e((string)$row['attack_speed']) ?>"
                        data-crit="<?= e((string)$row['crit_chance']) ?>"
                        data-reward-exp="<?= e((string)($row['reward_exp'] ?? 0)) ?>"
                        data-reward-bits="<?= e((string)($row['reward_bits'] ?? 0)) ?>"
                        data-image="<?= e((string)($row['image_path'] ?? '')) ?>"
                        title="Editar"
                        aria-label="Editar inimigo"
                      >
                        <i class="fas fa-pen text-primary"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-default btn-delete"
                        data-id="<?= e((string)$row['id']) ?>"
                        data-name="<?= e((string)$row['species']) ?>"
                        title="Excluir"
                        aria-label="Excluir inimigo"
                      >
                        <i class="fas fa-trash text-danger"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modal-manage-enemy" tabindex="-1" role="dialog" aria-labelledby="modal-title" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <form action="/app/admin/enemies.php" method="post" enctype="multipart/form-data" id="form-enemy">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create" id="form-action">
            <input type="hidden" name="id" id="input-id" value="">

            <div class="modal-header">
              <h5 class="modal-title" id="modal-title">Novo Inimigo</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="modal-body">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="input-image">Imagem</label>
                    <div class="text-center mb-2">
                      <img src="/dist/img/default-150x150.png" id="image-preview" class="img-fluid img-thumbnail" alt="Pré-visualização da imagem">
                    </div>
                    <div class="custom-file">
                      <input type="file" class="custom-file-input" id="input-image" name="image" accept="image/*" onchange="previewImage(this)">
                      <label class="custom-file-label" for="input-image">Escolher arquivo</label>
                    </div>
                  </div>
                </div>

                <div class="col-md-8">
                  <div class="form-group">
                    <label for="input-species">Espécie</label>
                    <input type="text" name="species" id="input-species" class="form-control" required>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label for="select-level">Nível</label>
                      <select name="level" id="select-level" class="form-control select2" required>
                        <?php foreach ($levels as $lvl): ?>
                          <option value="<?= e($lvl) ?>"><?= e($lvl) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="select-attribute">Atributo</label>
                      <select name="attribute" id="select-attribute" class="form-control select2" required>
                        <?php foreach ($attributes as $val => $label): ?>
                          <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-4">
                      <label for="input-max-health">HP máximo</label>
                      <input type="number" name="max_health" id="input-max-health" class="form-control" min="0" required>
                    </div>
                    <div class="form-group col-md-4">
                      <label for="input-attack">Ataque</label>
                      <input type="number" name="attack" id="input-attack" class="form-control" min="0" required>
                    </div>
                    <div class="form-group col-md-4">
                      <label for="input-defense">Defesa</label>
                      <input type="number" name="defense" id="input-defense" class="form-control" min="0" required>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="input-attack-speed">Velocidade de ataque</label>
                      <input type="number" name="attack_speed" id="input-attack-speed" class="form-control" min="0" required>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="input-crit-chance">Crítico (%)</label>
                      <input type="number" name="crit_chance" id="input-crit-chance" class="form-control" min="0" max="100" step="0.01" required>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="input-reward-exp">Recompensa EXP</label>
                      <input type="number" name="reward_exp" id="input-reward-exp" class="form-control" min="0" required>
                    </div>
                    <div class="form-group col-md-6">
                      <label for="input-reward-bits">Recompensa Bits</label>
                      <input type="number" name="reward_bits" id="input-reward-bits" class="form-control" min="0" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-danger">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <form id="form-delete" action="/app/admin/enemies.php" method="post" class="d-none">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="delete-id" value="">
    </form>
    <?php
};

require __DIR__ . '/../_layout.php';
