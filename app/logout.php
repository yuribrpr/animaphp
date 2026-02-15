<?php
require __DIR__ . '/_init.php';

logout_user();
set_flash('success', 'Você saiu com sucesso.');
redirect('/app/login.php');

