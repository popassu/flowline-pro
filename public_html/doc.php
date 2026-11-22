<?php
/* Client view of a quotation, proposal, invoice (?t=token) or receipt (?r=token). */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/schema.php';
require __DIR__ . '/includes/doc-templates.php';
require __DIR__ . '/includes/doc-render.php';
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$notFound = function (): never {
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><title>Not found</title><p style="font-family:sans-serif;padding:2rem">This link is not valid. Please contact Power Plumbing on '
        . e(cfg('phone_display')) . '.</p>');
};
if (!db_ready()) $notFound();
$pdo = db();
pp_migrate($pdo);

$tok = (string)($_GET['t'] ?? $_GET['r'] ?? '');
if (!preg_match('/^[a-f0-9]{48}$/', $tok)) $notFound();

$receipt = null;
if (isset($_GET['r'])) {
    $st = $pdo->prepare('SELECT * FROM payments WHERE token = ?');
    $st->execute([$tok]);
    $receipt = $st->fetch() ?: $notFound();
    $doc = doc_load($pdo, (int)$receipt['document_id']) ?: $notFound();
} else {
    $st = $pdo->prepare('SELECT id FROM documents WHERE token = ?');
    $st->execute([$tok]);
    $id = (int)$st->fetchColumn() ?: $notFound();
    $doc = doc_load($pdo, $id);
}

$canAccept = !$receipt && $doc['type'] !== 'invoice' && in_array($doc['status'], ['draft', 'sent'], true)
    && strtotime($doc['doc_date']) + 86400 * ((int)$doc['valid_days'] + 1) > time();
$msg = '';

if ($canAccept && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mb_substr(trim((string)($_POST['accept_name'] ?? '')), 0, 120);
    if (!csrf_ok()) {
        $msg = 'The page timed out. Please try again.';
    } elseif ($name === '' || empty($_POST['agree'])) {
        $msg = 'Type your name and tick the box to accept.';
    } else {
        $pdo->prepare("UPDATE documents SET status='accepted', accepted_name=?, accepted_at=?, updated_at=? WHERE id=?")
            ->execute([$name, now(), now(), $doc['id']]);
        require __DIR__ . '/includes/mailer.php';
        [, , , $total] = doc_totals($doc['items'], (bool)$doc['vat'], (float)$doc['discount']);
        pp_mail(cfg('notify_email'), "Accepted: {$doc['number']} — " . ($doc['client_company'] ?: $doc['client_name']),
            "{$name} accepted {$doc['number']}" . ($doc['title'] ? " ({$doc['title']})" : '') . " online on " . now() . ".\n\nTotal: KES " . money($total)
            . "\n\nConvert it to an invoice: " . rtrim(cfg('site_url'), '/') . "/admin/documents.php?id={$doc['id']}\n", $doc['client_email'] ?: null);
        redirect('doc.php?t=' . $tok . '&accepted=1');
    }
}
$typeLabel = $receipt ? 'Receipt' : doc_types()[$doc['type']];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($typeLabel . ' ' . ($receipt['receipt_no'] ?? $doc['number'])) ?> — Power Plumbing Services Ltd</title>
<link rel="icon" href="assets/img/logo-mark.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,400;0,600;0,700;1,600&family=Barlow+Condensed:wght@700;800&display=swap">
<link rel="stylesheet" href="assets/css/admin.css">
<link rel="stylesheet" href="assets/css/doc.css">
</head>
<body class="doc-page">
<div class="doc-actions">
  <strong><?= e($typeLabel) ?> from Power Plumbing Services Ltd</strong>
  <span class="spacer"></span>
  <a class="btn ghost" href="https://wa.me/<?= e(cfg('whatsapp')) ?>?text=<?= e(rawurlencode('Hello Power Plumbing, about ' . ($receipt['receipt_no'] ?? $doc['number']) . ': ')) ?>" target="_blank" rel="noopener">Ask on WhatsApp</a>
  <button class="btn" type="button" data-print>Print / save as PDF</button>
</div>

<?php if (isset($_GET['accepted'])): ?>
  <div class="doc-actions"><p class="note ok" style="flex:1">Thank you — your acceptance is recorded. We will contact you to confirm the start date.</p></div>
<?php endif; ?>

<?php if ($canAccept): ?>
  <form method="post" class="doc-actions accept-box no-print">
    <?= csrf_field() ?>
    <div style="flex:1">
      <h2>Accept this <?= e(strtolower($typeLabel)) ?></h2>
      <?php if ($msg): ?><p class="note err"><?= e($msg) ?></p><?php endif; ?>
      <div class="accept-row">
        <label>Your full name<input name="accept_name" required maxlength="120" autocomplete="name"></label>
        <label class="check"><input type="checkbox" name="agree" value="1" required> I accept <?= e($doc['number']) ?> and its terms</label>
        <button class="btn">Accept</button>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php
if ($receipt) {
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE document_id = ? AND (paid_on < ? OR (paid_on = ? AND id <= ?))');
    $st->execute([$doc['id'], $receipt['paid_on'], $receipt['paid_on'], $receipt['id']]);
    render_receipt($receipt, $doc, (float)$st->fetchColumn());
} else {
    $opt = [];
    if ($doc['type'] === 'invoice') {
        $opt['paid'] = doc_paid($pdo, (int)$doc['id']);
        $st = $pdo->prepare('SELECT * FROM payments WHERE document_id = ? ORDER BY paid_on, id');
        $st->execute([$doc['id']]);
        $opt['payments'] = $st->fetchAll();
        if ($doc['source_id'] && ($src = doc_load($pdo, (int)$doc['source_id']))) $opt['source_number'] = $src['number'];
    }
    render_document($doc, $opt);
}
?>
<script src="assets/js/print.js"></script>
</body>
</html>
