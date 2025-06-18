# Fotser - Fotoğraf Albümü Yönetim Sistemi

<div align="center">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-blue" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-purple" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/MySQL-5.7%2B-orange" alt="MySQL 5.7+">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="License MIT">
</div>

<br>

Fotser, modern ve kullanıcı dostu bir fotoğraf albümü yönetim sistemidir. Hiyerarşik albüm yapısı ve kolay kurulum sihirbazı ile fotoğraflarınızı düzenli bir şekilde yönetmenizi sağlar.

![Fotser Screenshot](screenshot.png)

## ✨ Özellikler

- **Hiyerarşik Albüm Yapısı**: Ana ve alt albümler oluşturarak fotoğraflarınızı organize edin
- **Çoklu Fotoğraf Yükleme**: Sürükle-bırak ile birden çok fotoğraf yükleyin
- **Arama ve Filtreleme**: Fotoğraf ve albümleri isim, tarih bazlı arayın ve filtreleyin
- **Toplu İşlemler**: Fotoğrafları toplu taşıma, silme ve yeniden adlandırma
- **Responsive Tasarım**: Mobil cihazlarla uyumlu modern arayüz
- **Güvenlik Odaklı**: CSRF koruması, SQL injection ve XSS önleme

## 🚀 Hızlı Kurulum

1. Dosyaları sunucunuza yükleyin
2. `http://siteniz.com/fotser/setup.php` adresini ziyaret edin
3. Kurulum sihirbazını takip edin:
   - Veritabanı bilgilerinizi girin
   - Yönetici hesabı oluşturun
   - Giriş yapın ve kullanmaya başlayın

## 📋 Sistem Gereksinimleri

- PHP 8.0 veya üzeri
- MySQL 5.7+ / MariaDB 10.2+
- GD veya Imagick PHP eklentisi
- mod_rewrite (Apache) veya eşdeğeri

## 🔧 Teknik Detaylar

### Desteklenen Dosya Formatları
- JPG/JPEG
- PNG
- GIF
- WebP

### Dosya Yapısı
```
fotser/
├── assets/          # CSS ve JavaScript dosyaları
├── includes/        # PHP sınıfları ve yardımcı fonksiyonlar
├── uploads/         # Yüklenen fotoğraflar (otomatik oluşturulur)
├── config.php       # Konfigürasyon (kurulum sonrası oluşturulur)
├── setup.php        # Kurulum sihirbazı
├── login.php        # Giriş sayfası
└── index.php        # Ana sayfa
```

## 🔒 Güvenlik Özellikleri

- PDO prepared statements ile SQL injection koruması
- CSRF token koruması
- Dosya türü ve boyut kontrolü
- Güvenli dosya adlandırma
- XSS koruması (htmlspecialchars)

## 📝 Lisans

Bu proje MIT Lisansı altında lisanslanmıştır. Daha fazla bilgi için [LICENSE](LICENSE) dosyasına bakın.