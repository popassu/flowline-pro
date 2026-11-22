<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/schema.php';
require __DIR__ . '/../includes/doc-templates.php';
require __DIR__ . '/../includes/doc-render.php';
header('X-Robots-Tag: noindex, nofollow');
require_admin();
define('PP_DOC_BASE', '../');

$pdo = db();
pp_migrate($pdo);
$receipt = null;
if (!empty($_GET['receipt'])) {
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([(int)$_GET['receipt']]);
    $receipt = $st->fetch();
    $doc = $receipt ? doc_load($pdo, (int)$receipt['document_id']) : null;
} else {
    $doc = doc_load($pdo, (int)($_GET['id'] ?? 0));
}
if (!$doc) { http_response_code(404); exit('Document not found.'); }
$title = $receipt ? $receipt['receipt_no'] : $doc['number'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> — Power Plumbing Services Ltd</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:ital,wght@0,400;0,600;0,700;1,600&family=Barlow+Condensed:wght@700;800&display=swap">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/doc.css">
</head>
<body class="doc-page">
<div class="doc-actions">
  <a class="btn ghost" href="documents.php?id=<?= (int)$doc['id'] ?>">Back to editing</a>
  <span class="spacer"></span>
  <button class="btn" type="button" data-print>Print / save as PDF</button>
</div>
<?php
if ($receipt) {
    // paid-to-date as at this receipt
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
<script src="../assets/js/print.js"></script>
</body>
</html>
