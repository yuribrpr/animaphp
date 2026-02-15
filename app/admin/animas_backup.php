<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$levels = ['Rookie', 'Champion', 'Ultimate', 'Mega', 'Burst Mode'];
$attributes = ['virus' => 'Virus', 'vacina' => 'Vacina', 'data' => 'Data', 'unknown' => 'Unknown'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'create') {
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada. Tente novamente.');
        redirect('/app/admin/animas.php');
    }

    $species = trim((string)($_POST['species'] ?? ''));
    $nextEvolutionIdRaw = trim((string)($_POST['next_evolution_id'] ?? ''));
    $level = (string)($_POST['level'] ?? '');
    $attribute = (string)($_POST['attribute'] ?? '');
    $attack = (int)($_POST['attack'] ?? 0);
    $defense = (int)($_POST['defense'] ?? 0);
    $maxHealth = (int)($_POST['max_health'] ?? 0);
    $attackSpeed = (int)($_POST['attack_speed'] ?? 0);
    $critChance = (float)($_POST['crit_chance'] ?? 0);

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
        $errors[] = 'Atributos numéricos não podem ser negativos.';
    }
    if ($critChance < 0 || $critChance > 100) {
        $errors[] = 'Chance de crítico deve estar entre 0 e 100.';
    }

    $nextEvolutionId = null;
    if ($nextEvolutionIdRaw !== '') {
        $nextEvolutionId = (int)$nextEvolutionIdRaw;
        if ($nextEvolutionId <= 0) {
            $errors[] = 'Próxima evolução inválida.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM animas WHERE id = ? LIMIT 1');
            $stmt->execute([$nextEvolutionId]);
            if (!$stmt->fetch()) {
                $errors[] = 'Próxima evolução não encontrada.';
            }
        }
    }

    if ($errors) {
        set_flash('error', implode(' ', $errors));
        redirect('/app/admin/animas.php');
    }

    $imagePath = null;
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        set_flash('error', 'Envie uma imagem para o anima.');
        redirect('/app/admin/animas.php');
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        set_flash('error', 'Falha no upload da imagem.');
        redirect('/app/admin/animas.php');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        set_flash('error', 'Imagem inválida.');
        redirect('/app/admin/animas.php');
    }

    $maxBytes = 6 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        set_flash('error', 'Tamanho máximo da imagem: 6MB.');
        redirect('/app/admin/animas.php');
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

    if (!$ext) {
        set_flash('error', 'Formato de imagem não suportado.');
        redirect('/app/admin/animas.php');
    }

    $rootDir = dirname(__DIR__, 2);
    $uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'animas';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        set_flash('error', 'Pasta de upload sem permissão.');
        redirect('/app/admin/animas.php');
    }

    $rand = bin2hex(random_bytes(16));
    $filename = 'anima_' . $rand . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmpName, $dest)) {
        set_flash('error', 'Não foi possível salvar a imagem.');
        redirect('/app/admin/animas.php');
    }

    $imagePath = '/uploads/animas/' . $filename;

    $name = $species;
    $stmt = $pdo->prepare(
        'INSERT INTO animas (name, species, next_evolution_id, level, attribute, attack, defense, max_health, attack_speed, crit_chance, image_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $species,
        $nextEvolutionId,
        $level,
        $attribute,
        $attack,
        $defense,
        $maxHealth,
        $attackSpeed,
        number_format($critChance, 2, '.', ''),
        $imagePath,
    ]);

    set_flash('success', 'Anima criado com sucesso.');
    redirect('/app/admin/animas.php');
}

$speciesRows = $pdo->query('SELECT DISTINCT species FROM animas ORDER BY species')->fetchAll();
$speciesList = array_values(array_filter(array_map(fn ($r) => (string)($r['species'] ?? ''), $speciesRows)));

$evolutionCandidates = $pdo->query('SELECT id, name, species, level FROM animas ORDER BY name, id')->fetchAll();

$animas = $pdo->query(
    'SELECT a.*, ne.species AS next_evolution_species
     FROM animas a
     LEFT JOIN animas ne ON ne.id = a.next_evolution_id
     ORDER BY a.created_at DESC, a.id DESC'
)->fetchAll();

$pageTitle = 'Biblioteca de Animas';

$extraCss = [
    '/plugins/select2/css/select2.min.css',
    '/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    '/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    '/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    '/plugins/datatables-buttons/css/buttons.bootstrap4.min.css',
    '/plugins/datatables-searchpanes/css/searchPanes.bootstrap4.min.css',
    '/plugins/datatables-select/css/select.bootstrap4.min.css',
];

$extraJs = [
    '/plugins/select2/js/select2.full.min.js',
    '/plugins/select2/js/i18n/pt-BR.js',
    '/plugins/datatables/jquery.dataTables.min.js',
    '/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    '/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    '/plugins/datatables-responsive/js/responsive.bootstrap4.min.js',
    '/plugins/datatables-buttons/js/dataTables.buttons.min.js',
    '/plugins/datatables-buttons/js/buttons.bootstrap4.min.js',
    '/plugins/jszip/jszip.min.js',
    '/plugins/pdfmake/pdfmake.min.js',
    '/plugins/pdfmake/vfs_fonts.js',
    '/plugins/datatables-buttons/js/buttons.html5.min.js',
    '/plugins/datatables-buttons/js/buttons.print.min.js',
    '/plugins/datatables-buttons/js/buttons.colVis.min.js',
    '/plugins/datatables-select/js/dataTables.select.min.js',
    '/plugins/datatables-searchpanes/js/dataTables.searchPanes.min.js',
    '/plugins/datatables-searchpanes/js/searchPanes.bootstrap4.min.js',
    '/plugins/bs-custom-file-input/bs-custom-file-input.min.js',
];

$inlineJs = <<<'JS'
(function () {
  if (typeof $ === 'undefined') return;

  $('.select2').select2({ theme: 'bootstrap4', language: 'pt-BR', allowClear: true });
  $('#select-next-evolution').select2({ theme: 'bootstrap4', language: 'pt-BR', allowClear: true });

  if (typeof bsCustomFileInput !== 'undefined') {
    bsCustomFileInput.init();
  }

  function applyPresetByLevel(level) {
    var presets = {
      'Rookie': { attack: 100, defense: 100, max_health: 1000, attack_speed: 2000, crit_chance: 5.00 },
      'Champion': { attack: 400, defense: 400, max_health: 3000, attack_speed: 1500, crit_chance: 7.50 },
      'Ultimate': { attack: 1500, defense: 1500, max_health: 7000, attack_speed: 1000, crit_chance: 10.00 },
      'Mega': { attack: 5000, defense: 5000, max_health: 12000, attack_speed: 700, crit_chance: 12.50 },
      'Burst Mode': { attack: 10000, defense: 10000, max_health: 20000, attack_speed: 500, crit_chance: 15.00 }
    };

    if (!presets[level]) return;

    $('#input-attack').val(presets[level].attack);
    $('#input-defense').val(presets[level].defense);
    $('#input-max-health').val(presets[level].max_health);
    $('#input-attack-speed').val(presets[level].attack_speed);
    $('#input-crit-chance').val(presets[level].crit_chance.toFixed(2));
  }

  if ($.fn.DataTable) {
    var table = $('#table-animas').DataTable({
      responsive: true,
      autoWidth: false,
      dom: 'PlBfrtip',
      searchPanes: {
        cascadePanes: true,
        viewTotal: true
      },
      buttons: [
        { extend: 'copy', text: 'Copiar' },
        { extend: 'csv', text: 'CSV' },
        { extend: 'excel', text: 'Excel' },
        { extend: 'print', text: 'Imprimir' },
        { extend: 'colvis', text: 'Colunas' }
      ],
      columnDefs: [
        { orderable: false, targets: [0] },
        { searchPanes: { show: false }, targets: [0, 5, 6, 7, 8, 9] }
      ]
    });

    table.buttons().container().appendTo('#table-animas_wrapper .col-md-6:eq(0)');
  }

  $('#modal-create-anima').on('shown.bs.modal', function () {
    var level = $('#select-level').val() || 'Rookie';
    applyPresetByLevel(level);
  });

  $(document).on('change', '#select-level', function () {
    applyPresetByLevel($(this).val());
  });
})();
JS;

$renderContent = function () use ($levels, $attributes, $speciesList, $evolutionCandidates, $animas): void {
    ?>
    <div class="row">
      <div class="col-12">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Animas cadastrados</h3>
            <div class="card-tools">
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal-create-anima">
                <i class="fas fa-plus"></i> Novo Anima
              </button>
            </div>
          </div>
          <div class="card-body">
            <table id="table-animas" class="table table-bordered table-striped table-hover">
              <thead>
              <tr>
                <th>Imagem</th>
                <th>Espécie</th>
                <th>Próxima evolução</th>
                <th>Nível</th>
                <th>Atributo</th>
                <th>Ataque</th>
                <th>Defesa</th>
                <th>Vida máxima</th>
                <th>Vel. ataque</th>
                <th>% Crítico</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($animas as $row): ?>
                <?php
                $img = (string)($row['image_path'] ?? '');
                if ($img === '') {
                    $img = '/dist/img/default-150x150.png';
                }
                ?>
                <tr data-id="<?= e((string)$row['id']) ?>">
                  <td class="text-center">
                    <img src="<?= e($img) ?>" alt="imagem" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">
                  </td>
                  <td><?= e((string)$row['species']) ?></td>
                  <td><?= e((string)($row['next_evolution_species'] ?? '')) ?></td>
                  <td><?= e((string)$row['level']) ?></td>
                  <td><?= e(ucfirst((string)$row['attribute'])) ?></td>
                  <td><?= e((string)$row['attack']) ?></td>
                  <td><?= e((string)$row['defense']) ?></td>
                  <td><?= e((string)$row['max_health']) ?></td>
                  <td><?= e((string)$row['attack_speed']) ?></td>
                  <td><?= e((string)$row['crit_chance']) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="modal-create-anima" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <form action="/app/admin/animas.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
              <h5 class="modal-title">Criar Anima</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Espécie</label>
                    <input type="text" name="species" class="form-control" list="datalist-species" placeholder="Ex: Agumon" required>
                    <datalist id="datalist-species">
                      <?php foreach ($speciesList as $sp): ?>
                        <option value="<?= e($sp) ?>"></option>
                      <?php endforeach; ?>
                    </datalist>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Imagem do anima</label>
                    <div class="custom-file">
                      <input type="file" class="custom-file-input" id="create-image" name="image" accept="image/gif,image/png,image/jpeg,image/webp" required>
                      <label class="custom-file-label" for="create-image">Escolher arquivo</label>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Próxima evolução</label>
                    <select name="next_evolution_id" id="select-next-evolution" class="form-control select2" data-placeholder="Opcional" style="width: 100%;">
                      <option value=""></option>
                      <?php foreach ($evolutionCandidates as $ev): ?>
                        <option value="<?= e((string)$ev['id']) ?>"><?= e((string)$ev['name']) ?> (<?= e((string)$ev['species']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Nível</label>
                    <select name="level" id="select-level" class="form-control select2" style="width: 100%;" required>
                      <?php foreach ($levels as $lvl): ?>
                        <option value="<?= e($lvl) ?>"><?= e($lvl) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Atributo</label>
                    <select name="attribute" class="form-control select2" style="width: 100%;" required>
                      <?php foreach ($attributes as $val => $label): ?>
                        <option value="<?= e($val) ?>"><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="col-md-3">
                  <div class="form-group">
                    <label>Ataque</label>
                    <input type="number" min="0" name="attack" id="input-attack" class="form-control" value="0" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Defesa</label>
                    <input type="number" min="0" name="defense" id="input-defense" class="form-control" value="0" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Vida máxima</label>
                    <input type="number" min="0" name="max_health" id="input-max-health" class="form-control" value="0" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>Velocidade de ataque</label>
                    <input type="number" min="0" name="attack_speed" id="input-attack-speed" class="form-control" value="0" required>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label>% de chance de crítico</label>
                    <input type="number" min="0" max="100" step="0.01" name="crit_chance" id="input-crit-chance" class="form-control" value="0" required>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
};

require __DIR__ . '/../_layout.php';
