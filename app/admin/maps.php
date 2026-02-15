<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Mapas';

$rootDir = dirname(__DIR__, 2);
$uploadDir = $rootDir . '/uploads/maps';

$deleteMapImage = static function (string $imagePath) use ($rootDir): void {
    if ($imagePath === '' || !str_starts_with($imagePath, '/uploads/maps/')) {
        return;
    }

    $fullPath = $rootDir . $imagePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
};

$storeMapImage = static function (array $file) use ($uploadDir): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    $ext = match ($mime) {
        'image/gif' => 'gif',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        default => null,
    };

    if ($ext === null) {
        return null;
    }

    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        return null;
    }

    $filename = 'map_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        return null;
    }

    return '/uploads/maps/' . $filename;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessao expirada. Tente novamente.');
        redirect('/app/admin/maps.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'Mapa invalido.');
            redirect('/app/admin/maps.php');
        }

        $stmt = $pdo->prepare('SELECT id, background_image_path FROM maps WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $map = $stmt->fetch();

        if (!$map) {
            set_flash('error', 'Mapa nao encontrado.');
            redirect('/app/admin/maps.php');
        }

        $oldImage = (string)($map['background_image_path'] ?? '');

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM map_enemies WHERE map_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM maps WHERE id = ?')->execute([$id]);
            $pdo->commit();
            $deleteMapImage($oldImage);
            set_flash('success', 'Mapa removido com sucesso.');
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Erro ao remover mapa.');
        }

        redirect('/app/admin/maps.php');
    }

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $enemyIdsRaw = $_POST['enemy_ids'] ?? [];

        $enemyIds = [];
        if (is_array($enemyIdsRaw)) {
            foreach ($enemyIdsRaw as $enemyId) {
                $parsedId = (int)$enemyId;
                if ($parsedId > 0) {
                    $enemyIds[] = $parsedId;
                }
            }
        }
        $enemyIds = array_values(array_unique($enemyIds));

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Informe um nome de mapa valido.';
        }
        if (count($enemyIds) < 1) {
            $errors[] = 'Selecione pelo menos um inimigo.';
        }

        if (!empty($enemyIds)) {
            $placeholders = implode(',', array_fill(0, count($enemyIds), '?'));
            $stmt = $pdo->prepare('SELECT id FROM enemies WHERE id IN (' . $placeholders . ')');
            $stmt->execute($enemyIds);
            $foundIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $foundIds = array_map('intval', $foundIds ?: []);
            sort($foundIds);
            $expectedIds = $enemyIds;
            sort($expectedIds);

            if ($foundIds !== $expectedIds) {
                $errors[] = 'Existe inimigo invalido na selecao.';
            }
        }

        $file = $_FILES['background_image'] ?? null;
        $uploadedImagePath = null;

        if ($action === 'create') {
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Imagem de fundo obrigatoria para criar mapa.';
            }
        }

        if (!$errors && is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadedImagePath = $storeMapImage($file);
            if ($uploadedImagePath === null) {
                $errors[] = 'Falha ao enviar imagem. Use JPG, PNG, GIF ou WEBP.';
            }
        }

        if ($errors) {
            if ($uploadedImagePath !== null) {
                $deleteMapImage($uploadedImagePath);
            }
            set_flash('error', implode("\n", $errors));
            redirect('/app/admin/maps.php');
        }

        if ($action === 'create') {
            try {
                $pdo->beginTransaction();

                $stmtInsert = $pdo->prepare('INSERT INTO maps (name, background_image_path) VALUES (?, ?)');
                $stmtInsert->execute([$name, $uploadedImagePath]);
                $mapId = (int)$pdo->lastInsertId();

                $stmtLink = $pdo->prepare('INSERT INTO map_enemies (map_id, enemy_id) VALUES (?, ?)');
                foreach ($enemyIds as $enemyId) {
                    $stmtLink->execute([$mapId, $enemyId]);
                }

                $pdo->commit();
                set_flash('success', 'Mapa criado com sucesso.');
            } catch (Throwable $t) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($uploadedImagePath !== null) {
                    $deleteMapImage($uploadedImagePath);
                }
                set_flash('error', 'Erro ao criar mapa.');
            }

            redirect('/app/admin/maps.php');
        }

        $stmt = $pdo->prepare('SELECT id, background_image_path FROM maps WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $existingMap = $stmt->fetch();

        if (!$existingMap) {
            if ($uploadedImagePath !== null) {
                $deleteMapImage($uploadedImagePath);
            }
            set_flash('error', 'Mapa nao encontrado.');
            redirect('/app/admin/maps.php');
        }

        $oldImagePath = (string)($existingMap['background_image_path'] ?? '');
        $newImagePath = $uploadedImagePath ?? $oldImagePath;

        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare('UPDATE maps SET name = ?, background_image_path = ? WHERE id = ?');
            $stmtUpdate->execute([$name, $newImagePath, $id]);

            $pdo->prepare('DELETE FROM map_enemies WHERE map_id = ?')->execute([$id]);
            $stmtLink = $pdo->prepare('INSERT INTO map_enemies (map_id, enemy_id) VALUES (?, ?)');
            foreach ($enemyIds as $enemyId) {
                $stmtLink->execute([$id, $enemyId]);
            }

            $pdo->commit();

            if ($uploadedImagePath !== null && $oldImagePath !== '' && $oldImagePath !== $uploadedImagePath) {
                $deleteMapImage($oldImagePath);
            }

            set_flash('success', 'Mapa atualizado com sucesso.');
        } catch (Throwable $t) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($uploadedImagePath !== null) {
                $deleteMapImage($uploadedImagePath);
            }
            set_flash('error', 'Erro ao atualizar mapa.');
        }

        redirect('/app/admin/maps.php');
    }
}

$enemies = $pdo->query('SELECT id, species, level FROM enemies ORDER BY species, id')->fetchAll();

$maps = $pdo->query(
    'SELECT m.id, m.name, m.background_image_path,
            COUNT(me.enemy_id) AS enemy_count,
            GROUP_CONCAT(DISTINCT e.species ORDER BY e.species SEPARATOR ", ") AS enemy_species,
            m.created_at
     FROM maps m
     LEFT JOIN map_enemies me ON me.map_id = m.id
     LEFT JOIN enemies e ON e.id = me.enemy_id
     GROUP BY m.id, m.name, m.background_image_path, m.created_at
     ORDER BY m.name ASC'
)->fetchAll();

$mapEnemyRows = $pdo->query('SELECT map_id, enemy_id FROM map_enemies ORDER BY map_id, enemy_id')->fetchAll();
$mapEnemyIds = [];
foreach ($mapEnemyRows as $pair) {
    $mapId = (int)($pair['map_id'] ?? 0);
    $enemyId = (int)($pair['enemy_id'] ?? 0);
    if ($mapId > 0 && $enemyId > 0) {
        if (!isset($mapEnemyIds[$mapId])) {
            $mapEnemyIds[$mapId] = [];
        }
        $mapEnemyIds[$mapId][] = $enemyId;
    }
}

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
  $('.select2').select2({ theme: 'bootstrap4', language: 'pt-BR' });
  bsCustomFileInput.init();

  $('#table-maps').DataTable({
    responsive: true,
    autoWidth: false,
    lengthChange: false,
    language: { url: 'https://cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json' },
    columnDefs: [
      { orderable: false, targets: [0, 4] },
      { className: 'align-middle', targets: '_all' }
    ]
  });

  window.previewMapImage = function (input) {
    if (input.files && input.files[0]) {
      var reader = new FileReader();
      reader.onload = function (e) {
        $('#map-image-preview').attr('src', e.target.result);
      };
      reader.readAsDataURL(input.files[0]);
    }
  };

  window.resetMapForm = function () {
    $('#form-map')[0].reset();
    $('#form-action').val('create');
    $('#input-id').val('');
    $('#modal-title').text('Novo mapa');
    $('#map-image-preview').attr('src', '/dist/img/photo1.png');
    $('#select-enemy-ids').val([]).trigger('change');
    $('.custom-file-label').text('Escolher arquivo');
  };

  $(document).on('click', '.btn-edit-map', function () {
    var raw = $(this).attr('data-json') || '{}';
    var data = {};

    try {
      data = JSON.parse(raw);
    } catch (e) {
      data = {};
    }

    $('#form-action').val('update');
    $('#input-id').val(data.id || '');
    $('#modal-title').text('Editar mapa');
    $('#input-name').val(data.name || '');

    var imagePath = data.background_image_path || '/dist/img/photo1.png';
    $('#map-image-preview').attr('src', imagePath);

    var enemyIds = data.enemy_ids || [];
    enemyIds = enemyIds.map(function (x) { return String(x); });
    $('#select-enemy-ids').val(enemyIds).trigger('change');

    $('.custom-file-label').text('Escolher arquivo');
    $('#modal-manage-map').modal('show');
  });

  $(document).on('click', '.btn-delete-map', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');

    Swal.fire({
      title: 'Excluir mapa?',
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

$renderContent = function () use ($maps, $enemies, $mapEnemyIds): void {
    ?>
    <div class="row">
      <div class="col-12">
        <div class="card card-info card-outline">
          <div class="card-header">
            <h3 class="card-title">Mapas cadastrados</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modal-manage-map" onclick="resetMapForm()">
                <i class="fas fa-plus mr-1"></i>Novo mapa
              </button>
            </div>
          </div>
          <div class="card-body p-0">
            <table id="table-maps" class="table table-striped table-hover mb-0">
              <thead>
              <tr>
                <th class="text-center">Background</th>
                <th>Mapa</th>
                <th>Inimigos</th>
                <th>Quantidade</th>
                <th class="text-right">Acoes</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($maps as $row): ?>
                <?php
                $mapId = (int)($row['id'] ?? 0);
                $imagePath = (string)($row['background_image_path'] ?? '');
                if ($imagePath === '') {
                    $imagePath = '/dist/img/photo1.png';
                }

                $payload = [
                    'id' => $mapId,
                    'name' => (string)($row['name'] ?? ''),
                    'background_image_path' => (string)($row['background_image_path'] ?? ''),
                    'enemy_ids' => array_values(array_unique(array_map('intval', $mapEnemyIds[$mapId] ?? []))),
                ];
                $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
                ?>
                <tr>
                  <td class="text-center">
                    <img src="<?= e($imagePath) ?>" alt="<?= e((string)$row['name']) ?>" class="img-fluid" style="max-width: 80px; max-height: 45px;">
                  </td>
                  <td>
                    <div class="font-weight-bold"><?= e((string)$row['name']) ?></div>
                    <small class="text-muted">ID: <?= e((string)$mapId) ?></small>
                  </td>
                  <td>
                    <small class="text-muted"><?= e((string)($row['enemy_species'] ?? '')) ?></small>
                  </td>
                  <td>
                    <span class="badge badge-info"><?= e((string)($row['enemy_count'] ?? 0)) ?></span>
                  </td>
                  <td class="text-right">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Acoes do mapa">
                      <button type="button" class="btn btn-default btn-edit-map" data-json='<?= e($payloadJson !== false ? $payloadJson : '{}') ?>' title="Editar" aria-label="Editar mapa">
                        <i class="fas fa-pen text-primary"></i>
                      </button>
                      <button type="button" class="btn btn-default btn-delete-map" data-id="<?= e((string)$mapId) ?>" data-name="<?= e((string)$row['name']) ?>" title="Excluir" aria-label="Excluir mapa">
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

    <div class="modal fade" id="modal-manage-map" tabindex="-1" role="dialog" aria-labelledby="modal-title" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <form action="/app/admin/maps.php" method="post" enctype="multipart/form-data" id="form-map">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create" id="form-action">
            <input type="hidden" name="id" value="" id="input-id">

            <div class="modal-header">
              <h5 class="modal-title" id="modal-title">Novo mapa</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>

            <div class="modal-body">
              <div class="form-group">
                <label for="input-name">Nome do mapa</label>
                <input type="text" class="form-control" id="input-name" name="name" required>
              </div>

              <div class="form-group">
                <label for="input-background-image">Background</label>
                <div class="mb-2 text-center">
                  <img id="map-image-preview" src="/dist/img/photo1.png" class="img-fluid img-thumbnail" alt="Preview do mapa" style="max-height: 180px;">
                </div>
                <div class="custom-file">
                  <input type="file" class="custom-file-input" id="input-background-image" name="background_image" accept="image/*" onchange="previewMapImage(this)">
                  <label class="custom-file-label" for="input-background-image">Escolher arquivo</label>
                </div>
                <small class="form-text text-muted">Obrigatorio no cadastro. Opcional na edicao.</small>
              </div>

              <div class="form-group mb-0">
                <label for="select-enemy-ids">Inimigos do mapa</label>
                <select id="select-enemy-ids" name="enemy_ids[]" class="form-control select2" multiple required>
                  <?php foreach ($enemies as $enemy): ?>
                    <option value="<?= e((string)$enemy['id']) ?>">
                      <?= e((string)$enemy['species']) ?> (<?= e((string)$enemy['level']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Selecione pelo menos um inimigo.</small>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-info">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <form id="form-delete" action="/app/admin/maps.php" method="post" class="d-none">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="delete-id" value="">
    </form>
    <?php
};

require __DIR__ . '/../_layout.php';
