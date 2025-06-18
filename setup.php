<?php
session_start();

// Config dosyası varsa setup'ı engelle
if (file_exists('config.php')) {
    header('Location: index.php');
    exit('Kurulum zaten tamamlanmış. config.php dosyası mevcut.');
}

$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$errors = [];
$success = [];

// Veritabanı bağlantısı test et
function testDatabaseConnection($host, $username, $password, $database): bool {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Tabloları oluştur
function createTables($pdo): bool {
    try {
        // Users tablosu
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Albums tablosu
        $pdo->exec("CREATE TABLE IF NOT EXISTS albums (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            parent_id INT NULL,
            path VARCHAR(500) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES albums(id) ON DELETE CASCADE
        )");

        // Photos tablosu
        $pdo->exec("CREATE TABLE IF NOT EXISTS photos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            original_name VARCHAR(255) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            album_id INT NOT NULL,
            file_size BIGINT,
            file_type VARCHAR(50),
            width INT DEFAULT 0,
            height INT DEFAULT 0,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE
        )");

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Config dosyası oluştur
function createConfigFile($host, $username, $password, $database): bool {
    $configContent = "<?php
// Fotser Konfigürasyon Dosyası
define('DB_HOST', '$host');
define('DB_USERNAME', '$username');
define('DB_PASSWORD', '$password');
define('DB_NAME', '$database');

// Güvenlik anahtarı
define('SECRET_KEY', '" . bin2hex(random_bytes(32)) . "');

// Upload dizini
define('UPLOAD_DIR', 'uploads/');

// Uygulama ayarları
define('APP_NAME', 'Fotser');
define('APP_VERSION', '1.0.0');
?>";

    return file_put_contents('config.php', $configContent) !== false;
}

// Admin kullanıcı oluştur
function createAdminUser($pdo, $username, $password, $email): bool {
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')");
        return $stmt->execute([$username, $hashedPassword, $email]);
    } catch (PDOException $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Veritabanı bilgileri kontrolü
        $host = trim($_POST['db_host'] ?? '');
        $username = trim($_POST['db_username'] ?? '');
        $password = $_POST['db_password'] ?? '';
        $database = trim($_POST['db_name'] ?? '');

        if (empty($host) || empty($username) || empty($database)) {
            $errors[] = 'Tüm veritabanı bilgileri gerekli.';
        } else {
            if (testDatabaseConnection($host, $username, $password, $database)) {
                $_SESSION['db_config'] = compact('host', 'username', 'password', 'database');
                $step = 2;
                $success[] = 'Veritabanı bağlantısı başarılı!';
            } else {
                $errors[] = 'Veritabanı bağlantısı başarısız. Bilgileri kontrol edin.';
            }
        }
    } elseif ($step === 2) {
        // Admin kullanıcı oluşturma
        $adminUsername = trim($_POST['admin_username'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($adminUsername) || empty($adminPassword) || empty($adminEmail)) {
            $errors[] = 'Tüm admin bilgileri gerekli.';
        } elseif ($adminPassword !== $confirmPassword) {
            $errors[] = 'Şifreler eşleşmiyor.';
        } elseif (strlen($adminPassword) < 6) {
            $errors[] = 'Şifre en az 6 karakter olmalı.';
        } else {
            if (isset($_SESSION['db_config'])) {
                $dbConfig = $_SESSION['db_config'];
                try {
                    $pdo = new PDO(
                        "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset=utf8mb4",
                        $dbConfig['username'],
                        $dbConfig['password']
                    );
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Tabloları oluştur
                    if (createTables($pdo)) {
                        // Admin kullanıcı oluştur
                        if (createAdminUser($pdo, $adminUsername, $adminPassword, $adminEmail)) {
                            // Config dosyası oluştur
                            if (createConfigFile($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['database'])) {
                                // Upload dizini oluştur
                                if (!is_dir('uploads')) {
                                    mkdir('uploads', 0755, true);
                                }
                                
                                unset($_SESSION['db_config']);
                                $step = 3;
                                $success[] = 'Kurulum başarıyla tamamlandı!';
                            } else {
                                $errors[] = 'Config dosyası oluşturulamadı.';
                            }
                        } else {
                            $errors[] = 'Admin kullanıcı oluşturulamadı.';
                        }
                    } else {
                        $errors[] = 'Veritabanı tabloları oluşturulamadı.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Veritabanı bilgileri bulunamadı. Lütfen tekrar başlayın.';
                $step = 1;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fotser Kurulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h2><i class="fas fa-camera"></i> Fotser Kurulum</h2>
                        <p class="mb-0">Albüm ve Fotoğraf Yönetim Sistemi</p>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="progress mb-4">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo ($step / 3) * 100; ?>%"></div>
                        </div>

                        <!-- Errors -->
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Success -->
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <ul class="mb-0">
                                    <?php foreach ($success as $msg): ?>
                                        <li><?php echo htmlspecialchars($msg); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($step === 1): ?>
                            <!-- Adım 1: Veritabanı Bilgileri -->
                            <h4 class="mb-3">Adım 1: Veritabanı Bilgileri</h4>
                            <form method="POST">
                                <input type="hidden" name="step" value="1">
                                
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Veritabanı Sunucusu</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" 
                                           value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_username" class="form-label">Kullanıcı Adı</label>
                                    <input type="text" class="form-control" id="db_username" name="db_username" 
                                           value="<?php echo htmlspecialchars($_POST['db_username'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_password" class="form-label">Şifre</label>
                                    <input type="password" class="form-control" id="db_password" name="db_password">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Veritabanı Adı</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" 
                                           value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-arrow-right"></i> İleri
                                </button>
                            </form>

                        <?php elseif ($step === 2): ?>
                            <!-- Adım 2: Admin Kullanıcı -->
                            <h4 class="mb-3">Adım 2: Admin Kullanıcı Oluştur</h4>
                            <form method="POST">
                                <input type="hidden" name="step" value="2">
                                
                                <div class="mb-3">
                                    <label for="admin_username" class="form-label">Admin Kullanıcı Adı</label>
                                    <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                           value="<?php echo htmlspecialchars($_POST['admin_username'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_email" class="form-label">Admin E-posta</label>
                                    <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                           value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admin_password" class="form-label">Admin Şifre</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    <div class="form-text">En az 6 karakter olmalı.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Şifre Tekrar</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i> Kurulumu Tamamla
                                </button>
                            </form>

                        <?php elseif ($step === 3): ?>
                            <!-- Adım 3: Tamamlandı -->
                            <div class="text-center">
                                <h4 class="text-success mb-3">
                                    <i class="fas fa-check-circle"></i> Kurulum Tamamlandı!
                                </h4>
                                <p class="mb-4">Fotser başarıyla kuruldu. Artık sistemi kullanmaya başlayabilirsiniz.</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-home"></i> Ana Sayfaya Git
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 