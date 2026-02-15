<?php
require __DIR__ . '/_init.php';

if (current_user()) {
    redirect('/app/home.php');
}

redirect('/app/login.php');

