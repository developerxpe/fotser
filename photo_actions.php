<?php
require_once 'includes/init.php';

// Giriş kontrolü
$auth->requireLogin();

// CSRF token kontrolü
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setToast('Güvenlik hatası. Lütfen sayfayı yenileyin.', 'error');
    redirect();
}

$action = $_POST['action'] ?? '';
$photoId = (int)($_POST['photo_id'] ?? 0);
$currentAlbumId = (int)($_POST['current_album'] ?? 0);

try {
    switch ($action) {
        case 'upload':
            $albumId = (int)($_POST['album_id'] ?? 0);
            
            if (!$albumId) {
                throw new Exception('Geçersiz albüm ID.');
            }
            
            if (empty($_FILES['photos']['name'][0])) {
                throw new Exception('Lütfen en az bir fotoğraf seçin.');
            }
            
            $uploadedCount = 0;
            $errorCount = 0;
            
            foreach ($_FILES['photos']['name'] as $index => $fileName) {
                if (empty($fileName)) continue;
                
                $tempFile = $_FILES['photos']['tmp_name'][$index];
                $fileSize = $_FILES['photos']['size'][$index];
                $fileError = $_FILES['photos']['error'][$index];
                
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errorCount++;
                    continue;
                }
                
                try {
                    if ($photo->upload($tempFile, $fileName, $albumId, $fileSize)) {
                        $uploadedCount++;
                    } else {
                        $errorCount++;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                }
            }
            
            if ($uploadedCount > 0) {
                setToast("{$uploadedCount} fotoğraf başarıyla yüklendi.", 'success');
            }
            if ($errorCount > 0) {
                setToast("{$errorCount} fotoğraf yüklenemedi.", 'warning');
            }
            break;
            
        case 'rename':
            $newName = trim($_POST['new_name'] ?? '');
            
            if (empty($newName)) {
                throw new Exception('Yeni fotoğraf adı gereklidir.');
            }
            
            if (!$photoId) {
                throw new Exception('Geçersiz fotoğraf ID.');
            }
            
            if ($photo->rename($photoId, $newName)) {
                setToast('Fotoğraf adı başarıyla değiştirildi.', 'success');
            } else {
                throw new Exception('Fotoğraf adı değiştirilemedi.');
            }
            break;
            
        case 'move':
            $newAlbumId = (int)($_POST['new_album_id'] ?? 0);
            
            if (!$photoId) {
                throw new Exception('Geçersiz fotoğraf ID.');
            }
            
            if (!$newAlbumId) {
                throw new Exception('Geçersiz hedef albüm.');
            }
            
            if ($photo->move($photoId, $newAlbumId)) {
                setToast('Fotoğraf başarıyla taşındı.', 'success');
            } else {
                throw new Exception('Fotoğraf taşınamadı.');
            }
            break;
            
        case 'move_multiple':
            $photoIds = $_POST['photo_ids'] ?? [];
            $newAlbumId = (int)($_POST['new_album_id'] ?? 0);
            
            if (empty($photoIds) || !is_array($photoIds)) {
                throw new Exception('Lütfen taşınacak fotoğrafları seçin.');
            }
            
            if (!$newAlbumId) {
                throw new Exception('Geçersiz hedef albüm.');
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($photoIds as $id) {
                if ($photo->move((int)$id, $newAlbumId)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
            
            if ($successCount > 0) {
                setToast("{$successCount} fotoğraf başarıyla taşındı.", 'success');
            }
            if ($errorCount > 0) {
                setToast("{$errorCount} fotoğraf taşınamadı.", 'warning');
            }
            break;
            
        case 'delete':
            if (!$photoId) {
                throw new Exception('Geçersiz fotoğraf ID.');
            }
            
            if ($photo->delete($photoId)) {
                setToast('Fotoğraf başarıyla silindi.', 'success');
            } else {
                throw new Exception('Fotoğraf silinemedi.');
            }
            break;
            
        case 'delete_multiple':
            $photoIds = $_POST['photo_ids'] ?? [];
            
            if (empty($photoIds) || !is_array($photoIds)) {
                throw new Exception('Lütfen silinecek fotoğrafları seçin.');
            }
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($photoIds as $id) {
                if ($photo->delete((int)$id)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
            
            if ($successCount > 0) {
                setToast("{$successCount} fotoğraf başarıyla silindi.", 'success');
            }
            if ($errorCount > 0) {
                setToast("{$errorCount} fotoğraf silinemedi.", 'warning');
            }
            break;
            
        default:
            throw new Exception('Geçersiz işlem.');
    }
} catch (Exception $e) {
    setToast($e->getMessage(), 'error');
}

// Ana sayfaya yönlendir
$redirectUrl = $currentAlbumId ? '?album=' . $currentAlbumId : '';
redirect($redirectUrl);
?> 