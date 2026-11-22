<?php
require __DIR__ . '/../includes/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
require __DIR__ . '/../includes/schema.php';
require __DIR__ . '/../includes/mailer.php';
require __DIR__ . '/../includes/admin-ui.php';
require_admin();

$pdo = db();
pp_migrate($pdo);
$cats = gallery_categories();
$statuses = ['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted', 'won' => 'Job won', 'closed' => 'Closed'];
$tab = in_array($_GET['tab'] ?? '', ['leads', 'photos', 'account'], true) ? $_GET['tab'] : 'leads';

/* ---------- CSV export ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quote-requests-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Name', 'Phone', 'Email', 'Location', 'Service', 'Message', 'Status']);
    foreach ($pdo->query('SELECT * FROM leads ORDER BY id DESC') as $r) {
        // neutralise spreadsheet formulas
        $row = array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v,
            [$r['created_at'], $r['name'], $r['phone'], $r['email'], $r['location'], $r['service'], $r['message'], $r['status']]);
        fputcsv($out, $row);
    }
    exit;
}

/* ---------- actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('The form expired. Try again.', 'err');
        redirect('dashboard.php?tab=' . $tab);
    }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    switch ($action) {
        case 'lead_status':
            $s = $_POST['status'] ?? '';
            if (isset($statuses[$s])) {
                $pdo->prepare('UPDATE leads SET status = ? WHERE id = ?')->execute([$s, $id]);
                flash('Status updated.');
            }
            redirect('dashboard.php?tab=leads');

        case 'lead_reply':
            $st = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
            $st->execute([$id]);
            $lead = $st->fetch();
            $subj = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 200);
            $msg = trim((string)($_POST['body'] ?? ''));
            if (!$lead || !$lead['email']) {
                flash('This request has no email address. Call or WhatsApp instead.', 'err');
            } elseif ($subj === '' || $msg === '') {
                flash('Add a subject and a message before sending.', 'err');
            } else {
                $ok = pp_mail($lead['email'], $subj, $msg, cfg('email'), $err);
                $pdo->prepare('INSERT INTO replies (lead_id, to_email, subject, body, ok, error, created_at) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$id, $lead['email'], $subj, $msg, $ok ? 1 : 0, $ok ? null : mb_substr((string)$err, 0, 255), now()]);
                if ($ok) {
                    if ($lead['status'] === 'new') $pdo->prepare("UPDATE leads SET status = 'contacted' WHERE id = ?")->execute([$id]);
                    flash('Email sent to ' . $lead['email'] . '.');
                } else {
                    flash('Email not sent: ' . e((string)$err), 'err');
                }
            }
            redirect('dashboard.php?tab=leads#lead-' . $id);

        case 'test_mail':
            $ok = pp_mail(cfg('notify_email'), 'Test email from the Power Plumbing website',
                "This is a test sent through " . (cfg('smtp')['enabled'] ? cfg('smtp')['host'] . ':' . cfg('smtp')['port'] : 'PHP mail()') . ' at ' . now() . ".\nIf you can read this, email from the website works.", null, $err);
            flash($ok ? 'Test email sent to ' . cfg('notify_email') . '. Check the inbox (and spam folder).' : 'Test failed: ' . e((string)$err), $ok ? 'ok' : 'err');
            redirect('dashboard.php?tab=account');

        case 'lead_delete':
            $pdo->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
            flash('Request deleted.');
            redirect('dashboard.php?tab=leads');

        case 'photo_upload':
            $cat = isset($cats[$_POST['category'] ?? '']) ? $_POST['category'] : 'plumbing';
            $cap = mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 160);
            $files = $_FILES['photos'] ?? null;
            $ok = 0;
            $errs = [];
            if ($files && is_array($files['name'])) {
                $job = !empty($_POST['on_job']) ? 1 : 0;
                $ins = $pdo->prepare('INSERT INTO photos (filename, thumb, caption, category, media, on_job, sort_order, created_at) VALUES (?,?,?,?,?,?,?,?)');
                foreach ($files['name'] as $i => $name) {
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    if ($files['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($files['tmp_name'][$i])) {
                        $errs[] = e($name) . ': upload failed (too large for the server?)';
                        continue;
                    }
                    try {
                        if (preg_match('/\.(mp4|m4v|mov)$/i', $name)) {
                            $full = process_video($files['tmp_name'][$i]);
                            $ins->execute([$full, '', $cap ?: null, $cat, 'video', 0, 5, now()]);
                        } else {
                            [$full, $thumb] = process_image($files['tmp_name'][$i]);
                            $ins->execute([$full, $thumb, $cap ?: null, $cat, 'image', $job, 5, now()]);
                        }
                        $ok++;
                    } catch (Throwable $ex) {
                        $errs[] = e($name) . ': ' . e($ex->getMessage());
                    }
                }
            }
            if ($ok) flash($ok . ' item' . ($ok > 1 ? 's' : '') . ' added to the gallery.' . ($errs ? ' Skipped: ' . implode('; ', $errs) : ''), $errs ? 'warn' : 'ok');
            else flash($errs ? implode('; ', $errs) : 'Choose at least one photo or video to upload.', 'err');
            redirect('dashboard.php?tab=photos');

        case 'photo_update':
            $cat = isset($cats[$_POST['category'] ?? '']) ? $_POST['category'] : 'plumbing';
            $pdo->prepare('UPDATE photos SET caption = ?, category = ?, sort_order = ?, on_job = ? WHERE id = ?')
                ->execute([mb_substr(trim((string)$_POST['caption']), 0, 160) ?: null, $cat, (int)$_POST['sort_order'], !empty($_POST['on_job']) ? 1 : 0, $id]);
            flash('Photo details saved.');
            redirect('dashboard.php?tab=photos');

        case 'photo_delete':
            $st = $pdo->prepare('SELECT filename, thumb FROM photos WHERE id = ?');
            $st->execute([$id]);
            if ($p = $st->fetch()) {
                foreach ([$p['filename'], $p['thumb']] as $file) {
                    if (!$file) continue;
                    $path = PP_GALLERY_DIR . '/' . basename($file);
                    if (is_file($path)) unlink($path);
                }
                $pdo->prepare('DELETE FROM photos WHERE id = ?')->execute([$id]);
                flash('Photo removed from the gallery.');
            }
            redirect('dashboard.php?tab=photos');

        case 'seed':
            $n = pp_seed_gallery($pdo);
            flash($n ? "$n starter items added to the gallery." : 'All starter items are already in the gallery.');
            redirect('dashboard.php?tab=photos');

        case 'password':
            $st = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
            $st->execute([$_SESSION['admin_id']]);
            $hash = (string)$st->fetchColumn();
            $new = (string)($_POST['new'] ?? '');
            if (!password_verify((string)($_POST['current'] ?? ''), $hash)) {
                flash('Your current password is not correct.', 'err');
            } elseif (strlen($new) < 10 || $new !== ($_POST['new2'] ?? '')) {
                flash('The new password must be 10+ characters and match in both boxes.', 'err');
            } else {
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
                session_regenerate_id(true);
                flash('Password changed.');
            }
            redirect('dashboard.php?tab=account');
    }
    redirect('dashboard.php');
}

/* ---------- data ---------- */
$counts = $pdo->query("SELECT status, COUNT(*) c FROM leads GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$filter = isset($statuses[$_GET['status'] ?? '']) ? $_GET['status'] : '';
if ($filter) {
    $st = $pdo->prepare('SELECT * FROM leads WHERE status = ? ORDER BY id DESC LIMIT 300');
    $st->execute([$filter]);
} else {
    $st = $pdo->query('SELECT * FROM leads ORDER BY id DESC LIMIT 300');
}
$leads = $st->fetchAll();
$replies = [];
foreach ($pdo->query('SELECT * FROM replies ORDER BY id ASC') as $r) $replies[(int)$r['lead_id']][] = $r;
$photos = $pdo->query('SELECT * FROM photos ORDER BY sort_order ASC, id DESC')->fetchAll();
$f = flash();
$maxMb = min((int)cfg('max_upload_mb', 8), (int)ini_get('upload_max_filesize') ?: 8);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Admin — Power Plumbing</title>
<link rel="icon" href="../assets/img/logo-mark.svg" type="image/svg+xml">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php admin_bar($tab, (int)($counts['new'] ?? 0)); ?>

<main class="wrap">
<?php if ($f): ?><p class="note <?= e($f['type']) ?>"><?= $f['type'] === 'warn' || $f['type'] === 'err' ? $f['msg'] : e($f['msg']) ?></p><?php endif; ?>

<?php if ($tab === 'leads'): ?>
  <div class="head">
    <h1>Quote requests</h1>
    <a class="btn ghost" href="?tab=leads&export=csv">Download CSV</a>
  </div>
  <nav class="filters" aria-label="Filter by status">
    <a href="?tab=leads" <?= !$filter ? 'aria-current="true"' : '' ?>>All (<?= array_sum($counts) ?>)</a>
    <?php foreach ($statuses as $k => $label): ?>
      <a href="?tab=leads&status=<?= $k ?>" <?= $filter === $k ? 'aria-current="true"' : '' ?>><?= $label ?> (<?= (int)($counts[$k] ?? 0) ?>)</a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$leads): ?>
    <p class="empty">No quote requests<?= $filter ? ' with this status' : ' yet' ?>. New requests from the website form appear here and are emailed to <?= e(cfg('notify_email')) ?>.</p>
  <?php else: ?>
  <div class="leads">
    <?php foreach ($leads as $l): ?>
    <article class="lead status-<?= e($l['status']) ?>" id="lead-<?= (int)$l['id'] ?>">
      <div class="lead-main">
        <h2><?= e($l['name']) ?> <small><?= e(date('j M Y, H:i', strtotime($l['created_at']))) ?></small></h2>
        <p class="lead-meta">
          <a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $l['phone'])) ?>"><?= e($l['phone']) ?></a>
          <a href="https://wa.me/<?= e(preg_replace('/^0/', '254', preg_replace('/[^0-9]/', '', $l['phone']))) ?>" target="_blank" rel="noopener">WhatsApp</a>
          <?php if ($l['email']): ?><a href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a><?php endif; ?>
        </p>
        <p><strong><?= e($l['service'] ?: 'General enquiry') ?></strong><?= $l['location'] ? ' in ' . e($l['location']) : '' ?></p>
        <?php if ($l['message']): ?><p class="msg"><?= nl2br(e($l['message'])) ?></p><?php endif; ?>
        <?php foreach ($replies[(int)$l['id']] ?? [] as $r): ?>
          <div class="reply <?= $r['ok'] ? '' : 'failed' ?>">
            <p class="reply-meta"><?= $r['ok'] ? 'Emailed' : 'Failed to send' ?> <?= e(date('j M, H:i', strtotime($r['created_at']))) ?> — <strong><?= e($r['subject']) ?></strong></p>
            <p><?= nl2br(e($r['body'])) ?></p>
            <?php if (!$r['ok']): ?><p class="reply-err"><?= e($r['error']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($l['email']): ?>
        <details class="reply-box">
          <summary>Reply by email</summary>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="lead_reply"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
            <label>To<input value="<?= e($l['email']) ?>" disabled></label>
            <label>Subject<input name="subject" required maxlength="200" value="Your <?= e(strtolower($l['service'] ?: 'plumbing')) ?> quote — Power Plumbing"></label>
            <label>Message<textarea name="body" rows="7" required><?= e("Hello " . strtok($l['name'], ' ') . ",\n\nThank you for your request. \n\nRegards,\nPower Plumbing Services Ltd\n" . cfg('phone_display') . " | " . cfg('email')) ?></textarea></label>
            <button class="btn small">Send email</button>
            <span class="muted">Sent from <?= e(cfg('mail_from')) ?>. Customer replies go to that inbox.</span>
          </form>
        </details>
        <?php endif; ?>
      </div>
      <div class="lead-actions">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="lead_status"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <label class="sr" for="st<?= (int)$l['id'] ?>">Status</label>
          <select id="st<?= (int)$l['id'] ?>" name="status" data-autosubmit>
            <?php foreach ($statuses as $k => $label): ?><option value="<?= $k ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
          </select>
          <noscript><button class="btn small">Save</button></noscript>
        </form>
        <a class="btn small" href="documents.php?new=quote&lead=<?= (int)$l['id'] ?>">Create quote</a>
        <a class="link" href="documents.php?new=proposal&lead=<?= (int)$l['id'] ?>">Create proposal</a>
        <form method="post" data-confirm="Delete this request permanently?">
          <?= csrf_field() ?><input type="hidden" name="action" value="lead_delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
          <button class="link danger">Delete</button>
        </form>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php elseif ($tab === 'photos'): ?>
  <div class="head"><h1>Gallery</h1><span class="muted"><?= count(array_filter($photos, fn($x) => ($x['media'] ?? 'image') === 'image')) ?> photos, <?= count(array_filter($photos, fn($x) => ($x['media'] ?? '') === 'video')) ?> videos on the website</span></div>

  <form class="panel upload" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="photo_upload">
    <h2>Add photos or videos</h2>
    <p class="muted">Photos: JPG, PNG or WebP up to <?= $maxMb ?> MB — straight from a phone is fine; they are resized and location data is removed.
      Videos: MP4 up to <?= (int)cfg('max_video_mb', 60) ?> MB. Short clips (under a minute) load fastest.</p>
    <div class="grid3">
      <label>Photos or videos<input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,video/mp4" multiple required></label>
      <label>Category<select name="category"><?php foreach ($cats as $k => $label): ?><option value="<?= $k ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>Caption (optional, used for all)<input name="caption" maxlength="160" placeholder="e.g. Borehole pump installed in Kitengela"></label>
    </div>
    <label class="check"><input type="checkbox" name="on_job" value="1"> Show photos in the "On the job" strip (workers at work)</label>
    <button class="btn" type="submit">Upload</button>
  </form>
  <form method="post" class="seed-form"><?= csrf_field() ?><input type="hidden" name="action" value="seed">
    <button class="link">Load the starter photos and videos</button> <span class="muted">(adds any that are missing; nothing is duplicated)</span>
  </form>

  <?php if (!$photos): ?>
    <p class="empty">No photos yet. Upload some above and they will show in the gallery on the home page.</p>
  <?php else: ?>
  <div class="photos">
    <?php foreach ($photos as $p): ?>
    <figure class="photo">
      <?php if (($p['media'] ?? 'image') === 'video'): ?>
        <video src="../<?= PP_GALLERY_URL . '/' . e($p['filename']) ?>#t=1" <?= $p['thumb'] ? 'poster="../' . PP_GALLERY_URL . '/' . e($p['thumb']) . '"' : '' ?> muted playsinline preload="metadata" controls></video>
      <?php else: ?>
        <img src="../<?= PP_GALLERY_URL . '/' . e($p['thumb']) ?>" alt="" loading="lazy">
      <?php endif; ?>
      <?php if (!empty($p['on_job'])): ?><span class="tag">On the job</span><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="photo_update"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <label>Caption<input name="caption" maxlength="160" value="<?= e($p['caption']) ?>"></label>
        <div class="row">
          <label>Category<select name="category"><?php foreach ($cats as $k => $label): ?><option value="<?= $k ?>" <?= $p['category'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label class="narrow">Order<input type="number" name="sort_order" value="<?= (int)$p['sort_order'] ?>"></label>
        </div>
        <?php if (($p['media'] ?? 'image') === 'image'): ?>
        <label class="check"><input type="checkbox" name="on_job" value="1" <?= !empty($p['on_job']) ? 'checked' : '' ?>> On the job strip</label>
        <?php endif; ?>
        <button class="btn small">Save</button>
      </form>
      <form method="post" data-confirm="Remove this photo from the website?">
        <?= csrf_field() ?><input type="hidden" name="action" value="photo_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="link danger">Remove</button>
      </form>
    </figure>
    <?php endforeach; ?>
  </div>
  <p class="muted">Lower order numbers show first. New uploads get order 5, so they appear at the front.</p>
  <?php endif; ?>

<?php else: ?>
  <div class="head"><h1>Account</h1></div>
  <form class="panel narrow-panel" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h2>Change password</h2>
    <label>Current password<input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password (10+ characters)<input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
    <label>Repeat new password<input type="password" name="new2" required minlength="10" autocomplete="new-password"></label>
    <button class="btn">Change password</button>
  </form>
  <form class="panel narrow-panel" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
    <h2>Email</h2>
    <p class="muted">Sending through <?= cfg('smtp')['enabled'] ? e(cfg('smtp')['host'] . ':' . cfg('smtp')['port'] . ' as ' . cfg('smtp')['user']) : 'PHP mail() (SMTP is off)' ?>.
      New requests go to <?= e(cfg('notify_email')) ?>.</p>
    <button class="btn ghost">Send a test email</button>
  </form>
<?php endif; ?>
</main>
<script src="../assets/js/admin.js"></script>
</body>
</html>
