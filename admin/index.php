<?php
require __DIR__ . '/_init.php';
$admin = require_admin();

/* ---------- Actions (suspendre / réactiver / supprimer) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $back = 'index.php' . (!empty($_POST['qs']) ? '?' . preg_replace('/[^\w=&%+\-.]/u', '', (string) $_POST['qs']) : '');
    if ($id > 0) {
        switch ($_POST['action'] ?? '') {
            case 'toggle':
                db()->prepare("UPDATE members SET statut = IF(statut = 'actif', 'suspendu', 'actif') WHERE id = ?")->execute([$id]);
                flash('success', 'Statut du membre mis à jour.');
                break;
            case 'delete':
                db()->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
                flash('success', 'Membre supprimé.');
                break;
        }
    }
    redirect($back);
}

/* ---------- Statistiques ---------- */
$s = db()->query("SELECT
    COUNT(*)                                             AS total,
    COALESCE(SUM(last_login IS NOT NULL), 0)             AS connectes,
    COALESCE(SUM(last_login IS NULL), 0)                 AS jamais,
    COALESCE(SUM(last_login >= NOW() - INTERVAL 7 DAY), 0)  AS actifs7,
    COALESCE(SUM(created_at >= NOW() - INTERVAL 30 DAY), 0) AS nouveaux30,
    COALESCE(SUM(statut = 'suspendu'), 0)                AS suspendus
  FROM members")->fetch();
$total = (int) $s['total'];
$pctConn = $total ? round($s['connectes'] / $total * 100) : 0;
$logins7 = (int) db()->query('SELECT COUNT(*) FROM login_logs WHERE logged_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();

$byPole = db()->query('SELECT pole, COUNT(*) AS n, SUM(last_login IS NOT NULL) AS c
    FROM members GROUP BY pole ORDER BY n DESC')->fetchAll();
$maxPole = max(array_merge([1], array_column($byPole, 'n')));

// Inscriptions des 14 derniers jours
$rows = db()->query('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM members
    WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)')->fetchAll(PDO::FETCH_KEY_PAIR);
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = (int) ($rows[$d] ?? 0);
}
$maxDay = max(1, max($days));

$recent = db()->query('SELECT l.logged_at, m.prenom, m.nom, m.pole FROM login_logs l
    JOIN members m ON m.id = l.member_id ORDER BY l.id DESC LIMIT 8')->fetchAll();

/* ---------- Liste filtrée ---------- */
$q = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'pole'      => (string) ($_GET['pole'] ?? ''),
    'connexion' => (string) ($_GET['connexion'] ?? ''),
    'statut'    => (string) ($_GET['statut'] ?? ''),
    'tri'       => (string) ($_GET['tri'] ?? 'recent'),
];
[$where, $params, $order] = member_filters($q);

$st = db()->prepare('SELECT COUNT(*) FROM members' . $where);
$st->execute($params);
$found = (int) $st->fetchColumn();

$perPage = 25;
$pages = max(1, (int) ceil($found / $perPage));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$st = db()->prepare('SELECT * FROM members' . $where . ' ORDER BY ' . $order . " LIMIT $perPage OFFSET $offset");
$st->execute($params);
$members = $st->fetchAll();

$qs = http_build_query(array_filter($q + ['page' => $page > 1 ? $page : null], fn($v) => $v !== '' && $v !== null));
$exportQs = http_build_query(array_filter($q, fn($v) => $v !== ''));
function page_link(array $q, int $p): string
{
    return '?' . http_build_query(array_filter($q + ['page' => $p], fn($v) => $v !== ''));
}

admin_nav('index.php');
page_header('Tableau de bord', '../', 'admin');
?>
<section class="admin-dash">
  <div class="wrap">
    <div class="hello">
      <div>
        <div class="eyebrow">Administration</div>
        <h1>Tableau de bord</h1>
        <p class="muted">Bonjour <?= e($admin['nom']) ?> · <?= e(date('d/m/Y H:i')) ?></p>
      </div>
      <a class="btn btn-gold" href="export.php<?= $exportQs ? '?' . e($exportQs) : '' ?>">⬇ Exporter (Excel/CSV)</a>
    </div>

    <!-- Indicateurs -->
    <div class="kpis">
      <div class="kpi"><span>Membres inscrits</span><b><?= $total ?></b><small>+<?= (int) $s['nouveaux30'] ?> ces 30 derniers jours</small></div>
      <div class="kpi ok"><span>Déjà connectés</span><b><?= (int) $s['connectes'] ?></b><small><?= $pctConn ?> % des inscrits</small></div>
      <div class="kpi warn"><span>Jamais connectés</span><b><?= (int) $s['jamais'] ?></b><small><a href="?connexion=non">Voir la liste →</a></small></div>
      <div class="kpi"><span>Actifs (7 jours)</span><b><?= (int) $s['actifs7'] ?></b><small><?= $logins7 ?> connexions cette semaine</small></div>
    </div>

    <div class="charts">
      <div class="card">
        <h3>Connectés vs jamais connectés</h3>
        <div class="donut" style="--p:<?= $pctConn ?>">
          <div><b><?= $pctConn ?>%</b><span>connectés</span></div>
        </div>
        <div class="legend"><span><i class="c1"></i>Connectés (<?= (int) $s['connectes'] ?>)</span><span><i class="c2"></i>Jamais (<?= (int) $s['jamais'] ?>)</span></div>
      </div>

      <div class="card">
        <h3>Inscriptions — 14 derniers jours</h3>
        <div class="bars">
          <?php foreach ($days as $d => $n): ?>
            <div class="bar" title="<?= e(date('d/m', strtotime($d))) ?> : <?= $n ?> inscription(s)">
              <em><?= $n ?: '' ?></em><i style="height:<?= round($n / $maxDay * 100) ?>%"></i><span><?= e(date('d', strtotime($d))) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h3>Répartition par pôle</h3>
        <?php if (!$byPole): ?><p class="muted">Aucun membre pour l'instant.</p><?php endif; ?>
        <div class="hbars">
          <?php foreach ($byPole as $p): ?>
            <a class="hbar" href="?pole=<?= urlencode($p['pole']) ?>">
              <span class="hl"><?= e($p['pole']) ?></span>
              <span class="track"><i style="width:<?= round($p['n'] / $maxPole * 100) ?>%"></i><i class="in" style="width:<?= round($p['c'] / $maxPole * 100) ?>%"></i></span>
              <span class="hn"><?= (int) $p['n'] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php if ($byPole): ?><div class="legend"><span><i class="c1"></i>Connectés</span><span><i class="c3"></i>Inscrits</span></div><?php endif; ?>
      </div>

      <div class="card">
        <h3>Dernières connexions</h3>
        <?php if (!$recent): ?><p class="muted">Aucune connexion pour l'instant.</p><?php endif; ?>
        <ul class="feed">
          <?php foreach ($recent as $r): ?>
            <li><span class="av"><?= e(mb_strtoupper(mb_substr($r['prenom'], 0, 1) . mb_substr($r['nom'], 0, 1))) ?></span>
              <div><b><?= e($r['prenom'] . ' ' . $r['nom']) ?></b><small><?= e($r['pole']) ?></small></div>
              <time title="<?= e(fmt_date($r['logged_at'])) ?>"><?= e(time_ago($r['logged_at'])) ?></time></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- Liste des membres -->
    <div class="card table-card" id="membres">
      <div class="table-head">
        <h3>Membres <span class="muted">(<?= $found ?> résultat<?= $found > 1 ? 's' : '' ?>)</span></h3>
      </div>
      <form class="filters" method="get" action="#membres">
        <input type="search" name="q" value="<?= e($q['q']) ?>" placeholder="Rechercher : nom, e-mail, téléphone, CNE…">
        <select name="pole"><option value="">Tous les pôles</option>
          <?php foreach (POLES as $p): ?><option<?= $q['pole'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select>
        <select name="connexion">
          <option value="">Toutes les connexions</option>
          <option value="oui"<?= $q['connexion'] === 'oui' ? ' selected' : '' ?>>Déjà connectés</option>
          <option value="non"<?= $q['connexion'] === 'non' ? ' selected' : '' ?>>Jamais connectés</option>
          <option value="actifs"<?= $q['connexion'] === 'actifs' ? ' selected' : '' ?>>Actifs (30 jours)</option>
        </select>
        <select name="statut">
          <option value="">Tous les statuts</option>
          <option value="actif"<?= $q['statut'] === 'actif' ? ' selected' : '' ?>>Actifs</option>
          <option value="suspendu"<?= $q['statut'] === 'suspendu' ? ' selected' : '' ?>>Suspendus</option>
        </select>
        <select name="tri">
          <option value="recent"<?= $q['tri'] === 'recent' ? ' selected' : '' ?>>Plus récents</option>
          <option value="ancien"<?= $q['tri'] === 'ancien' ? ' selected' : '' ?>>Plus anciens</option>
          <option value="nom"<?= $q['tri'] === 'nom' ? ' selected' : '' ?>>Nom (A→Z)</option>
          <option value="login"<?= $q['tri'] === 'login' ? ' selected' : '' ?>>Dernière connexion</option>
          <option value="nb"<?= $q['tri'] === 'nb' ? ' selected' : '' ?>>Nb de connexions</option>
        </select>
        <button class="btn btn-gold btn-sm">Filtrer</button>
        <?php if (array_filter($q, fn($v, $k) => $v !== '' && $k !== 'tri', ARRAY_FILTER_USE_BOTH)): ?><a class="btn btn-ghost btn-sm" href="index.php#membres">Réinitialiser</a><?php endif; ?>
      </form>

      <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            <th>Membre</th><th>Contact</th><th>Parcours</th><th>Pôle</th><th>Inscrit le</th><th>Dernière connexion</th><th>Nb</th><th></th>
          </tr></thead>
          <tbody>
          <?php if (!$members): ?>
            <tr><td colspan="8" class="empty">Aucun membre ne correspond à ces critères.</td></tr>
          <?php endif; ?>
          <?php foreach ($members as $m): ?>
            <tr class="<?= $m['statut'] === 'suspendu' ? 'is-off' : '' ?>">
              <td data-l="Membre"><div>
                <details><summary><b><?= e($m['prenom'] . ' ' . $m['nom']) ?></b>
                  <?php if ($m['statut'] === 'suspendu'): ?><span class="tag tag-off">Suspendu</span><?php endif; ?></summary>
                  <div class="more">
                    <?php if ($m['cne']): ?><p><b>CNE :</b> <?= e($m['cne']) ?></p><?php endif; ?>
                    <p><b>Intérêts :</b> <?= e($m['interets'] ?: '—') ?></p>
                    <p><b>Motivation :</b> <?= nl2br(e($m['motivation'] ?: '—')) ?></p>
                  </div>
                </details>
              </div></td>
              <td data-l="Contact"><div><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><br><small><?= e($m['telephone']) ?></small></div></td>
              <td data-l="Parcours"><div><?= e($m['filiere']) ?><br><small><?= e($m['annee']) ?></small></div></td>
              <td data-l="Pôle"><div><span class="tag"><?= e($m['pole']) ?></span></div></td>
              <td data-l="Inscrit le"><div><?= e(fmt_date($m['created_at'], false)) ?></div></td>
              <td data-l="Dernière connexion"><div>
                <?php if ($m['last_login']): ?>
                  <span class="dot on"></span><span title="<?= e(fmt_date($m['last_login'])) ?>"><?= e(time_ago($m['last_login'])) ?></span>
                <?php else: ?>
                  <span class="dot"></span><span class="muted">Jamais connecté</span>
                <?php endif; ?>
              </div></td>
              <td data-l="Connexions"><div><?= (int) $m['login_count'] ?></div></td>
              <td class="actions">
                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>"><input type="hidden" name="action" value="toggle">
                  <button class="mini"><?= $m['statut'] === 'actif' ? 'Suspendre' : 'Réactiver' ?></button></form>
                <form method="post" data-confirm="Supprimer définitivement <?= e($m['prenom'] . ' ' . $m['nom']) ?> ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>"><input type="hidden" name="action" value="delete">
                  <button class="mini danger">Supprimer</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="pager">
          <?php if ($page > 1): ?><a href="<?= e(page_link($q, $page - 1)) ?>#membres">← Précédent</a><?php endif; ?>
          <span>Page <?= $page ?> / <?= $pages ?></span>
          <?php if ($page < $pages): ?><a href="<?= e(page_link($q, $page + 1)) ?>#membres">Suivant →</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
