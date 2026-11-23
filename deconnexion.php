<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['member_id'], $_SESSION['prev_login']);
    session_regenerate_id(true);
    flash('success', 'Vous êtes déconnecté·e. À bientôt !');
}
redirect('connexion.php');
