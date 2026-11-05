<?php
/* =========================================================
   Market2Door — waitlist handler
   Receives the waitlist form (multipart or urlencoded POST), validates it,
   saves it to the database, emails the support inbox and sends the person
   a branded auto-reply. Always answers with JSON:
     { "ok": true, "already": false }  or  { "ok": false, "error": "...", "field": "..." }
   ========================================================= */

declare(strict_types=1);

require __DIR__ . '/backend/mailer.php';

const RATE_LIMIT  = 5;     // sign-ups allowed per IP address...
const RATE_WINDOW = 3600;  // ...per this many seconds

function fail(string $error, ?string $field = null, int $status = 422): void
{
    json_response($status, array_filter(['ok' => false, 'error' => $error, 'field' => $field], fn ($v) => $v !== null));
}

/** A value from $_POST that must be one of $allowed ('' when it isn't). */
function pick(string $name, array $allowed, int $max = 60): string
{
    $v = clean_line($_POST[$name] ?? '', $max);
    return in_array($v, $allowed, true) ? $v : '';
}

$origin = allow_cors();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST');
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    fail('Please use the waitlist form on market2door.ng.', null, 405);
}
if ($origin !== '' && !in_array($origin, ALLOWED_ORIGINS, true) && !is_local_origin($origin)) {
    fail('Sign-ups are only accepted from market2door.ng.', null, 403);
}

// Spam traps: a filled hidden field, or a form sent within 2.5 seconds of loading.
// Bots get a normal-looking success so they don't retry.
$started = (int) ($_POST['started'] ?? 0);
if (clean_line($_POST['website'] ?? '') !== '' || ($started > 0 && (microtime(true) * 1000 - $started) < 2500)) {
    json_response(200, ['ok' => true]);
}

/* ---------- Validate ---------- */
$r = [
    'name'  => clean_line($_POST['name'] ?? '', 100),
    'email' => strtolower(clean_line($_POST['email'] ?? '', 160)),
    'phone' => clean_line($_POST['phone'] ?? '', 25),
    'city'  => clean_line($_POST['city'] ?? '', 80),
    'state' => pick('state', STATES, 40),
    'role'  => pick('role', array_keys(ROLES), 40),
    'vehicle_type' => '', 'fleet_size' => '', 'business_name' => '', 'business_type' => '',
];

if (mb_strlen($r['name']) < 2)                         fail('Please enter your full name.', 'name');
if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL))   fail('Please enter a valid email address.', 'email');
$digits = strlen(preg_replace('/\D/', '', $r['phone']));
if (!preg_match('/^[0-9+()\-.\s]+$/', $r['phone']) || $digits < 10 || $digits > 15) {
    fail('Please enter a valid phone number, for example 0803 123 4567.', 'phone');
}
// City, state and role are optional (the waitlist asks only for name, email and phone),
// but anything sent must be valid. Role-specific details are optional too.
if ($r['city'] !== '' && mb_strlen($r['city']) < 2)                  fail('Please check your city or area.', 'city');
if (($_POST['state'] ?? '') !== '' && $r['state'] === '')            fail('Please choose your state from the list.', 'state');
if (($_POST['role'] ?? '') !== '' && $r['role'] === '')              fail('Please choose how you’ll use Market2Door.', 'role');

if ($r['role'] === 'Rider') {
    $r['vehicle_type'] = pick('rider_vehicle', RIDER_VEHICLES);
}
if ($r['role'] === 'Vehicle owner') {
    $r['vehicle_type'] = pick('fleet_vehicle', FLEET_VEHICLES);
    $r['fleet_size']   = pick('fleet_size', FLEET_SIZES);
}
if ($r['role'] === 'Business') {
    $r['business_name'] = clean_line($_POST['business_name'] ?? '', 120);
    $r['business_type'] = pick('business_type', BUSINESS_TYPES);
}

$r['interests'] = implode(', ', array_intersect(INTERESTS, (array) ($_POST['interests'] ?? [])));
$r['frequency'] = pick('frequency', FREQUENCIES);
$r['heard']     = pick('heard', SOURCES);
$r['message']   = clean_text($_POST['message'] ?? '', 1000);
$r['source']    = $origin;
$r['created_at'] = now();

if (($_POST['consent'] ?? '') !== 'yes') {
    fail('Please tick the box so we can contact you about your place on the waitlist.', 'consent');
}

try {
    /* ---------- Rate limit ---------- */
    if (recent_hits('signup', RATE_WINDOW)['ip'] >= RATE_LIMIT) {
        fail('You’ve already sent a few sign-ups. Please try again later, or email ' . MAIL_FROM . '.', null, 429);
    }
    record_hit('signup');

    /* ---------- Already on the list? ---------- */
    $stmt = db()->prepare('SELECT id FROM signups WHERE email = ?');
    $stmt->execute([$r['email']]);
    if ($stmt->fetchColumn()) {
        json_response(200, ['ok' => true, 'already' => true]);
    }

    /* ---------- Save ---------- */
    $cols = array_keys($r);
    db()->prepare('INSERT INTO signups (' . implode(', ', $cols) . ') VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ')')
        ->execute(array_values($r));
    $id = (int) db()->lastInsertId();
} catch (Throwable $ex) {
    error_log('Market2Door waitlist: ' . $ex->getMessage());
    fail('We couldn’t save your details just now. Please try again, or email ' . MAIL_FROM . '.', null, 500);
}

/* ---------- Emails (a failure here never loses the sign-up) ---------- */
$notified = send_signup_notification($r);
$replied = get_settings()['autoreply_enabled'] === 'on' && send_autoreply($r);
db()->prepare('UPDATE signups SET notified = ?, autoreply_sent = ? WHERE id = ?')->execute([(int) $notified, (int) $replied, $id]);

json_response(200, ['ok' => true, 'already' => false]);
