<?php
/** Top navigation bar shared by admin pages. $active: leads | docs | photos | account */
function admin_bar(string $active, int $newLeads = 0): void
{
    $tabs = [
        'leads'   => ['dashboard.php?tab=leads', 'Quote requests'],
        'docs'    => ['documents.php', 'Quotes & proposals'],
        'photos'  => ['dashboard.php?tab=photos', 'Gallery'],
        'account' => ['dashboard.php?tab=account', 'Account'],
    ];
    ?>
<header class="bar no-print">
  <a href="dashboard.php" class="bar-brand"><img src="../assets/img/logo-mark.svg" alt=""> Admin</a>
  <nav class="tabs" aria-label="Admin sections">
    <?php foreach ($tabs as $key => [$href, $label]): ?>
      <a href="<?= $href ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= $label ?><?php if ($key === 'leads' && $newLeads): ?> <span class="pill"><?= $newLeads ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="bar-end">
    <a href="../" target="_blank" rel="noopener">View site</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button class="link">Sign out</button></form>
  </div>
</header>
<?php
}

function admin_new_leads(PDO $pdo): int
{
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
