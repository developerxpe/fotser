<?php
require_once 'includes/init.php';

// Giriş kontrolü
$auth->requireLogin();

// Çıkış işlemi
if (isset($_GET['logout'])) {
    $auth->logout();
    setToast('Başarıyla çıkış yapıldı.', 'success');
    redirect();
}

$pageTitle = 'Ana Sayfa';
$currentAlbumId = isset($_GET['album']) ? (int)$_GET['album'] : null;
$currentAlbum = null;
$breadcrumbs = [];

// Mevcut albüm bilgilerini al
if ($currentAlbumId) {
    $currentAlbum = $album->getById($currentAlbumId);
    if (!$currentAlbum) {
        setToast('Albüm bulunamadı.', 'error');
        redirect();
    }
    $pageTitle = $currentAlbum['name'];
    $breadcrumbs = $album->getBreadcrumbs($currentAlbumId);
}

// Albümleri al (ana albümler veya mevcut albümün alt albümleri)
$albums = $currentAlbumId ? $album->getChildren($currentAlbumId) : $album->getRootAlbums();

// Fotoğrafları al (sadece mevcut albümde varsa)
$photos = $currentAlbumId ? $photo->getByAlbum($currentAlbumId) : [];

// Tüm albümleri al (taşıma için)
$allAlbums = $album->getAll();

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        setToast('Güvenlik kontrolü başarısız.', 'error');
        redirect();
    }

    switch ($action) {
        case 'create_album':
            $albumName = trim($_POST['album_name'] ?? '');
            if (!empty($albumName)) {
                $result = $album->create($albumName, $currentAlbumId);
                setToast($result['message'], $result['success'] ? 'success' : 'error');
            } else {
                setToast('Albüm adı gerekli.', 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'update_album':
            $albumId = (int)($_POST['album_id'] ?? 0);
            $albumName = trim($_POST['album_name'] ?? '');
            if ($albumId && !empty($albumName)) {
                $result = $album->update($albumId, $albumName);
                setToast($result['message'], $result['success'] ? 'success' : 'error');
            } else {
                setToast('Albüm adı gerekli.', 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'delete_album':
            $albumId = (int)($_POST['album_id'] ?? 0);
            if ($albumId) {
                $result = $album->delete($albumId);
                setToast($result['message'], $result['success'] ? 'success' : 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'rename_photo':
            $photoId = (int)($_POST['photo_id'] ?? 0);
            $newName = trim($_POST['new_name'] ?? '');
            if ($photoId && !empty($newName)) {
                // Fotoğraf bilgilerini al
                $photoData = $photo->getById($photoId);
                if ($photoData) {
                    // Orijinal dosya uzantısını koru
                    $originalExt = pathinfo($photoData['original_name'], PATHINFO_EXTENSION);
                    
                    // Yeni adı uzantı ile birleştir
                    $fullNewName = $newName . '.' . $originalExt;
                    
                    $result = $photo->rename($photoId, $fullNewName);
                    setToast($result['message'], $result['success'] ? 'success' : 'error');
                } else {
                    setToast('Fotoğraf bulunamadı.', 'error');
                }
            } else {
                setToast('Fotoğraf adı gerekli.', 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'move_photo':
            $photoId = (int)($_POST['photo_id'] ?? 0);
            $targetAlbumId = (int)($_POST['target_album_id'] ?? 0);
            if ($photoId && $targetAlbumId) {
                $result = $photo->moveToAlbum($photoId, $targetAlbumId);
                setToast($result['message'], $result['success'] ? 'success' : 'error');
            } else {
                setToast('Geçersiz taşıma işlemi.', 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'delete_photo':
            $photoId = (int)($_POST['photo_id'] ?? 0);
            if ($photoId) {
                $result = $photo->delete($photoId);
                setToast($result['message'], $result['success'] ? 'success' : 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'upload_photos':
            if ($currentAlbumId && isset($_FILES['photos'])) {
                $result = $photo->uploadMultiple($_FILES['photos'], $currentAlbumId);
                setToast($result['message'], $result['success'] ? 'success' : 'error');
                redirect($_SERVER['REQUEST_URI']);
            }
            break;

        case 'bulk_delete':
            $selectedItems = $_POST['selected_items'] ?? '';
            if (!empty($selectedItems)) {
                $items = explode(',', $selectedItems);
                $successCount = 0;
                $totalCount = count($items);
                $errors = [];
                
                foreach ($items as $item) {
                    [$type, $id] = explode('_', $item, 2);
                    $id = (int)$id;
                    
                    if ($type === 'album') {
                        $result = $album->delete($id);
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $errors[] = $result['message'];
                        }
                    } elseif ($type === 'photo') {
                        $result = $photo->delete($id);
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $errors[] = $result['message'];
                        }
                    }
                }
                
                if ($successCount === $totalCount) {
                    setToast("$successCount/$totalCount öğe başarıyla silindi.", 'success');
                } else {
                    setToast("$successCount/$totalCount öğe silindi. Bazı öğeler silinemedi.", $successCount > 0 ? 'warning' : 'error');
                }
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'bulk_move':
            $selectedItems = $_POST['selected_items'] ?? '';
            $targetAlbumId = (int)($_POST['target_album_id'] ?? 0);
            
            if (!empty($selectedItems) && $targetAlbumId) {
                $items = explode(',', $selectedItems);
                $successCount = 0;
                $totalCount = count($items);
                
                foreach ($items as $item) {
                    [$type, $id] = explode('_', $item, 2);
                    $id = (int)$id;
                    
                    if ($type === 'photo') {
                        $result = $photo->moveToAlbum($id, $targetAlbumId);
                        if ($result['success']) {
                            $successCount++;
                        }
                    }
                    // Not: Albümleri taşıma işlemi daha karmaşık olduğu için şimdilik sadece fotoğraflar
                }
                
                setToast("$successCount/$totalCount fotoğraf başarıyla taşındı.", $successCount > 0 ? 'success' : 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;

        case 'bulk_rename':
            $newNames = $_POST['new_names'] ?? '';
            $selectedItems = $_POST['selected_items'] ?? '';
            
            if (!empty($newNames) && !empty($selectedItems)) {
                $names = array_filter(array_map('trim', explode("\n", $newNames)));
                $items = explode(',', $selectedItems);
                $successCount = 0;
                
                foreach ($items as $index => $item) {
                    if (!isset($names[$index])) continue;
                    
                    [$type, $id] = explode('_', $item, 2);
                    $id = (int)$id;
                    $newName = $names[$index];
                    
                    // Sadece fotoğraflar için isim değiştirme işlemi
                    if ($type === 'photo') {
                        // Mevcut fotoğrafı al
                        $photoData = $photo->getById($id);
                        if ($photoData) {
                            // Orijinal dosya uzantısını koru
                            $originalExt = pathinfo($photoData['original_name'], PATHINFO_EXTENSION);
                            
                            // Yeni adı uzantı ile birleştir
                            $fullNewName = $newName . '.' . $originalExt;
                            
                            $result = $photo->rename($id, $fullNewName);
                            if ($result['success']) {
                                $successCount++;
                            }
                        }
                    }
                }
                
                setToast("$successCount fotoğraf başarıyla yeniden adlandırıldı.", $successCount > 0 ? 'success' : 'error');
            }
            redirect($_SERVER['REQUEST_URI']);
            break;
    }
}
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
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo url(); ?>">
                <i class="fas fa-camera me-2"></i><?php echo h(APP_NAME); ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo !$currentAlbumId ? 'active' : ''; ?>" href="<?php echo url(); ?>">
                            <i class="fas fa-home me-1"></i>Ana Sayfa
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="navbar-text me-3 d-block mx-auto">
                            <i class="fas fa-user me-1"></i><?php echo h($auth->getCurrentUser()['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('?logout=1'); ?>">
                            <i class="fas fa-sign-out-alt me-1"></i>Çıkış
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
        <?php $toast = getToast(); ?>
        <?php if ($toast): ?>
        <div class="toast show" role="alert">
            <div class="toast-header bg-<?php echo $toast['type'] === 'success' ? 'success' : ($toast['type'] === 'error' ? 'danger' : $toast['type']); ?> text-white">
                <i class="fas fa-<?php echo $toast['type'] === 'success' ? 'check-circle' : ($toast['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                <strong class="me-auto">
                    <?php echo $toast['type'] === 'success' ? 'Başarılı' : ($toast['type'] === 'error' ? 'Hata' : 'Bilgi'); ?>
                </strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                <?php echo h($toast['message']); ?>
            </div>
        </div>
        <script>
            // Toast'ı 5 saniye sonra otomatik kapat
            setTimeout(function() {
                const toast = document.querySelector('.toast');
                if (toast) {
                    const bsToast = new bootstrap.Toast(toast);
                    bsToast.hide();
                }
            }, 5000);
        </script>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="container-lg mt-4">
        <?php /* Breadcrumbs kaldırıldı - yerine albüm hiyerarşisi eklendi */ ?>

        <!-- Arama ve Sıralama -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body py-3">
                        <div class="row g-3 align-items-center">
                            <!-- Arama -->
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="searchInput"
                                           placeholder="Albüm ve fotoğraf ara...">
                                </div>
                            </div>
                            
                            <!-- Sıralama -->
                            <div class="col-md-6">
                                <select class="form-select" id="sortOrder">
                                    <option value="name_asc">A'dan Z'ye</option>
                                    <option value="name_desc">Z'den A'ya</option>
                                    <option value="date_desc" selected>Yeniden Eskiye</option>
                                    <option value="date_asc">Eskiden Yeniye</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Albüm Navigasyonu -->
        <?php if (empty($currentAlbumId)): // Sadece ana sayfada göster ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body py-2">
                        <h6 class="card-title mb-2"><i class="fas fa-filter me-2"></i>Albüm Filtresi</h6>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-filter-album btn-primary" 
                                    data-album-id="all">
                                <i class="fas fa-home me-1"></i>Tümünü Göster
                            </button>
                            
                            <?php
                            // Tüm albümleri al
                            $allAlbums = $album->getAll();
                            $albumTree = [];
                            
                            // Albümleri parent_id'ye göre grupla
                            foreach ($allAlbums as $item) {
                                $parentId = $item['parent_id'] ?: 0;
                                if (!isset($albumTree[$parentId])) {
                                    $albumTree[$parentId] = [];
                                }
                                $albumTree[$parentId][] = $item;
                            }
                            
                            // Ana albümleri göster
                            if (!empty($albumTree[0])) {
                                foreach ($albumTree[0] as $rootAlbum) {
                                    $hasChildren = isset($albumTree[$rootAlbum['id']]);
                                    ?>
                                    <div class="btn-group mb-1 dropdown album-dropdown">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-filter-album"
                                                data-album-id="<?php echo $rootAlbum['id']; ?>">
                                            <i class="fas fa-folder me-1"></i>
                                            <?php echo h($rootAlbum['name']); ?>
                                            <?php if (isset($rootAlbum['child_count']) && $rootAlbum['child_count'] > 0): ?>
                                                <span class="badge bg-light text-dark ms-1"><?php echo $rootAlbum['child_count']; ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <?php if ($hasChildren): ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                    data-bs-toggle="dropdown">
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php foreach ($albumTree[$rootAlbum['id']] as $childAlbum): ?>
                                                    <li>
                                                        <span class="dropdown-item disabled">
                                                            <i class="fas fa-folder me-1"></i>
                                                            <?php echo h($childAlbum['name']); ?>
                                                            <?php 
                                                            // Alt albümlerin alt albümleri varsa onları da göster
                                                            if (isset($albumTree[$childAlbum['id']])): 
                                                            ?>
                                                            <i class="fas fa-chevron-right float-end mt-1"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                        <?php 
                                                        // Alt albümün alt albümleri varsa onları da göster
                                                        if (isset($albumTree[$childAlbum['id']])): 
                                                        ?>
                                                        <ul class="submenu dropdown-menu">
                                                            <?php foreach ($albumTree[$childAlbum['id']] as $subChildAlbum): ?>
                                                            <li>
                                                                <span class="dropdown-item disabled">
                                                                    <i class="fas fa-folder me-1"></i>
                                                                    <?php echo h($subChildAlbum['name']); ?>
                                                                </span>
                                                            </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h1>
                    <?php if ($currentAlbum): ?>
                        <i class="fas fa-folder-open me-2"></i><?php echo h($currentAlbum['name']); ?>
                    <?php else: ?>
                        <i class="fas fa-home me-2"></i>Albümler
                    <?php endif; ?>
                </h1>
                <?php if ($currentAlbum): ?>
                    <p class="text-muted">
                        <?php echo $currentAlbum['photo_count']; ?> fotoğraf, 
                        <?php echo $currentAlbum['child_count']; ?> alt albüm
                    </p>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">
                                <i class="fas fa-link"></i>
                            </span>
                            <input type="text" class="form-control form-control-sm bg-light" 
                                   value="<?php echo $_SERVER['HTTP_HOST'] . '/uploads/' . $currentAlbum['path']; ?>" 
                                   id="albumUrlInput" readonly>
                            <button class="btn btn-outline-secondary btn-sm" type="button" 
                                    onclick="copyToClipboard('albumUrlInput')" 
                                    title="Bağlantıyı Kopyala">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Geri Dönüş Butonu -->
                    <div class="mb-3">
                        <?php if ($currentAlbum['parent_id']): ?>
                            <a href="<?php echo url('index.php?album=' . $currentAlbum['parent_id']); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Üst Albüme Dön
                            </a>
                        <?php else: ?>
                            <a href="<?php echo url(); ?>" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>Ana Sayfaya Dön
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <!-- Toplu İşlemler -->
                <div class="btn-group me-2" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="selectAllBtn">
                        <i class="fas fa-check-square"></i> Tümünü Seç
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="bulkActionsBtn" 
                            data-bs-toggle="dropdown" disabled>
                        <i class="fas fa-cogs"></i> Toplu İşlem
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" id="bulkDeleteBtn">
                            <i class="fas fa-trash me-2"></i>Seçilenleri Sil
                        </a></li>
                        <?php if ($currentAlbumId): // Sadece fotoğraflar için taşıma ve adlandırma seçeneklerini göster ?>
                        <li><a class="dropdown-item" href="#" id="bulkMoveBtn">
                            <i class="fas fa-arrows-alt me-2"></i>Seçilenleri Taşı
                        </a></li>
                        <li><a class="dropdown-item" href="#" id="bulkRenameBtn">
                            <i class="fas fa-edit me-2"></i>Toplu Adlandır
                        </a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="btn-group" role="group">
                    <!-- Albüm Oluştur -->
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAlbumModal">
                        <i class="fas fa-folder-plus me-1"></i>Yeni Albüm
                    </button>
                    
                    <!-- Fotoğraf Yükle -->
                    <?php if ($currentAlbumId): ?>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadPhotosModal">
                        <i class="fas fa-upload me-1"></i>Fotoğraf Yükle
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Albümler -->
        <?php if (!empty($albums)): ?>
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">
                    <i class="fas fa-folder me-2"></i>Albümler
                    <span class="badge bg-secondary"><?php echo count($albums); ?></span>
                </h5>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 album-cards-container">
                    <?php foreach ($albums as $albumItem): ?>
                    <div class="col">
                        <div class="position-relative">
                            <a href="<?php echo url('index.php?album=' . $albumItem['id']); ?>" class="text-decoration-none album-link">
                                <div class="card h-100 album-card-improved shadow-sm" 
                                     data-searchable="<?php echo h(strtolower($albumItem['name'])); ?>"
                                     data-name="<?php echo h($albumItem['name']); ?>"
                                     data-date="<?php echo $albumItem['created_at']; ?>"
                                     data-type="album"
                                     data-album-id="<?php echo $albumItem['id']; ?>">
                                    <!-- Seçim Checkbox -->
                                    <div class="position-absolute top-0 start-0 p-2" style="z-index: 10;" onclick="event.stopPropagation();">
                                        <input type="checkbox" 
                                               class="form-check-input item-checkbox" 
                                               value="album_<?php echo $albumItem['id']; ?>"
                                               data-type="album"
                                               data-id="<?php echo $albumItem['id']; ?>"
                                               onclick="event.stopPropagation();">
                                    </div>
                                    
                                    <!-- Albüm İkonu -->
                                    <div class="card-img-top album-icon-improved d-flex align-items-center justify-content-center">
                                        <i class="fas fa-folder fa-3x text-primary"></i>
                                    </div>
                                    
                                    <!-- Albüm Bilgileri -->
                                    <div class="card-body">
                                        <h6 class="card-title fw-bold">
                                            <?php echo h($albumItem['name']); ?>
                                        </h6>
                                        <p class="card-text small text-muted">
                                            <?php echo formatDate($albumItem['created_at']); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-images me-1"></i><?php echo $albumItem['photo_count']; ?>
                                                <i class="fas fa-folder ms-2 me-1"></i><?php echo $albumItem['child_count']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </a>
                            
                            <!-- Albüm İşlem Butonları - Kartın dışında -->
                            <div class="btn-group w-100 mt-2" role="group">
                                <button class="btn btn-sm btn-outline-secondary flex-grow-1" type="button" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editAlbumModal"
                                        data-album-id="<?php echo $albumItem['id']; ?>"
                                        data-album-name="<?php echo h($albumItem['name']); ?>">
                                    <i class="fas fa-edit me-1"></i>Düzenle
                                </button>
                                <button class="btn btn-sm btn-outline-danger flex-grow-1" type="button"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteAlbumModal"
                                        data-album-id="<?php echo $albumItem['id']; ?>"
                                        data-album-name="<?php echo h($albumItem['name']); ?>">
                                    <i class="fas fa-trash me-1"></i>Sil
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($photos)): ?>
        <hr class="my-4">
        <?php endif; ?>
        <?php endif; ?>

        <!-- Fotoğraflar -->
        <?php if (!empty($photos)): ?>
        <div class="row">
            <div class="col-12">
                <h5 class="mb-3">
                    <i class="fas fa-images me-2"></i>Fotoğraflar
                    <span class="badge bg-secondary"><?php echo count($photos); ?></span>
                </h5>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3 photo-cards-container">
                    <?php foreach ($photos as $photoItem): ?>
                    <div class="col">
                        <div class="card h-100 photo-card-improved shadow-sm"
                             data-searchable="<?php echo h(strtolower($photoItem['original_name'])); ?>"
                             data-name="<?php echo h($photoItem['original_name']); ?>"
                             data-date="<?php echo $photoItem['uploaded_at']; ?>"
                             data-size="<?php echo $photoItem['file_size']; ?>"
                             data-type="photo">
                            <!-- Fotoğraf -->
                            <div class="position-relative">
                                <!-- Seçim Checkbox -->
                                <div class="position-absolute top-0 start-0 p-2" style="z-index: 15;">
                                    <input type="checkbox" 
                                           class="form-check-input item-checkbox" 
                                           value="photo_<?php echo $photoItem['id']; ?>"
                                           data-type="photo"
                                           data-id="<?php echo $photoItem['id']; ?>">
                                </div>
                                
                                <a href="<?php echo url($photo->getPhotoUrl($photoItem)); ?>" target="_blank">
                                    <img src="<?php echo url($photo->getPhotoUrl($photoItem)); ?>" 
                                         class="card-img-top photo-img-improved"
                                         alt="<?php echo h($photoItem['original_name']); ?>"
                                         loading="lazy">
                                </a>
                                
                                <!-- Fotoğraf İşlemleri - Açık Butonlar -->
                                <div class="position-absolute top-0 end-0 p-1">
                                    <div class="btn-group-vertical" role="group">
                                        <a href="<?php echo url($photo->getPhotoUrl($photoItem)); ?>" 
                                           class="btn btn-sm btn-dark bg-dark bg-opacity-75 mb-1" 
                                           title="Görüntüle" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo url($photo->getPhotoUrl($photoItem)); ?>" 
                                           class="btn btn-sm btn-dark bg-dark bg-opacity-75 mb-1" 
                                           title="İndir" download="<?php echo h($photoItem['original_name']); ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button class="btn btn-sm btn-dark bg-dark bg-opacity-75 mb-1" 
                                                type="button" title="Linki Kopyala"
                                                onclick="copyImageUrl('<?php echo $_SERVER['HTTP_HOST'] . '/' . $photo->getPhotoUrl($photoItem); ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button class="btn btn-sm btn-dark bg-dark bg-opacity-75 mb-1" 
                                                type="button" title="Adını Değiştir"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#renamePhotoModal"
                                                data-photo-id="<?php echo $photoItem['id']; ?>"
                                                data-photo-name="<?php echo h($photoItem['original_name']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-dark bg-dark bg-opacity-75 mb-1" 
                                                type="button" title="Taşı"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#movePhotoModal"
                                                data-photo-id="<?php echo $photoItem['id']; ?>"
                                                data-photo-name="<?php echo h($photoItem['original_name']); ?>">
                                            <i class="fas fa-arrows-alt"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger bg-danger bg-opacity-75" 
                                                type="button" title="Sil"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deletePhotoModal"
                                                data-photo-id="<?php echo $photoItem['id']; ?>"
                                                data-photo-name="<?php echo h($photoItem['original_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fotoğraf Bilgileri -->
                            <div class="card-body p-2">
                                <h6 class="card-title small fw-bold mb-1 text-truncate" title="<?php echo h($photoItem['original_name']); ?>">
                                    <?php echo h($photoItem['original_name']); ?>
                                </h6>
                                <div class="small text-muted">
                                    <?php echo formatFileSize($photoItem['file_size']); ?><br>
                                    <?php echo formatDate($photoItem['uploaded_at']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Boş Durum -->
        <?php if (empty($albums) && empty($photos)): ?>
        <div class="text-center py-5">
            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
            <h4 class="text-muted">Bu albümde henüz içerik yok</h4>
            <p class="text-muted">Yeni albümler oluşturun veya fotoğraf yükleyin.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    
    <!-- Albüm Oluştur Modal -->
    <div class="modal fade" id="createAlbumModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Yeni Albüm Oluştur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="create_album">
                        
                        <div class="mb-3">
                            <label for="album_name" class="form-label">Albüm Adı</label>
                            <input type="text" class="form-control" id="album_name" name="album_name" required>
                        </div>
                        
                        <?php if ($currentAlbum): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Bu albüm "<strong><?php echo h($currentAlbum['name']); ?></strong>" albümünün alt albümü olarak oluşturulacak.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Albüm Düzenle Modal -->
    <div class="modal fade" id="editAlbumModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Albüm Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="update_album">
                        <input type="hidden" name="album_id" id="edit_album_id">
                        
                        <div class="mb-3">
                            <label for="edit_album_name" class="form-label">Albüm Adı</label>
                            <input type="text" class="form-control" id="edit_album_name" name="album_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Albüm Sil Modal -->
    <div class="modal fade" id="deleteAlbumModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Albüm Sil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete_album">
                        <input type="hidden" name="album_id" id="delete_album_id">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong id="delete_album_name"></strong> albümünü silmek istediğinizden emin misiniz?
                            <br><small>Bu işlem geri alınamaz!</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fotoğraf Yeniden Adlandır Modal -->
    <div class="modal fade" id="renamePhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Fotoğraf Adını Değiştir</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="rename_photo">
                        <input type="hidden" name="photo_id" id="rename_photo_id">
                        
                        <div class="mb-3">
                            <label for="rename_new_name" class="form-label">Yeni Ad</label>
                            <input type="text" class="form-control" id="rename_new_name" name="new_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fotoğraf Taşı Modal -->
    <div class="modal fade" id="movePhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-arrows-alt me-2"></i>Fotoğraf Taşı</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="move_photo">
                        <input type="hidden" name="photo_id" id="move_photo_id">
                        
                        <div class="mb-3">
                            <label for="move_target_album" class="form-label">Hedef Albüm</label>
                            <select class="form-select" id="move_target_album" name="target_album_id" required>
                                <option value="">Albüm seçin...</option>
                                <?php foreach ($allAlbums as $albumOption): ?>
                                    <?php if ($albumOption['id'] != $currentAlbumId): ?>
                                    <option value="<?php echo $albumOption['id']; ?>">
                                        <?php echo str_repeat('— ', substr_count($albumOption['path'], '/')); ?>
                                        <?php echo h($albumOption['name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong id="move_photo_name"></strong> fotoğrafı seçilen albüme taşınacak.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrows-alt me-1"></i>Taşı
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fotoğraf Sil Modal -->
    <div class="modal fade" id="deletePhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Fotoğraf Sil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete_photo">
                        <input type="hidden" name="photo_id" id="delete_photo_id">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong id="delete_photo_name"></strong> fotoğrafını silmek istediğinizden emin misiniz?
                            <br><small>Bu işlem geri alınamaz!</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fotoğraf Yükle Modal -->
    <?php if ($currentAlbumId): ?>
    <div class="modal fade" id="uploadPhotosModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Fotoğraf Yükle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="upload_photos">
                        
                        <div class="mb-3">
                            <label for="photos" class="form-label">Fotoğrafları Seçin</label>
                            <input type="file" class="form-control" id="photos" name="photos[]" 
                                   multiple accept="image/*" required>
                            <div class="form-text">
                                Birden fazla fotoğraf seçebilirsiniz. Desteklenen formatlar: JPG, PNG, GIF, WebP
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload me-1"></i>Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toplu İşlem Modalları -->
    
    <!-- Toplu Silme Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkDeleteForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Toplu Silme</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="bulk_delete">
                        <input type="hidden" name="selected_items" id="bulkDeleteItems">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong id="bulkDeleteCount">0</strong> öğeyi silmek istediğinizden emin misiniz?
                            <br><small>Bu işlem geri alınamaz!</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toplu Taşıma Modal -->
    <div class="modal fade" id="bulkMoveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="bulkMoveForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-arrows-alt me-2"></i>Toplu Taşıma</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="bulk_move">
                        <input type="hidden" name="selected_items" id="bulkMoveItems">
                        
                        <div class="mb-3">
                            <label for="bulk_target_album" class="form-label">Hedef Albüm</label>
                            <select class="form-select" id="bulk_target_album" name="target_album_id" required>
                                <option value="">Albüm seçin...</option>
                                <?php foreach ($allAlbums as $albumOption): ?>
                                <option value="<?php echo $albumOption['id']; ?>">
                                    <?php echo str_repeat('— ', substr_count($albumOption['path'], '/')); ?>
                                    <?php echo h($albumOption['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong id="bulkMoveCount">0</strong> öğe seçilen albüme taşınacak.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrows-alt me-1"></i>Taşı
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toplu Adlandırma Modal -->
    <div class="modal fade" id="bulkRenameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="bulkRenameForm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Toplu Adlandırma</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="bulk_rename">
                        <input type="hidden" name="selected_items" id="bulkRenameItems">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Her satırda bir isim yazın. Sıralama seçilen öğelerin sırasıyla aynıdır.
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk_rename_list" class="form-label">Yeni İsimler (Her satırda bir isim)</label>
                            <textarea class="form-control" id="bulk_rename_list" name="new_names" rows="10" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Modal event handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Albüm düzenleme modal
            const editAlbumModal = document.getElementById('editAlbumModal');
            if (editAlbumModal) {
                editAlbumModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const albumId = button.getAttribute('data-album-id');
                    const albumName = button.getAttribute('data-album-name');
                    
                    document.getElementById('edit_album_id').value = albumId;
                    document.getElementById('edit_album_name').value = albumName;
                });
            }

            // Albüm silme modal
            const deleteAlbumModal = document.getElementById('deleteAlbumModal');
            if (deleteAlbumModal) {
                deleteAlbumModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const albumId = button.getAttribute('data-album-id');
                    const albumName = button.getAttribute('data-album-name');
                    
                    document.getElementById('delete_album_id').value = albumId;
                    document.getElementById('delete_album_name').textContent = albumName;
                });
            }

            // Fotoğraf yeniden adlandırma modal
            const renamePhotoModal = document.getElementById('renamePhotoModal');
            if (renamePhotoModal) {
                renamePhotoModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const photoId = button.getAttribute('data-photo-id');
                    const photoName = button.getAttribute('data-photo-name');
                    
                    document.getElementById('rename_photo_id').value = photoId;
                    
                    // Uzantıyı kaldır ve sadece dosya adını göster
                    const fileName = photoName.replace(/\.[^/.]+$/, "");
                    document.getElementById('rename_new_name').value = fileName;
                });
            }

            // Fotoğraf taşıma modal
            const movePhotoModal = document.getElementById('movePhotoModal');
            if (movePhotoModal) {
                movePhotoModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const photoId = button.getAttribute('data-photo-id');
                    const photoName = button.getAttribute('data-photo-name');
                    
                    document.getElementById('move_photo_id').value = photoId;
                    document.getElementById('move_photo_name').textContent = photoName;
                });
            }

            // Fotoğraf silme modal
            const deletePhotoModal = document.getElementById('deletePhotoModal');
            if (deletePhotoModal) {
                deletePhotoModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const photoId = button.getAttribute('data-photo-id');
                    const photoName = button.getAttribute('data-photo-name');
                    
                    document.getElementById('delete_photo_id').value = photoId;
                    document.getElementById('delete_photo_name').textContent = photoName;
                });
            }
            
            // Albüm kartı tıklama işlevi
            document.querySelectorAll('.album-link').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    // Eğer tıklama checkbox veya butonlardan geliyorsa, yönlendirmeyi engelle
                    if (event.target.closest('.item-checkbox') || event.target.closest('.btn-group')) {
                        event.preventDefault();
                        return false;
                    }
                });
            });
        });
    </script>
    
    <script>
        // Albüm filtreleme fonksiyonu
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.btn-filter-album');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const albumId = this.getAttribute('data-album-id');
                    
                    // Tüm butonların active sınıfını kaldır
                    filterButtons.forEach(btn => {
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-outline-secondary');
                    });
                    
                    // Tıklanan butona active sınıfını ekle
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-primary');
                    
                    // Albüm ve fotoğrafları filtrele
                    if (albumId === 'all') {
                        // Tüm albüm ve fotoğrafları göster
                        document.querySelectorAll('.album-cards-container .col, .photo-cards-container .col').forEach(item => {
                            item.style.display = '';
                        });
                    } else {
                        // Sadece seçilen albüme ait öğeleri göster
                        document.querySelectorAll('.album-cards-container .col').forEach(item => {
                            const card = item.querySelector('.card');
                            const cardAlbumId = card.getAttribute('data-album-id');
                            
                            if (cardAlbumId === albumId) {
                                item.style.display = '';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    }
                });
            });
        });
    </script>
    
    <script>
        // Arama ve Sıralama Fonksiyonları
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const sortOrder = document.getElementById('sortOrder');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const bulkActionsBtn = document.getElementById('bulkActionsBtn');
            
            // Arama işlevi
            function performSearch() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const cards = document.querySelectorAll('[data-searchable]');
                
                cards.forEach(card => {
                    const searchableText = card.getAttribute('data-searchable');
                    if (searchableText.includes(searchTerm)) {
                        card.closest('.col').style.display = '';
                    } else {
                        card.closest('.col').style.display = 'none';
                    }
                });
            }
            
            // Sıralama işlevi
            function performSort() {
                const sortValue = sortOrder.value;
                const [criteria, direction] = sortValue.split('_');
                
                ['album', 'photo'].forEach(type => {
                    const containerSelector = `.${type}-cards-container`;
                    const container = document.querySelector(containerSelector);
                    if (!container) return;
                    
                    // Tüm kartları seç
                    const cards = Array.from(container.querySelectorAll('.col')).map(col => {
                        const card = col.querySelector(`[data-type="${type}"]`);
                        return {
                            element: col,
                            name: card.getAttribute('data-name') || '',
                            date: card.getAttribute('data-date') || '',
                            size: parseInt(card.getAttribute('data-size') || '0')
                        };
                    });
                    
                    // Sıralama işlemi
                    cards.sort((a, b) => {
                        let aValue, bValue;
                        
                        switch(criteria) {
                            case 'name':
                                aValue = a.name.toLowerCase();
                                bValue = b.name.toLowerCase();
                                break;
                            case 'date':
                                aValue = new Date(a.date).getTime();
                                bValue = new Date(b.date).getTime();
                                break;
                            case 'size':
                                aValue = a.size;
                                bValue = b.size;
                                break;
                            default:
                                return 0;
                        }
                        
                        if (direction === 'asc') {
                            return aValue < bValue ? -1 : aValue > bValue ? 1 : 0;
                        } else {
                            return aValue > bValue ? -1 : aValue < bValue ? 1 : 0;
                        }
                    });
                    
                    // DOM'u güncelle
                    cards.forEach(card => {
                        container.appendChild(card.element);
                    });
                });
            }
            
            // Toplu seçim işlevleri
            function updateBulkActions() {
                const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
                const count = checkedBoxes.length;
                
                bulkActionsBtn.disabled = count === 0;
                selectAllBtn.innerHTML = count > 0 ? 
                    '<i class="fas fa-square"></i> Seçimi Temizle' : 
                    '<i class="fas fa-check-square"></i> Tümünü Seç';
            }
            
            // Event listeners
            searchInput.addEventListener('input', performSearch);
            sortOrder.addEventListener('change', performSort);
            
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('.item-checkbox');
                const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
                const shouldCheck = checkedCount === 0;
                
                checkboxes.forEach(cb => cb.checked = shouldCheck);
                updateBulkActions();
            });
            
            // Checkbox değişikliklerini dinle
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('item-checkbox')) {
                    updateBulkActions();
                }
            });
            
            // Toplu işlem modal'ları
            document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
                const selectedItems = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                
                document.getElementById('bulkDeleteItems').value = selectedItems;
                document.getElementById('bulkDeleteCount').textContent = checkedBoxes.length;
                
                new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
            });
            
            document.getElementById('bulkMoveBtn').addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
                const selectedItems = Array.from(checkedBoxes).map(cb => cb.value).join(',');
                
                document.getElementById('bulkMoveItems').value = selectedItems;
                document.getElementById('bulkMoveCount').textContent = checkedBoxes.length;
                
                new bootstrap.Modal(document.getElementById('bulkMoveModal')).show();
            });
            
            document.getElementById('bulkRenameBtn').addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.item-checkbox:checked[data-type="photo"]');
                if (checkedBoxes.length === 0) {
                    alert('Lütfen en az bir fotoğraf seçin!');
                    return;
                }
                
                const selectedItems = Array.from(checkedBoxes);
                const selectedValues = selectedItems.map(cb => cb.value).join(',');
                
                // Seçilen fotoğrafların isimlerini textarea'ya yerleştir
                const currentNames = selectedItems.map(cb => {
                    const card = cb.closest('[data-name]');
                    const fileName = card.getAttribute('data-name');
                    // Uzantısız dosya adını göster
                    return fileName.substring(0, fileName.lastIndexOf('.'));
                }).join('\n');
                
                document.getElementById('bulkRenameItems').value = selectedValues;
                document.getElementById('bulk_rename_list').value = currentNames;
                
                new bootstrap.Modal(document.getElementById('bulkRenameModal')).show();
            });
            
            // İlk yükleme
            updateBulkActions();
            
            // Sayfa yüklendiğinde varsayılan sıralamayı uygula
            performSort();
        });
    </script>
    
    <script>
        // Panoya kopyalama fonksiyonları
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');
            
            // Kopyalandı bildirimi
            const originalBgColor = element.style.backgroundColor;
            element.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                element.style.backgroundColor = originalBgColor;
            }, 1000);
        }
        
        function copyImageUrl(url) {
            const tempInput = document.createElement('input');
            tempInput.value = url;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            // Kopyalandı bildirimi göster
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.style.zIndex = '5000';
            toast.innerHTML = `
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Başarılı</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Fotoğraf linki panoya kopyalandı.
                    </div>
                </div>
            `;
            
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html> 