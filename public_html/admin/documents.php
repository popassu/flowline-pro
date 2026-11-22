<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/schema.php';
require __DIR__ . '/../includes/mailer.php';
require __DIR__ . '/../includes/admin-ui.php';
require __DIR__ . '/../includes/doc-templates.php';
header('X-Robots-Tag: noindex, nofollow');
require_admin();

$pdo = db();
pp_migrate($pdo);
$types = doc_types();

/* ---------- create a new draft ---------- */
if (isset($_GET['new'], $types[$_GET['new']])) {
    $type = $_GET['new'];
    $lead = null;
    if (!empty($_GET['lead'])) {
        $st = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
        $st->execute([(int)$_GET['lead']]);
        $lead = $st->fetch() ?: null;
    }
    $title = $lead && $lead['service'] ? $lead['service'] : '';
    $sections = $type === 'proposal'
        ? array_map(fn($s) => ['heading' => $s[0], 'body' => $s[1]], doc_proposal_sections())
        : [];
    $items = [['desc' => $title ? 'Supply and install — ' . $title : '', 'qty' => 1, 'unit' => 'lot', 'price' => 0]];
    $pdo->prepare('INSERT INTO documents (type, number, lead_id, title, client_name, client_phone, client_email, site, items, sections, vat, discount, notes, terms, valid_days, due_days, status, token, doc_date, created_at, updated_at)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$type, doc_next_number($pdo, $type), $lead['id'] ?? null, $title ?: null, $lead['name'] ?? null, $lead['phone'] ?? null,
                   $lead['email'] ?? null, $lead['location'] ?? null, json_encode($items), json_encode($sections), 1, 0,
                   $lead && $lead['message'] ? "Client's request: " . $lead['message'] : null, doc_default_terms($type), 30, 14, 'draft',
                   bin2hex(random_bytes(24)), date('Y-m-d'), now(), now()]);
    redirect('documents.php?id=' . $pdo->lastInsertId());
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $back = 'documents.php?id=' . $id;
    if (!csrf_ok()) { flash('The form expired. Try again.', 'err'); redirect($back); }
    $d = $id ? doc_load($pdo, $id) : null;
    if (!$d) { flash('That document no longer exists.', 'err'); redirect('documents.php'); }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $f = fn(string $k, int $max = 255) => ($v = mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max)) === '' ? null : $v;
        $items = [];
        $in = $_POST['items'] ?? [];
        foreach (($in['desc'] ?? []) as $i => $desc) {
            $desc = mb_substr(trim((string)$desc), 0, 500);
            if ($desc === '') continue;
            $items[] = [
                'desc'  => $desc,
                'qty'   => round((float)str_replace(',', '', (string)($in['qty'][$i] ?? 1)), 2),
                'unit'  => mb_substr(trim((string)($in['unit'][$i] ?? '')), 0, 20),
                'price' => round((float)str_replace(',', '', (string)($in['price'][$i] ?? 0)), 2),
            ];
        }
        $sections = [];
        foreach (($_POST['sec']['heading'] ?? []) as $i => $h) {
            $h = mb_substr(trim((string)$h), 0, 160);
            $b = trim((string)($_POST['sec']['body'][$i] ?? ''));
            if ($h === '' && $b === '') continue;
            $sections[] = ['heading' => $h, 'body' => $b];
        }
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['doc_date'] ?? '')) ? $_POST['doc_date'] : date('Y-m-d');
        $pdo->prepare('UPDATE documents SET title=?, client_name=?, client_company=?, client_phone=?, client_email=?, client_address=?, site=?, items=?, sections=?,
                       vat=?, discount=?, notes=?, terms=?, valid_days=?, due_days=?, doc_date=?, updated_at=? WHERE id=?')
            ->execute([$f('title', 200), $f('client_name', 120), $f('client_company', 160), $f('client_phone', 40), $f('client_email', 160),
                       $f('client_address'), $f('site', 160), json_encode($items), json_encode($sections), !empty($_POST['vat']) ? 1 : 0,
                       max(0, round((float)str_replace(',', '', (string)($_POST['discount'] ?? 0)), 2)), $f('notes', 5000), $f('terms', 5000),
                       max(1, (int)($_POST['valid_days'] ?? 30)), max(0, (int)($_POST['due_days'] ?? 14)), $date, now(), $id]);
        flash('Saved.');
        redirect(($_POST['then'] ?? '') === 'preview' ? 'print.php?id=' . $id : $back);
    }

    if ($action === 'status') {
        $s = $_POST['status'] ?? '';
        if (isset(doc_statuses($d['type'])[$s])) {
            $pdo->prepare('UPDATE documents SET status=?, updated_at=? WHERE id=?')->execute([$s, now(), $id]);
            flash('Status set to ' . doc_statuses($d['type'])[$s] . '.');
        }
        redirect($back);
    }

    if ($action === 'convert' && $d['type'] !== 'invoice') {
        $pdo->prepare('INSERT INTO documents (type, number, lead_id, source_id, title, client_name, client_company, client_phone, client_email, client_address, site, items, sections, vat, discount, notes, terms, valid_days, due_days, status, token, doc_date, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute(['invoice', doc_next_number($pdo, 'invoice'), $d['lead_id'], $d['id'], $d['title'], $d['client_name'], $d['client_company'],
                       $d['client_phone'], $d['client_email'], $d['client_address'], $d['site'], json_encode($d['items']), '[]', $d['vat'], $d['discount'],
                       null, doc_default_terms('invoice'), 30, 14, 'draft', bin2hex(random_bytes(24)), date('Y-m-d'), now(), now()]);
        $newId = $pdo->lastInsertId();
        $pdo->prepare("UPDATE documents SET status='invoiced', updated_at=? WHERE id=?")->execute([now(), $id]);
        flash('Invoice created from ' . $d['number'] . '. Check the details, then email it to the client.');
        redirect('documents.php?id=' . $newId);
    }

    if ($action === 'duplicate') {
        $pdo->prepare('INSERT INTO documents (type, number, lead_id, title, client_name, client_company, client_phone, client_email, client_address, site, items, sections, vat, discount, notes, terms, valid_days, due_days, status, token, doc_date, created_at, updated_at)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$d['type'], doc_next_number($pdo, $d['type']), $d['lead_id'], $d['title'], $d['client_name'], $d['client_company'], $d['client_phone'],
                       $d['client_email'], $d['client_address'], $d['site'], json_encode($d['items']), json_encode($d['sections']), $d['vat'], $d['discount'],
                       $d['notes'], $d['terms'], $d['valid_days'], $d['due_days'], 'draft', bin2hex(random_bytes(24)), date('Y-m-d'), now(), now()]);
        flash('Copy created.');
        redirect('documents.php?id=' . $pdo->lastInsertId());
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM payments WHERE document_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
        flash($d['number'] . ' deleted.');
        redirect('documents.php');
    }

    if ($action === 'email') {
        if (!$d['client_email']) { flash('Add the client email address first, then save.', 'err'); redirect($back); }
        [, , , $total] = doc_totals($d['items'], (bool)$d['vat'], (float)$d['discount']);
        $label = $types[$d['type']];
        $first = strtok((string)$d['client_name'], ' ') ?: 'Sir/Madam';
        $body = "Dear {$first},\n\n";
        if ($d['type'] === 'invoice') {
            $bal = max($total - doc_paid($pdo, $id), 0);
            $body .= "Please find our invoice {$d['number']}" . ($d['title'] ? " for {$d['title']}" : '') . ".\n\n"
                   . 'Amount due: KES ' . money($bal) . "\nDue date: " . date('j M Y', strtotime($d['doc_date']) + 86400 * $d['due_days']) . "\n\n"
                   . "How to pay:\n" . cfg('payment_details') . "\nReference: {$d['number']}\n\n";
        } else {
            $body .= "Thank you for considering Power Plumbing Services Ltd. Please find our " . strtolower($label) . " {$d['number']}"
                   . ($d['title'] ? " for {$d['title']}" : '') . ".\n\nTotal: KES " . money($total) . "\nValid until: "
                   . date('j M Y', strtotime($d['doc_date']) + 86400 * $d['valid_days']) . "\n\n";
        }
        $body .= "View, print or download it here" . ($d['type'] !== 'invoice' ? ', and accept it online' : '') . ":\n" . doc_public_url($d) . "\n\n"
               . trim((string)($_POST['message'] ?? '')) . (trim((string)($_POST['message'] ?? '')) ? "\n\n" : '')
               . "Regards,\nPower Plumbing Services Ltd\n" . cfg('phone_display') . ' | ' . cfg('email') . "\n";
        if (pp_mail($d['client_email'], "$label {$d['number']} — Power Plumbing", $body, cfg('email'), $err)) {
            if ($d['status'] === 'draft') $pdo->prepare("UPDATE documents SET status='sent', updated_at=? WHERE id=?")->execute([now(), $id]);
            flash("$label emailed to {$d['client_email']}.");
        } else {
            flash('Email not sent: ' . e((string)$err), 'err');
        }
        redirect($back);
    }

    if ($action === 'payment' && $d['type'] === 'invoice') {
        $amount = round((float)str_replace(',', '', (string)($_POST['amount'] ?? 0)), 2);
        $method = isset(pay_methods()[$_POST['method'] ?? '']) ? $_POST['method'] : 'mpesa';
        $paidOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['paid_on'] ?? '')) ? $_POST['paid_on'] : date('Y-m-d');
        if ($amount <= 0) { flash('Enter the amount received.', 'err'); redirect($back); }
        $rno = receipt_next_number($pdo);
        $tok = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO payments (document_id, receipt_no, amount, method, reference, paid_on, note, token, created_at) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $rno, $amount, $method, mb_substr(trim((string)($_POST['reference'] ?? '')), 0, 80) ?: null, $paidOn,
                       mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255) ?: null, $tok, now()]);
        $pid = $pdo->lastInsertId();
        [, , , $total] = doc_totals($d['items'], (bool)$d['vat'], (float)$d['discount']);
        $paid = doc_paid($pdo, $id);
        $pdo->prepare('UPDATE documents SET status=?, updated_at=? WHERE id=?')->execute([$paid + 0.005 >= $total ? 'paid' : 'partly', now(), $id]);
        $msg = "Payment of KES " . money($amount) . " recorded. Receipt $rno issued.";
        if (!empty($_POST['email_receipt']) && $d['client_email']) {
            $url = rtrim(cfg('site_url'), '/') . '/doc.php?r=' . $tok;
            $body = 'Dear ' . (strtok((string)$d['client_name'], ' ') ?: 'Sir/Madam') . ",\n\nThank you. We have received KES " . money($amount)
                  . " for invoice {$d['number']}.\n\nYour receipt $rno: $url\n\nBalance remaining: KES " . money(max($total - $paid, 0))
                  . "\n\nRegards,\nPower Plumbing Services Ltd\n" . cfg('phone_display') . ' | ' . cfg('email') . "\n";
            $msg .= pp_mail($d['client_email'], "Receipt $rno — Power Plumbing", $body, cfg('email'), $err)
                ? " Receipt emailed to {$d['client_email']}." : ' Receipt email failed: ' . e((string)$err);
        }
        flash($msg);
        redirect($back . '#payments');
    }

    if ($action === 'payment_delete') {
        $pdo->prepare('DELETE FROM payments WHERE id = ? AND document_id = ?')->execute([(int)$_POST['pid'], $id]);
        [, , , $total] = doc_totals($d['items'], (bool)$d['vat'], (float)$d['discount']);
        $paid = doc_paid($pdo, $id);
        $pdo->prepare('UPDATE documents SET status=?, updated_at=? WHERE id=?')
            ->execute([$paid <= 0 ? 'sent' : ($paid + 0.005 >= $total ? 'paid' : 'partly'), now(), $id]);
        flash('Payment removed and its receipt cancelled.');
        redirect($back . '#payments');
    }
    redirect($back);
}

$f = flash();
$doc = isset($_GET['id']) ? doc_load($pdo, (int)$_GET['id']) : null;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $doc ? e($doc['number']) . ' — ' : '' ?>Quotes &amp; proposals — Power Plumbing</title>
<link rel="icon" href="../assets/img/logo-mark.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php admin_bar('docs', admin_new_leads($pdo)); ?>
<main class="wrap">
<?php if ($f): ?><p class="note <?= e($f['type']) ?>"><?= $f['type'] === 'err' ? $f['msg'] : e($f['msg']) ?></p><?php endif; ?>

<?php if (!$doc): /* ================= LIST ================= */
    $ft = isset($types[$_GET['type'] ?? '']) ? $_GET['type'] : '';
    $q = trim((string)($_GET['q'] ?? ''));
    $sql = 'SELECT * FROM documents WHERE 1=1';
    $args = [];
    if ($ft) { $sql .= ' AND type = ?'; $args[] = $ft; }
    if ($q !== '') { $sql .= ' AND (number LIKE ? OR client_name LIKE ? OR client_company LIKE ? OR title LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
    $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT 300');
    $st->execute($args);
    $docs = $st->fetchAll();
    $counts = $pdo->query('SELECT type, COUNT(*) FROM documents GROUP BY type')->fetchAll(PDO::FETCH_KEY_PAIR);
    $outstanding = 0.0;
    foreach ($pdo->query("SELECT * FROM documents WHERE type='invoice' AND status IN ('sent','partly')") as $inv) {
        [, , , $t] = doc_totals(json_decode((string)$inv['items'], true) ?: [], (bool)$inv['vat'], (float)$inv['discount']);
        $outstanding += max($t - doc_paid($pdo, (int)$inv['id']), 0);
    }
?>
  <div class="head">
    <h1>Quotes &amp; proposals</h1>
    <div class="actions-row">
      <a class="btn" href="?new=quote">New quotation</a>
      <a class="btn ghost" href="?new=proposal">New proposal</a>
      <a class="btn ghost" href="?new=invoice">New invoice</a>
    </div>
  </div>
  <?php if ($outstanding > 0): ?><p class="note warn">Outstanding on unpaid invoices: <strong>KES <?= money($outstanding) ?></strong></p><?php endif; ?>
  <div class="list-tools">
    <nav class="filters" aria-label="Filter by type">
      <a href="documents.php" <?= !$ft ? 'aria-current="true"' : '' ?>>All (<?= array_sum($counts) ?>)</a>
      <?php foreach ($types as $k => $label): ?><a href="?type=<?= $k ?>" <?= $ft === $k ? 'aria-current="true"' : '' ?>><?= $label ?>s (<?= (int)($counts[$k] ?? 0) ?>)</a><?php endforeach; ?>
    </nav>
    <form class="search" method="get"><?php if ($ft): ?><input type="hidden" name="type" value="<?= e($ft) ?>"><?php endif; ?>
      <label class="sr" for="q">Search</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Search number, client or title"></form>
  </div>
  <?php if (!$docs): ?>
    <p class="empty">No documents<?= $q || $ft ? ' match this filter' : ' yet' ?>. Start a quotation above, or open a quote request and choose <strong>Create quote</strong> to fill in the client's details automatically.</p>
  <?php else: ?>
  <div class="table-wrap"><table class="doc-list">
    <thead><tr><th>Number</th><th>Client</th><th>Title</th><th class="r">Total (KES)</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
    <?php foreach ($docs as $row): [, , , $t] = doc_totals(json_decode((string)$row['items'], true) ?: [], (bool)$row['vat'], (float)$row['discount']); ?>
      <tr>
        <td><a href="?id=<?= (int)$row['id'] ?>"><strong><?= e($row['number']) ?></strong></a><br><small class="muted"><?= e($types[$row['type']]) ?></small></td>
        <td><?= e($row['client_company'] ?: $row['client_name'] ?: '—') ?></td>
        <td><?= e($row['title'] ?: '—') ?></td>
        <td class="r"><?= money($t) ?><?php if ($row['type'] === 'invoice' && in_array($row['status'], ['partly', 'sent'], true)): ?><br><small class="due-txt">Due <?= money(max($t - doc_paid($pdo, (int)$row['id']), 0)) ?></small><?php endif; ?></td>
        <td><span class="st st-<?= e($row['status']) ?>"><?= e(doc_statuses($row['type'])[$row['status']] ?? $row['status']) ?></span></td>
        <td><?= date('j M Y', strtotime($row['doc_date'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>

<?php else: /* ================= EDITOR ================= */
    $isInv = $doc['type'] === 'invoice';
    $payments = [];
    if ($isInv) {
        $st = $pdo->prepare('SELECT * FROM payments WHERE document_id = ? ORDER BY paid_on, id');
        $st->execute([$doc['id']]);
        $payments = $st->fetchAll();
    }
    [$sub, $disc, $tax, $total] = doc_totals($doc['items'], (bool)$doc['vat'], (float)$doc['discount']);
    $paid = $isInv ? doc_paid($pdo, (int)$doc['id']) : 0;
    $source = $doc['source_id'] ? doc_load($pdo, (int)$doc['source_id']) : null;
    $children = $pdo->prepare("SELECT id, number FROM documents WHERE source_id = ?");
    $children->execute([$doc['id']]);
    $children = $children->fetchAll();
?>
  <p class="crumb"><a href="documents.php">← All documents</a></p>
  <div class="head">
    <h1><?= e($types[$doc['type']]) ?> <?= e($doc['number']) ?></h1>
    <span class="st st-<?= e($doc['status']) ?>"><?= e(doc_statuses($doc['type'])[$doc['status']] ?? $doc['status']) ?></span>
  </div>
  <?php if ($source): ?><p class="muted">Created from <a href="?id=<?= (int)$source['id'] ?>"><?= e($source['number']) ?></a>.</p><?php endif; ?>
  <?php foreach ($children as $c): ?><p class="muted">Invoiced as <a href="?id=<?= (int)$c['id'] ?>"><?= e($c['number']) ?></a>.</p><?php endforeach; ?>
  <?php if ($doc['accepted_at']): ?><p class="note ok">Accepted online by <?= e($doc['accepted_name']) ?> on <?= date('j M Y, H:i', strtotime($doc['accepted_at'])) ?>.</p><?php endif; ?>

  <div class="doc-grid">
  <form method="post" class="doc-form" id="docForm">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">

    <section class="panel">
      <h2>Client</h2>
      <div class="grid2">
        <label>Contact name<input name="client_name" maxlength="120" value="<?= e($doc['client_name']) ?>"></label>
        <label>Company (optional)<input name="client_company" maxlength="160" value="<?= e($doc['client_company']) ?>"></label>
        <label>Phone<input name="client_phone" maxlength="40" value="<?= e($doc['client_phone']) ?>"></label>
        <label>Email<input type="email" name="client_email" maxlength="160" value="<?= e($doc['client_email']) ?>"></label>
        <label>Postal / physical address<input name="client_address" maxlength="255" value="<?= e($doc['client_address']) ?>"></label>
        <label>Site / project location<input name="site" maxlength="160" value="<?= e($doc['site']) ?>"></label>
      </div>
    </section>

    <section class="panel">
      <h2>Details</h2>
      <div class="grid3">
        <label class="span2">Title<input name="title" maxlength="200" value="<?= e($doc['title']) ?>" placeholder="e.g. Booster pump and tank installation"></label>
        <label>Date<input type="date" name="doc_date" value="<?= e($doc['doc_date']) ?>"></label>
        <?php if ($isInv): ?>
          <label>Payment due in (days)<input type="number" name="due_days" min="0" value="<?= (int)$doc['due_days'] ?>"></label>
          <input type="hidden" name="valid_days" value="<?= (int)$doc['valid_days'] ?>">
        <?php else: ?>
          <label>Valid for (days)<input type="number" name="valid_days" min="1" value="<?= (int)$doc['valid_days'] ?>"></label>
          <input type="hidden" name="due_days" value="<?= (int)$doc['due_days'] ?>">
        <?php endif; ?>
      </div>
    </section>

    <?php if ($doc['type'] === 'proposal'): ?>
    <section class="panel">
      <h2>Proposal sections</h2>
      <p class="muted">Edit the ready-written text. <code>{client}</code>, <code>{company}</code>, <code>{site}</code> and <code>{title}</code> fill in automatically. A section containing only <code>{pricing}</code> shows the priced items below. Lines starting with "-" become bullet points.</p>
      <div id="sections">
        <?php foreach ($doc['sections'] as $sec): ?>
        <div class="sec">
          <div class="sec-head">
            <input name="sec[heading][]" value="<?= e($sec['heading']) ?>" aria-label="Section heading">
            <button type="button" class="icon" data-move="-1" aria-label="Move up">↑</button>
            <button type="button" class="icon" data-move="1" aria-label="Move down">↓</button>
            <button type="button" class="icon danger" data-remove aria-label="Remove section">×</button>
          </div>
          <textarea name="sec[body][]" rows="5" aria-label="Section text"><?= e($sec['body']) ?></textarea>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn small ghost" id="addSection">Add section</button>
    </section>
    <?php endif; ?>

    <section class="panel">
      <h2><?= $doc['type'] === 'proposal' ? 'Pricing schedule' : 'Items' ?></h2>
      <div class="table-wrap">
      <table class="item-edit">
        <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Unit price</th><th class="r">Amount</th><th></th></tr></thead>
        <tbody id="items">
          <?php foreach ($doc['items'] ?: [['desc' => '', 'qty' => 1, 'unit' => '', 'price' => 0]] as $it): ?>
          <tr>
            <td><input name="items[desc][]" list="catalogue" value="<?= e($it['desc']) ?>" aria-label="Description"></td>
            <td><input name="items[qty][]" inputmode="decimal" value="<?= e((string)(float)$it['qty']) ?>" class="num" aria-label="Quantity"></td>
            <td><input name="items[unit][]" value="<?= e($it['unit']) ?>" class="unit" aria-label="Unit"></td>
            <td><input name="items[price][]" inputmode="decimal" value="<?= e((string)(float)$it['price']) ?>" class="num" aria-label="Unit price"></td>
            <td class="r amt">0.00</td>
            <td><button type="button" class="icon danger" data-remove-row aria-label="Remove item">×</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <datalist id="catalogue">
        <?php foreach (doc_catalogue() as [$desc, $unit]): ?><option value="<?= e($desc) ?>" data-unit="<?= e($unit) ?>"></option><?php endforeach; ?>
      </datalist>
      <button type="button" class="btn small ghost" id="addItem">Add item</button>
      <div class="sum">
        <label class="check"><input type="checkbox" name="vat" value="1" <?= $doc['vat'] ? 'checked' : '' ?>> Add VAT <?= DOC_VAT_RATE ?>%</label>
        <label class="inline">Discount (KES)<input name="discount" inputmode="decimal" class="num" value="<?= e((string)(float)$doc['discount']) ?>"></label>
        <table class="sum-table" data-vat-rate="<?= DOC_VAT_RATE ?>">
          <tr><th>Subtotal</th><td id="tSub"><?= money($sub) ?></td></tr>
          <tr><th>Discount</th><td id="tDisc"><?= money($disc) ?></td></tr>
          <tr><th>VAT</th><td id="tVat"><?= money($tax) ?></td></tr>
          <tr class="grand"><th>Total (KES)</th><td id="tTotal"><?= money($total) ?></td></tr>
        </table>
      </div>
    </section>

    <section class="panel">
      <h2>Notes and terms</h2>
      <label>Notes (shown on the document)<textarea name="notes" rows="3"><?= e($doc['notes']) ?></textarea></label>
      <label>Terms and conditions<textarea name="terms" rows="7"><?= e($doc['terms']) ?></textarea></label>
    </section>

    <div class="save-bar">
      <button class="btn" type="submit">Save</button>
      <button class="btn ghost" type="submit" name="then" value="preview">Save &amp; preview</button>
    </div>
  </form>

  <aside class="doc-side">
    <section class="panel">
      <h2>Total</h2>
      <p class="big-total">KES <?= money($total) ?></p>
      <?php if ($isInv): ?>
        <p>Paid: <strong>KES <?= money($paid) ?></strong><br>Balance: <strong class="due-txt">KES <?= money(max($total - $paid, 0)) ?></strong></p>
      <?php endif; ?>
      <p><a class="btn wide" href="print.php?id=<?= (int)$doc['id'] ?>" target="_blank">Preview / print / PDF</a></p>
      <p><a class="link" href="<?= e(doc_public_url($doc)) ?>" target="_blank" rel="noopener">Open the client's view link</a></p>
    </section>

    <section class="panel">
      <h2>Send to client</h2>
      <?php if ($doc['client_email']): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="email"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
        <label>Extra message (optional)<textarea name="message" rows="3" placeholder="e.g. We can start on Monday."></textarea></label>
        <button class="btn wide">Email to <?= e($doc['client_email']) ?></button>
      </form>
      <p class="muted small">Sends a link where they can view, print<?= $isInv ? '' : ' and accept' ?> it. Save your changes first.</p>
      <?php else: ?><p class="muted">Add the client's email above and save to email it.</p><?php endif; ?>
      <?php if ($doc['client_phone']): $waNum = preg_replace('/^0/', '254', preg_replace('/\D/', '', $doc['client_phone']));
        $waText = "Hello " . (strtok((string)$doc['client_name'], ' ') ?: '') . ", here is your " . strtolower($types[$doc['type']]) . " {$doc['number']} from Power Plumbing: " . doc_public_url($doc); ?>
        <p><a class="btn wide wa" href="https://wa.me/<?= e($waNum) ?>?text=<?= e(rawurlencode($waText)) ?>" target="_blank" rel="noopener">Send link on WhatsApp</a></p>
      <?php endif; ?>
    </section>

    <?php if (!$isInv): ?>
    <section class="panel">
      <h2>Next step</h2>
      <form method="post" data-confirm="Create an invoice from <?= e($doc['number']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="convert"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
        <button class="btn wide">Convert to invoice</button>
      </form>
      <p class="muted small">Copies the client and all items into a new invoice and marks this <?= strtolower($types[$doc['type']]) ?> as invoiced.</p>
    </section>
    <?php else: ?>
    <section class="panel" id="payments">
      <h2>Record a payment</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
        <label>Amount (KES)<input name="amount" inputmode="decimal" required value="<?= e((string)max(round($total - $paid, 2), 0)) ?>"></label>
        <label>Method<select name="method"><?php foreach (pay_methods() as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></label>
        <label>Reference (M-Pesa code, cheque no.)<input name="reference" maxlength="80"></label>
        <label>Date received<input type="date" name="paid_on" value="<?= date('Y-m-d') ?>"></label>
        <label>Note (optional)<input name="note" maxlength="255"></label>
        <?php if ($doc['client_email']): ?><label class="check"><input type="checkbox" name="email_receipt" value="1" checked> Email the receipt to the client</label><?php endif; ?>
        <button class="btn wide">Record payment &amp; issue receipt</button>
      </form>
      <?php if ($payments): ?>
      <h3>Receipts</h3>
      <ul class="receipts">
        <?php foreach ($payments as $p): ?>
        <li>
          <a href="print.php?receipt=<?= (int)$p['id'] ?>" target="_blank"><strong><?= e($p['receipt_no']) ?></strong></a>
          KES <?= money((float)$p['amount']) ?> · <?= e(pay_methods()[$p['method']] ?? $p['method']) ?> · <?= date('j M Y', strtotime($p['paid_on'])) ?>
          <form method="post" data-confirm="Remove this payment and cancel receipt <?= e($p['receipt_no']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="payment_delete"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button class="link danger">Remove</button></form>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="panel">
      <h2>Status</h2>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
        <select name="status" data-autosubmit aria-label="Status"><?php foreach (doc_statuses($doc['type']) as $k => $v): ?><option value="<?= $k ?>" <?= $doc['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
      </form>
      <div class="side-links">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="link">Make a copy</button></form>
        <form method="post" data-confirm="Delete <?= e($doc['number']) ?><?= $payments ? ' and its receipts' : '' ?> permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button class="link danger">Delete</button></form>
      </div>
    </section>
  </aside>
  </div>
<?php endif; ?>
</main>
<script src="../assets/js/admin.js"></script>
<script src="../assets/js/admin-docs.js"></script>
</body>
</html>
