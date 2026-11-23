<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_member()) redirect('espace.php');

$fields = ['prenom', 'nom', 'email', 'telephone', 'cne', 'filiere', 'annee', 'pole', 'motivation'];
$old = array_fill_keys($fields, '');
$oldInterets = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Piège anti-robots : ce champ caché doit rester vide
    if (!empty($_POST['website'])) {
        redirect('inscription.php');
    }

    foreach ($fields as $f) {
        $old[$f] = trim((string) ($_POST[$f] ?? ''));
    }
    $old['email'] = mb_strtolower($old['email']);
    $old['telephone'] = preg_replace('/[\s.\-]/', '', $old['telephone']);
    $old['cne'] = strtoupper(preg_replace('/\s/', '', $old['cne']));
    $oldInterets = array_values(array_intersect(INTERETS, (array) ($_POST['interets'] ?? [])));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($old['prenom']) < 2 || mb_strlen($old['prenom']) > 80) $errors['prenom'] = 'Prénom requis.';
    if (mb_strlen($old['nom']) < 2 || mb_strlen($old['nom']) > 80) $errors['nom'] = 'Nom requis.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) $errors['email'] = 'Adresse e-mail invalide.';
    if (!preg_match('/^(\+212|00212|0)[5-7]\d{8}$/', $old['telephone'])) $errors['telephone'] = 'Numéro invalide (ex : 06 12 34 56 78).';
    if ($old['cne'] !== '' && !preg_match('/^[A-Z0-9]{5,20}$/', $old['cne'])) $errors['cne'] = 'CNE / Code Massar invalide.';
    if (mb_strlen($old['filiere']) < 2 || mb_strlen($old['filiere']) > 120) $errors['filiere'] = 'Indiquez votre filière.';
    if (!in_array($old['annee'], ANNEES, true)) $errors['annee'] = "Choisissez votre année d'études.";
    if (!in_array($old['pole'], POLES, true)) $errors['pole'] = 'Choisissez un pôle.';
    if (mb_strlen($old['motivation']) > 1000) $errors['motivation'] = '1000 caractères maximum.';
    if (mb_strlen($password) < 8) $errors['password'] = 'Au moins 8 caractères.';
    elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) $errors['password'] = 'Utilisez au moins une lettre et un chiffre.';
    if ($password !== $password2) $errors['password2'] = 'Les mots de passe ne correspondent pas.';
    if (empty($_POST['consent'])) $errors['consent'] = 'Votre accord est nécessaire pour vous inscrire.';

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM members WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetchColumn()) {
            $errors['email'] = 'Cette adresse est déjà inscrite. <a href="connexion.php">Se connecter</a>';
        }
    }

    if (!$errors) {
        $st = db()->prepare('INSERT INTO members
            (prenom, nom, email, telephone, cne, filiere, annee, pole, interets, motivation, password_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $old['prenom'], $old['nom'], $old['email'], $old['telephone'],
            $old['cne'] !== '' ? $old['cne'] : null,
            $old['filiere'], $old['annee'], $old['pole'],
            $oldInterets ? implode(', ', $oldInterets) : null,
            $old['motivation'] !== '' ? $old['motivation'] : null,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $_SESSION['last_email'] = $old['email'];
        flash('success', 'Bienvenue dans la communauté EVENTAURA, ' . $old['prenom'] . ' ! Connectez-vous pour accéder à votre espace.');
        redirect('connexion.php');
    }
}

function err(array $errors, string $k): string
{
    // Certains messages contiennent un lien interne contrôlé par le code
    return isset($errors[$k]) ? '<small class="err">' . $errors[$k] . '</small>' : '';
}
function inv(array $errors, string $k): string
{
    return isset($errors[$k]) ? ' aria-invalid="true"' : '';
}

page_header('Inscription');
?>
<section class="auth">
  <div class="wrap auth-grid">
    <aside class="auth-side">
      <img src="logo.png" alt="" class="auth-logo">
      <div class="eyebrow">Adhésion 2026–2027</div>
      <h1>Rejoins <span class="g">l'aura</span></h1>
      <p>Inscris-toi pour devenir membre d'EVENTAURA, le club des ENSiens qui fait vivre l'art, la culture, le sport et l'action sociale à l'ENS Casablanca.</p>
      <ul class="perks">
        <li>Accès à ton espace membre</li>
        <li>Participation aux événements et sorties du club</li>
        <li>Possibilité de rejoindre un pôle de l'équipe</li>
      </ul>
      <p class="muted small">Déjà inscrit·e ? <a href="connexion.php">Se connecter</a></p>
    </aside>

    <form class="card form" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <h2>Formulaire d'inscription</h2>
      <?php if ($errors): ?><div class="alert alert-error">Merci de corriger les champs indiqués.</div><?php endif; ?>

      <fieldset>
        <legend>Informations personnelles</legend>
        <div class="row2">
          <label>Prénom *<input name="prenom" value="<?= e($old['prenom']) ?>" autocomplete="given-name" required<?= inv($errors, 'prenom') ?>><?= err($errors, 'prenom') ?></label>
          <label>Nom *<input name="nom" value="<?= e($old['nom']) ?>" autocomplete="family-name" required<?= inv($errors, 'nom') ?>><?= err($errors, 'nom') ?></label>
        </div>
        <div class="row2">
          <label>E-mail *<input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required<?= inv($errors, 'email') ?>><?= err($errors, 'email') ?></label>
          <label>Téléphone *<input type="tel" name="telephone" value="<?= e($old['telephone']) ?>" placeholder="06 12 34 56 78" autocomplete="tel" required<?= inv($errors, 'telephone') ?>><?= err($errors, 'telephone') ?></label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Parcours à l'ENS</legend>
        <div class="row2">
          <label>Filière *<input name="filiere" value="<?= e($old['filiere']) ?>" placeholder="ex : Licence d'éducation – Informatique" required<?= inv($errors, 'filiere') ?>><?= err($errors, 'filiere') ?></label>
          <label>Année d'études *
            <select name="annee" required<?= inv($errors, 'annee') ?>>
              <option value="">Choisir…</option>
              <?php foreach (ANNEES as $a): ?><option<?= $old['annee'] === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
            </select><?= err($errors, 'annee') ?></label>
        </div>
        <label><span>CNE / Code Massar <span class="muted">(facultatif)</span></span><input name="cne" value="<?= e($old['cne']) ?>"<?= inv($errors, 'cne') ?>><?= err($errors, 'cne') ?></label>
      </fieldset>

      <fieldset>
        <legend>Ton engagement</legend>
        <label>Pôle souhaité *
          <select name="pole" required<?= inv($errors, 'pole') ?>>
            <option value="">Choisir…</option>
            <?php foreach (POLES as $p): ?><option<?= $old['pole'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
          </select><?= err($errors, 'pole') ?></label>
        <div class="lbl">Centres d'intérêt</div>
        <div class="chips">
          <?php foreach (INTERETS as $i): ?>
            <label class="chip"><input type="checkbox" name="interets[]" value="<?= e($i) ?>"<?= in_array($i, $oldInterets, true) ? ' checked' : '' ?>><span><?= e($i) ?></span></label>
          <?php endforeach; ?>
        </div>
        <label><span>Pourquoi veux-tu rejoindre EVENTAURA ? <span class="muted">(facultatif)</span></span>
          <textarea name="motivation" rows="3" maxlength="1000"<?= inv($errors, 'motivation') ?>><?= e($old['motivation']) ?></textarea><?= err($errors, 'motivation') ?></label>
      </fieldset>

      <fieldset>
        <legend>Ton compte</legend>
        <div class="row2">
          <label>Mot de passe *
            <span class="pass"><input type="password" id="pw1" name="password" autocomplete="new-password" required<?= inv($errors, 'password') ?>><button type="button" data-toggle-pass="pw1">Afficher</button></span>
            <?= err($errors, 'password') ?: '<small class="muted">8 caractères min., lettres et chiffres.</small>' ?></label>
          <label>Confirmer *
            <span class="pass"><input type="password" id="pw2" name="password2" autocomplete="new-password" required<?= inv($errors, 'password2') ?>><button type="button" data-toggle-pass="pw2">Afficher</button></span>
            <?= err($errors, 'password2') ?></label>
        </div>
      </fieldset>

      <label class="check"><input type="checkbox" name="consent" value="1"<?= !empty($_POST['consent']) ? ' checked' : '' ?>>
        <span>J'accepte que mes informations soient enregistrées et utilisées uniquement par le bureau d'EVENTAURA pour la gestion des adhésions (loi 09-08).</span></label>
      <?= err($errors, 'consent') ?>

      <button class="btn btn-gold btn-block" type="submit">Créer mon compte →</button>
    </form>
  </div>
</section>
<?php page_footer();
