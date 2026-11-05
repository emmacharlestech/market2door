<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/signup_filters.php';

require_admin();

[, $sqlWhere, $args] = signup_filters($_GET);
$stmt = db()->prepare('SELECT * FROM signups' . $sqlWhere . ' ORDER BY id');
$stmt->execute($args);

/** Stops spreadsheet apps treating a cell as a formula. */
$cell = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="market2door-waitlist-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // lets Excel read the file as UTF-8
fputcsv($out, ['Joined', 'Name', 'Email', 'Phone', 'City or area', 'State', 'Joined as', 'Vehicle', 'Fleet size', 'Business name',
               'Business type', 'Interested in', 'Shops for food', 'Heard via', 'Message', 'Status', 'Welcome email sent', 'Unsubscribed']);
while ($r = $stmt->fetch()) {
    fputcsv($out, array_map($cell, [$r['created_at'], $r['name'], $r['email'], $r['phone'], $r['city'], $r['state'], role_label($r['role']),
        $r['vehicle_type'], $r['fleet_size'], $r['business_name'], $r['business_type'], $r['interests'], $r['frequency'],
        $r['heard'], $r['message'], SIGNUP_STATUSES[$r['status']] ?? $r['status'], $r['autoreply_sent'] ? 'Yes' : 'No', $r['unsubscribed'] ? 'Yes' : 'No']));
}
fclose($out);
