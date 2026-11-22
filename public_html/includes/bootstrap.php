<?php
declare(strict_types=1);

$CFG = require __DIR__ . '/config.php';

if (!empty($CFG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
date_default_timezone_set('Africa/Nairobi');

define('PP_ROOT', dirname(__DIR__));
define('PP_GALLERY_DIR', PP_ROOT . '/uploads/gallery');
define('PP_GALLERY_URL', 'uploads/gallery');
define('PP_INSTALL_LOCK', PP_ROOT . '/data/installed.lock');

/* ---------- session ---------- */
function pp_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('pp_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => pp_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/* ---------- helpers ---------- */
function cfg(string $key, $default = null)
{
    global $CFG;
    return $CFG[$key] ?? $default;
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- database ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $d = cfg('db');
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if ($d['driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $d['sqlite'], null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['name']);
        $pdo = new PDO($dsn, $d['user'], $d['pass'], $opts);
    }
    return $pdo;
}

function db_ready(): bool
{
    return is_file(PP_INSTALL_LOCK);
}

/* ---------- content ---------- */
function gallery_categories(): array
{
    return [
        'plumbing'     => 'Plumbing & pipework',
        'pumps'        => 'Pumps & water treatment',
        'solar'        => 'Solar water heating',
        'bathrooms'    => 'Bathrooms & showers',
        'kitchens'     => 'Kitchens & sinks',
        'commercial'   => 'Commercial kitchens & salons',
        'external'     => 'Drainage & external works',
        'tanks'        => 'Tanks & boreholes',
        'construction' => 'Joinery & finishing',
        'electrical'   => 'Electrical & lighting',
    ];
}

/** All gallery items (photos and videos) in display order. */
function gallery_photos(int $limit = 300): array
{
    if (!db_ready()) return [];
    try {
        return db()->query('SELECT * FROM photos ORDER BY sort_order ASC, id DESC LIMIT ' . (int)$limit)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Photos ticked "On the job" in admin, for the scrolling strip. */
function on_job_photos(): array
{
    return array_values(array_filter(gallery_photos(), fn($p) => (int)($p['on_job'] ?? 0) === 1 && ($p['media'] ?? 'image') === 'image'));
}

/**
 * Validate an uploaded MP4 and move it into the gallery. Returns the stored file name.
 * Hosting cannot re-encode video, so compress clips on the phone or with HandBrake before uploading.
 */
function process_video(string $src): string
{
    $max = (int)cfg('max_video_mb', 60) * 1024 * 1024;
    if (!is_file($src) || filesize($src) > $max) {
        throw new RuntimeException('Each video must be under ' . cfg('max_video_mb', 60) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($src);
    $head = (string)file_get_contents($src, false, null, 4, 4);
    if (!in_array($mime, ['video/mp4', 'video/quicktime'], true) || $head !== 'ftyp') {
        throw new RuntimeException('Only MP4 videos are accepted.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.mp4';
    if (!move_uploaded_file($src, PP_GALLERY_DIR . '/' . $name)) {
        throw new RuntimeException('The video could not be saved.');
    }
    return $name;
}

/* ---------- admin ---------- */
function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) redirect('index.php');
    // idle timeout: 30 minutes
    if (time() - ($_SESSION['admin_seen'] ?? 0) > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('You were signed out after 30 minutes of inactivity.', 'warn');
        redirect('index.php');
    }
    $_SESSION['admin_seen'] = time();
}

/* ---------- image handling ---------- */
/**
 * Validate and re-encode an uploaded image. Returns [filename, thumbname].
 * Re-encoding with GD strips EXIF/GPS data and any payload hidden in the file.
 */
function process_image(string $src): array
{
    $max = (int)cfg('max_upload_mb', 8) * 1024 * 1024;
    if (!is_file($src) || filesize($src) > $max) {
        throw new RuntimeException('Each photo must be under ' . cfg('max_upload_mb', 8) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($src);
    $info = @getimagesize($src);
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Only JPG, PNG or WebP photos are accepted.');
    }
    [$w, $h] = $info;
    if ($w < 200 || $h < 200 || $w * $h > 40_000_000) {
        throw new RuntimeException('That photo is too small or too large to use.');
    }

    $img = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($src),
        'image/png'  => imagecreatefrompng($src),
        'image/webp' => imagecreatefromwebp($src),
    };
    if (!$img) throw new RuntimeException('That photo could not be read.');

    // Respect phone camera orientation
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3) $img = imagerotate($img, 180, 0);
        if ($o === 6) $img = imagerotate($img, -90, 0);
        if ($o === 8) $img = imagerotate($img, 90, 0);
    }

    $base = date('Ymd') . '-' . bin2hex(random_bytes(6));
    $full = $base . '.jpg';
    $thumb = $base . '-t.jpg';

    save_resized($img, PP_GALLERY_DIR . '/' . $full, 1600, 82);
    save_resized($img, PP_GALLERY_DIR . '/' . $thumb, 640, 78);
    imagedestroy($img);

    return [$full, $thumb];
}

function save_resized(GdImage $img, string $dest, int $maxSide, int $quality): void
{
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxSide / max($w, $h));
    $nw = (int)round($w * $scale);
    $nh = (int)round($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($out, true);
    imagejpeg($out, $dest, $quality);
    imagedestroy($out);
}
