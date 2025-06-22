<?php
// Fotser Initialization File

// Config dosyasını yükle
$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) {
    // Config dosyası yoksa setup.php'ye yönlendir
    $currentDir = dirname($_SERVER['SCRIPT_NAME']);
    // Eğer fotser klasörü içindeysek, üst dizini al
    if (basename($currentDir) === 'includes') {
        $currentDir = dirname($currentDir);
    }
    $setupUrl = rtrim($currentDir, '/') . '/setup.php';
    header('Location: ' . $setupUrl);
    exit('Kurulum gerekli. setup.php dosyasını çalıştırın.');
}

require_once $configPath;

// Error reporting (development için)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ana dizin tanımlaması
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// Sınıfları yükle
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Album.php';
require_once __DIR__ . '/Photo.php';

// Global değişkenler
$db = Database::getInstance();
$auth = new Auth($db);
$album = new Album($db);
$photo = new Photo($db);

// Upload klasörünü oluştur
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Yardımcı fonksiyonlar

/**
 * Toast mesajı ayarla
 */
function setToast(string $message, string $type = 'info'): void {
    $_SESSION['toast'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Toast mesajını göster ve temizle
 */
function getToast(): ?array {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return $toast;
    }
    return null;
}

/**
 * HTML çıktısını güvenli hale getir (h fonksiyonu esc'nin kısaltması)
 */
function h(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF token oluştur (büyük harfli versiyon)
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token doğrula (büyük harfli versiyon)
 */
function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Tam URL oluştur (redirect için)
 */
function fullUrl(string $path = ''): string {
    $baseUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * Güvenli yönlendirme
 */
function redirect(string $url = ''): void {
    $redirectUrl = fullUrl($url);
    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * HTML çıktısını güvenli hale getir
 */
function esc(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Dosya boyutunu okunabilir formata çevir
 */
function formatFileSize(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = 0;
    
    while ($bytes >= 1024 && $factor < count($units) - 1) {
        $bytes /= 1024;
        $factor++;
    }
    
    return round($bytes, 2) . ' ' . $units[$factor];
}

/**
 * Tarihi Türkçe formatta göster
 */
function formatDate(string $date): string {
    $timestamp = strtotime($date);
    return date('d.m.Y H:i', $timestamp);
}

/**
 * Göreli zamanı Türkçe olarak göster
 */
function timeAgo(string $date): string {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Az önce';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' dakika önce';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' saat önce';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' gün önce';
    } else {
        return formatDate($date);
    }
}

/**
 * URL oluştur (sadece path döndür)
 */
function url(string $path = ''): string {
    return '/' . ltrim($path, '/');
}

/**
 * Asset URL oluştur
 */
function asset(string $path): string {
    return '/assets/' . ltrim($path, '/');
}

/**
 * Upload URL oluştur
 */
function uploadUrl(string $path): string {
    return '/uploads/' . ltrim($path, '/');
}

/**
 * Mevcut sayfa URL'sini al
 */
function currentUrl(): string {
    return $_SERVER['REQUEST_URI'];
}

/**
 * Aktif menü öğesini kontrol et
 */
function isActive(string $path): bool {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $currentPath = rtrim($currentPath, '/');
    $checkPath = rtrim($path, '/');
    
    if ($checkPath === '') {
        return $currentPath === '' || $currentPath === '/index.php';
    }
    
    return strpos($currentPath, $checkPath) === 0;
}

/**
 * Debug fonksiyonu
 */
function dd($data): void {
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}

/**
 * Sayıyı Türkçe formatla
 */
function formatNumber(int $number): string {
    return number_format($number, 0, ',', '.');
}

/**
 * String'i kırp
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * JSON yanıt gönder
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Başarı mesajı ile JSON yanıt
 */
function jsonSuccess(string $message, array $data = []): void {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Hata mesajı ile JSON yanıt
 */
function jsonError(string $message, int $statusCode = 400): void {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

/**
 * Kullanıcı giriş yapmış mı kontrol et
 */
function isLoggedIn(): bool {
    global $auth;
    return $auth->isLoggedIn();
}

/**
 * Kullanıcı bilgilerini al
 */
function getCurrentUser(): ?array {
    global $auth;
    return $auth->getCurrentUser();
}

/**
 * Giriş yapması gereken sayfalarda kullan
 */
function requireLogin(): void {
    global $auth;
    $auth->requireLogin();
}

/**
 * Önbelleği temizle
 * @param string|null $path Belirli bir klasör/dosya yolu (null ise tüm önbellek temizlenir)
 * @return bool Temizleme işlemi başarılı oldu mu?
 */
function clearCache(?string $path = null): bool {
    // Tarayıcı önbelleğini temizlemek için HTTP başlıkları
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Belirli bir dosya veya klasör için önbellek temizleme
    if ($path !== null && file_exists($path)) {
        // Dosya ise
        if (is_file($path)) {
            // Dosyanın son değiştirilme zamanını güncelle
            return touch($path);
        } 
        // Klasör ise
        else if (is_dir($path)) {
            // Klasördeki tüm dosyaların son değiştirilme zamanını güncelle
            $success = true;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    if (!touch($item->getPathname())) {
                        $success = false;
                    }
                }
            }
            return $success;
        }
    }
    
    return true;
}
?> 