<?php
/* Receives the quote form, stores the lead and emails the office. */
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('./#quote');

function back(string $status, array $old = []): never
{
    $_SESSION['quote_status'] = $status;
    $_SESSION['quote_old'] = $old;
    redirect('./#quote');
}

$f = fn(string $k, int $max) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
$old = [
    'name'     => $f('name', 120),
    'phone'    => $f('phone', 40),
    'email'    => $f('email', 160),
    'location' => $f('location', 160),
    'service'  => $f('service', 80),
    'message'  => $f('message', 3000),
];

// Bots: hidden field filled, or form sent faster than a person could type
$started = (int)($_SESSION['quote_started'] ?? 0);
if (($_POST['website'] ?? '') !== '' || ($started && time() - $started < 3)) {
    back('sent'); // pretend success, store nothing
}
if (!csrf_ok()) back('expired', $old);

if ($old['name'] === '' || !preg_match('/^[+0-9 ()-]{9,20}$/', $old['phone'])) back('invalid', $old);
if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) back('invalid', $old);
if (!db_ready()) back('error', $old);

try {
    $pdo = db();
    // Max 5 requests per hour from one address
    $st = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > ?');
    $st->execute([client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$st->fetchColumn() >= 5) back('limit', $old);

    $pdo->prepare('INSERT INTO leads (name, phone, email, location, service, message, ip, created_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$old['name'], $old['phone'], $old['email'] ?: null, $old['location'] ?: null,
                   $old['service'] ?: null, $old['message'] ?: null, client_ip(), now()]);
} catch (Throwable $ex) {
    error_log('quote insert: ' . $ex->getMessage());
    back('error', $old);
}

// Email the office (the lead is already saved even if mail fails)
require __DIR__ . '/includes/mailer.php';
$body = "A new quote request came in from the website.\n\n"
      . "Name:     {$old['name']}\nPhone:    {$old['phone']}\nEmail:    {$old['email']}\n"
      . "Location: {$old['location']}\nService:  {$old['service']}\n\nMessage:\n{$old['message']}\n\n"
      . 'Received ' . now() . " (Nairobi time)\nReply from the admin portal: " . rtrim(cfg('site_url'), '/') . "/admin/\n";
if (!pp_mail(cfg('notify_email'), 'New quote request: ' . ($old['service'] ?: 'General') . ' — ' . $old['name'], $body, $old['email'] ?: null, $err)) {
    error_log('quote notify mail: ' . $err);
}

// Acknowledge the customer
if ($old['email'] !== '' && cfg('auto_reply', true)) {
    $first = strtok($old['name'], ' ');
    $ack = "Hello {$first},\n\nThank you for contacting Power Plumbing Services Ltd. We have received your request"
         . ($old['service'] ? " for {$old['service']}" : '') . " and will call you on {$old['phone']} shortly.\n\n"
         . 'If it is urgent, call or WhatsApp us on ' . cfg('phone_display') . ".\n\nPower Plumbing Services Ltd\n"
         . cfg('phone_display') . ' | ' . cfg('email') . "\n" . cfg('site_url') . "\n";
    if (!pp_mail($old['email'], 'We received your request — Power Plumbing', $ack, cfg('email'), $err)) {
        error_log('quote ack mail: ' . $err);
    }
}

unset($_SESSION['quote_started']);
back('sent');
