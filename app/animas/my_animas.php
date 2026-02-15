<?php
require __DIR__ . '/../_init.php';

$user = require_login();
$pdo = db();

$pageTitle = 'Meus Animas';

// Handle Actions (Set Main)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    
    if (!csrf_validate($_POST['_csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada.');
        redirect('/app/animas/my_animas.php');
    }

    if ($action === 'set_main') {
        $userAnimaId = (int)($_POST['id'] ?? 0);
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT id FROM user_animas WHERE id = ? AND user_id = ?");
        $stmt->execute([$userAnimaId, $user['id']]);
        if ($stmt->fetch()) {
            // Reset all to false
            $pdo->prepare("UPDATE user_animas SET is_main = 0 WHERE user_id = ?")->execute([$user['id']]);
            // Set new main
            $pdo->prepare("UPDATE user_animas SET is_main = 1 WHERE id = ?")->execute([$userAnimaId]);
            
            set_flash('success', 'Anima principal atualizado!');
        } else {
            set_flash('error', 'Anima não encontrado.');
        }
        redirect('/app/animas/my_animas.php');
    }
}

// Fetch User Animas
$sql = "
    SELECT ua.*, a.species, a.image_path, a.attribute 
    FROM user_animas ua 
    JOIN animas a ON ua.anima_id = a.id 
    WHERE ua.user_id = ? 
    ORDER BY ua.is_main DESC, ua.level DESC, ua.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$myAnimas = $stmt->fetchAll();

$extraCss = [
    '/plugins/toastr/toastr.min.css'
];
$extraJs = [
    '/plugins/toastr/toastr.min.js'
];
$inlineJs = <<<'JS'
$(function() {
    $('.btn-set-main').on('click', function() {
        var id = $(this).data('id');
        $('#main-id').val(id);
        $('#form-main').submit();
    });
    
    $('.alert-success').each(function() { toastr.success($(this).text()); $(this).hide(); });
    $('.alert-danger').each(function() { toastr.error($(this).text()); $(this).hide(); });
});
JS;

$renderContent = function() use ($myAnimas) {
?>
    <style>
        .anima-avatar {
            width: 60px; height: 60px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #dee2e6;
        }
        .is-main-avatar {
            border-color: #ffc107; /* Warning color for 'Main' status */
            box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
        }
    </style>

    <div class="row">
        <?php foreach ($myAnimas as $row): ?>
            <?php 
                $img = $row['image_path'] ?? '/dist/img/default-150x150.png';
                $isMain = (bool)$row['is_main'];
                
                // Styles for Main vs Reserve
                $headerColor = $isMain ? 'bg-warning' : 'bg-secondary';
                $badgeText = $isMain ? 'Principal' : 'Reserva';
                
                // Calc visual exp percentage
                $expPercent = ($row['next_level_exp'] > 0) ? min(100, round(($row['current_exp'] / $row['next_level_exp']) * 100)) : 0;
            ?>
            <div class="col-md-4 col-sm-6 col-12">
                <div class="card card-widget widget-user-2 shadow-sm">
                    <!-- Add the bg color to the header using any of the bg-* classes -->
                    <div class="widget-user-header <?= $headerColor ?>">
                        <div class="widget-user-image">
                            <img class="img-circle elevation-2" src="<?= e($img) ?>" alt="User Avatar" style="width:65px; height:65px; object-fit:cover; background:#fff;">
                        </div>
                        <!-- /.widget-user-image -->
                        <h3 class="widget-user-username" style="font-weight: 600;"><?= e($row['nickname']) ?></h3>
                        <h5 class="widget-user-desc" style="font-size: 0.9rem; opacity: 0.9;"><?= e($row['species']) ?></h5>
                    </div>
                    <div class="card-footer p-0">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <span class="nav-link">
                                    Nível <span class="float-right badge bg-primary"><?= e($row['level']) ?></span>
                                </span>
                            </li>
                            <li class="nav-item">
                                <span class="nav-link">
                                    Experiência
                                    <div class="progress progress-xs mt-2">
                                        <div class="progress-bar bg-success" style="width: <?= $expPercent ?>%"></div>
                                    </div>
                                    <span class="d-block text-right text-muted small mt-1"><?= e($row['current_exp']) ?>/<?= e($row['next_level_exp']) ?></span>
                                </span>
                            </li>
                            <li class="nav-item p-2 text-center">
                                <?php if (!$isMain): ?>
                                    <button class="btn btn-block btn-outline-dark btn-sm btn-set-main" data-id="<?= e($row['id']) ?>">
                                        <i class="fas fa-star text-warning"></i> Tornar Principal
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-block btn-warning btn-sm disabled" disabled>
                                        <i class="fas fa-check"></i> Anima Principal
                                    </button>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($myAnimas)): ?>
            <div class="col-12 text-center mt-5">
                <i class="fas fa-paw fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Você ainda não tem parceiros!</h4>
                <a href="/app/animas/adoption.php" class="btn btn-primary mt-3">
                    Ir para o Centro de Adoção
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <form id="form-main" action="/app/animas/my_animas.php" method="post" style="display:none;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="set_main">
        <input type="hidden" name="id" id="main-id">
    </form>
<?php
};

require __DIR__ . '/../_layout.php';
