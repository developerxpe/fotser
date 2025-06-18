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
$albumId = (int)($_POST['album_id'] ?? 0);

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            
            if (empty($name)) {
                throw new Exception('Albüm adı gereklidir.');
            }
            
            $newAlbumId = $album->create($name, $parentId);
            if ($newAlbumId) {
                setToast('Albüm başarıyla oluşturuldu.', 'success');
            } else {
                throw new Exception('Albüm oluşturulamadı.');
            }
            break;
            
        case 'rename':
            $newName = trim($_POST['new_name'] ?? '');
            
            if (empty($newName)) {
                throw new Exception('Yeni albüm adı gereklidir.');
            }
            
            if (!$albumId) {
                throw new Exception('Geçersiz albüm ID.');
            }
            
            if ($album->rename($albumId, $newName)) {
                setToast('Albüm adı başarıyla değiştirildi.', 'success');
            } else {
                throw new Exception('Albüm adı değiştirilemedi.');
            }
            break;
            
        case 'move':
            $newParentId = !empty($_POST['new_parent_id']) ? (int)$_POST['new_parent_id'] : null;
            
            if (!$albumId) {
                throw new Exception('Geçersiz albüm ID.');
            }
            
            if ($album->move($albumId, $newParentId)) {
                setToast('Albüm başarıyla taşındı.', 'success');
            } else {
                throw new Exception('Albüm taşınamadı.');
            }
            break;
            
        case 'delete':
            if (!$albumId) {
                throw new Exception('Geçersiz albüm ID.');
            }
            
            // Albümün alt albümleri ve fotoğrafları var mı kontrol et
            $albumData = $album->getById($albumId);
            if (!$albumData) {
                throw new Exception('Albüm bulunamadı.');
            }
            
            $children = $album->getChildren($albumId);
            $photos = $photo->getByAlbum($albumId);
            
            if (!empty($children) || !empty($photos)) {
                // Onay gerekli
                if (!isset($_POST['confirm_delete'])) {
                    setToast('Bu albümde alt albümler veya fotoğraflar var. Silmek için onaylayın.', 'warning');
                    redirect('?album=' . ($albumData['parent_id'] ?? '') . '&confirm_delete=' . $albumId);
                }
            }
            
            if ($album->delete($albumId)) {
                setToast('Albüm başarıyla silindi.', 'success');
            } else {
                throw new Exception('Albüm silinemedi.');
            }
            break;
            
        default:
            throw new Exception('Geçersiz işlem.');
    }
} catch (Exception $e) {
    setToast($e->getMessage(), 'error');
}

// Ana sayfaya yönlendir
$redirectUrl = isset($_POST['current_album']) && $_POST['current_album'] !== '' 
    ? '?album=' . (int)$_POST['current_album'] 
    : '';
redirect($redirectUrl);
?> 