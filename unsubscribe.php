<?php
/**
 * Unsubscribe from Market2Door update emails (the link at the bottom of every update email).
 * GET shows a confirm button (so link scanners can't unsubscribe people by accident);
 * POST unsubscribes. Mail apps' one-click unsubscribe POSTs here directly.
 */
declare(strict_types=1);

require __DIR__ . '/backend/bootstrap.php';

header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

const SUPPORT = 'support@market2door.ng';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$valid = $id > 0 && hash_equals(unsubscribe_token($id), $token);

$person = false;
if ($valid) {
    $stmt = db()->prepare('SELECT id, name, email, unsubscribed FROM signups WHERE id = ?');
    $stmt->execute([$id]);
    $person = $stmt->fetch();
}

$state = !$person ? 'invalid' : ($person['unsubscribed'] ? 'done' : 'confirm');
if ($person && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    db()->prepare('UPDATE signups SET unsubscribed = 1 WHERE id = ?')->execute([$id]);
    $state = 'done';
}

$titles = [
    'confirm' => 'Stop update emails?',
    'done'    => 'You’re unsubscribed',
    'invalid' => 'This link doesn’t work',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($titles[$state]) ?> · Market2Door</title>
  <link rel="icon" href="assets/brand/favicon-32.png" type="image/png">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Plus+Jakarta+Sans:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root { --green: #123821; --gold: #E8B04B; --muted: #5B6A60; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #0D2717 radial-gradient(circle at 50% 120%, rgba(232, 176, 75, .32), transparent 55%); font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: var(--green); }
    .box { width: 100%; max-width: 440px; padding: 36px 28px; border-radius: 24px; background: #fff; text-align: center; box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .6); }
    .brand { display: block; width: 210px; height: auto; margin: 0 auto 18px; }
    h1 { margin: 0 0 10px; font: 600 1.6rem/1.2 Fraunces, Georgia, serif; }
    p { margin: 0 0 18px; color: var(--muted); line-height: 1.6; }
    a { color: var(--green); }
    button, .link { display: inline-flex; align-items: center; justify-content: center; min-height: 50px; padding: 0 26px; border: 0; border-radius: 999px; background: var(--gold); color: var(--green); font: 700 1rem 'Plus Jakarta Sans', system-ui, sans-serif; text-decoration: none; cursor: pointer; }
    .link--ghost { background: none; text-decoration: underline; text-underline-offset: 4px; }
  </style>
</head>
<body>
  <main class="box">
    <img class="brand" src="assets/brand/market2door-logo.png" alt="Market2Door" width="900" height="530">
    <h1><?= e($titles[$state]) ?></h1>
<?php if ($state === 'confirm'): ?>
    <p><?= e(first_name($person['name'])) ?>, you’ll stop getting Market2Door update emails at <strong><?= e($person['email']) ?></strong>. You’ll keep your place on the waitlist.</p>
    <form method="post">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="t" value="<?= e($token) ?>">
      <button type="submit">Unsubscribe</button>
    </form>
    <p style="margin:16px 0 0"><a class="link link--ghost" href="./">Keep me subscribed</a></p>
<?php elseif ($state === 'done'): ?>
    <p>We won’t send update emails to <strong><?= e($person['email']) ?></strong> any more, and you keep your place on the waitlist. Changed your mind? Email <a href="mailto:<?= SUPPORT ?>"><?= SUPPORT ?></a>.</p>
    <a class="link" href="./">Back to market2door.ng</a>
<?php else: ?>
    <p>The unsubscribe link is incomplete or has expired. To stop update emails, reply to any Market2Door email or write to <a href="mailto:<?= SUPPORT ?>"><?= SUPPORT ?></a>.</p>
    <a class="link" href="./">Back to market2door.ng</a>
<?php endif; ?>
  </main>
</body>
</html>
