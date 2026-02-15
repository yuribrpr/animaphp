<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Meus Animas';

function my_anima_default_image(?string $path): string
{
    $path = trim((string)$path);
    return $path !== '' ? $path : '/dist/img/default-150x150.png';
}

function my_anima_score(float $value, float $min, float $max, bool $invert = false): int
{
    if ($max <= $min) {
        return 50;
    }

    $ratio = ($value - $min) / ($max - $min);
    if ($invert) {
        $ratio = 1 - $ratio;
    }

    $ratio = max(0.0, min(1.0, $ratio));
    return (int)round($ratio * 100);
}

function my_anima_collect_ancestor_ids(int $currentId, array $prevByNextId): array
{
    $ancestors = [];
    $stack = [$currentId];
    $visited = [];

    while (!empty($stack)) {
        $nodeId = (int)array_pop($stack);
        $parents = $prevByNextId[$nodeId] ?? [];

        foreach ($parents as $parentIdRaw) {
            $parentId = (int)$parentIdRaw;
            if ($parentId <= 0 || isset($visited[$parentId])) {
                continue;
            }

            $visited[$parentId] = true;
            $ancestors[] = $parentId;
            $stack[] = $parentId;
        }
    }

    return $ancestors;
}

function my_anima_collect_descendant_ids(int $currentId, array $animaById, int $maxSteps = 64): array
{
    $descendants = [];
    $visited = [];
    $cursor = $currentId;
    $steps = 0;

    while ($steps < $maxSteps) {
        if (!isset($animaById[$cursor])) {
            break;
        }

        $nextId = (int)($animaById[$cursor]['next_evolution_id'] ?? 0);
        if ($nextId <= 0 || isset($visited[$nextId])) {
            break;
        }

        $visited[$nextId] = true;
        $descendants[] = $nextId;
        $cursor = $nextId;
        $steps++;
    }

    return $descendants;
}

function my_anima_build_flow_path_ids(int $currentId, array $animaById, array $prevByNextId): array
{
    if ($currentId <= 0 || !isset($animaById[$currentId])) {
        return [];
    }

    $ancestorIds = my_anima_collect_ancestor_ids($currentId, $prevByNextId);
    $descendantIds = my_anima_collect_descendant_ids($currentId, $animaById);

    $subgraphSet = [$currentId => true];
    foreach ($ancestorIds as $id) {
        $subgraphSet[(int)$id] = true;
    }
    foreach ($descendantIds as $id) {
        $subgraphSet[(int)$id] = true;
    }

    $roots = [];
    foreach (array_keys($subgraphSet) as $nodeIdRaw) {
        $nodeId = (int)$nodeIdRaw;
        $hasParentInside = false;
        foreach (($prevByNextId[$nodeId] ?? []) as $parentIdRaw) {
            $parentId = (int)$parentIdRaw;
            if (isset($subgraphSet[$parentId])) {
                $hasParentInside = true;
                break;
            }
        }

        if (!$hasParentInside) {
            $roots[] = $nodeId;
        }
    }

    if (empty($roots)) {
        $roots[] = $currentId;
    }

    $levelOrder = ['Rookie' => 1, 'Champion' => 2, 'Ultimate' => 3, 'Mega' => 4, 'Burst Mode' => 5];
    usort($roots, function (int $a, int $b) use ($animaById, $levelOrder): int {
        $levelA = (string)($animaById[$a]['level'] ?? '');
        $levelB = (string)($animaById[$b]['level'] ?? '');
        $weightA = $levelOrder[$levelA] ?? 99;
        $weightB = $levelOrder[$levelB] ?? 99;

        if ($weightA !== $weightB) {
            return $weightA <=> $weightB;
        }

        return strcmp((string)($animaById[$a]['species'] ?? ''), (string)($animaById[$b]['species'] ?? ''));
    });

    $paths = [];
    $signatureSet = [];
    foreach ($roots as $rootId) {
        $path = [];
        $cursor = $rootId;
        $visitedPath = [];
        $steps = 0;

        while (
            $cursor > 0 &&
            isset($subgraphSet[$cursor]) &&
            !isset($visitedPath[$cursor]) &&
            $steps < 64
        ) {
            $path[] = $cursor;
            $visitedPath[$cursor] = true;
            $steps++;

            $nextId = (int)($animaById[$cursor]['next_evolution_id'] ?? 0);
            if ($nextId <= 0 || !isset($subgraphSet[$nextId])) {
                break;
            }
            $cursor = $nextId;
        }

        if (!empty($path)) {
            $signature = implode('>', $path);
            if (!isset($signatureSet[$signature])) {
                $signatureSet[$signature] = true;
                $paths[] = $path;
            }
        }
    }

    if (empty($paths)) {
        return [[$currentId]];
    }

    return $paths;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessao expirada.');
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
            set_flash('error', 'Anima nao encontrado.');
        }

        redirect('/app/animas/my_animas.php');
    }
}

$sql = '
    SELECT
        ua.*,
        a.id AS anima_base_id,
        a.species,
        a.image_path,
        a.attribute,
        a.level AS stage_level,
        a.next_evolution_id,
        a.attack,
        a.defense,
        a.max_health,
        a.attack_speed,
        a.crit_chance
    FROM user_animas ua
    JOIN animas a ON ua.anima_id = a.id
    WHERE ua.user_id = ?
    ORDER BY ua.is_main DESC, ua.level DESC, ua.created_at DESC
';
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$myAnimas = $stmt->fetchAll();

$catalogRows = $pdo->query(
    'SELECT
        id,
        species,
        level,
        attribute,
        next_evolution_id,
        image_path,
        attack,
        defense,
        max_health,
        attack_speed,
        crit_chance
     FROM animas
     ORDER BY id ASC'
)->fetchAll();

$animaById = [];
$prevByNextId = [];
$statRanges = [
    'attack' => ['min' => null, 'max' => null],
    'defense' => ['min' => null, 'max' => null],
    'hp' => ['min' => null, 'max' => null],
    'speed' => ['min' => null, 'max' => null],
    'crit' => ['min' => null, 'max' => null],
];

foreach ($catalogRows as $catRow) {
    $id = (int)($catRow['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $attack = max(0, (int)($catRow['attack'] ?? 0));
    $defense = max(0, (int)($catRow['defense'] ?? 0));
    $hp = max(1, (int)($catRow['max_health'] ?? 1));
    $speed = max(500, (int)($catRow['attack_speed'] ?? 2000));
    $crit = max(0.0, (float)($catRow['crit_chance'] ?? 0.0));
    $nextId = isset($catRow['next_evolution_id']) ? (int)$catRow['next_evolution_id'] : null;
    if ($nextId !== null && $nextId <= 0) {
        $nextId = null;
    }

    $animaById[$id] = [
        'id' => $id,
        'species' => (string)($catRow['species'] ?? ''),
        'level' => (string)($catRow['level'] ?? ''),
        'attribute' => (string)($catRow['attribute'] ?? 'unknown'),
        'next_evolution_id' => $nextId,
        'image_path' => my_anima_default_image((string)($catRow['image_path'] ?? '')),
        'attack' => $attack,
        'defense' => $defense,
        'max_health' => $hp,
        'attack_speed' => $speed,
        'crit_chance' => $crit,
    ];

    if ($nextId !== null) {
        if (!isset($prevByNextId[$nextId])) {
            $prevByNextId[$nextId] = [];
        }
        $prevByNextId[$nextId][] = $id;
    }

    foreach (
        [
            'attack' => $attack,
            'defense' => $defense,
            'hp' => $hp,
            'speed' => $speed,
            'crit' => $crit,
        ] as $metric => $value
    ) {
        if ($statRanges[$metric]['min'] === null || $value < $statRanges[$metric]['min']) {
            $statRanges[$metric]['min'] = $value;
        }
        if ($statRanges[$metric]['max'] === null || $value > $statRanges[$metric]['max']) {
            $statRanges[$metric]['max'] = $value;
        }
    }
}

$defaultRanges = [
    'attack' => ['min' => 0.0, 'max' => 1000.0],
    'defense' => ['min' => 0.0, 'max' => 1000.0],
    'hp' => ['min' => 1.0, 'max' => 10000.0],
    'speed' => ['min' => 500.0, 'max' => 2500.0],
    'crit' => ['min' => 0.0, 'max' => 25.0],
];
foreach ($statRanges as $metric => $range) {
    if ($range['min'] === null || $range['max'] === null) {
        $statRanges[$metric] = $defaultRanges[$metric];
    } else {
        $statRanges[$metric]['min'] = (float)$range['min'];
        $statRanges[$metric]['max'] = (float)$range['max'];
    }
}

$detailsPayloadByUserAnimaId = [];
foreach ($myAnimas as $row) {
    $baseId = (int)($row['anima_base_id'] ?? 0);
    $baseAttack = max(0, (int)($row['attack'] ?? 0));
    $baseDefense = max(0, (int)($row['defense'] ?? 0));
    $baseHp = max(1, (int)($row['max_health'] ?? 1));
    $baseSpeed = max(500, (int)($row['attack_speed'] ?? 2000));
    $baseCrit = max(0.0, (float)($row['crit_chance'] ?? 0.0));

    $effectiveAttack = max(1, $baseAttack + (int)($row['bonus_attack'] ?? 0));
    $effectiveDefense = max(1, $baseDefense + (int)($row['bonus_defense'] ?? 0));
    $effectiveHpMax = $baseHp;
    $effectiveSpeed = max(500, $baseSpeed - (int)($row['reduction_attack_speed'] ?? 0));
    $effectiveCrit = max(0.0, min(100.0, $baseCrit + (float)($row['bonus_crit_chance'] ?? 0.0)));

    $rawCurrentHealth = (int)($row['current_health'] ?? 0);
    if ($rawCurrentHealth <= 0) {
        $effectiveHpCurrent = $effectiveHpMax;
    } else {
        $effectiveHpCurrent = max(0, min($effectiveHpMax, $rawCurrentHealth));
    }

    $expCurrent = max(0, (int)($row['current_exp'] ?? 0));
    $expNext = max(1, (int)($row['next_level_exp'] ?? 1));
    $userLevel = max(1, (int)($row['level'] ?? 1));

    $flowPathIds = my_anima_build_flow_path_ids($baseId, $animaById, $prevByNextId);
    $evolutionFlow = [];
    foreach ($flowPathIds as $pathIds) {
        $nodes = [];
        foreach ($pathIds as $nodeIdRaw) {
            $nodeId = (int)$nodeIdRaw;
            if (!isset($animaById[$nodeId])) {
                continue;
            }

            $node = $animaById[$nodeId];
            $nodes[] = [
                'id' => (int)$node['id'],
                'species' => (string)$node['species'],
                'level' => (string)$node['level'],
                'image_path' => my_anima_default_image((string)$node['image_path']),
                'is_current' => ((int)$node['id'] === $baseId),
            ];
        }

        if (!empty($nodes)) {
            $evolutionFlow[] = $nodes;
        }
    }

    if (empty($evolutionFlow)) {
        $evolutionFlow[] = [[
            'id' => $baseId,
            'species' => (string)($row['species'] ?? 'Anima'),
            'level' => (string)($row['stage_level'] ?? ''),
            'image_path' => my_anima_default_image((string)($row['image_path'] ?? '')),
            'is_current' => true,
        ]];
    }

    $detailsPayloadByUserAnimaId[(int)$row['id']] = [
        'partner' => [
            'id' => (int)$row['id'],
            'nickname' => (string)($row['nickname'] ?? ''),
            'is_main' => (bool)($row['is_main'] ?? false),
            'user_level' => $userLevel,
            'exp_current' => $expCurrent,
            'exp_next' => $expNext,
            'hp_current' => $effectiveHpCurrent,
            'hp_max' => $effectiveHpMax,
        ],
        'anima' => [
            'id' => $baseId,
            'species' => (string)($row['species'] ?? ''),
            'stage_level' => (string)($row['stage_level'] ?? ''),
            'attribute' => (string)($row['attribute'] ?? 'unknown'),
            'image_path' => my_anima_default_image((string)($row['image_path'] ?? '')),
        ],
        'stats_effective' => [
            'attack' => $effectiveAttack,
            'defense' => $effectiveDefense,
            'max_health' => $effectiveHpMax,
            'attack_speed' => $effectiveSpeed,
            'crit_chance' => round($effectiveCrit, 2),
        ],
        'radar_scores' => [
            'attack' => my_anima_score((float)$effectiveAttack, $statRanges['attack']['min'], $statRanges['attack']['max']),
            'defense' => my_anima_score((float)$effectiveDefense, $statRanges['defense']['min'], $statRanges['defense']['max']),
            'hp' => my_anima_score((float)$effectiveHpMax, $statRanges['hp']['min'], $statRanges['hp']['max']),
            'speed' => my_anima_score((float)$effectiveSpeed, $statRanges['speed']['min'], $statRanges['speed']['max'], true),
            'crit' => my_anima_score((float)$effectiveCrit, $statRanges['crit']['min'], $statRanges['crit']['max']),
        ],
        'evolution_flow' => $evolutionFlow,
    ];
}

$extraCss = [
    '/plugins/toastr/toastr.min.css',
];

$extraJs = [
    '/plugins/toastr/toastr.min.js',
    '/plugins/chart.js/Chart.min.js',
];

$inlineJs = <<<'JS'
$(function () {
  var animaRadarChart = null;
  var ATTR_BADGES = 'badge-danger badge-success badge-info badge-secondary';

  function safeParseJson(value) {
    if (!value) {
      return {};
    }
    if (typeof value === 'object') {
      return value;
    }
    try {
      return JSON.parse(value);
    } catch (e) {
      return {};
    }
  }

  function asNumber(value, fallback) {
    var num = Number(value);
    return Number.isFinite(num) ? num : fallback;
  }

  function defaultImage(path) {
    return path ? path : '/dist/img/default-150x150.png';
  }

  function attrMeta(attribute) {
    var attr = (attribute || 'unknown').toString().toLowerCase();
    if (attr === 'virus') return { label: 'Virus', badge: 'badge-danger' };
    if (attr === 'vacina') return { label: 'Vacina', badge: 'badge-success' };
    if (attr === 'data') return { label: 'Data', badge: 'badge-info' };
    return { label: 'Unknown', badge: 'badge-secondary' };
  }

  function renderRadar(scores) {
    var canvas = document.getElementById('anima-radar-chart');
    var fallback = $('#anima-radar-fallback');
    if (!canvas) {
      return;
    }

    if (animaRadarChart) {
      animaRadarChart.destroy();
      animaRadarChart = null;
    }

    if (typeof Chart === 'undefined') {
      fallback.removeClass('d-none');
      return;
    }

    fallback.addClass('d-none');
    var ctx = canvas.getContext('2d');
    animaRadarChart = new Chart(ctx, {
      type: 'radar',
      data: {
        labels: ['ATK', 'DEF', 'HP', 'SPD', 'CRIT'],
        datasets: [{
          label: 'Atributos Efetivos (Score)',
          data: [
            asNumber(scores.attack, 0),
            asNumber(scores.defense, 0),
            asNumber(scores.hp, 0),
            asNumber(scores.speed, 0),
            asNumber(scores.crit, 0)
          ],
          backgroundColor: 'rgba(60,141,188,0.2)',
          borderColor: 'rgba(60,141,188,1)',
          pointBackgroundColor: 'rgba(60,141,188,1)',
          pointBorderColor: '#fff',
          pointHoverBackgroundColor: '#fff',
          pointHoverBorderColor: 'rgba(60,141,188,1)'
        }]
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        legend: {
          display: true,
          position: 'bottom'
        },
        scale: {
          ticks: {
            beginAtZero: true,
            min: 0,
            max: 100,
            stepSize: 20
          },
          pointLabels: {
            fontSize: 11
          }
        }
      }
    });
  }

  function buildFlowNode(node) {
    var card = $('<div class="evo-node card card-outline card-light mb-1"></div>');
    if (node && node.is_current) {
      card.addClass('evo-current border-primary');
    }

    var body = $('<div class="card-body p-2 text-center"></div>');
    var img = $('<img class="img-circle mb-1" width="48" height="48" alt="Anima">');
    img.attr('src', defaultImage(node && node.image_path ? node.image_path : ''));
    img.attr('alt', node && node.species ? node.species : 'Anima');
    body.append(img);

    body.append(
      $('<div class="evo-species font-weight-bold small mb-0"></div>').text(node && node.species ? node.species : 'Anima')
    );
    body.append(
      $('<div class="evo-level text-muted small"></div>').text(node && node.level ? node.level : '')
    );

    if (node && node.is_current) {
      body.append('<span class="badge badge-primary mt-1">Atual</span>');
    }

    card.append(body);
    return card;
  }

  function renderFlow(paths) {
    var container = $('#anima-evolution-flow');
    container.empty();

    if (!Array.isArray(paths) || paths.length === 0) {
      container.append('<div class="text-muted small">Sem evolucao cadastrada.</div>');
      return;
    }

    var hasValidPath = false;
    paths.forEach(function (path) {
      if (!Array.isArray(path) || path.length === 0) {
        return;
      }

      hasValidPath = true;
      var row = $('<div class="evo-path d-flex flex-wrap align-items-center mb-2"></div>');
      path.forEach(function (node, index) {
        row.append(buildFlowNode(node));
        if (index < path.length - 1) {
          row.append('<div class="evo-arrow px-2 text-muted"><i class="fas fa-arrow-right"></i></div>');
        }
      });
      container.append(row);
    });

    if (!hasValidPath) {
      container.append('<div class="text-muted small">Sem evolucao cadastrada.</div>');
    }
  }

  function fillModal(data) {
    var partner = data.partner || {};
    var anima = data.anima || {};
    var stats = data.stats_effective || {};
    var scores = data.radar_scores || {};
    var flow = data.evolution_flow || [];

    var attr = attrMeta(anima.attribute);
    var isMain = !!partner.is_main;
    var expCurrent = Math.max(0, asNumber(partner.exp_current, 0));
    var expNext = Math.max(1, asNumber(partner.exp_next, 1));
    var hpCurrent = Math.max(0, asNumber(partner.hp_current, 0));
    var hpMax = Math.max(1, asNumber(partner.hp_max, 1));

    var expPct = Math.min(100, Math.round((expCurrent / expNext) * 100));
    var hpPct = Math.min(100, Math.round((hpCurrent / hpMax) * 100));

    $('#detail-img').attr('src', defaultImage(anima.image_path || ''));
    $('#detail-img').attr('alt', anima.species || 'Anima');
    $('#detail-nickname').text(partner.nickname || '-');
    $('#detail-species').text(anima.species || '-');
    $('#detail-stage').text(anima.stage_level || '-');
    $('#detail-user-level').text(asNumber(partner.user_level, 1));

    $('#detail-attribute')
      .text(attr.label)
      .removeClass(ATTR_BADGES)
      .addClass(attr.badge);

    $('#detail-status')
      .text(isMain ? 'Principal' : 'Reserva')
      .removeClass('badge-primary badge-secondary')
      .addClass(isMain ? 'badge-primary' : 'badge-secondary');

    $('#detail-exp-text').text(expCurrent + ' / ' + expNext);
    $('#detail-exp-bar').css('width', expPct + '%').attr('aria-valuenow', expPct);

    $('#detail-hp-text').text(hpCurrent + ' / ' + hpMax);
    $('#detail-hp-bar').css('width', hpPct + '%').attr('aria-valuenow', hpPct);

    $('#detail-stat-atk').text(asNumber(stats.attack, 0));
    $('#detail-stat-def').text(asNumber(stats.defense, 0));
    $('#detail-stat-hp').text(asNumber(stats.max_health, 0));
    $('#detail-stat-speed').text(asNumber(stats.attack_speed, 0) + ' ms');
    $('#detail-stat-crit').text(asNumber(stats.crit_chance, 0).toFixed(2) + '%');

    renderFlow(flow);
    renderRadar(scores);
  }

  $('.btn-set-main').on('click', function () {
    var id = $(this).data('id');
    $('#main-id').val(id);
    $('#form-main').trigger('submit');
  });

  $(document).on('click', '.btn-view-details', function () {
    var raw = $(this).attr('data-json') || '{}';
    var payload = safeParseJson(raw);
    fillModal(payload);
    $('#modal-anima-details').modal('show');
  });

  $('#modal-anima-details').on('hidden.bs.modal', function () {
    if (animaRadarChart) {
      animaRadarChart.destroy();
      animaRadarChart = null;
    }
    $('#anima-evolution-flow').empty();
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

$renderContent = function () use ($myAnimas, $detailsPayloadByUserAnimaId): void {
    ?>
    <style>
      .evo-path {
        gap: 0;
      }
      .evo-node {
        min-width: 140px;
        max-width: 180px;
      }
      .evo-node.evo-current {
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
      }
      .evo-arrow {
        font-size: 0.95rem;
      }
      @media (max-width: 575.98px) {
        .evo-path {
          flex-direction: column;
          align-items: stretch !important;
        }
        .evo-node {
          max-width: none;
          width: 100%;
        }
        .evo-arrow {
          width: 100%;
          text-align: center;
          padding: 0.2rem 0;
        }
        .evo-arrow i {
          transform: rotate(90deg);
        }
      }
    </style>

    <div class="row">
      <?php foreach ($myAnimas as $row): ?>
        <?php
        $img = my_anima_default_image((string)($row['image_path'] ?? ''));
        $isMain = (bool)$row['is_main'];
        $headerClass = $isMain ? 'card-primary' : 'card-secondary';
        $statusText = $isMain ? 'Principal' : 'Reserva';

        $currentExp = max(0, (int)($row['current_exp'] ?? 0));
        $nextExp = max(1, (int)($row['next_level_exp'] ?? 1));
        $expPercent = min(100, (int)round(($currentExp / $nextExp) * 100));

        $payload = $detailsPayloadByUserAnimaId[(int)$row['id']] ?? [];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>
        <div class="col-12 col-sm-6 col-lg-4">
          <div class="card <?= e($headerClass) ?> card-outline">
            <div class="card-header">
              <h3 class="card-title"><?= e((string)$row['nickname']) ?></h3>
              <div class="card-tools">
                <span class="badge badge-light"><?= e($statusText) ?></span>
              </div>
            </div>
            <div class="card-body text-center">
              <img src="<?= e($img) ?>" alt="<?= e((string)$row['species']) ?>" class="img-circle img-size-64 mb-2">
              <h5 class="mb-0"><?= e((string)$row['species']) ?></h5>
              <small class="text-muted">Nivel <?= e((string)$row['level']) ?></small>

              <div class="mt-3 text-left">
                <small class="d-block mb-1 text-muted">Experiencia</small>
                <div class="progress progress-sm">
                  <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $expPercent ?>%" aria-valuenow="<?= $expPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
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

              <button
                type="button"
                class="btn btn-outline-info btn-sm btn-block btn-view-details"
                data-json="<?= e($payloadJson !== false ? $payloadJson : '{}') ?>"
              >
                <i class="fas fa-eye mr-1"></i>Ver detalhes
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if (empty($myAnimas)): ?>
        <div class="col-12">
          <div class="card">
            <div class="card-body text-center py-5">
              <i class="fas fa-paw fa-3x text-muted mb-3"></i>
              <h4 class="text-muted">Voce ainda nao tem parceiros.</h4>
              <a href="/app/animas/adoption.php" class="btn btn-primary mt-2">Ir para o Centro de Adocao</a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="modal fade" id="modal-anima-details" tabindex="-1" role="dialog" aria-labelledby="modal-anima-details-title" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modal-anima-details-title">
              <i class="fas fa-eye mr-1"></i>Detalhes do Anima
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-12 col-lg-4">
                <div class="text-center mb-3">
                  <img id="detail-img" src="/dist/img/default-150x150.png" class="img-circle img-size-128 mb-2" alt="Anima">
                  <h4 class="mb-0" id="detail-nickname">-</h4>
                  <div class="text-muted" id="detail-species">-</div>
                  <div class="mt-2">
                    <span class="badge badge-secondary mr-1" id="detail-stage">-</span>
                    <span class="badge badge-secondary mr-1" id="detail-attribute">-</span>
                    <span class="badge badge-secondary" id="detail-status">-</span>
                  </div>
                </div>

                <ul class="list-group list-group-unbordered mb-3">
                  <li class="list-group-item">
                    <b>Nível</b> <span class="float-right" id="detail-user-level">1</span>
                  </li>
                  <li class="list-group-item">
                    <b>Ataque</b> <span class="float-right text-info" id="detail-stat-atk">0</span>
                  </li>
                  <li class="list-group-item">
                    <b>Defesa</b> <span class="float-right text-primary" id="detail-stat-def">0</span>
                  </li>
                  <li class="list-group-item">
                    <b>Vida</b> <span class="float-right text-success" id="detail-stat-hp">0</span>
                  </li>
                  <li class="list-group-item">
                    <b>Vel. Ataque</b> <span class="float-right text-secondary" id="detail-stat-speed">0 ms</span>
                  </li>
                  <li class="list-group-item">
                    <b>Critico</b> <span class="float-right text-warning" id="detail-stat-crit">0.00%</span>
                  </li>
                </ul>

                <div class="mb-3">
                  <small class="d-block text-muted mb-1">Experiencia</small>
                  <div class="progress progress-sm mb-1">
                    <div id="detail-exp-bar" class="progress-bar bg-warning" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <small class="text-muted" id="detail-exp-text">0 / 1</small>
                </div>

                <div>
                  <small class="d-block text-muted mb-1">HP Atual</small>
                  <div class="progress progress-sm mb-1">
                    <div id="detail-hp-bar" class="progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                  <small class="text-muted" id="detail-hp-text">0 / 1</small>
                </div>
              </div>

              <div class="col-12 col-lg-8">
                <div class="card card-outline card-info mb-3">
                  <div class="card-header p-2">
                    <h3 class="card-title text-sm">Radar de atributos (score 0-100)</h3>
                  </div>
                  <div class="card-body">
                    <div style="height: 320px;">
                      <canvas id="anima-radar-chart"></canvas>
                    </div>
                    <small id="anima-radar-fallback" class="text-muted d-none">Chart.js nao disponivel no momento.</small>
                  </div>
                </div>

                <div class="card card-outline card-secondary mb-0">
                  <div class="card-header p-2">
                    <h3 class="card-title text-sm">Arvore evolutiva</h3>
                  </div>
                  <div class="card-body">
                    <div id="anima-evolution-flow"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>

    <form id="form-main" action="/app/animas/my_animas.php" method="post" class="d-none">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="set_main">
      <input type="hidden" name="id" id="main-id">
    </form>
    <?php
};

require __DIR__ . '/../_layout.php';
