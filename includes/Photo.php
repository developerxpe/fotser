<?php

class Photo {
    private Database $db;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function upload(string $tempFile, string $originalName, int $albumId, int $fileSize): bool {
        // Dosya türü kontrolü
        if (!$this->isValidFileType($tempFile)) {
            throw new Exception('Geçersiz dosya türü. Sadece JPG, PNG, GIF ve WebP dosyaları kabul edilir.');
        }
        
        // Dosya boyutu kontrolü (10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            throw new Exception('Dosya boyutu 10MB\'dan büyük olamaz.');
        }
        
        // Albüm kontrolü
        $album = new Album($this->db);
        $albumData = $album->getById($albumId);
        if (!$albumData) {
            throw new Exception('Geçersiz albüm.');
        }
        
        // Dosya adını temizle ve benzersiz yap
        $fileName = $this->sanitizeFileName($originalName);
        $fileName = $this->makeUniqueFileName($fileName, $albumId);
        
        // Dosya yolunu oluştur
        $albumPath = UPLOAD_PATH . '/' . $albumData['path'];
        $filePath = $albumPath . '/' . $fileName;
        
        // Klasörü oluştur (yoksa)
        if (!is_dir($albumPath)) {
            if (!mkdir($albumPath, 0755, true)) {
                throw new Exception('Yükleme klasörü oluşturulamadı.');
            }
        }
        
        // Dosyayı taşı
        if (!move_uploaded_file($tempFile, $filePath)) {
            throw new Exception('Dosya yüklenemedi.');
        }
        
        // Resim boyutlarını al
        $imageInfo = getimagesize($filePath);
        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;
        
        // Eğer dosya adı değiştiyse, original_name'i de güncelle
        $updatedOriginalName = $originalName;
        if ($fileName !== $this->sanitizeFileName($originalName)) {
            // Dosya adı benzersiz hale getirildi, original_name'i de güncelle
            $updatedOriginalName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . pathinfo($originalName, PATHINFO_EXTENSION);
        }
        
        // Veritabanına kaydet
        $sql = "INSERT INTO photos (album_id, filename, original_name, file_size, width, height, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $result = $this->db->insert($sql, [$albumId, $fileName, $updatedOriginalName, $fileSize, $width, $height]);
        
        // Önbelleği temizle
        clearCache($filePath);
        clearCache($albumPath);
        
        return $result !== null;
    }

    public function uploadMultiple(array $files, int $albumId): array {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $uploadedFiles = [];

        if (empty($files['name'][0])) {
            return ['success' => false, 'message' => 'Hiç dosya seçilmedi.'];
        }

        for ($i = 0; $i < count($files['name']); $i++) {
            if (empty($files['name'][$i])) {
                continue;
            }

            try {
                $result = $this->upload(
                    $files['tmp_name'][$i],
                    $files['name'][$i],
                    $albumId,
                    $files['size'][$i]
                );
                
                if ($result) {
                    $successCount++;
                    // Başarılı yüklenen dosya bilgilerini ekle
                    $uploadedFiles[] = [
                        'name' => $files['name'][$i],
                        'size' => $files['size'][$i],
                        'status' => 'success'
                    ];
                } else {
                    $errorCount++;
                    $errors[] = $files['name'][$i] . ': Yükleme başarısız.';
                    $uploadedFiles[] = [
                        'name' => $files['name'][$i],
                        'size' => $files['size'][$i],
                        'status' => 'error',
                        'message' => 'Yükleme başarısız.'
                    ];
                }
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = $files['name'][$i] . ': ' . $e->getMessage();
                $uploadedFiles[] = [
                    'name' => $files['name'][$i],
                    'size' => $files['size'][$i],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }

        $response = [
            'success' => $successCount > 0,
            'message' => $successCount > 0 ? 
                ($errorCount > 0 ? "$successCount fotoğraf yüklendi, $errorCount hata oluştu." : "$successCount fotoğraf başarıyla yüklendi.") : 
                'Hiç fotoğraf yüklenemedi.' . (!empty($errors) ? ' Hatalar: ' . implode(', ', $errors) : ''),
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'files' => $uploadedFiles
        ];

        return $response;
    }

    public function delete(int $id): array {
        $photo = $this->getById($id);
        if (!$photo) {
            return ['success' => false, 'message' => 'Fotoğraf bulunamadı.'];
        }
        
        // Albüm bilgilerini al
        $album = new Album($this->db);
        $albumData = $album->getById($photo['album_id']);
        if ($albumData) {
            // Fiziksel dosyayı sil
            $filePath = UPLOAD_PATH . '/' . $albumData['path'] . '/' . $photo['filename'];
            $albumPath = UPLOAD_PATH . '/' . $albumData['path'];
            
            if (file_exists($filePath)) {
                // Dosyayı silmeden önce önbelleği temizle
                clearCache($filePath);
                clearCache($albumPath);
                unlink($filePath);
            }
        }
        
        // Veritabanından sil
        $sql = "DELETE FROM photos WHERE id = ?";
        $result = $this->db->execute($sql, [$id]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Fotoğraf başarıyla silindi.'];
        }
        
        return ['success' => false, 'message' => 'Fotoğraf silinemedi.'];
    }

    public function deleteMultiple(array $photoIds): array {
        $successCount = 0;
        $errorCount = 0;

        foreach ($photoIds as $id) {
            $result = $this->delete((int)$id);
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'message' => "Toplam: " . count($photoIds) . " fotoğraf. Silinen: $successCount, Hatalı: $errorCount",
            'success_count' => $successCount,
            'error_count' => $errorCount
        ];
    }

    public function rename(int $id, string $newName): array {
        $photo = $this->getById($id);
        if (!$photo) {
            return ['success' => false, 'message' => 'Fotoğraf bulunamadı.'];
        }
        
        // Dosya uzantısını koru
        $extension = pathinfo($photo['filename'], PATHINFO_EXTENSION);
        
        // Yeni isimde uzantı var mı kontrol et
        $newExtension = pathinfo($newName, PATHINFO_EXTENSION);
        
        // Eğer yeni isimde uzantı yoksa, orijinal uzantıyı ekle
        if (empty($newExtension)) {
            $newFileName = $this->sanitizeFileName($newName . '.' . $extension);
            $newDisplayName = $newName . '.' . $extension; // Görüntülenecek isim
        } else {
            // Uzantı varsa, sanitize et
            $newFileName = $this->sanitizeFileName($newName);
            $newDisplayName = $newName; // Görüntülenecek isim
        }
        
        // Aynı albümde aynı isimde dosya var mı kontrol et
        $newFileName = $this->makeUniqueFileName($newFileName, $photo['album_id'], $id);
        
        // Eğer dosya adı benzersiz hale getirildiyse, görüntülenecek ismi de güncelle
        if ($newFileName !== $this->sanitizeFileName($newDisplayName)) {
            // Benzersiz hale getirilen dosya adını görüntülenecek isme dönüştür
            $newDisplayName = pathinfo($newFileName, PATHINFO_FILENAME) . '.' . pathinfo($newDisplayName, PATHINFO_EXTENSION);
        }
        
        // Albüm bilgilerini al
        $album = new Album($this->db);
        $albumData = $album->getById($photo['album_id']);
        if (!$albumData) {
            return ['success' => false, 'message' => 'Albüm bulunamadı.'];
        }
        
        // Eski ve yeni dosya yolları
        $albumPath = UPLOAD_PATH . '/' . $albumData['path'];
        $oldFilePath = $albumPath . '/' . $photo['filename'];
        $newFilePath = $albumPath . '/' . $newFileName;
        
        // Dosyayı yeniden adlandır
        if (file_exists($oldFilePath) && $oldFilePath !== $newFilePath) {
            // Dosyayı yeniden adlandırmadan önce önbelleği temizle
            clearCache($oldFilePath);
            
            if (!rename($oldFilePath, $newFilePath)) {
                return ['success' => false, 'message' => 'Dosya yeniden adlandırılamadı.'];
            }
            
            // Yeni dosya için önbelleği temizle
            clearCache($newFilePath);
            clearCache($albumPath);
        }
        
        // Veritabanını güncelle
        $sql = "UPDATE photos SET filename = ?, original_name = ? WHERE id = ?";
        $result = $this->db->execute($sql, [$newFileName, $newDisplayName, $id]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Fotoğraf adı başarıyla değiştirildi.'];
        }
        
        return ['success' => false, 'message' => 'Fotoğraf adı değiştirilemedi.'];
    }

    public function move(int $id, int $newAlbumId): array {
        return $this->moveToAlbum($id, $newAlbumId);
    }

    public function moveToAlbum(int $photoId, int $targetAlbumId): array {
        try {
            $photo = $this->getById($photoId);
            if (!$photo) {
                return ['success' => false, 'message' => 'Fotoğraf bulunamadı.'];
            }
            
            // Hedef albüm kontrolü
            $album = new Album($this->db);
            $targetAlbum = $album->getById($targetAlbumId);
            if (!$targetAlbum) {
                return ['success' => false, 'message' => 'Hedef albüm bulunamadı.'];
            }
            
            // Kaynak albüm kontrolü
            $sourceAlbum = $album->getById($photo['album_id']);
            if (!$sourceAlbum) {
                return ['success' => false, 'message' => 'Kaynak albüm bulunamadı.'];
            }
            
            // Aynı albüme taşıma kontrolü
            if ($photo['album_id'] == $targetAlbumId) {
                return ['success' => false, 'message' => 'Fotoğraf zaten bu albümde.'];
            }
            
            // Kaynak ve hedef dosya yolları
            $sourceAlbumPath = UPLOAD_PATH . '/' . $sourceAlbum['path'];
            $targetAlbumPath = UPLOAD_PATH . '/' . $targetAlbum['path'];
            $sourceFilePath = $sourceAlbumPath . '/' . $photo['filename'];
            
            // Hedef albümde aynı isimde dosya var mı kontrol et
            $newFileName = $this->makeUniqueFileName($photo['filename'], $targetAlbumId);
            $targetFilePath = $targetAlbumPath . '/' . $newFileName;
            
            // Hedef klasörü oluştur (yoksa)
            if (!is_dir($targetAlbumPath)) {
                if (!mkdir($targetAlbumPath, 0755, true)) {
                    return ['success' => false, 'message' => 'Hedef klasör oluşturulamadı.'];
                }
            }
            
            // Dosyayı taşı
            if (file_exists($sourceFilePath)) {
                // Dosyayı taşımadan önce önbelleği temizle
                clearCache($sourceFilePath);
                
                if (!copy($sourceFilePath, $targetFilePath)) {
                    return ['success' => false, 'message' => 'Dosya taşınamadı.'];
                }
                
                // Kaynak dosyayı sil
                unlink($sourceFilePath);
                
                // Yeni dosya için önbelleği temizle
                clearCache($targetFilePath);
                clearCache($sourceAlbumPath);
                clearCache($targetAlbumPath);
            }
            
            // Veritabanını güncelle
            $sql = "UPDATE photos SET album_id = ?, filename = ? WHERE id = ?";
            $result = $this->db->execute($sql, [$targetAlbumId, $newFileName, $photoId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Fotoğraf başarıyla taşındı.'];
            }
            
            return ['success' => false, 'message' => 'Fotoğraf taşınamadı.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Hata: ' . $e->getMessage()];
        }
    }

    public function moveMultipleToAlbum(array $photoIds, int $targetAlbumId): array {
        $successCount = 0;
        $errorCount = 0;

        foreach ($photoIds as $id) {
            $result = $this->moveToAlbum((int)$id, $targetAlbumId);
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'message' => "Toplam: " . count($photoIds) . " fotoğraf. Taşınan: $successCount, Hatalı: $errorCount",
            'success_count' => $successCount,
            'error_count' => $errorCount
        ];
    }

    public function getById(int $id): ?array {
        return $this->db->fetch(
            "SELECT p.*, a.name as album_name, a.path as album_path
             FROM photos p 
             LEFT JOIN albums a ON p.album_id = a.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getByAlbum(int $albumId): array {
        return $this->db->fetchAll(
            "SELECT p.*, a.name as album_name, a.path as album_path
             FROM photos p 
             LEFT JOIN albums a ON p.album_id = a.id
             WHERE p.album_id = ?
             ORDER BY p.uploaded_at DESC",
            [$albumId]
        );
    }

    public function getAll(): array {
        return $this->db->fetchAll(
            "SELECT p.*, a.name as album_name, a.path as album_path
             FROM photos p 
             LEFT JOIN albums a ON p.album_id = a.id
             ORDER BY p.uploaded_at DESC"
        );
    }

    public function getPhotoUrl(array $photo): string {
        $albumPath = $photo['album_path'] ?? '';
        return 'uploads/' . $albumPath . '/' . $photo['filename'] . '?v=' . time();
    }

    private function sanitizeFileName(string $fileName): string {
        // Dosya uzantısını ayır
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        
        // Türkçe karakterleri dönüştür
        $baseName = str_replace(
            ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'],
            ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'i', 'o', 's', 'u'],
            $baseName
        );
        
        // Güvenli karakterlere dönüştür
        $baseName = strtolower(trim($baseName));
        $baseName = preg_replace('/[^a-z0-9\-_]/', '-', $baseName);
        $baseName = preg_replace('/-+/', '-', $baseName);
        $baseName = trim($baseName, '-');
        
        if (empty($baseName)) {
            $baseName = 'photo';
        }
        
        return $baseName . '.' . strtolower($extension);
    }

    private function generateUniqueFilename(string $originalFilename, string $albumPath): string {
        $pathInfo = pathinfo($originalFilename);
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $name = $pathInfo['filename'];

        $counter = 1;
        $filename = $originalFilename;

        while (file_exists(UPLOAD_PATH . '/' . $albumPath . '/' . $filename)) {
            $filename = $name . '-' . $counter . $extension;
            $counter++;
        }

        return $filename;
    }

    private function isValidFileType(string $filePath): bool {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        return in_array($mimeType, $this->allowedTypes);
    }

    private function makeUniqueFileName(string $fileName, int $albumId, ?int $excludeId = null): string {
        $originalFileName = $fileName;
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $counter = 1;
        
        while ($this->fileExists($fileName, $albumId, $excludeId)) {
            $fileName = $baseName . '-' . $counter . '.' . $extension;
            $counter++;
        }
        
        return $fileName;
    }

    private function fileExists(string $fileName, int $albumId, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM photos WHERE filename = ? AND album_id = ?";
        $params = [$fileName, $albumId];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result['count'] > 0;
    }

    public function getStats(): array {
        $sql = "SELECT 
                    COUNT(*) as total_photos,
                    SUM(file_size) as total_size,
                    AVG(file_size) as avg_size
                FROM photos";
        return $this->db->fetch($sql) ?? [
            'total_photos' => 0,
            'total_size' => 0,
            'avg_size' => 0
        ];
    }
} 