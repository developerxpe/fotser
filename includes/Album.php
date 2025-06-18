<?php

class Album {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Yeni albüm oluştur
     */
    public function create(string $name, ?int $parentId = null): array {
        $slug = $this->generateSlug($name);
        $path = $this->generatePath($slug, $parentId);
        
        // Yol klasörünü oluştur
        $fullPath = UPLOAD_PATH . '/' . $path;
        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                return ['success' => false, 'message' => 'Albüm klasörü oluşturulamadı.'];
            }
        }
        
        $sql = "INSERT INTO albums (name, slug, path, parent_id, created_at) VALUES (?, ?, ?, ?, NOW())";
        $albumId = $this->db->insert($sql, [$name, $slug, $path, $parentId]);
        
        if ($albumId) {
            return ['success' => true, 'message' => 'Albüm başarıyla oluşturuldu.', 'id' => $albumId];
        }
        
        return ['success' => false, 'message' => 'Albüm oluşturulamadı.'];
    }

    /**
     * Albüm adını değiştir
     */
    public function rename(int $id, string $newName): bool {
        return $this->update($id, $newName);
    }

    /**
     * Albümü güncelle
     */
    public function update(int $id, string $newName): array {
        $album = $this->getById($id);
        if (!$album) {
            return ['success' => false, 'message' => 'Albüm bulunamadı.'];
        }
        
        $newSlug = $this->generateSlug($newName);
        $newPath = $this->generatePath($newSlug, $album['parent_id']);
        
        // Eski ve yeni yolları al
        $oldFullPath = UPLOAD_PATH . '/' . $album['path'];
        $newFullPath = UPLOAD_PATH . '/' . $newPath;
        
        // Klasörü yeniden adlandır
        if (is_dir($oldFullPath) && $oldFullPath !== $newFullPath) {
            if (!rename($oldFullPath, $newFullPath)) {
                return ['success' => false, 'message' => 'Klasör yeniden adlandırılamadı.'];
            }
        }
        
        // Veritabanını güncelle
        $sql = "UPDATE albums SET name = ?, slug = ?, path = ? WHERE id = ?";
        $result = $this->db->execute($sql, [$newName, $newSlug, $newPath, $id]);
        
        if ($result) {
            // Alt albümlerin yollarını güncelle
            $this->updateChildrenPaths($id);
            return ['success' => true, 'message' => 'Albüm başarıyla güncellendi.'];
        }
        
        return ['success' => false, 'message' => 'Albüm güncellenemedi.'];
    }

    /**
     * Albümü taşı
     */
    public function move(int $id, ?int $newParentId): bool {
        $album = $this->getById($id);
        if (!$album) {
            return false;
        }
        
        // Kendi kendine taşıma kontrolü
        if ($id === $newParentId) {
            return false;
        }
        
        // Döngüsel referans kontrolü
        if ($newParentId && $this->isDescendant($newParentId, $id)) {
            return false;
        }
        
        $newPath = $this->generatePath($album['slug'], $newParentId);
        
        // Eski ve yeni yolları al
        $oldFullPath = UPLOAD_PATH . '/' . $album['path'];
        $newFullPath = UPLOAD_PATH . '/' . $newPath;
        
        // Klasörü taşı
        if (is_dir($oldFullPath) && $oldFullPath !== $newFullPath) {
            // Hedef klasörün üst dizinini oluştur
            $parentDir = dirname($newFullPath);
            if (!is_dir($parentDir)) {
                if (!mkdir($parentDir, 0755, true)) {
                    return false;
                }
            }
            
            if (!rename($oldFullPath, $newFullPath)) {
                return false;
            }
        }
        
        // Veritabanını güncelle
        $sql = "UPDATE albums SET parent_id = ?, path = ? WHERE id = ?";
        $result = $this->db->execute($sql, [$newParentId, $newPath, $id]);
        
        if ($result) {
            // Alt albümlerin yollarını güncelle
            $this->updateChildrenPaths($id);
        }
        
        return $result;
    }

    /**
     * Alt albümlerin yollarını güncelle
     */
    private function updateChildrenPaths(int $parentId): void {
        $children = $this->getChildren($parentId);
        
        foreach ($children as $child) {
            $newPath = $this->generatePath($child['slug'], $parentId);
            $oldFullPath = UPLOAD_PATH . '/' . $child['path'];
            $newFullPath = UPLOAD_PATH . '/' . $newPath;
            
            // Klasörü taşı
            if (is_dir($oldFullPath) && $oldFullPath !== $newFullPath) {
                rename($oldFullPath, $newFullPath);
            }
            
            // Veritabanını güncelle
            $sql = "UPDATE albums SET path = ? WHERE id = ?";
            $this->db->execute($sql, [$newPath, $child['id']]);
            
            // Alt albümleri de güncelle (recursive)
            $this->updateChildrenPaths($child['id']);
        }
    }

    /**
     * Döngüsel referans kontrolü
     */
    private function isDescendant(int $potentialChild, int $ancestor): bool {
        $album = $this->getById($potentialChild);
        
        while ($album && $album['parent_id']) {
            if ($album['parent_id'] === $ancestor) {
                return true;
            }
            $album = $this->getById($album['parent_id']);
        }
        
        return false;
    }

    /**
     * Albümü sil
     */
    public function delete(int $id): array {
        $album = $this->getById($id);
        if (!$album) {
            return ['success' => false, 'message' => 'Albüm bulunamadı.'];
        }
        
        // Alt albümleri sil (recursive)
        $children = $this->getChildren($id);
        foreach ($children as $child) {
            $this->delete($child['id']);
        }
        
        // Fotoğrafları sil
        $photo = new Photo($this->db);
        $photos = $photo->getByAlbum($id);
        foreach ($photos as $photoData) {
            $photo->delete($photoData['id']);
        }
        
        // Klasörü sil
        $fullPath = UPLOAD_PATH . '/' . $album['path'];
        if (is_dir($fullPath)) {
            $this->deleteDirectory($fullPath);
        }
        
        // Veritabanından sil
        $sql = "DELETE FROM albums WHERE id = ?";
        $result = $this->db->execute($sql, [$id]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Albüm başarıyla silindi.'];
        }
        
        return ['success' => false, 'message' => 'Albüm silinemedi.'];
    }

    /**
     * Klasörü ve içeriğini sil
     */
    private function deleteDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                $this->deleteDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        
        return rmdir($dir);
    }

    /**
     * ID'ye göre albüm getir
     */
    public function getById(int $id): ?array {
        $sql = "SELECT a.*, 
                       (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) as photo_count,
                       (SELECT COUNT(*) FROM albums c WHERE c.parent_id = a.id) as child_count
                FROM albums a 
                WHERE a.id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Ana albümleri getir (parent_id = NULL)
     */
    public function getRootAlbums(): array {
        $sql = "SELECT a.*, 
                       (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) as photo_count,
                       (SELECT COUNT(*) FROM albums c WHERE c.parent_id = a.id) as child_count
                FROM albums a 
                WHERE a.parent_id IS NULL 
                ORDER BY a.name ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Belirtilen albümün alt albümlerini getir
     */
    public function getChildren(int $parentId): array {
        $sql = "SELECT a.*, 
                       (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) as photo_count,
                       (SELECT COUNT(*) FROM albums c WHERE c.parent_id = a.id) as child_count
                FROM albums a 
                WHERE a.parent_id = ? 
                ORDER BY a.name ASC";
        return $this->db->fetchAll($sql, [$parentId]);
    }

    /**
     * Tüm albümleri getir (dropdown için)
     */
    public function getAll(): array {
        $sql = "SELECT * FROM albums ORDER BY path ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Breadcrumb navigation oluştur
     */
    public function getBreadcrumbs(int $albumId): array {
        $breadcrumbs = [];
        $currentAlbum = $this->getById($albumId);
        
        while ($currentAlbum) {
            array_unshift($breadcrumbs, $currentAlbum);
            $currentAlbum = $currentAlbum['parent_id'] ? $this->getById($currentAlbum['parent_id']) : null;
        }
        
        return $breadcrumbs;
    }

    /**
     * Slug oluştur
     */
    private function generateSlug(string $name): string {
        // Türkçe karakterleri dönüştür
        $name = str_replace(
            ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'],
            ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'i', 'o', 's', 'u'],
            $name
        );
        
        // Slug'a dönüştür
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        return $slug ?: 'album';
    }

    /**
     * Albüm yolunu oluştur
     */
    private function generatePath(string $slug, ?int $parentId = null): string {
        if (!$parentId) {
            return $slug;
        }
        
        $parent = $this->getById($parentId);
        if (!$parent) {
            return $slug;
        }
        
        return $parent['path'] . '/' . $slug;
    }

    /**
     * Albüm istatistiklerini getir
     */
    public function getStats(int $albumId): array {
        // Alt albüm sayısı
        $sql = "SELECT COUNT(*) as count FROM albums WHERE parent_id = ?";
        $albumCount = $this->db->fetch($sql, [$albumId])['count'] ?? 0;
        
        // Fotoğraf sayısı
        $sql = "SELECT COUNT(*) as count FROM photos WHERE album_id = ?";
        $photoCount = $this->db->fetch($sql, [$albumId])['count'] ?? 0;
        
        // Toplam boyut
        $sql = "SELECT SUM(file_size) as total_size FROM photos WHERE album_id = ?";
        $totalSize = $this->db->fetch($sql, [$albumId])['total_size'] ?? 0;
        
        return [
            'album_count' => (int)$albumCount,
            'photo_count' => (int)$photoCount,
            'total_size' => (int)$totalSize
        ];
    }
} 