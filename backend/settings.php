<?php
/**
 * Site settings (key/value) editable from Admin → Settings.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const DEFAULT_SETTINGS = [
    'notify_email'      => 'support@market2door.ng',
    'autoreply_enabled' => 'on',
    'autoreply_subject' => "You're on the Market2Door waitlist, {first_name}!",
    'autoreply_body'    => "Hi {first_name},\n\n"
        . "Thank you for joining the Market2Door waitlist. You're in line for early access.\n\n"
        . "Market2Door brings fresh foodstuff from the market, parcel delivery and van and truck hire together in one app. Our team does the market run for you, and you approve the final price before your order goes ahead.\n\n"
        . "We'll be in touch before we open in your area.\n\n"
        . "Got a question or an idea? Just reply to this email. We read every message.\n\n"
        . "From the market to your door,\n"
        . "The Market2Door team",
];

function get_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $rows = db()->query('SELECT key, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $cache = array_merge(DEFAULT_SETTINGS, array_intersect_key($rows, DEFAULT_SETTINGS));
    }
    return $cache;
}

function save_settings(array $values): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    foreach (array_intersect_key($values, DEFAULT_SETTINGS) as $k => $v) {
        $stmt->execute([$k, (string) $v]);
    }
}

/** The notification addresses, validated (comma or space separated in Settings). */
function notify_addresses(array $s): array
{
    $list = preg_split('/[\s,;]+/', $s['notify_email'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_filter($list, fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
}
