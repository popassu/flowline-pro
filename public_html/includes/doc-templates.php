<?php
/*
 * Quotation and business proposal templates.
 * Edit the wording here; {client}, {company}, {site} and {title} are filled in automatically.
 */

const DOC_VAT_RATE = 16; // Kenya VAT %

function doc_types(): array
{
    return ['quote' => 'Quotation', 'proposal' => 'Business proposal', 'invoice' => 'Invoice'];
}

function doc_statuses(string $type = 'quote'): array
{
    if ($type === 'invoice') {
        return ['draft' => 'Draft', 'sent' => 'Sent', 'partly' => 'Partly paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
    }
    return ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'invoiced' => 'Invoiced'];
}

function pay_methods(): array
{
    return ['mpesa' => 'M-Pesa', 'bank' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque'];
}

/** Total paid against an invoice. */
function doc_paid(PDO $pdo, int $docId): float
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE document_id = ?');
    $st->execute([$docId]);
    return round((float)$st->fetchColumn(), 2);
}

function receipt_next_number(PDO $pdo): string
{
    $prefix = 'PPS-R-' . date('Y') . '-';
    $st = $pdo->prepare('SELECT receipt_no FROM payments WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = (string)$st->fetchColumn();
    $n = $last ? (int)substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Amount in words for receipts, e.g. 12500.50 -> "Twelve thousand five hundred shillings and fifty cents". */
function amount_words(float $n): string
{
    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
             'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $chunk = function (int $x) use ($ones, $tens): string {
        $w = [];
        if ($x >= 100) { $w[] = $ones[intdiv($x, 100)] . ' hundred'; $x %= 100; if ($x) $w[] = 'and'; }
        if ($x >= 20) { $w[] = $tens[intdiv($x, 10)] . ($x % 10 ? '-' . $ones[$x % 10] : ''); }
        elseif ($x > 0) { $w[] = $ones[$x]; }
        return implode(' ', $w);
    };
    $whole = (int)floor($n);
    $cents = (int)round(($n - $whole) * 100);
    if ($whole === 0) $words = 'zero';
    else {
        $parts = [];
        foreach ([1_000_000_000 => 'billion', 1_000_000 => 'million', 1000 => 'thousand', 1 => ''] as $div => $name) {
            if ($whole >= $div) {
                $parts[] = trim($chunk(intdiv($whole, $div)) . ' ' . $name);
                $whole %= $div;
            }
        }
        $words = implode(' ', $parts);
    }
    $out = $words . ' shillings' . ($cents ? ' and ' . $chunk($cents) . ' cents' : '') . ' only';
    return ucfirst($out);
}

/** Common line items offered as suggestions when typing a description. */
function doc_catalogue(): array
{
    return [
        ['Site visit and assessment', 'lot'],
        ['Labour — plumbing installation', 'lot'],
        ['Labour — plumber', 'day'],
        ['Supply and install submersible borehole pump', 'no.'],
        ['Supply and install booster pump set with pressure tank', 'set'],
        ['Supply and install voltage stabiliser', 'no.'],
        ['Supply and install water storage tank', 'no.'],
        ['Construct steel tank stand', 'no.'],
        ['Supply and install PPR pipe and fittings', 'm'],
        ['Supply and install uPVC waste pipe', 'm'],
        ['Construct inspection chamber / manhole', 'no.'],
        ['Supply and install solar water heater (200 L)', 'no.'],
        ['Supply and install instant shower heater', 'no.'],
        ['Supply and install reverse-osmosis water filter', 'set'],
        ['Supply and fit WC (close-coupled)', 'no.'],
        ['Supply and fit wash-hand basin with mixer', 'no.'],
        ['Supply and fit kitchen sink with mixer', 'no.'],
        ['Supply and fit shower set with concealed mixer', 'set'],
        ['Supply and fit frameless shower screen', 'no.'],
        ['Unblock drain / sewer line', 'lot'],
        ['Leak detection and repair', 'lot'],
        ['Fire hose reel installation', 'no.'],
        ['Floor / wall tiling (labour and materials)', 'm²'],
        ['Supply and install gutters and downpipes', 'm'],
        ['Domestic wiring — socket / lighting point', 'point'],
        ['Fit light fitting / chandelier', 'no.'],
        ['Kitchen units — supply and fit', 'lot'],
        ['Painting (two coats)', 'm²'],
        ['Transport and logistics', 'lot'],
        ['Making good and site clean-up', 'lot'],
    ];
}

function doc_default_terms(string $type): string
{
    if ($type === 'invoice') {
        return "1. Payment is due by the due date shown above.\n"
             . "2. Quote the invoice number as the payment reference.\n"
             . "3. Equipment remains the property of Power Plumbing Services Ltd until paid in full.\n"
             . "4. Workmanship is guaranteed for 12 months from handover.";
    }
    $t = "1. Prices are in Kenya Shillings (KES)" . " and include VAT at " . DOC_VAT_RATE . "% where shown.\n"
       . "2. This " . ($type === 'proposal' ? 'proposal' : 'quotation') . " is valid for the number of days stated above.\n"
       . "3. A deposit of 60% is required before materials are ordered; the balance is due on completion and handover.\n"
       . "4. Payment by M-Pesa or bank transfer. Bank details are provided on the invoice.\n"
       . "5. Any work outside the listed scope will be quoted separately before it is done.\n"
       . "6. Workmanship is guaranteed for 12 months from handover. Manufacturer warranties apply to supplied equipment.\n"
       . "7. The client provides access to the site, water and power during the works.";
    return $t;
}

/** Proposal sections: [heading, body]. A section whose body is {pricing} shows the priced items. */
function doc_proposal_sections(): array
{
    return [
        ['Introduction',
         "Dear {client},\n\nThank you for the opportunity to propose for {title} at {site}. This document sets out our understanding of what you need, how we will deliver it, the programme, and the cost."],
        ['About Power Plumbing Services Ltd',
         "Power Plumbing Services Ltd is a Nairobi-based firm of registered plumbers working across East Africa. We design, install and maintain water supply, drainage, hot water, fire-fighting, compressed air and steam systems for homes, estates, schools, hotels and commercial premises. We also handle the finishing work around plumbing — tiling, gutters, domestic wiring, fittings and joinery — so clients deal with one accountable team."],
        ['Our understanding of your requirements',
         "From our discussions and site visit, {company} requires:\n- \n- \n- "],
        ['Scope of work',
         "We will:\n- Survey the site and confirm all measurements and connection points\n- Supply all materials listed in the pricing schedule\n- Install, connect and pressure-test the systems\n- Make good any areas disturbed by our works\n- Commission the installation and hand over with a walk-through"],
        ['Approach and methodology',
         "1. Mobilisation — confirm drawings, order materials, agree working hours and site access.\n2. First fix — pipe runs, chambers and concealed work, inspected before closing up.\n3. Second fix — fittings, equipment and connections.\n4. Testing — pressure tests, leak checks and flow tests, recorded on a test sheet.\n5. Handover — demonstration, test certificates and maintenance guidance."],
        ['Programme',
         "We estimate the works will take ___ working days from mobilisation, subject to site access and timely approvals. A detailed programme will be shared at mobilisation."],
        ['Materials and standards',
         "We use PPR and uPVC pipework, brass valves and fittings from reputable suppliers, and pumps and equipment backed by local warranties. All work follows good plumbing practice and the relevant Kenyan building regulations."],
        ['Health, safety and site conduct',
         "Our team works in protective equipment, keeps the site tidy and secure, and removes debris daily. We coordinate with other contractors on site and respect occupants' privacy and working hours."],
        ['Pricing', '{pricing}'],
        ['Warranty and aftercare',
         "Workmanship is guaranteed for 12 months from handover. During that period we return to correct any defect arising from our work at no cost. Planned maintenance visits can be arranged on request."],
        ['Next steps',
         "To proceed, accept this proposal online using the link provided, or sign below and return a copy. We will then confirm a start date and issue a deposit invoice.\n\nWe look forward to working with {company}."],
    ];
}

/* ---------- helpers ---------- */

function money(float $n): string
{
    return number_format($n, 2);
}

/** Returns [subtotal, discount, vat, total]. */
function doc_totals(array $items, bool $vat, float $discount): array
{
    $sub = 0.0;
    foreach ($items as $it) {
        $sub += (float)($it['qty'] ?? 0) * (float)($it['price'] ?? 0);
    }
    $discount = min(max($discount, 0), $sub);
    $net = $sub - $discount;
    $tax = $vat ? round($net * DOC_VAT_RATE / 100, 2) : 0.0;
    return [round($sub, 2), round($discount, 2), $tax, round($net + $tax, 2)];
}

function doc_next_number(PDO $pdo, string $type): string
{
    $prefix = ['proposal' => 'PPS-P-', 'invoice' => 'PPS-INV-'][$type] ?? 'PPS-Q-';
    $prefix .= date('Y') . '-';
    $st = $pdo->prepare('SELECT number FROM documents WHERE number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = (string)$st->fetchColumn();
    $n = $last ? (int)substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function doc_fill(string $text, array $doc): string
{
    $client = trim((string)($doc['client_name'] ?? '')) ?: 'Sir/Madam';
    return strtr($text, [
        '{client}'  => $client,
        '{company}' => trim((string)($doc['client_company'] ?? '')) ?: $client,
        '{site}'    => trim((string)($doc['site'] ?? '')) ?: 'your premises',
        '{title}'   => trim((string)($doc['title'] ?? '')) ?: 'the works',
    ]);
}

function doc_load(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM documents WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) return null;
    $d['items'] = json_decode((string)$d['items'], true) ?: [];
    $d['sections'] = json_decode((string)$d['sections'], true) ?: [];
    return $d;
}

function doc_public_url(array $d): string
{
    return rtrim(cfg('site_url'), '/') . '/doc.php?t=' . $d['token'];
}
