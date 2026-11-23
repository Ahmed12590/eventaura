<?php
require __DIR__ . '/_init.php';
require_admin();

[$where, $params, $order] = member_filters($_GET);
$st = db()->prepare('SELECT * FROM members' . $where . ' ORDER BY ' . $order);
$st->execute($params);

$filename = 'eventaura-membres-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM pour qu'Excel affiche bien les accents

// Neutralise les formules Excel (=, +, -, @) saisies par un utilisateur
$safe = fn($v) => (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) ? "'" . $v : $v;

fputcsv($out, ['N°', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'CNE', 'Filière', 'Année', 'Pôle',
    'Intérêts', 'Motivation', 'Statut', 'Inscrit le', 'Dernière connexion', 'Nb connexions', 'Connecté ?'], ';');

while ($m = $st->fetch()) {
    fputcsv($out, array_map($safe, [
        'EVA-' . str_pad((string) $m['id'], 4, '0', STR_PAD_LEFT),
        $m['prenom'], $m['nom'], $m['email'], $m['telephone'], $m['cne'],
        $m['filiere'], $m['annee'], $m['pole'], $m['interets'], $m['motivation'],
        $m['statut'], fmt_date($m['created_at']),
        $m['last_login'] ? fmt_date($m['last_login']) : 'Jamais',
        $m['login_count'], $m['last_login'] ? 'Oui' : 'Non',
    ]), ';');
}
fclose($out);
