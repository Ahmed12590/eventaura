<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

function admin_nav(string $active): void
{
    $items = ['index.php' => 'Tableau de bord', 'parametres.php' => 'Paramètres'];
    $html = '';
    foreach ($items as $href => $label) {
        $html .= '<a href="' . $href . '"' . ($active === $href ? ' class="act"' : '') . '>' . $label . '</a>';
    }
    $html .= '<form method="post" action="deconnexion.php" class="inline">' . csrf_field()
           . '<button class="link-btn">Déconnexion</button></form>';
    $GLOBALS['ADMIN_NAV'] = $html;
}

/** Filtres communs au tableau et à l'export CSV */
function member_filters(array $q): array
{
    $where = [];
    $params = [];
    $search = trim((string) ($q['q'] ?? ''));
    if ($search !== '') {
        $where[] = '(CONCAT(prenom, " ", nom) LIKE ? OR email LIKE ? OR telephone LIKE ? OR cne LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if (in_array($q['pole'] ?? '', POLES, true)) {
        $where[] = 'pole = ?';
        $params[] = $q['pole'];
    }
    switch ($q['connexion'] ?? '') {
        case 'oui':    $where[] = 'last_login IS NOT NULL'; break;
        case 'non':    $where[] = 'last_login IS NULL'; break;
        case 'actifs': $where[] = 'last_login >= (NOW() - INTERVAL 30 DAY)'; break;
    }
    if (in_array($q['statut'] ?? '', ['actif', 'suspendu'], true)) {
        $where[] = 'statut = ?';
        $params[] = $q['statut'];
    }
    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $sorts = [
        'recent'  => 'created_at DESC',
        'ancien'  => 'created_at ASC',
        'nom'     => 'nom ASC, prenom ASC',
        'login'   => 'last_login IS NULL, last_login DESC',
        'nb'      => 'login_count DESC',
    ];
    $order = $sorts[$q['tri'] ?? ''] ?? $sorts['recent'];

    return [$sql, $params, $order];
}
