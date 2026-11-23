<?php
/* Gabarit commun des pages PHP. $base = '' (racine) ou '../' (dossier admin) */

function page_header(string $title, string $base = '', string $bodyClass = ''): void
{
    $member = $base === '' ? current_member() : null;
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> — EVENTAURA</title>
<link rel="icon" href="<?= $base ?>logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/app.css">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="topbar">
  <div class="wrap topbar-in">
    <a href="<?= $base ?>index.html" class="brand"><img src="<?= $base ?>logo.png" alt=""><span class="bt">EVENT<b>AURA</b></span></a>
    <nav class="top-links">
      <?php if ($base === ''): ?>
        <a href="index.html">← Retour au site</a>
        <?php if ($member): ?>
          <a href="espace.php" class="pill-link">Mon espace</a>
        <?php else: ?>
          <a href="connexion.php">Connexion</a>
          <a href="inscription.php" class="pill-link">S'inscrire</a>
        <?php endif; ?>
      <?php else: ?>
        <?= $GLOBALS['ADMIN_NAV'] ?? '' ?>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
    <?php
    $f = flash();
    if ($f): ?>
  <div class="wrap"><div class="alert alert-<?= e($f[0]) ?>" role="status"><?= e($f[1]) ?></div></div>
    <?php endif;
}

function page_footer(): void
{
    ?>
</main>
<footer class="foot">
  <div class="wrap foot-in">
    <span>© <?= date('Y') ?> EVENTAURA (EVA) · ENS Casablanca</span>
    <span>Developed by <a href="https://github.com/ahmed12590" target="_blank" rel="noopener">Ahmed</a></span>
  </div>
</footer>
<script>
document.querySelectorAll('[data-toggle-pass]').forEach(function (b) {
  b.addEventListener('click', function () {
    var i = document.getElementById(b.dataset.togglePass);
    i.type = i.type === 'password' ? 'text' : 'password';
    b.textContent = i.type === 'password' ? 'Afficher' : 'Masquer';
  });
});
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (!window.confirm(f.dataset.confirm)) ev.preventDefault();
  });
});
</script>
</body>
</html>
    <?php
}
