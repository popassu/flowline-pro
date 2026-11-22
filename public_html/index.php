<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/content.php';

$photos = gallery_photos();
$onJob = on_job_photos();
$videoCount = count(array_filter($photos, fn($p) => ($p['media'] ?? 'image') === 'video'));
$cats = gallery_categories();
$usedCats = array_values(array_unique(array_column($photos, 'category')));

$_SESSION['quote_started'] = time();
$qStatus = $_SESSION['quote_status'] ?? '';
$old = $_SESSION['quote_old'] ?? [];
unset($_SESSION['quote_status'], $_SESSION['quote_old']);
$qMessages = [
    'sent'    => ['ok',  'Thank you — your request is in. We will call you back shortly.'],
    'invalid' => ['err', 'Please add your name and a phone number we can reach you on (for example 0712 345 678).'],
    'expired' => ['err', 'The form timed out. Check your details below and send it again.'],
    'limit'   => ['err', 'We have already received several requests from you this hour. Call ' . cfg('phone_display') . ' if it is urgent.'],
    'error'   => ['err', 'The request could not be saved. Call or WhatsApp ' . cfg('phone_display') . ' and we will help straight away.'],
];
$o = fn(string $k) => e($old[$k] ?? '');

$tel = 'tel:' . cfg('phone_intl');
$waDisplay = '+' . substr(cfg('whatsapp'), 0, 3) . ' ' . substr(cfg('whatsapp'), 3, 3) . ' ' . substr(cfg('whatsapp'), 6);
$waPlain = 'https://wa.me/' . cfg('whatsapp');
$wa = 'https://wa.me/' . cfg('whatsapp') . '?text=' . rawurlencode('Hello Power Plumbing, I would like a quote for ');
$site = rtrim(cfg('site_url'), '/');
$serviceOptions = array_merge(
    ...array_map(fn($g) => array_column($g, 0), array_values($plumbing_groups)),
    ...[array_column($other_services, 0)]
);

$ld = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Plumber',
            'name' => cfg('site_name'),
            'url' => $site . '/',
            'logo' => $site . '/assets/img/logo.svg',
            'telephone' => cfg('phone_intl'),
            'email' => cfg('email'),
            'address' => ['@type' => 'PostalAddress', 'postOfficeBoxNumber' => '8557-00100', 'addressLocality' => 'Nairobi', 'addressCountry' => 'KE'],
            'areaServed' => ['Kenya', 'Uganda', 'Tanzania', 'Rwanda', 'East Africa'],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type' => 'Question', 'name' => $f[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
            ], $faqs),
        ],
    ],
];

$groupIcons = [
    'Water supply' => '<path d="M12 3s6 6.4 6 10.5A6 6 0 0 1 6 13.5C6 9.4 12 3 12 3z"/>',
    'Inside the building' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
    'Drainage' => '<path d="M4 4v6a4 4 0 0 0 4 4h12"/><path d="M16 10l4 4-4 4"/>',
    'Commercial & industrial' => '<path d="M3 21V10l6 4V10l6 4V6h6v15z"/><path d="M7 17h2M13 17h2"/>',
];
?><!doctype html>
<html lang="en-KE">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Power Plumbing Services Ltd — Plumbers in Nairobi &amp; East Africa</title>
<meta name="description" content="Registered plumbers in Nairobi: boreholes, water tanks, pipework, drainage, solar water heating and fire-fighting systems. Plus tiling, gutters and domestic wiring. Call 0721 814 678.">
<link rel="canonical" href="<?= e($site) ?>/">
<meta property="og:type" content="website">
<meta property="og:title" content="Power Plumbing Services Ltd">
<meta property="og:description" content="Water from the source to every tap — boreholes, tanks, pipework and fittings across East Africa.">
<meta property="og:url" content="<?= e($site) ?>/">
<?php if ($photos): ?><meta property="og:image" content="<?= e($site . '/' . PP_GALLERY_URL . '/' . $photos[0]['filename']) ?>"><?php endif; ?>
<meta name="theme-color" content="#071A3D">
<link rel="icon" href="assets/img/logo-mark.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap">
<link rel="stylesheet" href="assets/css/style.css?v=2">
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

<header class="site-header" id="top">
  <div class="container header-row">
    <a href="#top" class="brand" aria-label="Power Plumbing Services Ltd, home">
      <img src="assets/img/logo-mark.svg" alt="" width="56" height="56">
      <span class="brand-text"><span class="brand-name">Power Plumbing</span><span class="brand-sub">Services Ltd</span></span>
    </a>
    <button class="menu-btn" aria-expanded="false" aria-controls="nav"><span></span><span class="sr">Menu</span></button>
    <nav id="nav" class="nav" aria-label="Main">
      <a href="#services">Services</a>
      <a href="#how">How we work</a>
      <a href="#work">Our work</a>
      <a href="#faqs">FAQs</a>
      <a href="#quote">Contact</a>
      <a class="nav-phone" href="<?= e($tel) ?>"><?= e(cfg('phone_display')) ?></a>
      <a class="btn btn-water" href="#quote">Request a quote</a>
    </nav>
  </div>
</header>

<main id="main">
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <h1>Water from the source to every tap in your home.</h1>
      <p class="lead">Registered plumbers for boreholes, storage tanks, pipework and fittings — based in Nairobi, working across East Africa. We also take care of the work around it: tiling, gutters, wiring and fixtures.</p>
      <div class="hero-cta">
        <a class="btn btn-water btn-lg" href="#quote">Request a quote</a>
        <a class="btn btn-outline btn-lg" href="<?= e($tel) ?>">Call <?= e(cfg('phone_display')) ?></a>
      </div>
      <a class="wa-link" href="<?= e($wa) ?>" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.1.1.6-.1 1.2z"/></svg>
        Or send us a WhatsApp
      </a>
      <ul class="hero-trust">
        <li>Registered plumbers</li>
        <li>Written quote before work starts</li>
        <li>Emergency call-outs</li>
      </ul>
    </div>
    <div class="hero-art">
      <?php include __DIR__ . '/includes/hero-art.php'; ?>
    </div>
  </div>
  <svg class="hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
    <path class="w1" d="M0 60c120 22 240 22 360 0s240-22 360 0 240 22 360 0 240-22 360 0 240 22 360 0 240-22 360 0v80H0z"/>
    <path class="w2" d="M0 76c120 18 240 18 360 0s240-18 360 0 240 18 360 0 240-18 360 0 240 18 360 0 240-18 360 0v80H0z"/>
  </svg>
</section>

<section class="services" id="services" aria-labelledby="services-title">
  <div class="container">
    <div class="section-head">
      <h2 id="services-title">What we do</h2>
      <p>Plumbing is most of our work, from a dripping tap to a full water system for an estate. When the job touches the rest of the building, the same team finishes it.</p>
    </div>
    <div class="services-grid">
      <div class="plumbing-panel">
        <h3 class="panel-title">Plumbing</h3>
        <div class="plumbing-groups">
          <?php foreach ($plumbing_groups as $group => $items): ?>
          <div class="pgroup">
            <h4><svg viewBox="0 0 24 24" aria-hidden="true"><?= $groupIcons[$group] ?></svg><?= e($group) ?></h4>
            <ul>
              <?php foreach ($items as [$name, $desc]): ?>
              <li><strong><?= e($name) ?></strong><span><?= e($desc) ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <aside class="other-panel" aria-labelledby="other-title">
        <h3 class="panel-title" id="other-title">Construction &amp; maintenance</h3>
        <p class="other-intro">One team for the plumbing and everything it touches — no chasing three different fundis.</p>
        <ul>
          <?php foreach ($other_services as [$name, $desc]): ?>
          <li><strong><?= e($name) ?></strong><span><?= e($desc) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>
  </div>
</section>

<section class="emergency" aria-label="Emergency plumbing">
  <div class="container emergency-row">
    <p><strong>Burst pipe, flooding or no water?</strong> Call us now and we will send the nearest plumber.</p>
    <a class="btn btn-red btn-lg" href="<?= e($tel) ?>">Call <?= e(cfg('phone_display')) ?></a>
  </div>
</section>

<section class="how" id="how" aria-labelledby="how-title">
  <div class="container">
    <div class="section-head">
      <h2 id="how-title">How a job runs</h2>
      <p>The same four steps whether it is one tap or a whole building.</p>
    </div>
    <ol class="steps">
      <?php foreach ($steps as $i => [$t, $d]): ?>
      <li><span class="step-n"><?= $i + 1 ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="coverage" aria-labelledby="coverage-title">
  <div class="container coverage-grid">
    <div>
      <h2 id="coverage-title">Based in Nairobi, working across East Africa</h2>
      <p>Daily call-outs across Nairobi and the surrounding counties. Larger projects — estates, schools, hotels, factories — anywhere in the region.</p>
    </div>
    <ul class="countries">
      <li class="home-base">Kenya <span>Head office, Nairobi</span></li>
      <li>Uganda</li>
      <li>Tanzania</li>
      <li>Rwanda</li>
      <li>South Sudan</li>
      <li>Burundi</li>
    </ul>
  </div>
</section>

<?php if (count($onJob) >= 4): ?>
<section class="onjob" aria-labelledby="onjob-title">
  <div class="container onjob-head">
    <h2 id="onjob-title">Our team on the job</h2>
    <p>Fitting, testing and finishing — photos from sites we are working on.</p>
  </div>
  <?php $half = (int)ceil(count($onJob) / 2); $rowsOJ = [array_slice($onJob, 0, $half), array_slice($onJob, $half) ?: array_slice($onJob, 0, $half)]; ?>
  <div class="reel" tabindex="0" aria-label="Scrolling photos of our team at work. Hover or focus to pause.">
    <?php foreach ($rowsOJ as $ri => $row): ?>
    <ul class="reel-row <?= $ri ? 'rev' : '' ?>">
      <?php foreach ([$row, $row] as $copy => $set): foreach ($set as $p): ?>
      <li <?= $copy ? 'aria-hidden="true"' : '' ?>>
        <img src="<?= PP_GALLERY_URL . '/' . e($p['thumb']) ?>" alt="<?= $copy ? '' : e($p['caption'] ?: 'Our team at work') ?>" loading="lazy" width="640" height="480">
        <?php if ($p['caption']): ?><span><?= e($p['caption']) ?></span><?php endif; ?>
      </li>
      <?php endforeach; endforeach; ?>
    </ul>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="work" id="work" aria-labelledby="work-title">
  <div class="container">
    <div class="section-head">
      <h2 id="work-title">Recent work</h2>
      <p>Photos<?= $videoCount ? ' and videos' : '' ?> from our own jobs. Tap any one to see it larger.</p>
    </div>
    <?php if ($photos): ?>
      <?php if (count($usedCats) > 1): ?>
      <div class="filter" role="group" aria-label="Filter photos">
        <button type="button" aria-pressed="true" data-filter="all">All</button>
        <?php if ($videoCount): ?><button type="button" aria-pressed="false" data-filter="video">Videos</button><?php endif; ?>
        <?php foreach ($usedCats as $c): ?>
        <button type="button" aria-pressed="false" data-filter="<?= e($c) ?>"><?= e($cats[$c] ?? ucfirst($c)) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <ul class="gallery">
        <?php foreach ($photos as $p): $isVid = ($p['media'] ?? 'image') === 'video'; ?>
        <li data-cat="<?= e($p['category']) ?>" data-media="<?= $isVid ? 'video' : 'image' ?>">
          <a href="<?= PP_GALLERY_URL . '/' . e($p['filename']) ?>" data-caption="<?= e($p['caption']) ?>" data-media="<?= $isVid ? 'video' : 'image' ?>">
            <?php if ($isVid && !$p['thumb']): ?>
              <video src="<?= PP_GALLERY_URL . '/' . e($p['filename']) ?>#t=1" muted playsinline preload="metadata" aria-hidden="true"></video>
            <?php else: ?>
              <img src="<?= PP_GALLERY_URL . '/' . e($p['thumb']) ?>" alt="<?= e($p['caption'] ?: 'Power Plumbing project photo') ?>" loading="lazy" width="480" height="640">
            <?php endif; ?>
            <?php if ($isVid): ?><span class="play" aria-hidden="true"></span><span class="sr">Video:</span><?php endif; ?>
            <?php if ($p['caption']): ?><span class="cap"><?= e($p['caption']) ?></span><?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="more-wrap"><button type="button" class="btn btn-ghost-navy more" hidden>Show more</button></div>
    <?php else: ?>
      <p class="empty">Project photos are on their way.</p>
    <?php endif; ?>
  </div>
</section>

<section class="faqs" id="faqs" aria-labelledby="faqs-title">
  <div class="container faqs-grid">
    <div class="section-head">
      <h2 id="faqs-title">Questions people ask</h2>
      <p>Not answered here? <a href="<?= e($wa) ?>" target="_blank" rel="noopener">Ask us on WhatsApp</a>.</p>
    </div>
    <div class="faq-list">
      <?php foreach ($faqs as $i => [$q, $a]): ?>
      <details <?= $i === 0 ? 'open' : '' ?>>
        <summary><?= e($q) ?></summary>
        <p><?= e($a) ?></p>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="quote" id="quote" aria-labelledby="quote-title">
  <div class="container quote-grid">
    <div class="contact-info">
      <h2 id="quote-title">Contact us</h2>
      <p>Talk to us on WhatsApp, call, or send the form and we will get back to you.</p>
      <a class="wa-big" href="<?= e($wa) ?>" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.1.1.6-.1 1.2z"/></svg>
        <span><strong>Talk to us on WhatsApp</strong><span><?= e($waDisplay) ?></span></span>
      </a>
      <dl>
        <div><dt>Phone</dt><dd><a href="<?= e($tel) ?>"><?= e(cfg('phone_display')) ?></a></dd></div>
        <div><dt>WhatsApp</dt><dd><a href="<?= e($waPlain) ?>" target="_blank" rel="noopener"><?= e($waDisplay) ?></a></dd></div>
        <div><dt>Email</dt><dd><a href="mailto:<?= e(cfg('email')) ?>"><?= e(cfg('email')) ?></a></dd></div>
        <div><dt>Post</dt><dd><?= e(cfg('po_box')) ?></dd></div>
        <div><dt>Hours</dt><dd>Mon–Sat, 7:30am–6:00pm<br>Emergencies any time</dd></div>
      </dl>
    </div>

    <form class="quote-form" method="post" action="quote.php" novalidate>
      <?php if (isset($qMessages[$qStatus])): ?>
        <p class="form-note <?= $qMessages[$qStatus][0] ?>" role="status"><?= e($qMessages[$qStatus][1]) ?></p>
      <?php endif; ?>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="fields">
        <label>Your name<input name="name" required maxlength="120" autocomplete="name" value="<?= $o('name') ?>"></label>
        <label>Phone<input name="phone" type="tel" required maxlength="40" autocomplete="tel" placeholder="07xx xxx xxx" value="<?= $o('phone') ?>"></label>
        <label>Email <span class="opt">(optional)</span><input name="email" type="email" maxlength="160" autocomplete="email" value="<?= $o('email') ?>"></label>
        <label>Location<input name="location" maxlength="160" placeholder="Estate or town" value="<?= $o('location') ?>"></label>
        <label class="wide">What do you need?
          <select name="service">
            <option value="">Choose a service</option>
            <?php foreach ($serviceOptions as $s): ?>
            <option <?= ($old['service'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
            <option <?= ($old['service'] ?? '') === 'Something else' ? 'selected' : '' ?>>Something else</option>
          </select>
        </label>
        <label class="wide">Details <span class="opt">(optional)</span><textarea name="message" rows="4" maxlength="3000" placeholder="What's the problem or project? Size of tank, number of bathrooms, when you need it…"><?= $o('message') ?></textarea></label>
      </div>
      <div class="form-actions">
        <button class="btn btn-navy btn-lg" type="submit">Send request</button>
        <button class="btn btn-wa btn-lg" type="button" data-wa="<?= e(cfg('whatsapp')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.1.1.6-.1 1.2z"/></svg> Send on WhatsApp instead</button>
      </div>
      <p class="form-small">We use your details only to reply to this request.</p>
    </form>
  </div>
</section>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <img class="footer-logo" src="assets/img/logo-mark.svg" alt="" width="48" height="48">
      <p class="footer-name">Power Plumbing Services Ltd</p>
      <p>Registered plumbers · Drainage · Compressed air · Fire fighting · Water reticulation · Steam · Solar heating &amp; renovation</p>
    </div>
    <nav aria-label="Footer">
      <a href="#services">Services</a><a href="#how">How we work</a><a href="#work">Our work</a><a href="#faqs">FAQs</a><a href="#quote">Contact</a>
    </nav>
    <div>
      <p><a href="<?= e($tel) ?>"><?= e(cfg('phone_display')) ?></a><br><a href="mailto:<?= e(cfg('email')) ?>"><?= e(cfg('email')) ?></a><br><?= e(cfg('po_box')) ?></p>
    </div>
  </div>
  <div class="container footer-base">
    <span>© <?= date('Y') ?> Power Plumbing Services Ltd. All rights reserved.</span>
    <span>Developed by <a href="https://apopsassurance.com" target="_blank" rel="noopener">apopsassurance.com</a></span>
  </div>
</footer>

<a class="wa-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2.1 1-2.4c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.1.1.6-.1 1.2z"/></svg>
</a>

<dialog class="lightbox" aria-label="Photo viewer">
  <button class="lb-close" type="button" aria-label="Close">×</button>
  <img alt="">
  <video controls playsinline muted hidden></video>
  <p class="lb-cap"></p>
</dialog>

<script src="assets/js/main.js?v=2" defer></script>
</body>
</html>
