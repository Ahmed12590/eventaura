<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_member()) redirect('espace.php');

$email = $_SESSION['last_email'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (too_many_attempts('member')) {
        $error = 'Trop de tentatives. Réessayez dans ' . ATTEMPT_WINDOW . ' minutes.';
    } else {
        $st = db()->prepare('SELECT id, prenom, password_hash, statut, last_login FROM members WHERE email = ?');
        $st->execute([$email]);
        $m = $st->fetch();

        if ($m && password_verify($password, $m['password_hash'])) {
            if ($m['statut'] !== 'actif') {
                $error = 'Votre compte est suspendu. Contactez le bureau du club.';
            } else {
                session_regenerate_id(true);
                $_SESSION['member_id'] = (int) $m['id'];
                $_SESSION['prev_login'] = $m['last_login'];
                unset($_SESSION['last_email']);

                db()->prepare('UPDATE members SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?')
                    ->execute([$m['id']]);
                db()->prepare('INSERT INTO login_logs (member_id, ip, user_agent) VALUES (?, ?, ?)')
                    ->execute([$m['id'], client_ip(), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
                if (password_needs_rehash($m['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE members SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $m['id']]);
                }
                clear_attempts('member');
                redirect('espace.php');
            }
        } else {
            record_attempt('member', $email);
            $error = 'E-mail ou mot de passe incorrect.';
        }
    }
}

page_header('Connexion');
?>
<section class="auth auth-narrow">
  <div class="wrap">
    <form class="card form login" method="post" novalidate>
      <?= csrf_field() ?>
      <img src="logo.png" alt="" class="auth-logo sm">
      <h2>Espace membre</h2>
      <p class="muted center">Connecte-toi avec l'e-mail utilisé lors de ton inscription.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <label>E-mail<input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus></label>
      <label>Mot de passe
        <span class="pass"><input type="password" id="pw" name="password" autocomplete="current-password" required><button type="button" data-toggle-pass="pw">Afficher</button></span></label>
      <button class="btn btn-gold btn-block" type="submit">Se connecter</button>
      <p class="muted center small">Pas encore membre ? <a href="inscription.php">S'inscrire</a></p>
      <p class="muted center small">Mot de passe oublié ? Écrivez à <a href="mailto:eventauraensiens@gmail.com">eventauraensiens@gmail.com</a></p>
    </form>
  </div>
</section>
<?php page_footer();
