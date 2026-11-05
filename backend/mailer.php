<?php
/**
 * Outgoing email: the support-inbox notification, the branded auto-reply and update emails.
 */
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

const MAIL_FROM      = 'support@market2door.ng';
const MAIL_FROM_NAME = 'Market2Door';

function mime_header(string $text): string
{
    return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

/** Send a plain-text email, with an optional HTML version. Returns mail()'s result. */
function send_mail(string $to, string $subject, string $text, ?string $html = null, string $replyTo = '', string $fromName = MAIL_FROM_NAME, array $extraHeaders = []): bool
{
    $headers = [
        'From: ' . mime_header($fromName) . ' <' . MAIL_FROM . '>',
        'MIME-Version: 1.0',
        'X-Mailer: Market2Door',
        ...$extraHeaders,
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    if ($html === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($text));
    } else {
        $b = 'm2d_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$b--\r\n";
    }
    $ok = @mail($to, mime_header($subject), $body, implode("\r\n", $headers), '-f' . MAIL_FROM);
    if (!$ok) {
        error_log("Market2Door mail: mail() failed sending '$subject' to $to");
    }
    return $ok;
}

/** Fill {first_name} / {name} in an auto-reply template. */
function fill_template(string $template, array $signup): string
{
    return strtr($template, ['{first_name}' => first_name($signup['name']), '{name}' => $signup['name']]);
}

/** Plain text → simple HTML: paragraphs, line breaks and clickable links. */
function text_to_html(string $text): string
{
    $html = '';
    foreach (preg_split("/\n{2,}/", trim($text)) as $para) {
        $p = nl2br(e($para), false);
        $p = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#1B4D30;font-weight:700">$1</a>', $p);
        $html .= '<p style="margin:0 0 16px">' . $p . '</p>';
    }
    return $html;
}

/** Branded HTML wrapper used by the auto-reply and update emails. */
function branded_html(string $title, string $bodyHtml, string $footerHtml = ''): string
{
    global $CONFIG;
    $site = rtrim($CONFIG['site_url'] ?? 'https://market2door.ng', '/');
    $footerHtml = $footerHtml ?: 'You\'re receiving this because you joined the Market2Door waitlist at market2door.ng. To be removed, reply to this email or write to <a href="mailto:' . MAIL_FROM . '" style="color:#5B6A60">' . MAIL_FROM . '</a>.';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . e($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F6F1E6">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F1E6"><tr><td align="center" style="padding:28px 14px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden">'
        . '<tr><td style="background:#123821;padding:26px 32px">'
        . '<img src="' . e($site) . '/assets/brand/market2door-logo-reversed.png" alt="Market2Door: From the Market to Your Door." width="200" style="display:block;width:200px;height:auto;border:0">'
        . '</td></tr>'
        . '<tr><td style="padding:32px;font:16px/1.6 Arial,Helvetica,sans-serif;color:#14221A">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:0 32px 32px"><a href="' . e($site) . '" style="display:inline-block;padding:14px 26px;border-radius:999px;background:#E8B04B;color:#123821;font:700 15px Arial,Helvetica,sans-serif;text-decoration:none">Visit market2door.ng</a></td></tr>'
        . '<tr><td style="padding:20px 32px;background:#F6F1E6;font:12px/1.6 Arial,Helvetica,sans-serif;color:#5B6A60">'
        . $footerHtml
        . '</td></tr></table></td></tr></table></body></html>';
}

/**
 * Email one update to one person on the waitlist, personally addressed,
 * with an unsubscribe link (and List-Unsubscribe header for one-click in Gmail etc.).
 */
function send_update_email(array $update, array $signup): bool
{
    global $CONFIG;
    $site = rtrim($CONFIG['site_url'], '/');
    $unsub = unsubscribe_url((int) $signup['id']);
    $hi = 'Hi ' . first_name($signup['name']) . ',';

    // Drafts can be emailed too (email-only news), so only link to the website when it's published there.
    $live = !empty($update['is_published']);
    $text = $hi . "\n\n" . trim($update['body']) . "\n\n" . ($live ? "Read it on the website: $site/#news\n\n" : '')
        . "The Market2Door team\n\n—\nYou're receiving this because you joined the Market2Door waitlist. Unsubscribe from updates: $unsub";

    $img = $update['image'] !== ''
        ? '<img src="' . e($site . '/' . $update['image']) . '" alt="" width="496" style="display:block;width:100%;height:auto;border:0;border-radius:14px;margin:0 0 22px">'
        : '';
    $body = $img
        . '<h1 style="margin:0 0 18px;font:700 24px/1.25 Georgia,\'Times New Roman\',serif;color:#123821">' . e($update['title']) . '</h1>'
        . '<p style="margin:0 0 16px">' . e($hi) . '</p>'
        . text_to_html($update['body'])
        . '<p style="margin:0">The Market2Door team</p>';
    $footer = 'You\'re receiving this because you joined the Market2Door waitlist at market2door.ng. '
        . '<a href="' . e($unsub) . '" style="color:#5B6A60">Unsubscribe from updates</a>.';

    return send_mail($signup['email'], $update['title'], $text, branded_html($update['title'], $body, $footer), MAIL_FROM, MAIL_FROM_NAME, [
        'List-Unsubscribe: <' . $unsub . '>, <mailto:' . MAIL_FROM . '?subject=Unsubscribe>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    ]);
}

/**
 * The people an update email would go to: one role (or everyone), or a single email address.
 * Unsubscribed people and anyone who already received this update are left out.
 */
function update_recipients(int $updateId, string $audience, string $oneEmail = '', ?int $limit = null): array
{
    $sql = 'SELECT s.id, s.name, s.email FROM signups s
        WHERE s.unsubscribed = 0
          AND NOT EXISTS (SELECT 1 FROM update_sends x WHERE x.update_id = ? AND x.signup_id = s.id)';
    $args = [$updateId];
    if ($audience === 'one') {
        $sql .= ' AND s.email = ?';
        $args[] = strtolower($oneEmail);
    } elseif ($audience !== 'all') {
        $sql .= ' AND s.role = ?';
        $args[] = $audience;
    }
    $sql .= ' ORDER BY s.id' . ($limit ? ' LIMIT ' . (int) $limit : '');
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

/** Send the "you're on the list" email to someone who just signed up. */
function send_autoreply(array $signup, ?string $to = null): bool
{
    global $CONFIG;
    $s = get_settings();
    $subject = fill_template($s['autoreply_subject'], $signup);
    $text = fill_template($s['autoreply_body'], $signup);
    $html = branded_html($subject, text_to_html($text));
    return send_mail($to ?? $signup['email'], $subject, $text . "\n\n—\n" . rtrim($CONFIG['site_url'], '/'), $html, MAIL_FROM);
}

/** The role-specific lines of a sign-up, as [label, value] pairs (used in the alert email and the admin). */
function signup_details(array $r): array
{
    return match ($r['role']) {
        'Rider'         => [['Rides a', $r['vehicle_type']]],
        'Vehicle owner' => [['Vehicles', $r['vehicle_type']], ['Fleet size', $r['fleet_size']]],
        'Business'      => [['Business', $r['business_name']], ['Business type', $r['business_type']]],
        default         => [],
    };
}

/** Tell the support inbox about a new sign-up. Reply-To is the person, so replying reaches them. */
function send_signup_notification(array $r): bool
{
    global $CONFIG;
    $pad = fn (string $label) => str_pad($label . ':', 18);
    $lines = [
        'New Market2Door waitlist sign-up',
        '',
        $pad('Name') . $r['name'],
        $pad('Email') . $r['email'],
        $pad('Phone') . $r['phone'],
        $pad('Based in') . place($r),
        $pad('Joining as') . role_label($r['role']),
    ];
    foreach (signup_details($r) as [$label, $value]) {
        $lines[] = $pad($label) . ($value ?: '—');
    }
    array_push($lines,
        $pad('Interested in') . ($r['interests'] ?: '—'),
        $pad('Shops for food') . ($r['frequency'] ?: '—'),
        $pad('Heard via') . ($r['heard'] ?: '—'),
        '',
        'Message:',
        $r['message'] !== '' ? $r['message'] : '—',
        '',
        '---',
        'Submitted: ' . format_date($r['created_at']),
        'From page: ' . ($r['source'] ?: 'unknown'),
        'See everyone on the waitlist: ' . rtrim($CONFIG['site_url'], '/') . '/admin/signups',
        'Reply to this email to answer ' . first_name($r['name']) . ' directly.'
    );
    $ok = false;
    foreach (notify_addresses(get_settings()) as $to) {
        $ok = send_mail($to, "New waitlist sign-up: {$r['name']} (" . role_label($r['role']) . ')', implode("\n", $lines), null, $r['email'], 'Market2Door Waitlist') || $ok;
    }
    return $ok;
}
