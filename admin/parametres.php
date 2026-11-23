<?php
require __DIR__ . '/_init.php';
$admin = require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'password') {
        $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([$admin['id']]);
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $st->fetchColumn())) $errors[] = 'Mot de passe actuel incorrect.';
        elseif (mb_strlen($new) < 10) $errors[] = 'Nouveau mot de passe : 10 caractères minimum.';
        elseif ($new !== (string) ($_POST['new2'] ?? '')) $errors[] = 'Les mots de passe ne correspondent pas.';
        else {
            db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            flash('success', 'Mot de passe modifié.');
            redirect('parametres.php');
        }
    }

    if ($action === 'add') {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $pw = (string) ($_POST['password'] ?? '');
        if (mb_strlen($nom) < 2) $errors[] = 'Indiquez un nom.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail invalide.';
        if (mb_strlen($pw) < 10) $errors[] = 'Mot de passe : 10 caractères minimum.';
        if (!$errors) {
            $st = db()->prepare('SELECT 1 FROM admins WHERE email = ?');
            $st->execute([$email]);
            if ($st->fetchColumn()) $errors[] = 'Cet e-mail est déjà administrateur.';
        }
        if (!$errors) {
            db()->prepare('INSERT INTO admins (nom, email, password_hash) VALUES (?, ?, ?)')->execute([$nom, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            flash('success', "Administrateur « $nom » ajouté.");
            redirect('parametres.php');
        }
    }

    if ($action === 'remove') {
        $id = (int) ($_POST['id'] ?? 0);
        $count = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($id === (int) $admin['id']) $errors[] = 'Vous ne pouvez pas supprimer votre propre compte.';
        elseif ($count <= 1) $errors[] = 'Il doit rester au moins un administrateur.';
        else {
            db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            flash('success', 'Administrateur retiré.');
            redirect('parametres.php');
        }
    }
}

$admins = db()->query('SELECT id, nom, email, created_at, last_login FROM admins ORDER BY created_at')->fetchAll();

admin_nav('parametres.php');
page_header('Paramètres', '../', 'admin');
?>
<section class="admin-dash">
  <div class="wrap">
    <div class="hello"><div><div class="eyebrow">Administration</div><h1>Paramètres</h1></div></div>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>

    <div class="mgrid">
      <div class="card">
        <h3>Administrateurs</h3>
        <ul class="feed">
          <?php foreach ($admins as $a): ?>
            <li><span class="av"><?= e(mb_strtoupper(mb_substr($a['nom'], 0, 2))) ?></span>
              <div><b><?= e($a['nom']) ?><?= (int) $a['id'] === (int) $admin['id'] ? ' (vous)' : '' ?></b><small><?= e($a['email']) ?> · dernière connexion : <?= e(time_ago($a['last_login'])) ?></small></div>
              <?php if ((int) $a['id'] !== (int) $admin['id']): ?>
                <form method="post" data-confirm="Retirer l'accès admin de <?= e($a['nom']) ?> ?"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="mini danger">Retirer</button></form>
              <?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <h4>Ajouter un administrateur</h4>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="add">
          <label>Nom<input name="nom" required></label>
          <label>E-mail<input type="email" name="email" required></label>
          <label>Mot de passe provisoire (10 car. min.)<input type="password" name="password" required autocomplete="new-password"></label>
          <button class="btn btn-gold btn-sm">Ajouter</button>
        </form>
      </div>

      <div class="card">
        <h3>Changer mon mot de passe</h3>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <label>Mot de passe actuel<input type="password" name="current" required autocomplete="current-password"></label>
          <label>Nouveau mot de passe<input type="password" name="new" required autocomplete="new-password"></label>
          <label>Confirmer<input type="password" name="new2" required autocomplete="new-password"></label>
          <button class="btn btn-gold btn-sm">Enregistrer</button>
        </form>
      </div>
    </div>
  </div>
</section>
<?php page_footer();
