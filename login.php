<?php
require_once 'includes/init.php';

// Zaten giriş yapmışsa ana sayfaya yönlendir
if ($auth->isLoggedIn()) {
    redirect();
}

$errors = [];
$loginAttempt = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginAttempt = true;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // CSRF kontrolü
    if (!verifyCSRFToken($csrfToken)) {
        $errors[] = 'Güvenlik kontrolü başarısız.';
    }

    // Giriş bilgileri kontrolü
    if (empty($username) || empty($password)) {
        $errors[] = 'Kullanıcı adı ve şifre gerekli.';
    }

    // Hata yoksa giriş yap
    if (empty($errors)) {
        if ($auth->login($username, $password)) {
            setToast('Başarıyla giriş yaptınız.', 'success');
            redirect();
        } else {
            $errors[] = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}

$pageTitle = 'Giriş Yap';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> - <?php echo h(APP_NAME); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo url('assets/css/style.css'); ?>" rel="stylesheet">
    
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .login-header {
            background: transparent;
            border: none;
            text-align: center;
            padding: 2rem 2rem 1rem;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), #6f42c1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }
        
        .login-body {
            padding: 1rem 2rem 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .form-control:focus {
            background: white;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="login-container d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card login-card">
                        <div class="card-header login-header">
                            <div class="login-logo">
                                <i class="fas fa-camera"></i>
                            </div>
                            <h3 class="mb-2 fw-bold"><?php echo h(APP_NAME); ?></h3>
                            <p class="text-muted mb-0">Fotoğraf Albümü Yönetim Sistemi</p>
                            <p class="text-muted small">Güvenli giriş yapın</p>
                        </div>
                        
                        <div class="card-body login-body">
                            <!-- Hatalar -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo h($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Giriş Formu -->
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="form-floating">
                                    <input type="text" 
                                           class="form-control <?php echo $loginAttempt && empty($username) ? 'is-invalid' : ''; ?>" 
                                           id="username" 
                                           name="username" 
                                           placeholder="Kullanıcı adı veya e-posta"
                                           value="<?php echo h($username ?? ''); ?>"
                                           required>
                                    <label for="username">
                                        <i class="fas fa-user me-2"></i>Kullanıcı Adı veya E-posta
                                    </label>
                                    <div class="invalid-feedback">
                                        Kullanıcı adı gerekli.
                                    </div>
                                </div>
                                
                                <div class="form-floating">
                                    <input type="password" 
                                           class="form-control <?php echo $loginAttempt && empty($password) ? 'is-invalid' : ''; ?>" 
                                           id="password" 
                                           name="password" 
                                           placeholder="Şifre"
                                           required>
                                    <label for="password">
                                        <i class="fas fa-lock me-2"></i>Şifre
                                    </label>
                                    <div class="invalid-feedback">
                                        Şifre gerekli.
                                    </div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Beni hatırla
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Versiyon Bilgisi -->
                    <div class="text-center mt-3">
                        <small class="text-white-50">
                            <?php echo h(APP_NAME); ?> v<?php echo h(APP_VERSION); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast container için -->
    <?php $toast = getToast(); ?>
    <?php if ($toast): ?>
        <script data-toast type="application/json">
            <?php echo json_encode($toast); ?>
        </script>
    <?php endif; ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo url('assets/js/main.js'); ?>"></script>
    
    <script>
        // Form validasyonu
        (function() {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Username alanına focus
            document.getElementById('username').focus();
        })();
    </script>
</body>
</html> 