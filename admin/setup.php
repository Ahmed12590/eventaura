<?php
/**
 * Création du PREMIER compte administrateur.
 * Ne fonctionne que si aucun admin n'existe encore.
 * Conseil : supprimez ce fichier du serveur une fois le compte créé.
 */
require __DIR__ . '/_init.php';

$hasAdmin = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
$errors = [];
$old = ['nom' => '', 'email' => ''];

if (!$hasAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['nom'] = trim((string) ($_POST['nom'] ?? ''));
    $old['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $pw = (string) ($_POST['password'] ?? '');

    if (mb_strlen($old['nom']) < 2) $errors[] = 'Indiquez un nom.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail invalide.';
    if (mb_strlen($pw) < 10) $errors[] = 'Mot de passe : 10 caractères minimum.';
    if ($pw !== (string) ($_POST['password2'] ?? '')) $errors[] = 'Les mots de passe ne correspondent pas.';

    if (!$errors) {
        db()->prepare('INSERT INTO admins (nom, email, password_hash) VALUES (?, ?, ?)')
            ->execute([$old['nom'], $old['email'], password_hash($pw, PASSWORD_DEFAULT)]);
        flash('success', 'Compte administrateur créé. Connectez-vous, puis supprimez admin/setup.php du serveur.');
        redirect('connexion.php');
    }
}

page_header('Installation', '../');
?>
<section class="auth auth-narrow">
  <div class="wrap">
    <div class="card form login">
      <img src="../logo.png" alt="" class="auth-logo sm">
      <h2>Installation de l'administration</h2>
      <?php if ($hasAdmin): ?>
        <div class="alert alert-info">Un administrateur existe déjà. Cette page est désactivée.</div>
        <a class="btn btn-gold btn-block" href="connexion.php">Aller à la connexion</a>
      <?php else: ?>
        <p class="muted center">Créez le premier compte qui aura accès au tableau de bord.</p>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post">
          <?= csrf_field() ?>
          <label>Nom complet<input name="nom" value="<?= e($old['nom']) ?>" required></label>
          <label>E-mail<input type="email" name="email" value="<?= e($old['email']) ?>" required></label>
          <label>Mot de passe (10 caractères min.)<span class="pass"><input type="password" id="a1" name="password" required autocomplete="new-password"><button type="button" data-toggle-pass="a1">Afficher</button></span></label>
          <label>Confirmer<input type="password" name="password2" required autocomplete="new-password"></label>
          <button class="btn btn-gold btn-block">Créer le compte admin</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
