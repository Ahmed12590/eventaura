<?php
declare(strict_types=1);

/* =========================================================
   EVENTAURA — Démarrage commun (session, BDD, sécurité)
   ========================================================= */

$CONFIG = require __DIR__ . '/config.php';

date_default_timezone_set($CONFIG['timezone']);
ini_set('display_errors', $CONFIG['debug'] ? '1' : '0');
error_reporting(E_ALL);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name('eva_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps,
]);
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ---------- Listes utilisées par le formulaire ---------- */
const POLES = [
    'Pôle Média',
    'Pôle Social',
    'Pôle Logistique',
    'Pôle Communication',
    'Pôle Trésorerie',
    'Sponsoring',
    'Pas encore décidé',
];
const ANNEES = ['1ère année', '2ème année', '3ème année', '4ème année', '5ème année', 'Master', 'Autre'];
const INTERETS = ['Art', 'Culture', 'Sport', 'Social', 'Événementiel'];

/* ---------- Base de données ---------- */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $CONFIG['db_host'], (int) $CONFIG['db_port'], $CONFIG['db_name']
        );
        try {
            $pdo = new PDO($dsn, $CONFIG['db_user'], $CONFIG['db_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Aligne l'heure MySQL sur celle de PHP (Maroc)
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            $msg = $CONFIG['debug'] ? e($e->getMessage()) : 'Vérifiez includes/config.php.';
            exit('<p style="font-family:sans-serif;padding:40px">Connexion à la base de données impossible. ' . $msg . '</p>');
        }
    }
    return $pdo;
}

/* ---------- Utilitaires ---------- */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type !== null) {
        $_SESSION['flash'] = [$type, $message];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function fmt_date(?string $dt, bool $withTime = true): string
{
    if (!$dt) return '—';
    $ts = strtotime($dt);
    return $withTime ? date('d/m/Y à H:i', $ts) : date('d/m/Y', $ts);
}

function time_ago(?string $dt): string
{
    if (!$dt) return 'Jamais';
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return "À l'instant";
    if ($diff < 3600)   return 'Il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400)  return 'Il y a ' . floor($diff / 3600) . ' h';
    if ($diff < 172800) return 'Hier';
    if ($diff < 2592000) return 'Il y a ' . floor($diff / 86400) . ' jours';
    return fmt_date($dt, false);
}

/* ---------- Protection CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('<p style="font-family:sans-serif;padding:40px">Session expirée. <a href="javascript:history.back()">Revenir</a> et réessayer.</p>');
    }
}

/* ---------- Limite des tentatives de connexion ---------- */
const MAX_ATTEMPTS = 8;      // tentatives
const ATTEMPT_WINDOW = 15;   // minutes

function too_many_attempts(string $scope): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts
        WHERE ip = ? AND scope = ? AND attempted_at > (NOW() - INTERVAL ' . ATTEMPT_WINDOW . ' MINUTE)');
    $st->execute([client_ip(), $scope]);
    return (int) $st->fetchColumn() >= MAX_ATTEMPTS;
}

function record_attempt(string $scope, string $email): void
{
    db()->prepare('INSERT INTO login_attempts (ip, scope, email) VALUES (?, ?, ?)')
        ->execute([client_ip(), $scope, mb_substr($email, 0, 190)]);
    // Nettoyage des vieilles lignes
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

function clear_attempts(string $scope): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ? AND scope = ?')->execute([client_ip(), $scope]);
}

/* ---------- Authentification membre ---------- */
function current_member(): ?array
{
    static $member = false;
    if ($member === false) {
        $member = null;
        if (!empty($_SESSION['member_id'])) {
            $st = db()->prepare('SELECT * FROM members WHERE id = ?');
            $st->execute([$_SESSION['member_id']]);
            $member = $st->fetch() ?: null;
            if (!$member || $member['statut'] !== 'actif') {
                unset($_SESSION['member_id']);
                $member = null;
            }
        }
    }
    return $member;
}

function require_member(): array
{
    $m = current_member();
    if (!$m) {
        flash('info', 'Connectez-vous pour accéder à votre espace.');
        redirect('connexion.php');
    }
    return $m;
}

/* ---------- Authentification admin ---------- */
function current_admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = null;
        if (!empty($_SESSION['admin_id'])) {
            $st = db()->prepare('SELECT id, nom, email, last_login FROM admins WHERE id = ?');
            $st->execute([$_SESSION['admin_id']]);
            $admin = $st->fetch() ?: null;
            if (!$admin) unset($_SESSION['admin_id']);
        }
    }
    return $admin;
}

function require_admin(): array
{
    $a = current_admin();
    if (!$a) redirect('connexion.php');
    // Déconnexion automatique après 2 h d'inactivité
    if (isset($_SESSION['admin_seen']) && time() - $_SESSION['admin_seen'] > 7200) {
        unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
        flash('info', 'Session expirée, reconnectez-vous.');
        redirect('connexion.php');
    }
    $_SESSION['admin_seen'] = time();
    return $a;
}
