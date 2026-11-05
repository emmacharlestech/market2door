<?php
/**
 * Shared setup for every backend script: config, SQLite database, helpers.
 */
declare(strict_types=1);

const BACKEND_DIR = __DIR__;
define('SITE_DIR', dirname(__DIR__));
define('UPLOAD_DIR', SITE_DIR . '/uploads');

$configFile = BACKEND_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'The site backend is not installed yet.']);
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configFile;

ini_set('display_errors', $CONFIG['debug'] ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Africa/Lagos');

/** Sites allowed to post to the waitlist and read public content. */
const ALLOWED_ORIGINS = ['https://market2door.ng', 'https://www.market2door.ng', 'https://market2door.vercel.app'];

/** Waitlist roles. Keys are stored in the database; values are what people see. */
const ROLES = [
    'Customer'      => 'Customer',
    'Rider'         => 'Rider',
    'Vehicle owner' => 'Van or truck owner',
    'Business'      => 'Business',
];
const RIDER_VEHICLES = ['Motorcycle', 'Tricycle (keke)', 'Car', 'Bicycle', 'Van'];
const FLEET_VEHICLES = ['Pickup or mini van', 'Large van or bus', 'Truck (up to 10 tonnes)', 'Truck or trailer (over 10 tonnes)'];
const FLEET_SIZES    = ['1 vehicle', '2-5 vehicles', '6-20 vehicles', 'More than 20 vehicles'];
const BUSINESS_TYPES = ['Restaurant or caterer', 'Supermarket or shop', 'Hotel or guest house', 'Office or company', 'Online store', 'Wholesaler or distributor', 'Other'];
const INTERESTS      = ['Foodstuff delivery', 'Market shopping for me', 'Parcel delivery', 'Van and truck hire', 'Home or office moving', 'Bulk supply for business'];
const FREQUENCIES    = ['Every week', 'Every two weeks', 'Once a month', 'Now and then'];
const SOURCES        = ['Instagram', 'Facebook', 'TikTok', 'WhatsApp', 'X (Twitter)', 'A friend or family member', 'Search engine', 'Other'];
const STATES = [
    'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta',
    'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT (Abuja)', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina',
    'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers',
    'Sokoto', 'Taraba', 'Yobe', 'Zamfara', 'Outside Nigeria',
];
const SIGNUP_STATUSES = ['new' => 'New', 'contacted' => 'Contacted'];
/** Who an update email can go to: everyone, or one waitlist role. */
const AUDIENCES = ['all' => 'Everyone', 'Customer' => 'Customers', 'Rider' => 'Riders', 'Vehicle owner' => 'Van and truck owners', 'Business' => 'Businesses'];

const SCHEMA_VERSION = 4;

/** The photos "Fresh from the market" starts with (see credits.html): [title, subtitle, image, alt]. */
const BUILTIN_GALLERY = [
    ['Tomatoes', 'Fresh, by the basket', 'assets/img/u-tomato-table.jpg', 'Shoppers around a table piled with fresh tomatoes'],
    ['Peppers', 'Tatashe, ata rodo and more', 'assets/img/u-hero-produce.jpg', 'A trader behind baskets of fresh tomatoes and peppers'],
    ['Rice', 'By the bag or the cup', 'assets/img/u-rice-baskets.jpg', 'Baskets of white rice at a market'],
    ['Beans & grains', 'Oloyin, brown and white', 'assets/img/u-grain-sacks.jpg', 'Open sacks of beans and grains'],
    ['Garri', 'White or yellow, by the bag', 'assets/img/garri-bags.jpg', 'A trader scooping from large open bags of garri'],
    ['Palm oil', 'Bottles and kegs', 'assets/img/palm-oil-bottles.jpg', 'Bottles of palm oil and groundnut oil on a trader’s table'],
    ['Spices', 'Pepper, curry and more', 'assets/img/u-spices-pile.jpg', 'Colourful spices piled high at a market'],
    ['Plantain', 'By the bunch', 'assets/img/u-plantain-stall.jpg', 'Two women at a food stall with plantains'],
    ['Watermelon', 'In season', 'assets/img/u-watermelon-seller.jpg', 'A seller with a wheelbarrow of sliced watermelon'],
    ['Fruit', 'Picked at the market', 'assets/img/u-fruit-stand.jpg', 'A fruit stand stacked with watermelons, oranges and pineapples'],
    ['Yam', 'Fresh tubers', 'assets/img/yam.jpg', 'Wheelbarrows and stacks of yam tubers at a market'],
    ['In bulk', 'By the sack for businesses', 'assets/img/u-grain-bags-market.jpg', 'A trader beside rows of large sacks of grain'],
];

function seed_gallery(PDO $pdo, int $startPosition = 1): void
{
    $ins = $pdo->prepare('INSERT INTO gallery (title, subtitle, image, alt, position, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (BUILTIN_GALLERY as $i => [$t, $s, $img, $alt]) {
        $ins->execute([$t, $s, $img, $alt, $startPosition + $i, now()]);
    }
}

/** v2: new built-in gallery (bags of rice, garri, oils…). Photos uploaded in the admin are kept, after the new ones. */
function migrate_v2(PDO $pdo): void
{
    $pdo->exec("DELETE FROM gallery WHERE image LIKE 'assets/img/%'");
    $pdo->exec('UPDATE gallery SET position = position + 100');
    seed_gallery($pdo);
}

/** Lazily-opened SQLite connection; creates and seeds the database on first use. */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo !== null) {
        return $pdo;
    }
    $dir = $CONFIG['data_dir'];
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    if (!is_file($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $pdo = new PDO('sqlite:' . $dir . '/market2door.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < SCHEMA_VERSION) {
        $pdo->beginTransaction();
        if ($version < 1) migrate_v1($pdo);
        if ($version < 2) migrate_v2($pdo);
        if ($version < 3) migrate_v2($pdo); // v3: refreshed built-in photos (same steps as v2)
        if ($version < 4) migrate_v2($pdo); // v4: Unsplash photo set for the redesigned page
        $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
        $pdo->commit();
    }
    return $pdo;
}

function migrate_v1(PDO $pdo): void
{
    global $CONFIG;
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY,
            email TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            last_login_at TEXT
        );
        CREATE TABLE IF NOT EXISTS signups (
            id INTEGER PRIMARY KEY,
            created_at TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            phone TEXT NOT NULL,
            city TEXT NOT NULL,
            state TEXT NOT NULL,
            role TEXT NOT NULL,
            vehicle_type TEXT NOT NULL DEFAULT '',
            fleet_size TEXT NOT NULL DEFAULT '',
            business_name TEXT NOT NULL DEFAULT '',
            business_type TEXT NOT NULL DEFAULT '',
            interests TEXT NOT NULL DEFAULT '',
            frequency TEXT NOT NULL DEFAULT '',
            heard TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            source TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'new',
            notified INTEGER NOT NULL DEFAULT 0,
            autoreply_sent INTEGER NOT NULL DEFAULT 0,
            unsubscribed INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS signups_created ON signups (created_at);
        CREATE TABLE IF NOT EXISTS updates (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            image TEXT NOT NULL DEFAULT '',
            is_published INTEGER NOT NULL DEFAULT 0,
            published_at TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS update_sends (
            update_id INTEGER NOT NULL REFERENCES updates (id) ON DELETE CASCADE,
            signup_id INTEGER NOT NULL REFERENCES signups (id) ON DELETE CASCADE,
            sent_at TEXT NOT NULL,
            PRIMARY KEY (update_id, signup_id)
        );
        CREATE TABLE IF NOT EXISTS gallery (
            id INTEGER PRIMARY KEY,
            title TEXT NOT NULL,
            subtitle TEXT NOT NULL DEFAULT '',
            image TEXT NOT NULL,
            alt TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            is_visible INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS hits (
            ip_hash TEXT NOT NULL,
            action TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT '',
            at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS hits_lookup ON hits (action, at);
    SQL);

    $a = $CONFIG['admin'] ?? [];
    if (!empty($a['password_hash']) && !(int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn()) {
        $pdo->prepare('INSERT INTO admins (email, name, password_hash) VALUES (?, ?, ?)')
            ->execute([strtolower($a['email']), $a['name'], $a['password_hash']]);
    }

    // The gallery is seeded by migrate_v2 (BUILTIN_GALLERY).
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Echo the CORS header for our own sites (and localhost while developing). */
function allow_cors(): string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, ALLOWED_ORIGINS, true) || is_local_origin($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    return $origin;
}

function is_local_origin(string $origin): bool
{
    return (bool) preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin);
}

/** Escape for HTML output. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim, strip control characters (incl. CR/LF: blocks header injection), cap length. */
function clean_line(mixed $value, int $max = 200): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    $value = preg_replace('/\p{C}+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Like clean_line but keeps line breaks (for message bodies). */
function clean_text(mixed $value, int $max = 5000): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[^\P{C}\n\t]+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Salted hash of the visitor's IP: lets us rate-limit without storing raw IPs. */
function visitor_hash(): string
{
    global $CONFIG;
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $CONFIG['app_secret']);
}

/** Count this visitor's recent hits for an action. */
function recent_hits(string $action, int $seconds, string $email = ''): array
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(ip_hash = ?), 0) AS by_ip, COALESCE(SUM(email = ? AND email <> \'\'), 0) AS by_email
        FROM hits WHERE action = ? AND at > ?');
    $stmt->execute([visitor_hash(), $email, $action, time() - $seconds]);
    $r = $stmt->fetch();
    return ['ip' => (int) $r['by_ip'], 'email' => (int) $r['by_email']];
}

function record_hit(string $action, string $email = ''): void
{
    db()->prepare('INSERT INTO hits (ip_hash, action, email, at) VALUES (?, ?, ?, ?)')
        ->execute([visitor_hash(), $action, $email, time()]);
    db()->prepare('DELETE FROM hits WHERE at < ?')->execute([time() - 86400]);
}

/** Local numbers (0803…) → international digits (234803…) for WhatsApp links. */
function whatsapp_digits(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($d) === 11 && $d[0] === '0') {
        $d = '234' . substr($d, 1);
    }
    return $d;
}

function format_date(?string $value, string $format = 'j M Y, g:ia'): string
{
    return $value ? date($format, strtotime($value)) : '—';
}

/** "Ikeja, Lagos" (or just the state when no city was given). */
function place(array $signup): string
{
    return implode(', ', array_filter([$signup['city'] ?? '', $signup['state'] ?? ''], fn ($v) => $v !== ''));
}

function role_label(string $role): string
{
    return ROLES[$role] ?? $role;
}

/** Signed token for a sign-up's unsubscribe link (no login needed, can't be guessed). */
function unsubscribe_token(int $signupId): string
{
    global $CONFIG;
    return substr(hash_hmac('sha256', 'unsubscribe:' . $signupId, $CONFIG['app_secret']), 0, 32);
}

function unsubscribe_url(int $signupId): string
{
    global $CONFIG;
    return rtrim($CONFIG['site_url'], '/') . '/unsubscribe.php?id=' . $signupId . '&t=' . unsubscribe_token($signupId);
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?: $name;
}
