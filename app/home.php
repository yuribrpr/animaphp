<?php
require __DIR__ . '/_init.php';

$user = require_login();
$pageTitle = 'Home';

$renderContent = function () use ($user): void {
    ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Área interna</h3>
      </div>
      <div class="card-body">
        <div>Você está logado como <strong><?= e((string)$user['email']) ?></strong>.</div>
      </div>
    </div>
    <?php
};

require __DIR__ . '/_layout.php';
