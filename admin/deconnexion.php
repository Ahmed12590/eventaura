<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
    session_regenerate_id(true);
    flash('success', 'Déconnexion réussie.');
}
redirect('connexion.php');
