<?php
/** Adds columns introduced after the first release, for sites installed earlier. */
function pp_migrate(PDO $pdo): void
{
    foreach (pp_schema(cfg('db')['driver']) as $sql) $pdo->exec($sql); // creates any new tables
    $cols = [];
    try {
        $row = $pdo->query('SELECT * FROM photos LIMIT 1')->fetch();
        if ($row) {
            $cols = array_keys($row);
        } else {
            $st = $pdo->query('SELECT * FROM photos LIMIT 0');
            for ($i = 0; $i < $st->columnCount(); $i++) $cols[] = $st->getColumnMeta($i)['name'];
        }
    } catch (Throwable $e) {
        return;
    }
    if (!in_array('media', $cols, true)) $pdo->exec("ALTER TABLE photos ADD COLUMN media VARCHAR(10) NOT NULL DEFAULT 'image'");
    if (!in_array('on_job', $cols, true)) $pdo->exec('ALTER TABLE photos ADD COLUMN on_job INT NOT NULL DEFAULT 0');
}

/** Inserts starter gallery items whose files exist and are not already listed. Returns count added. */
function pp_seed_gallery(PDO $pdo): int
{
    $seed = require __DIR__ . '/seed.php';
    $have = $pdo->query('SELECT filename FROM photos')->fetchAll(PDO::FETCH_COLUMN);
    $ins = $pdo->prepare('INSERT INTO photos (filename, thumb, caption, category, media, on_job, sort_order, created_at) VALUES (?,?,?,?,?,?,?,?)');
    $added = 0;
    foreach ($seed as $i => [$base, $cap, $cat, $media, $job]) {
        $file = $base . ($media === 'video' ? '.mp4' : '.jpg');
        if (in_array($file, $have, true) || !is_file(PP_GALLERY_DIR . '/' . $file)) continue;
        $ins->execute([$file, $base . '-t.jpg', $cap, $cat, $media, $job, 10 + $i * 10, now()]);
        $added++;
    }
    return $added;
}

/** Returns CREATE TABLE statements for the configured driver. */
function pp_schema(string $driver): array
{
    $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $tail = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id $id,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            last_login DATETIME NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS leads (
            id $id,
            name VARCHAR(120) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            email VARCHAR(160) NULL,
            location VARCHAR(160) NULL,
            service VARCHAR(80) NULL,
            message TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS photos (
            id $id,
            filename VARCHAR(120) NOT NULL,
            thumb VARCHAR(120) NOT NULL,
            caption VARCHAR(160) NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'plumbing',
            media VARCHAR(10) NOT NULL DEFAULT 'image',
            on_job INT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS replies (
            id $id,
            lead_id INT NOT NULL,
            to_email VARCHAR(160) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            ok INT NOT NULL DEFAULT 1,
            error VARCHAR(255) NULL,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS documents (
            id $id,
            type VARCHAR(12) NOT NULL DEFAULT 'quote',
            number VARCHAR(30) NOT NULL,
            lead_id INT NULL,
            source_id INT NULL,
            title VARCHAR(200) NULL,
            client_name VARCHAR(120) NULL,
            client_company VARCHAR(160) NULL,
            client_phone VARCHAR(40) NULL,
            client_email VARCHAR(160) NULL,
            client_address VARCHAR(255) NULL,
            site VARCHAR(160) NULL,
            items TEXT NULL,
            sections TEXT NULL,
            vat INT NOT NULL DEFAULT 1,
            discount DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            terms TEXT NULL,
            valid_days INT NOT NULL DEFAULT 30,
            due_days INT NOT NULL DEFAULT 14,
            status VARCHAR(12) NOT NULL DEFAULT 'draft',
            token VARCHAR(64) NOT NULL,
            doc_date DATE NOT NULL,
            accepted_name VARCHAR(120) NULL,
            accepted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS payments (
            id $id,
            document_id INT NOT NULL,
            receipt_no VARCHAR(30) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            method VARCHAR(20) NOT NULL,
            reference VARCHAR(80) NULL,
            paid_on DATE NOT NULL,
            note VARCHAR(255) NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME NOT NULL
        )$tail",

        "CREATE TABLE IF NOT EXISTS login_attempts (
            id $id,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL
        )$tail",
    ];
}
