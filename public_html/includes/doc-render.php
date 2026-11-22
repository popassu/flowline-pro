<?php
/* Branded, watermarked A4 rendering for quotations, proposals, invoices and receipts. */

function doc_letterhead(): void
{ ?>
  <header class="lh">
    <img class="lh-logo" src="<?= e(doc_asset('assets/img/logo-mark.svg')) ?>" alt="">
    <div class="lh-main">
      <div class="lh-name">POWER PLUMBING SERVICES LTD</div>
      <div class="lh-tag">Registered Plumbers | Drainage | Compressed Air | Fire Fighting | Water Reticulation | Steam | Solar Heating &amp; Renovation</div>
      <div class="lh-contacts">
        <span><?= e(cfg('po_box')) ?></span>
        <span>Tel: <?= e(cfg('phone_display')) ?> · WhatsApp: +<?= e(cfg('whatsapp')) ?></span>
        <span><?= e(cfg('email')) ?> · <?= e(preg_replace('#^https?://#', '', (string)cfg('site_url'))) ?></span>
        <?php if (cfg('kra_pin')): ?><span>KRA PIN: <?= e(cfg('kra_pin')) ?></span><?php endif; ?>
      </div>
    </div>
  </header>
  <div class="lh-rule"><span></span><span></span></div>
<?php }

function doc_asset(string $path): string
{
    return (defined('PP_DOC_BASE') ? PP_DOC_BASE : '') . $path;
}

function doc_stamp(string $type, string $status): ?array
{
    $map = [
        'draft' => ['DRAFT', 'grey'], 'accepted' => ['ACCEPTED', 'green'], 'declined' => ['DECLINED', 'grey'],
        'invoiced' => ['INVOICED', 'navy'], 'paid' => ['PAID', 'red'], 'partly' => ['PART PAID', 'amber'],
        'cancelled' => ['CANCELLED', 'grey'],
    ];
    return $map[$status] ?? null;
}

function doc_items_table(array $d, ?float $paid = null): void
{
    [$sub, $disc, $tax, $total] = doc_totals($d['items'], (bool)$d['vat'], (float)$d['discount']);
    ?>
  <table class="items">
    <thead><tr><th class="n">#</th><th>Description</th><th class="r">Qty</th><th>Unit</th><th class="r">Unit price</th><th class="r">Amount (KES)</th></tr></thead>
    <tbody>
    <?php $i = 0; foreach ($d['items'] as $it): if (trim((string)($it['desc'] ?? '')) === '') continue; $i++; $amt = (float)$it['qty'] * (float)$it['price']; ?>
      <tr><td class="n"><?= $i ?></td><td><?= nl2br(e($it['desc'])) ?></td><td class="r"><?= e(rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.')) ?></td><td><?= e($it['unit'] ?? '') ?></td><td class="r"><?= money((float)$it['price']) ?></td><td class="r"><?= money($amt) ?></td></tr>
    <?php endforeach; if (!$i): ?>
      <tr><td colspan="6" class="empty-row">No items yet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <table class="totals">
    <tr><th>Subtotal</th><td><?= money($sub) ?></td></tr>
    <?php if ($disc > 0): ?><tr><th>Discount</th><td>− <?= money($disc) ?></td></tr><?php endif; ?>
    <?php if ($d['vat']): ?><tr><th>VAT <?= DOC_VAT_RATE ?>%</th><td><?= money($tax) ?></td></tr><?php endif; ?>
    <tr class="grand"><th>Total (KES)</th><td><?= money($total) ?></td></tr>
    <?php if ($paid !== null): ?>
      <tr><th>Paid</th><td><?= money($paid) ?></td></tr>
      <tr class="grand due"><th>Balance due</th><td><?= money(max($total - $paid, 0)) ?></td></tr>
    <?php endif; ?>
  </table>
<?php }

function render_document(array $d, array $opt = []): void
{
    $types = doc_types();
    $paid = $opt['paid'] ?? null;
    $stamp = doc_stamp($d['type'], $d['status']);
    $date = strtotime($d['doc_date']);
    ?>
<article class="sheet type-<?= e($d['type']) ?>">
  <div class="wm" aria-hidden="true"><img src="<?= e(doc_asset('assets/img/logo-mark.svg')) ?>" alt=""></div>
  <?php doc_letterhead(); ?>

  <div class="doc-head">
    <div>
      <h1><?= e(strtoupper($types[$d['type']] ?? 'Document')) ?></h1>
      <?php if ($d['title']): ?><p class="doc-title"><?= e($d['title']) ?></p><?php endif; ?>
    </div>
    <?php if ($stamp): ?><div class="stamp <?= $stamp[1] ?>" aria-hidden="true"><?= $stamp[0] ?></div><?php endif; ?>
    <table class="meta">
      <tr><th><?= $d['type'] === 'invoice' ? 'Invoice no.' : 'Ref.' ?></th><td><?= e($d['number']) ?></td></tr>
      <tr><th>Date</th><td><?= date('j M Y', $date) ?></td></tr>
      <?php if ($d['type'] === 'invoice'): ?>
        <tr><th>Due date</th><td><?= date('j M Y', $date + 86400 * (int)$d['due_days']) ?></td></tr>
        <?php if (!empty($opt['source_number'])): ?><tr><th>Quotation</th><td><?= e($opt['source_number']) ?></td></tr><?php endif; ?>
      <?php else: ?>
        <tr><th>Valid until</th><td><?= date('j M Y', $date + 86400 * (int)$d['valid_days']) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="parties">
    <div>
      <h2><?= $d['type'] === 'invoice' ? 'Bill to' : 'Prepared for' ?></h2>
      <p>
        <?php if ($d['client_company']): ?><strong><?= e($d['client_company']) ?></strong><br><?php endif; ?>
        <?= $d['client_company'] ? 'Attn: ' : '<strong>' ?><?= e($d['client_name']) ?><?= $d['client_company'] ? '' : '</strong>' ?><br>
        <?php if ($d['client_address']): ?><?= nl2br(e($d['client_address'])) ?><br><?php endif; ?>
        <?php if ($d['client_phone']): ?><?= e($d['client_phone']) ?><br><?php endif; ?>
        <?php if ($d['client_email']): ?><?= e($d['client_email']) ?><?php endif; ?>
      </p>
    </div>
    <?php if ($d['site']): ?>
    <div><h2>Site / project</h2><p><?= nl2br(e($d['site'])) ?></p></div>
    <?php endif; ?>
  </div>

  <?php if ($d['type'] === 'proposal'): ?>
    <?php $n = 0; foreach ($d['sections'] as $sec): if (trim((string)($sec['body'] ?? '')) === '' && trim((string)($sec['heading'] ?? '')) === '') continue; $n++; ?>
      <section class="psec">
        <h2><?= $n ?>. <?= e($sec['heading']) ?></h2>
        <?php if (trim($sec['body']) === '{pricing}'): doc_items_table($d); else: ?>
          <?= doc_paragraphs(doc_fill($sec['body'], $d)) ?>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php else: ?>
    <?php doc_items_table($d, $d['type'] === 'invoice' ? (float)$paid : null); ?>
  <?php endif; ?>

  <?php if ($d['notes']): ?><section class="block"><h2>Notes</h2><?= doc_paragraphs($d['notes']) ?></section><?php endif; ?>

  <?php if ($d['type'] === 'invoice'): ?>
    <section class="block pay"><h2>How to pay</h2><?= doc_paragraphs((string)cfg('payment_details')) ?><p>Use <strong><?= e($d['number']) ?></strong> as the payment reference.</p></section>
  <?php endif; ?>

  <?php if ($d['terms']): ?><section class="block terms"><h2>Terms and conditions</h2><?= doc_paragraphs($d['terms']) ?></section><?php endif; ?>

  <?php if (!empty($opt['payments'])): ?>
    <section class="block"><h2>Payments received</h2>
      <table class="items small"><thead><tr><th>Date</th><th>Receipt</th><th>Method</th><th>Reference</th><th class="r">Amount (KES)</th></tr></thead><tbody>
      <?php foreach ($opt['payments'] as $p): ?>
        <tr><td><?= date('j M Y', strtotime($p['paid_on'])) ?></td><td><?= e($p['receipt_no']) ?></td><td><?= e(pay_methods()[$p['method']] ?? $p['method']) ?></td><td><?= e($p['reference']) ?></td><td class="r"><?= money((float)$p['amount']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </section>
  <?php endif; ?>

  <?php if ($d['type'] !== 'invoice'): ?>
  <div class="sign">
    <div><p class="line"></p><p>For Power Plumbing Services Ltd</p></div>
    <div>
      <?php if ($d['accepted_at']): ?>
        <p class="accepted">Accepted online by <?= e($d['accepted_name']) ?><br><?= date('j M Y, H:i', strtotime($d['accepted_at'])) ?></p>
      <?php else: ?>
        <p class="line"></p><p>Accepted by client — name, signature &amp; date</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <footer class="lh-foot">Thank you for choosing Power Plumbing Services Ltd · <?= e(cfg('phone_display')) ?> · <?= e(cfg('email')) ?></footer>
</article>
<?php }

function render_receipt(array $p, array $inv, float $paidToDate): void
{
    [, , , $total] = doc_totals($inv['items'], (bool)$inv['vat'], (float)$inv['discount']);
    $balance = max($total - $paidToDate, 0);
    ?>
<article class="sheet type-receipt">
  <div class="wm" aria-hidden="true"><img src="<?= e(doc_asset('assets/img/logo-mark.svg')) ?>" alt=""></div>
  <?php doc_letterhead(); ?>
  <div class="doc-head">
    <div><h1>OFFICIAL RECEIPT</h1></div>
    <div class="stamp red" aria-hidden="true">PAID</div>
    <table class="meta">
      <tr><th>Receipt no.</th><td><?= e($p['receipt_no']) ?></td></tr>
      <tr><th>Date</th><td><?= date('j M Y', strtotime($p['paid_on'])) ?></td></tr>
      <tr><th>Invoice</th><td><?= e($inv['number']) ?></td></tr>
    </table>
  </div>
  <dl class="rcpt">
    <div><dt>Received from</dt><dd><?= e($inv['client_company'] ?: $inv['client_name']) ?><?= $inv['client_company'] && $inv['client_name'] ? ' (' . e($inv['client_name']) . ')' : '' ?></dd></div>
    <div><dt>The sum of</dt><dd><?= e(amount_words((float)$p['amount'])) ?></dd></div>
    <div><dt>Being payment for</dt><dd><?= e($inv['title'] ?: 'Invoice ' . $inv['number']) ?></dd></div>
    <div><dt>Payment method</dt><dd><?= e(pay_methods()[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' — ref. ' . e($p['reference']) : '' ?></dd></div>
    <?php if ($p['note']): ?><div><dt>Note</dt><dd><?= e($p['note']) ?></dd></div><?php endif; ?>
  </dl>
  <div class="rcpt-amount"><span>KES</span><?= money((float)$p['amount']) ?></div>
  <table class="totals">
    <tr><th>Invoice total</th><td><?= money($total) ?></td></tr>
    <tr><th>Paid to date</th><td><?= money($paidToDate) ?></td></tr>
    <tr class="grand <?= $balance > 0 ? 'due' : '' ?>"><th>Balance</th><td><?= money($balance) ?></td></tr>
  </table>
  <div class="sign"><div><p class="line"></p><p>Received by, for Power Plumbing Services Ltd</p></div><div></div></div>
  <footer class="lh-foot">Thank you for your payment · <?= e(cfg('phone_display')) ?> · <?= e(cfg('email')) ?></footer>
</article>
<?php }

/** Turn plain text into paragraphs and bullet lists (lines starting with "-"). */
function doc_paragraphs(string $text): string
{
    $out = '';
    foreach (preg_split("/\R{2,}/", trim($text)) as $block) {
        $lines = preg_split("/\R/", $block);
        if (count(array_filter($lines, fn($l) => preg_match('/^\s*-\s?/', $l))) === count($lines)) {
            $out .= '<ul>' . implode('', array_map(fn($l) => '<li>' . e(preg_replace('/^\s*-\s?/', '', $l)) . '</li>', $lines)) . '</ul>';
        } else {
            $out .= '<p>' . implode('<br>', array_map('e', $lines)) . '</p>';
        }
    }
    return $out;
}
