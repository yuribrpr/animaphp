<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$levels = ['Rookie', 'Champion', 'Ultimate', 'Mega', 'Burst Mode'];
$attributes = ['virus' => 'Virus', 'vacina' => 'Vacina', 'data' => 'Data', 'unknown' => 'Unknown'];
$levelPresets = [
    'Rookie' => ['attack' => 110, 'defense' => 100, 'max_health' => 1100, 'attack_speed' => 1950, 'crit_chance' => 5.00],
    'Champion' => ['attack' => 390, 'defense' => 360, 'max_health' => 3000, 'attack_speed' => 1550, 'crit_chance' => 7.50],
    'Ultimate' => ['attack' => 1400, 'defense' => 1250, 'max_health' => 6800, 'attack_speed' => 1100, 'crit_chance' => 10.00],
    'Mega' => ['attack' => 4700, 'defense' => 4200, 'max_health' => 12500, 'attack_speed' => 780, 'crit_chance' => 12.50],
    'Burst Mode' => ['attack' => 8800, 'defense' => 7900, 'max_health' => 19000, 'attack_speed' => 620, 'crit_chance' => 15.00],
];

function anima_encode_sprite_path(string $filename): string
{
    return '/dist/img/sprites_digipets/' . rawurlencode($filename);
}

function anima_build_balanced_stats(string $level, string $role, string $species, array $levelPresets): array
{
    $roles = [
        'balanced' => ['attack' => 1.00, 'defense' => 1.00, 'max_health' => 1.00, 'attack_speed' => 1.00, 'crit_chance' => 1.00],
        'tank' => ['attack' => 0.92, 'defense' => 1.16, 'max_health' => 1.18, 'attack_speed' => 1.10, 'crit_chance' => 0.90],
        'striker' => ['attack' => 1.14, 'defense' => 0.96, 'max_health' => 1.03, 'attack_speed' => 0.94, 'crit_chance' => 1.10],
        'speed' => ['attack' => 1.08, 'defense' => 0.90, 'max_health' => 0.94, 'attack_speed' => 0.84, 'crit_chance' => 1.22],
        'support' => ['attack' => 0.96, 'defense' => 1.04, 'max_health' => 1.08, 'attack_speed' => 0.95, 'crit_chance' => 1.06],
        'bruiser' => ['attack' => 1.10, 'defense' => 1.06, 'max_health' => 1.10, 'attack_speed' => 1.02, 'crit_chance' => 1.00],
    ];

    $base = $levelPresets[$level] ?? $levelPresets['Rookie'];
    $profile = $roles[$role] ?? $roles['balanced'];

    $seed = (int)sprintf('%u', crc32(mb_strtolower($species, 'UTF-8')));
    $atkVar = 0.95 + (($seed % 11) / 100);
    $defVar = 0.95 + ((($seed >> 4) % 11) / 100);
    $hpVar = 0.95 + ((($seed >> 8) % 11) / 100);
    $spdVar = 0.95 + ((($seed >> 12) % 11) / 100);
    $critVar = 0.95 + ((($seed >> 16) % 11) / 100);

    return [
        'attack' => max(1, (int)round($base['attack'] * $profile['attack'] * $atkVar)),
        'defense' => max(1, (int)round($base['defense'] * $profile['defense'] * $defVar)),
        'max_health' => max(1, (int)round($base['max_health'] * $profile['max_health'] * $hpVar)),
        'attack_speed' => max(500, (int)round($base['attack_speed'] * $profile['attack_speed'] * $spdVar)),
        'crit_chance' => min(100.00, round($base['crit_chance'] * $profile['crit_chance'] * $critVar, 2)),
    ];
}

function anima_seed_digimon_main_lines(PDO $pdo, string $projectRoot, array $levelPresets): array
{
    $spriteDir = $projectRoot . '/dist/img/sprites_digipets';
    $lines = [
        [
            'line' => 'Agumon',
            'attribute' => 'vacina',
            'role' => 'bruiser',
            'stages' => [
                ['species' => 'Agumon', 'level' => 'Rookie', 'sprite' => 'Agumon.gif'],
                ['species' => 'Greymon', 'level' => 'Champion', 'sprite' => 'Greymon.gif'],
                ['species' => 'MetalGreymon', 'level' => 'Ultimate', 'sprite' => 'MetalGreymon (Vaccine).gif'],
                ['species' => 'WarGreymon', 'level' => 'Mega', 'sprite' => 'DotWarGreymon.gif'],
            ],
        ],
        [
            'line' => 'Gabumon',
            'attribute' => 'data',
            'role' => 'speed',
            'stages' => [
                ['species' => 'Gabumon', 'level' => 'Rookie', 'sprite' => 'Gabumon.gif'],
                ['species' => 'Garurumon', 'level' => 'Champion', 'sprite' => 'Garurumon34302.gif'],
                ['species' => 'WereGarurumon', 'level' => 'Ultimate', 'sprite' => 'WereGarurumon.gif'],
                ['species' => 'MetalGarurumon', 'level' => 'Mega', 'sprite' => 'MetalGarurumon.gif'],
            ],
        ],
        [
            'line' => 'Piyomon',
            'attribute' => 'vacina',
            'role' => 'speed',
            'stages' => [
                ['species' => 'Piyomon', 'level' => 'Rookie', 'sprite' => 'Piyomon.gif'],
                ['species' => 'Birdramon', 'level' => 'Champion', 'sprite' => 'Birdramon.gif'],
                ['species' => 'Garudamon', 'level' => 'Ultimate', 'sprite' => 'Garudamon.gif'],
                ['species' => 'Hououmon', 'level' => 'Mega', 'sprite' => 'Hououmon.gif'],
            ],
        ],
        [
            'line' => 'Tentomon',
            'attribute' => 'data',
            'role' => 'tank',
            'stages' => [
                ['species' => 'Tentomon', 'level' => 'Rookie', 'sprite' => 'Tentomon.gif'],
                ['species' => 'Kabuterimon', 'level' => 'Champion', 'sprite' => 'Kabuterimon.gif'],
                ['species' => 'MegaKabuterimon', 'level' => 'Ultimate', 'sprite' => 'MegaKabuterimon (Red).gif'],
                ['species' => 'HerculesKabuterimon', 'level' => 'Mega', 'sprite' => 'HerculesKabuterimonMutation.gif'],
            ],
        ],
        [
            'line' => 'Palmon',
            'attribute' => 'data',
            'role' => 'support',
            'stages' => [
                ['species' => 'Palmon', 'level' => 'Rookie', 'sprite' => 'Palmon.gif'],
                ['species' => 'Togemon', 'level' => 'Champion', 'sprite' => 'Togemon.gif'],
                ['species' => 'Lillymon', 'level' => 'Ultimate', 'sprite' => 'Lillymon.gif'],
                ['species' => 'Rosemon', 'level' => 'Mega', 'sprite' => 'Rosemon.gif'],
            ],
        ],
        [
            'line' => 'Gomamon',
            'attribute' => 'vacina',
            'role' => 'tank',
            'stages' => [
                ['species' => 'Gomamon', 'level' => 'Rookie', 'sprite' => 'Gomamon.gif'],
                ['species' => 'Ikkakumon', 'level' => 'Champion', 'sprite' => 'Ikkakumon.gif'],
                ['species' => 'Zudomon', 'level' => 'Ultimate', 'sprite' => 'Zudomon.gif'],
                ['species' => 'Vikemon', 'level' => 'Mega', 'sprite' => 'Vikemon.gif'],
            ],
        ],
        [
            'line' => 'Patamon',
            'attribute' => 'data',
            'role' => 'support',
            'stages' => [
                ['species' => 'Patamon', 'level' => 'Rookie', 'sprite' => 'Patamon41042.gif'],
                ['species' => 'Angemon', 'level' => 'Champion', 'sprite' => 'Angemon53005.gif'],
                ['species' => 'MagnaAngemon', 'level' => 'Ultimate', 'sprite' => 'MagnaAngemon.gif'],
                ['species' => 'Seraphimon', 'level' => 'Mega', 'sprite' => 'Seraphimon.gif'],
            ],
        ],
        [
            'line' => 'Salamon',
            'attribute' => 'vacina',
            'role' => 'support',
            'stages' => [
                ['species' => 'Salamon', 'level' => 'Rookie', 'sprite' => 'Salamon.gif'],
                ['species' => 'Gatomon', 'level' => 'Champion', 'sprite' => 'Gatomon46202.gif'],
                ['species' => 'Angewomon', 'level' => 'Ultimate', 'sprite' => 'Angewomon.gif'],
                ['species' => 'Magnadramon', 'level' => 'Mega', 'sprite' => 'Magnadramon X.png'],
            ],
        ],
        [
            'line' => 'Veemon',
            'attribute' => 'vacina',
            'role' => 'striker',
            'stages' => [
                ['species' => 'Veemon', 'level' => 'Rookie', 'sprite' => 'Veemon.gif'],
                ['species' => 'ExVeemon', 'level' => 'Champion', 'sprite' => 'ExVeemon.gif'],
                ['species' => 'AeroVeedramon', 'level' => 'Ultimate', 'sprite' => 'AeroVeedramon.gif'],
                ['species' => 'UlforceVeedramon', 'level' => 'Mega', 'sprite' => 'UlforceVeedramon.gif'],
            ],
        ],
        [
            'line' => 'Armadillomon',
            'attribute' => 'vacina',
            'role' => 'tank',
            'stages' => [
                ['species' => 'Armadillomon', 'level' => 'Rookie', 'sprite' => 'Armadillomon.gif'],
                ['species' => 'Ankylomon', 'level' => 'Champion', 'sprite' => 'Ankylomon40576.gif'],
                ['species' => 'Shakkoumon', 'level' => 'Ultimate', 'sprite' => 'Shakkoumon.gif'],
                ['species' => 'Vikemon', 'level' => 'Mega', 'sprite' => 'Vikemon.gif'],
            ],
        ],
        [
            'line' => 'Hawkmon',
            'attribute' => 'data',
            'role' => 'speed',
            'stages' => [
                ['species' => 'Hawkmon', 'level' => 'Rookie', 'sprite' => 'Hawkmon.gif'],
                ['species' => 'Aquilamon', 'level' => 'Champion', 'sprite' => 'Aquilamon33438.gif'],
                ['species' => 'Silphymon', 'level' => 'Ultimate', 'sprite' => 'Silphymon.gif'],
                ['species' => 'Valkyrimon', 'level' => 'Mega', 'sprite' => 'Valkyrimon.gif'],
            ],
        ],
        [
            'line' => 'Wormmon',
            'attribute' => 'virus',
            'role' => 'speed',
            'stages' => [
                ['species' => 'Wormmon', 'level' => 'Rookie', 'sprite' => 'Wormmon.gif'],
                ['species' => 'Stingmon', 'level' => 'Champion', 'sprite' => 'Stingmon.gif'],
                ['species' => 'JewelBeemon', 'level' => 'Ultimate', 'sprite' => 'JewelBeemon.gif'],
                ['species' => 'BanchoStingmon', 'level' => 'Mega', 'sprite' => 'BanchoStingmon.png'],
            ],
        ],
        [
            'line' => 'Guilmon',
            'attribute' => 'virus',
            'role' => 'striker',
            'stages' => [
                ['species' => 'Guilmon', 'level' => 'Rookie', 'sprite' => 'Guilmon.gif'],
                ['species' => 'Growlmon', 'level' => 'Champion', 'sprite' => 'Growlmon.gif'],
                ['species' => 'WarGrowlmon', 'level' => 'Ultimate', 'sprite' => 'WarGrowlmon.gif'],
                ['species' => 'Gallantmon', 'level' => 'Mega', 'sprite' => 'Gallantmon.gif'],
            ],
        ],
        [
            'line' => 'Renamon',
            'attribute' => 'data',
            'role' => 'speed',
            'stages' => [
                ['species' => 'Renamon', 'level' => 'Rookie', 'sprite' => 'Renamon.gif'],
                ['species' => 'Kyubimon', 'level' => 'Champion', 'sprite' => 'Kyubimon.gif'],
                ['species' => 'Taomon', 'level' => 'Ultimate', 'sprite' => 'Taomon.gif'],
                ['species' => 'Sakuyamon', 'level' => 'Mega', 'sprite' => 'Sakuyamon.gif'],
            ],
        ],
        [
            'line' => 'Terriermon',
            'attribute' => 'vacina',
            'role' => 'speed',
            'stages' => [
                ['species' => 'Terriermon', 'level' => 'Rookie', 'sprite' => 'Terriermon.gif'],
                ['species' => 'Gargomon', 'level' => 'Champion', 'sprite' => 'Gargomon.gif'],
                ['species' => 'Rapidmon', 'level' => 'Ultimate', 'sprite' => 'Rapidmon.gif'],
                ['species' => 'MegaGargomon', 'level' => 'Mega', 'sprite' => 'MegaGargomon.gif'],
            ],
        ],
        [
            'line' => 'Gaomon',
            'attribute' => 'data',
            'role' => 'striker',
            'stages' => [
                ['species' => 'Gaomon', 'level' => 'Rookie', 'sprite' => 'Gaomon.gif'],
                ['species' => 'Gaogamon', 'level' => 'Champion', 'sprite' => 'Gaogamon2878.gif'],
                ['species' => 'MachGaogamon', 'level' => 'Ultimate', 'sprite' => 'MachGaogamon.gif'],
                ['species' => 'MirageGaogamon', 'level' => 'Mega', 'sprite' => 'MirageGaogamon.gif'],
            ],
        ],
        [
            'line' => 'Lalamon',
            'attribute' => 'data',
            'role' => 'support',
            'stages' => [
                ['species' => 'Lalamon', 'level' => 'Rookie', 'sprite' => 'Lalamon.gif'],
                ['species' => 'Sunflowmon', 'level' => 'Champion', 'sprite' => 'Sunflowmon.gif'],
                ['species' => 'Lilamon', 'level' => 'Ultimate', 'sprite' => 'Lilamon.gif'],
                ['species' => 'Rosemon', 'level' => 'Mega', 'sprite' => 'Rosemon.gif'],
            ],
        ],
    ];

    $created = 0;
    $updated = 0;
    $linked = 0;
    $missingSprites = [];
    $lineStageIds = [];

    $stmtFind = $pdo->prepare('SELECT id, image_path FROM animas WHERE species = ? AND level = ? ORDER BY id ASC LIMIT 1');
    $stmtInsert = $pdo->prepare('INSERT INTO animas (name, species, next_evolution_id, level, attribute, attack, defense, max_health, attack_speed, crit_chance, image_path) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmtUpdate = $pdo->prepare('UPDATE animas SET name = ?, species = ?, level = ?, attribute = ?, attack = ?, defense = ?, max_health = ?, attack_speed = ?, crit_chance = ?, image_path = ? WHERE id = ?');
    $stmtLink = $pdo->prepare('UPDATE animas SET next_evolution_id = ? WHERE id = ?');

    foreach ($lines as $line) {
        $lineName = (string)$line['line'];
        $lineStageIds[$lineName] = [];
        $attribute = (string)$line['attribute'];
        $role = (string)($line['role'] ?? 'balanced');

        foreach ($line['stages'] as $stage) {
            $species = (string)$stage['species'];
            $level = (string)$stage['level'];
            $sprite = (string)($stage['sprite'] ?? '');

            $spritePath = null;
            if ($sprite !== '') {
                $absSpritePath = $spriteDir . '/' . $sprite;
                if (is_file($absSpritePath)) {
                    $spritePath = anima_encode_sprite_path($sprite);
                } else {
                    $missingSprites[] = $sprite;
                }
            }

            $stats = anima_build_balanced_stats($level, $role, $species, $levelPresets);
            $stmtFind->execute([$species, $level]);
            $existing = $stmtFind->fetch();

            if ($existing) {
                $animaId = (int)$existing['id'];
                $finalImagePath = $spritePath;
                if ($finalImagePath === null) {
                    $currentImagePath = trim((string)($existing['image_path'] ?? ''));
                    $finalImagePath = $currentImagePath !== '' ? $currentImagePath : null;
                }

                $stmtUpdate->execute([
                    $species,
                    $species,
                    $level,
                    $attribute,
                    $stats['attack'],
                    $stats['defense'],
                    $stats['max_health'],
                    $stats['attack_speed'],
                    number_format($stats['crit_chance'], 2, '.', ''),
                    $finalImagePath,
                    $animaId,
                ]);
                $updated++;
            } else {
                $stmtInsert->execute([
                    $species,
                    $species,
                    $level,
                    $attribute,
                    $stats['attack'],
                    $stats['defense'],
                    $stats['max_health'],
                    $stats['attack_speed'],
                    number_format($stats['crit_chance'], 2, '.', ''),
                    $spritePath,
                ]);
                $animaId = (int)$pdo->lastInsertId();
                $created++;
            }

            $lineStageIds[$lineName][] = $animaId;
        }
    }

    foreach ($lines as $line) {
        $lineName = (string)$line['line'];
        $ids = $lineStageIds[$lineName] ?? [];
        $count = count($ids);
        for ($i = 0; $i < $count; $i++) {
            $nextId = $i < ($count - 1) ? $ids[$i + 1] : null;
            $stmtLink->execute([$nextId, $ids[$i]]);
            $linked++;
        }
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'linked' => $linked,
        'missing_sprites' => array_values(array_unique($missingSprites)),
        'total_lines' => count($lines),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada. Tente novamente.');
        redirect('/app/admin/animas.php');
    }

    if ($action === 'seed_digimon_lines') {
        try {
            $seedResult = anima_seed_digimon_main_lines($pdo, dirname(__DIR__, 2), $levelPresets);
            $message = sprintf(
                'Linhas de Digimon importadas: %d linhas, %d criados, %d atualizados, %d evolucoes vinculadas.',
                (int)$seedResult['total_lines'],
                (int)$seedResult['created'],
                (int)$seedResult['updated'],
                (int)$seedResult['linked']
            );

            $missingSprites = (array)($seedResult['missing_sprites'] ?? []);
            if ($missingSprites) {
                $message .= ' Sprites ausentes: ' . implode(', ', $missingSprites) . '.';
            }

            set_flash('success', $message);
        } catch (Throwable $t) {
            set_flash('error', 'Falha ao importar linhas de Digimon: ' . $t->getMessage());
        }

        redirect('/app/admin/animas.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'Anima inválido.');
        } else {
            $stmt = $pdo->prepare('SELECT id, image_path FROM animas WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $anima = $stmt->fetch();

            if ($anima) {
                $imagePath = (string)($anima['image_path'] ?? '');
                if ($imagePath !== '' && str_starts_with($imagePath, '/uploads/animas/')) {
                    $fullPath = dirname(__DIR__, 2) . $imagePath;
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $stmt = $pdo->prepare('DELETE FROM animas WHERE id = ?');
                $stmt->execute([$id]);
                set_flash('success', 'Anima removido com sucesso.');
            } else {
                set_flash('error', 'Anima não encontrado.');
            }
        }

        redirect('/app/admin/animas.php');
    }

    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
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
            $errors[] = 'Atributos não podem ser negativos.';
        }
        if ($critChance < 0 || $critChance > 100) {
            $errors[] = 'Chance de crítico deve estar entre 0 e 100.';
        }

        $nextEvolutionId = null;
        if ($nextEvolutionIdRaw !== '') {
            $nextEvolutionId = (int)$nextEvolutionIdRaw;
            if ($action === 'update' && $nextEvolutionId === $id) {
                $errors[] = 'Um Anima não pode evoluir para si mesmo.';
            } elseif ($nextEvolutionId <= 0) {
                $errors[] = 'Próxima evolução inválida.';
            }
        }

        if ($errors) {
            set_flash('error', implode("\n", $errors));
            redirect('/app/admin/animas.php');
        }

        $imagePath = null;
        $file = $_FILES['image'] ?? null;

        if ($action === 'create' && (!isset($file) || $file['error'] !== UPLOAD_ERR_OK)) {
            set_flash('error', 'Imagem é obrigatória ao criar.');
            redirect('/app/admin/animas.php');
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
                redirect('/app/admin/animas.php');
            }

            $rootDir = dirname(__DIR__, 2);
            $uploadDir = $rootDir . '/uploads/animas';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filename = 'anima_' . bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($tmpName, $uploadDir . '/' . $filename)) {
                $imagePath = '/uploads/animas/' . $filename;
            } else {
                set_flash('error', 'Erro ao salvar a imagem.');
                redirect('/app/admin/animas.php');
            }
        }

        $name = $species;

        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO animas (name, species, next_evolution_id, level, attribute, attack, defense, max_health, attack_speed, crit_chance, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $species, $nextEvolutionId, $level, $attribute, $attack, $defense, $maxHealth, $attackSpeed, number_format($critChance, 2, '.', ''), $imagePath]);
            set_flash('success', 'Anima criado com sucesso.');
        } else {
            $sql = 'UPDATE animas SET name = ?, species = ?, next_evolution_id = ?, level = ?, attribute = ?, attack = ?, defense = ?, max_health = ?, attack_speed = ?, crit_chance = ?';
            $params = [$name, $species, $nextEvolutionId, $level, $attribute, $attack, $defense, $maxHealth, $attackSpeed, number_format($critChance, 2, '.', '')];

            if ($imagePath) {
                $sql .= ', image_path = ?';
                $params[] = $imagePath;
            }

            $sql .= ' WHERE id = ?';
            $params[] = $id;

            $pdo->prepare($sql)->execute($params);
            set_flash('success', 'Anima atualizado com sucesso.');
        }

        redirect('/app/admin/animas.php');
    }
}

$evolutionCandidates = $pdo->query('SELECT id, name, species, level FROM animas ORDER BY name, id')->fetchAll();
$animas = $pdo->query('SELECT a.*, ne.species AS next_evolution_species FROM animas a LEFT JOIN animas ne ON ne.id = a.next_evolution_id ORDER BY a.created_at DESC, a.id DESC')->fetchAll();

$pageTitle = 'Biblioteca de Animas';

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

  $('#table-animas').DataTable({
    responsive: true,
    autoWidth: false,
    lengthChange: false,
    language: { url: 'https://cdn.datatables.net/plug-ins/1.10.24/i18n/Portuguese-Brasil.json' },
    columnDefs: [
      { orderable: false, targets: [0, 5] },
      { className: 'align-middle', targets: '_all' }
    ]
  });

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
    $('#form-anima')[0].reset();
    $('#form-action').val('create');
    $('#input-id').val('');
    $('#modal-title').text('Novo Anima');
    $('#image-preview').attr('src', '/dist/img/default-150x150.png');
    $('#select-next-evolution').val('').trigger('change');
    $('#select-level').val('Rookie').trigger('change');
    $('#select-attribute').val('virus').trigger('change');
  };

  $(document).on('click', '.btn-edit', function () {
    var data = $(this).data('json');
    if (typeof data === 'string') {
      try {
        data = JSON.parse(data);
      } catch (e) {
        data = {};
      }
    }

    $('#form-action').val('update');
    $('#input-id').val(data.id || '');
    $('#modal-title').text('Editar Anima');

    $('#input-species').val(data.species || '');
    $('#select-level').val(data.level || 'Rookie').trigger('change');
    $('#select-attribute').val(data.attribute || 'virus').trigger('change');
    $('#select-next-evolution').val(data.next_evolution_id || '').trigger('change');

    $('#input-attack').val(data.attack || 0);
    $('#input-defense').val(data.defense || 0);
    $('#input-max-health').val(data.max_health || 0);
    $('#input-attack-speed').val(data.attack_speed || 0);
    $('#input-crit-chance').val(data.crit_chance || 0);

    var img = data.image_path ? data.image_path : '/dist/img/default-150x150.png';
    $('#image-preview').attr('src', img);

    $('#modal-manage-anima').modal('show');
  });

  $(document).on('click', '.btn-delete', function () {
    var id = $(this).data('id');
    var name = $(this).data('name');

    Swal.fire({
      title: 'Excluir Anima?',
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
      'Rookie': { attack: 110, defense: 100, max_health: 1100, attack_speed: 1950, crit_chance: 5.00 },
      'Champion': { attack: 390, defense: 360, max_health: 3000, attack_speed: 1550, crit_chance: 7.50 },
      'Ultimate': { attack: 1400, defense: 1250, max_health: 6800, attack_speed: 1100, crit_chance: 10.00 },
      'Mega': { attack: 4700, defense: 4200, max_health: 12500, attack_speed: 780, crit_chance: 12.50 },
      'Burst Mode': { attack: 8800, defense: 7900, max_health: 19000, attack_speed: 620, crit_chance: 15.00 }
    };

    if (!presets[level]) {
      return;
    }

    $('#input-attack').val(presets[level].attack);
    $('#input-defense').val(presets[level].defense);
    $('#input-max-health').val(presets[level].max_health);
    $('#input-attack-speed').val(presets[level].attack_speed);
    $('#input-crit-chance').val(presets[level].crit_chance.toFixed(2));
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

$renderContent = function () use ($levels, $attributes, $evolutionCandidates, $animas): void {
    ?>
    <div class="row">
      <div class="col-12">
        <div class="card card-primary card-outline">
          <div class="card-header">
            <h3 class="card-title">Biblioteca de Animas</h3>
            <div class="card-tools">
              <form action="/app/admin/animas.php" method="post" class="d-inline" onsubmit="return confirm('Importar/atualizar linhas principais de Digimon com balanceamento?');">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="seed_digimon_lines">
                <button type="submit" class="btn btn-outline-primary btn-sm mr-1">
                  <i class="fas fa-database mr-1"></i>Importar Linhas Digimon
                </button>
              </form>
              <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal-manage-anima" onclick="resetForm()">
                <i class="fas fa-plus mr-1"></i>Novo
              </button>
            </div>
          </div>
          <div class="card-body p-0">
            <table id="table-animas" class="table table-striped table-hover mb-0">
              <thead>
              <tr>
                <th class="text-center">Imagem</th>
                <th>Espécie</th>
                <th>Atributo</th>
                <th>Status (HP • ATK • DEF)</th>
                <th>Próxima evolução</th>
                <th class="text-right">Ações</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($animas as $row): ?>
                <?php
                $img = (string)($row['image_path'] ?? '');
                if ($img === '') {
                    $img = '/dist/img/default-150x150.png';
                }

                $rowJson = json_encode($row, JSON_UNESCAPED_UNICODE);
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
                  </td>
                  <td>
                    <?php if (!empty($row['next_evolution_species'])): ?>
                      <span><?= e((string)$row['next_evolution_species']) ?></span>
                    <?php else: ?>
                      <small class="text-muted">Sem evolução</small>
                    <?php endif; ?>
                  </td>
                  <td class="text-right">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ações do anima">
                      <button
                        type="button"
                        class="btn btn-default btn-edit"
                        data-json="<?= e($rowJson !== false ? $rowJson : '{}') ?>"
                        title="Editar"
                        aria-label="Editar anima"
                      >
                        <i class="fas fa-pen text-primary"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-default btn-delete"
                        data-id="<?= e((string)$row['id']) ?>"
                        data-name="<?= e((string)$row['species']) ?>"
                        title="Excluir"
                        aria-label="Excluir anima"
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

    <div class="modal fade" id="modal-manage-anima" tabindex="-1" role="dialog" aria-labelledby="modal-title" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <form action="/app/admin/animas.php" method="post" enctype="multipart/form-data" id="form-anima">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create" id="form-action">
            <input type="hidden" name="id" id="input-id" value="">

            <div class="modal-header">
              <h5 class="modal-title" id="modal-title">Novo Anima</h5>
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

                  <div class="form-group">
                    <label for="select-next-evolution">Próxima evolução</label>
                    <select name="next_evolution_id" id="select-next-evolution" class="form-control select2" data-placeholder="Selecione (opcional)">
                      <option value=""></option>
                      <?php foreach ($evolutionCandidates as $ev): ?>
                        <option value="<?= e((string)$ev['id']) ?>"><?= e((string)$ev['name']) ?> (<?= e((string)$ev['species']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
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
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <form id="form-delete" action="/app/admin/animas.php" method="post" class="d-none">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="delete-id" value="">
    </form>
    <?php
};

require __DIR__ . '/../_layout.php';
