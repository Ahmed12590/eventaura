<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$m = require_member();
$prev = $_SESSION['prev_login'] ?? null;

page_header('Mon espace');
?>
<section class="dash-member">
  <div class="wrap">
    <div class="hello">
      <div>
        <div class="eyebrow">Espace membre</div>
        <h1>Salut <span class="g"><?= e($m['prenom']) ?></span> 👋</h1>
        <p class="muted">
          <?= $prev ? 'Dernière visite : ' . e(fmt_date($prev)) : 'Première connexion — bienvenue dans la communauté !' ?>
        </p>
      </div>
      <form method="post" action="deconnexion.php"><?= csrf_field() ?><button class="btn btn-ghost">Se déconnecter</button></form>
    </div>

    <div class="mgrid">
      <div class="card">
        <h3>Ma carte de membre</h3>
        <div class="mcard">
          <img src="logo.png" alt="">
          <div>
            <strong><?= e($m['prenom'] . ' ' . $m['nom']) ?></strong>
            <span><?= e($m['pole']) ?></span>
            <small>Membre n° EVA-<?= str_pad((string) $m['id'], 4, '0', STR_PAD_LEFT) ?> · depuis le <?= e(fmt_date($m['created_at'], false)) ?></small>
          </div>
        </div>
        <dl class="info">
          <dt>E-mail</dt><dd><?= e($m['email']) ?></dd>
          <dt>Téléphone</dt><dd><?= e($m['telephone']) ?></dd>
          <dt>Filière</dt><dd><?= e($m['filiere']) ?> — <?= e($m['annee']) ?></dd>
          <?php if ($m['cne']): ?><dt>CNE</dt><dd><?= e($m['cne']) ?></dd><?php endif; ?>
          <dt>Intérêts</dt><dd><?= e($m['interets'] ?: '—') ?></dd>
        </dl>
        <p class="muted small">Une information à corriger ? Écrivez au bureau : eventauraensiens@gmail.com</p>
      </div>

      <div class="card">
        <h3>Au programme 2026–2027</h3>
        <ul class="plist">
          <li><span>🤝</span><div><b>Visite à une maison de retraite</b>Partage, écoute et solidarité.</div></li>
          <li><span>🌙</span><div><b>2ᵉ édition du Ftour Ramadan</b>Distribution de ftour aux personnes en situation de précarité.</div></li>
          <li><span>🎢</span><div><b>Sortie dans un espace de jeux</b>Détente et cohésion entre adhérents.</div></li>
          <li><span>🧭</span><div><b>Voyage découverte</b>Une sortie touristique et culturelle.</div></li>
          <li><span>🎤</span><div><b>Événement culturel et artistique</b>Mettre en valeur les talents des étudiants.</div></li>
          <li><span>🏀</span><div><b>Événement sportif</b>Compétitions amicales.</div></li>
          <li><span>💡</span><div><b>Workshops et ateliers</b>Développer ses compétences.</div></li>
        </ul>
        <a class="btn btn-gold btn-sm" href="index.html#evenements">Voir nos événements →</a>
      </div>
    </div>
  </div>
</section>
<?php page_footer();
