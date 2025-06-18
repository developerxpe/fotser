/**
 * Fotser - Ana JavaScript Dosyası (No AJAX)
 */

// Global değişkenler
let toastContainer;
let albumInfoSidebar;
let sidebarOverlay;

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // Toast bildirimlerini göster
    initToasts();
    
    // Sidebar yönetimi
    initSidebar();
    
    // Modal yönetimi
    initModals();
    
    // Form yönetimi
    initForms();
    
    // Drag & Drop yönetimi
    initDragDrop();
    
    // Multi-select yönetimi
    initMultiSelect();
    
    // Keyboard shortcuts
    initKeyboardShortcuts();
    
    // Tooltip'leri etkinleştir
    initTooltips();
});

/**
 * Bileşenleri başlat
 */
function initializeComponents() {
    // Toast container oluştur
    createToastContainer();
    
    // Sidebar ve overlay elemanlarını bul
    albumInfoSidebar = document.querySelector('.album-info-sidebar');
    sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    // CSRF token'ı meta tag'a ekle
    const token = document.querySelector('input[name="csrf_token"]');
    if (token) {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = token.value;
        document.head.appendChild(meta);
    }
}

/**
 * Event listener'ları başlat
 */
function initializeEventListeners() {
    // Sidebar kapatma
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    const sidebarCloseBtn = document.querySelector('.sidebar-close');
    if (sidebarCloseBtn) {
        sidebarCloseBtn.addEventListener('click', closeSidebar);
    }
    
    // ESC tuşu ile sidebar kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && albumInfoSidebar && albumInfoSidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
    
    // Form gönderim onayları
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Çoklu seçim işlemleri
    initializeMultiSelect();
    
    // Drag & Drop (eğer varsa)
    initializeDragAndDrop();
}

/**
 * Toast container oluştur
 */
function createToastContainer() {
    toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);
}

/**
 * Toast bildirimlerini göster
 */
function initToasts() {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        // Toast container yoksa oluştur
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
    }
    
    // Mevcut toast'ları göster
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(function(toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 5000
        });
        toast.show();
        
        // Toast kapandıktan sonra DOM'dan kaldır
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    });
}

/**
 * Toast mesajı göster
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container') || document.querySelector('.toast-container');
    if (!toastContainer) return;
    
    const iconMap = {
        'success': 'fas fa-check-circle text-success',
        'error': 'fas fa-exclamation-circle text-danger',
        'warning': 'fas fa-exclamation-triangle text-warning',
        'info': 'fas fa-info-circle text-info'
    };
    
    const toastHtml = `
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="${iconMap[type] || iconMap.info} me-2"></i>
                <strong class="me-auto">Bildirim</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Kapat"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const newToast = toastContainer.lastElementChild;
    const toast = new bootstrap.Toast(newToast, {
        autohide: true,
        delay: 5000
    });
    
    toast.show();
    
    // Toast kapandıktan sonra DOM'dan kaldır
    newToast.addEventListener('hidden.bs.toast', function() {
        newToast.remove();
    });
}

/**
 * Sidebar yönetimi
 */
function initSidebar() {
    const sidebarTriggers = document.querySelectorAll('[data-sidebar-toggle]');
    const sidebar = document.getElementById('album-info-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const closeBtn = document.querySelector('.sidebar-close');
    
    // Sidebar açma butonları
    sidebarTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const albumId = this.getAttribute('data-album-id');
            if (albumId) {
                openSidebar(albumId);
            }
        });
    });
    
    // Sidebar kapatma
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // ESC tuşu ile kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });
}

function openSidebar(albumId) {
    const sidebar = document.getElementById('album-info-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('album-info-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

/**
 * Modal yönetimi
 */
function initModals() {
    // Modal açma butonları
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('[data-bs-toggle="modal"]');
        if (trigger) {
            const targetId = trigger.getAttribute('data-bs-target');
            const modal = document.querySelector(targetId);
            
            if (modal) {
                // Form alanlarını temizle veya doldur
                const form = modal.querySelector('form');
                if (form) {
                    prepareModalForm(form, trigger);
                }
            }
        }
    });
    
    // Modal kapatma işlemleri
    document.addEventListener('hidden.bs.modal', function(e) {
        const modal = e.target;
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    });
}

function prepareModalForm(form, trigger) {
    // Trigger elementinden veri al ve forma yerleştir
    const albumId = trigger.getAttribute('data-album-id');
    const photoId = trigger.getAttribute('data-photo-id');
    const albumName = trigger.getAttribute('data-album-name');
    const photoName = trigger.getAttribute('data-photo-name');
    const currentAlbum = trigger.getAttribute('data-current-album');
    
    // Form alanlarını doldur
    if (albumId) {
        const albumIdField = form.querySelector('[name="album_id"]');
        if (albumIdField) albumIdField.value = albumId;
    }
    
    if (photoId) {
        const photoIdField = form.querySelector('[name="photo_id"]');
        if (photoIdField) photoIdField.value = photoId;
    }
    
    if (currentAlbum) {
        const currentAlbumField = form.querySelector('[name="current_album"]');
        if (currentAlbumField) currentAlbumField.value = currentAlbum;
    }
    
    if (albumName) {
        const nameField = form.querySelector('[name="new_name"]');
        if (nameField) nameField.value = albumName;
    }
    
    if (photoName) {
        const nameField = form.querySelector('[name="new_name"]');
        if (nameField) nameField.value = photoName.replace(/\.[^/.]+$/, ''); // Uzantıyı kaldır
    }
}

/**
 * Form yönetimi
 */
function initForms() {
    // Form gönderme işlemleri
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Loading state
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gönderiliyor...';
        }
        
        // CSRF token kontrolü
        const csrfToken = form.querySelector('[name="csrf_token"]');
        if (!csrfToken || !csrfToken.value) {
            e.preventDefault();
            showToast('Güvenlik hatası. Lütfen sayfayı yenileyin.', 'error');
            return;
        }
    });
    
    // Dosya yükleme önizlemesi
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            handleFilePreview(this);
        });
    });
}

function handleFilePreview(input) {
    const files = input.files;
    const previewContainer = input.closest('.modal').querySelector('.file-preview');
    
    if (!previewContainer) return;
    
    previewContainer.innerHTML = '';
    
    Array.from(files).forEach(function(file) {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'col-md-3 mb-2';
                preview.innerHTML = `
                    <div class="card">
                        <img src="${e.target.result}" class="card-img-top" style="height: 100px; object-fit: cover;">
                        <div class="card-body p-2">
                            <small class="text-muted">${file.name}</small>
                        </div>
                    </div>
                `;
                previewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
    });
}

/**
 * Drag & Drop yönetimi
 */
function initDragDrop() {
    const dropZones = document.querySelectorAll('.drop-zone');
    
    dropZones.forEach(function(dropZone) {
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        dropZone.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            const fileInput = this.querySelector('input[type="file"]');
            
            if (fileInput && files.length > 0) {
                fileInput.files = files;
                handleFilePreview(fileInput);
            }
        });
    });
}

/**
 * Multi-select yönetimi
 */
function initMultiSelect() {
    // Tümünü seç checkbox'ı
    const selectAllCheckbox = document.getElementById('select-all-photos');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const photoCheckboxes = document.querySelectorAll('.photo-select');
            photoCheckboxes.forEach(function(checkbox) {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateBulkActions();
        });
    }
    
    // Bireysel checkbox'lar
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('photo-select')) {
            updateBulkActions();
        }
    });
    
    // Bulk action butonları
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bulk-action-btn')) {
            e.preventDefault();
            const action = e.target.getAttribute('data-action');
            const selectedPhotos = getSelectedPhotos();
            
            if (selectedPhotos.length === 0) {
                showToast('Lütfen en az bir fotoğraf seçin.', 'warning');
                return;
            }
            
            handleBulkAction(action, selectedPhotos);
        }
    });
}

function updateBulkActions() {
    const selectedPhotos = getSelectedPhotos();
    const bulkActionsContainer = document.querySelector('.bulk-actions');
    const selectAllCheckbox = document.getElementById('select-all-photos');
    
    if (bulkActionsContainer) {
        if (selectedPhotos.length > 0) {
            bulkActionsContainer.style.display = 'block';
            bulkActionsContainer.querySelector('.selected-count').textContent = selectedPhotos.length;
        } else {
            bulkActionsContainer.style.display = 'none';
        }
    }
    
    // Select all checkbox durumunu güncelle
    if (selectAllCheckbox) {
        const photoCheckboxes = document.querySelectorAll('.photo-select');
        const allSelected = photoCheckboxes.length > 0 && selectedPhotos.length === photoCheckboxes.length;
        const someSelected = selectedPhotos.length > 0;
        
        selectAllCheckbox.checked = allSelected;
        selectAllCheckbox.indeterminate = someSelected && !allSelected;
    }
}

function getSelectedPhotos() {
    const checkboxes = document.querySelectorAll('.photo-select:checked');
    return Array.from(checkboxes).map(function(checkbox) {
        return checkbox.value;
    });
}

function handleBulkAction(action, photoIds) {
    switch (action) {
        case 'delete':
            if (confirm('Seçili fotoğrafları silmek istediğinizden emin misiniz?')) {
                submitBulkForm('delete_multiple', photoIds);
            }
            break;
        case 'move':
            const targetAlbum = prompt('Hedef albüm ID\'sini girin:');
            if (targetAlbum) {
                submitBulkForm('move_multiple', photoIds, { new_album_id: targetAlbum });
            }
            break;
    }
}

function submitBulkForm(action, photoIds, additionalData = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'photo_actions.php';
    form.style.display = 'none';
    
    // CSRF token
    const csrfToken = document.querySelector('[name="csrf_token"]').value;
    form.appendChild(createHiddenInput('csrf_token', csrfToken));
    
    // Action
    form.appendChild(createHiddenInput('action', action));
    
    // Photo IDs
    photoIds.forEach(function(id) {
        form.appendChild(createHiddenInput('photo_ids[]', id));
    });
    
    // Additional data
    Object.keys(additionalData).forEach(function(key) {
        form.appendChild(createHiddenInput(key, additionalData[key]));
    });
    
    // Current album
    const currentAlbum = new URLSearchParams(window.location.search).get('album') || '';
    form.appendChild(createHiddenInput('current_album', currentAlbum));
    
    document.body.appendChild(form);
    form.submit();
}

function createHiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

/**
 * Keyboard shortcuts
 */
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+A - Tümünü seç
        if (e.ctrlKey && e.key === 'a' && !e.target.matches('input, textarea')) {
            e.preventDefault();
            const selectAllCheckbox = document.getElementById('select-all-photos');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = !selectAllCheckbox.checked;
                selectAllCheckbox.dispatchEvent(new Event('change'));
            }
        }
        
        // Delete - Seçili öğeleri sil
        if (e.key === 'Delete') {
            const selectedPhotos = getSelectedPhotos();
            if (selectedPhotos.length > 0) {
                handleBulkAction('delete', selectedPhotos);
            }
        }
        
        // F5 - Sayfayı yenile
        if (e.key === 'F5') {
            e.preventDefault();
            window.location.reload();
        }
    });
}

/**
 * Tooltip'leri etkinleştir
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Dropdown menü yönetimi
 */
document.addEventListener('click', function(e) {
    // Dropdown tetikleyicilerinde event propagation'ı durdur
    if (e.target.closest('.dropdown button[data-bs-toggle="dropdown"]')) {
        e.stopPropagation();
    }
});

/**
 * Sayfa yüklenme durumu
 */
window.addEventListener('beforeunload', function() {
    // Loading göster
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    loadingOverlay.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
    loadingOverlay.style.zIndex = '9999';
    loadingOverlay.innerHTML = '<div class="spinner-border text-primary"></div>';
    document.body.appendChild(loadingOverlay);
});

/**
 * Utility fonksiyonlar
 */
function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
}

function formatNumber(number) {
    return new Intl.NumberFormat('tr-TR').format(number);
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

// Global fonksiyonları window objesine ekle
window.fotser = {
    showToast,
    hideToast,
    openSidebar,
    closeSidebar,
    loadAlbumInfo,
    getSelectedIds,
    showModal,
    hideModal,
    refreshPage,
    submitFormAjax
}; 