<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Centro de Adoção';

// Handle Adoption
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

        // Validate Anima exists and is Rookie
        $stmt = $pdo->prepare("SELECT * FROM animas WHERE id = ? AND level = 'Rookie'");
        $stmt->execute([$animaId]);
        $anima = $stmt->fetch();

        if (!$anima) {
            set_flash('error', 'Anima não disponível para adoção.');
            redirect('/app/animas/adoption.php');
        }

        // Validate Nickname
        if (mb_strlen($nickname) < 3 || mb_strlen($nickname) > 20) {
            set_flash('error', 'O apelido deve ter entre 3 e 20 caracteres.');
            redirect('/app/animas/adoption.php');
        }

        // Check if user already has animas (to set is_main)
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM user_animas WHERE user_id = ?");
        $stmtCount->execute([$user['id']]);
        $count = $stmtCount->fetchColumn();
        $isMain = ($count == 0) ? 1 : 0;

        // Create User Anima
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO user_animas (
                    user_id, anima_id, nickname, level, 
                    current_exp, next_level_exp, current_health, 
                    bonus_attack, bonus_defense, reduction_attack_speed, bonus_crit_chance, 
                    is_main
                ) VALUES (
                    ?, ?, ?, 1, 
                    0, 1000, ?, 
                    0, 0, 0, 0.00, 
                    ?
                )
            ");
            
            // Initial health is same as base Max Health (will implement dynamic calc later)
            // Ideally max health grows with level, but for now let's use base
            $initialHealth = $anima['max_health']; 

            $stmtInsert->execute([
                $user['id'], 
                $anima['id'], 
                $nickname, 
                $initialHealth,
                $isMain
            ]);

            set_flash('success', "Você adotou {$nickname} com sucesso!");
            redirect('/app/animas/my_animas.php');
        } catch (PDOException $e) {
            set_flash('error', 'Erro ao adotar: ' . $e->getMessage());
        }
    }
}

// Fetch available Rookies
$rookies = $pdo->query("SELECT * FROM animas WHERE level = 'Rookie' ORDER BY species")->fetchAll();

$extraCss = [
    '/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css',
    '/plugins/toastr/toastr.min.css'
];

$extraJs = [
    '/plugins/sweetalert2/sweetalert2.min.js',
    '/plugins/toastr/toastr.min.js'
];

$inlineJs = <<<'JS'
$(function() {
    $('.btn-adopt').on('click', function() {
        var id = $(this).data('id');
        var species = $(this).data('species');
        
        // Use SweetAlert2 for nickname input
        Swal.fire({
            title: 'Adotar ' + species,
            text: 'Escolha um apelido para seu novo parceiro:',
            input: 'text',
            inputPlaceholder: 'Apelido',
            showCancelButton: true,
            confirmButtonText: 'Confirmar Adoção',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) {
                    return 'Você precisa escolher um nome!';
                }
                if (value.length < 3 || value.length > 20) {
                    return 'O nome deve ter entre 3 e 20 caracteres.';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $('#adopt-id').val(id);
                $('#adopt-nickname').val(result.value);
                $('#form-adopt').submit();
            }
        });
    });

    $('.alert-success').each(function() { toastr.success($(this).text()); $(this).hide(); });
    $('.alert-danger').each(function() { toastr.error($(this).text()); $(this).hide(); });
});
JS;

$renderContent = function() use ($rookies) {
?>
    <style>
        .anima-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .anima-card:hover {
            transform: translateY(-5px);
        }
        .anima-img-wrapper {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f6f9;
            border-radius: 0.25rem 0.25rem 0 0;
            position: relative;
        }
        .anima-img {
            max-height: 140px;
            max-width: 140px;
            object-fit: contain;
            filter: drop-shadow(0 4px 4px rgba(0,0,0,0.1));
        }
        .attr-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            font-size: 0.85rem;
            margin-top: 10px;
            color: #6c757d;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
        }
    </style>

    <div class="row">
        <?php foreach ($rookies as $anima): ?>
            <?php 
                $img = $anima['image_path'] ?? '/dist/img/default-150x150.png';
                $attrInfo = match($anima['attribute']) {
                    'virus' => ['class' => 'badge-danger', 'text' => 'Virus'],
                    'vacina' => ['class' => 'badge-success', 'text' => 'Vacina'],
                    'data' => ['class' => 'badge-info', 'text' => 'Data'],
                    default => ['class' => 'badge-secondary', 'text' => 'Unknown']
                };
            ?>
            <div class="col-12 col-sm-6 col-md-4 col-lg-3 mb-4">
                <div class="card anima-card h-100">
                    <div class="anima-img-wrapper">
                        <span class="badge <?= $attrInfo['class'] ?> attr-badge"><?= $attrInfo['text'] ?></span>
                        <img src="<?= e($img) ?>" class="anima-img" alt="<?= e($anima['species']) ?>">
                    </div>
                    <div class="card-body pt-3">
                        <h5 class="text-center font-weight-bold mb-0"><?= e($anima['species']) ?></h5>
                        <p class="text-center text-muted small mb-3">Rookie</p>
                        
                        <div class="stat-list mb-3">
                            <div class="d-flex justify-content-center text-muted small">
                                <span class="mx-2"><i class="fas fa-heart text-danger mr-1"></i> <?= e($anima['max_health']) ?></span>
                                <span class="mx-2"><i class="fas fa-fist-raised text-warning mr-1"></i> <?= e($anima['attack']) ?></span>
                                <span class="mx-2"><i class="fas fa-shield-alt text-primary mr-1"></i> <?= e($anima['defense']) ?></span>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-block btn-sm btn-adopt rounded-pill" 
                            data-id="<?= e($anima['id']) ?>" 
                            data-species="<?= e($anima['species']) ?>">
                            Adotar
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        
        <?php if (empty($rookies)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i> Nenhum Anima disponível para adoção no momento.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <form id="form-adopt" action="/app/animas/adoption.php" method="post" style="display:none;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="adopt">
        <input type="hidden" name="anima_id" id="adopt-id">
        <input type="hidden" name="nickname" id="adopt-nickname">
    </form>
<?php
};

require __DIR__ . '/../_layout.php';
