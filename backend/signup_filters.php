<?php
/**
 * Waitlist search and filters, shared by Admin → Waitlist and the CSV export
 * so the download always matches what's on screen.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * @return array{0: array{status: string, role: string, state: string, q: string}, 1: string, 2: array}
 *         [the cleaned filters, the SQL WHERE clause (or ''), its arguments]
 */
function signup_filters(array $in): array
{
    $f = [
        'status' => isset(SIGNUP_STATUSES[$in['status'] ?? '']) ? (string) $in['status'] : '',
        'role'   => isset(ROLES[$in['role'] ?? '']) ? (string) $in['role'] : '',
        'state'  => in_array($in['state'] ?? '', STATES, true) ? (string) $in['state'] : '',
        'q'      => clean_line($in['q'] ?? '', 100),
    ];
    $where = [];
    $args  = [];
    if ($f['status'] !== '') { $where[] = 'status = ?'; $args[] = $f['status']; }
    if ($f['role'] !== '')   { $where[] = 'role = ?';   $args[] = $f['role']; }
    if ($f['state'] !== '')  { $where[] = 'state = ?';  $args[] = $f['state']; }
    if ($f['q'] !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR city LIKE ? OR business_name LIKE ?)';
        array_push($args, ...array_fill(0, 5, '%' . $f['q'] . '%'));
    }
    return [$f, $where ? ' WHERE ' . implode(' AND ', $where) : '', $args];
}
