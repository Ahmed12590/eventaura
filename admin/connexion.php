<?php
require __DIR__ . '/_init.php';

if (current_admin()) redirect('index.php');

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (too_many_attempts('admin')) {
        $error = 'Trop de tentatives. Réessayez dans ' . ATTEMPT_WINDOW . ' minutes.';
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM admins WHERE email = ?');
        $st->execute([$email]);
        $a = $st->fetch();
        if ($a && password_verify($password, $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $a['id'];
            $_SESSION['admin_seen'] = time();
            db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$a['id']]);
            clear_attempts('admin');
            redirect('index.php');
        }
        record_attempt('admin', $email);
        $error = 'Identifiants incorrects.';
    }
}

page_header('Administration', '../', 'admin');
?>
<section class="auth auth-narrow">
  <div class="wrap">
    <form class="card form login" method="post">
      <?= csrf_field() ?>
      <img src="../logo.png" alt="" class="auth-logo sm">
      <h2>Administration</h2>
      <p class="muted center">Accès réservé au bureau d'EVENTAURA.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <label>E-mail<input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></label>
      <label>Mot de passe<span class="pass"><input type="password" id="ap" name="password" required autocomplete="current-password"><button type="button" data-toggle-pass="ap">Afficher</button></span></label>
      <button class="btn btn-gold btn-block">Se connecter</button>
    </form>
  </div>
</section>
<?php page_footer();
