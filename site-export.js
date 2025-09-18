(function(){
    'use strict';
    
    let selectedPages = [];
    
    // Заменяем стандартную кнопку Экспорт
    function replaceExportButton() {
        const btn = document.getElementById('btnExport');
        if (!btn) return;
        
        // Удаляем старый обработчик
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        // Добавляем новый обработчик
        newBtn.addEventListener('click', openExportModal);
    }
    
    // Создаем модальное окно экспорта
    function createExportModal() {
        if (document.getElementById('exportModalBackdrop')) return;
        
        const backdrop = document.createElement('div');
        backdrop.id = 'exportModalBackdrop';
        backdrop.className = 'export-backdrop hidden';
        
        const modal = document.createElement('div');
        modal.className = 'export-modal';
        
        modal.innerHTML = `
            <div class="export-modal__header">
                <div class="export-modal__title">📦 Экспорт сайта</div>
                <button type="button" class="export-close">×</button>
            </div>
            <div class="export-modal__body">
                <div class="export-section">
                    <label class="export-label">Выберите страницы для экспорта:</label>
                    <div class="export-actions">
                        <button type="button" class="export-btn small" id="exportSelectAll">Выбрать все</button>
                        <button type="button" class="export-btn small" id="exportDeselectAll">Снять все</button>
                    </div>
                    <div class="export-pages" id="exportPagesList">
                        <div style="color:#6b7280">Загрузка...</div>
                    </div>
                </div>
                
                <div class="export-section">
                    <label class="export-label">Настройки экспорта:</label>
                    <div class="export-settings">
                        <label class="export-checkbox-item">
                            <input type="checkbox" id="exportIncludeApi" class="export-checkbox" checked>
                            <span>Включить API для удаленного управления</span>
                        </label>
                        <label class="export-checkbox-item">
                            <input type="checkbox" id="exportIncludeTracking" class="export-checkbox">
                            <span>Включить отслеживание посещений</span>
                        </label>
                    </div>
                </div>
                
                <div class="export-section">
                    <button type="button" class="export-btn primary" id="startExport">📥 Экспортировать</button>
                    <div id="exportStatus"></div>
                </div>
            </div>
        `;
        
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        
        // Обработчики
        modal.querySelector('.export-close').addEventListener('click', closeExportModal);
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeExportModal();
        });
        
        document.getElementById('exportSelectAll').addEventListener('click', selectAllPages);
        document.getElementById('exportDeselectAll').addEventListener('click', deselectAllPages);
        document.getElementById('startExport').addEventListener('click', startExport);
    }
    
    function openExportModal() {
        createExportModal();
        loadPagesList();
        document.getElementById('exportModalBackdrop').classList.remove('hidden');
    }
    
    function closeExportModal() {
        document.getElementById('exportModalBackdrop').classList.add('hidden');
    }
    
    async function loadPagesList() {
        try {
            const response = await fetch('/editor/api.php?action=listPages');
            const data = await response.json();
            
            if (data.ok) {
                const container = document.getElementById('exportPagesList');
                container.innerHTML = data.pages.map(page => `
                    <label class="export-page-item">
                        <input type="checkbox" value="${page.id}" class="export-page-check">
                        <span>${page.name}</span>
                    </label>
                `).join('');
                
                container.querySelectorAll('.export-page-check').forEach(cb => {
                    cb.addEventListener('change', updateSelectedPages);
                });
                
                // По умолчанию выбираем все страницы
                selectAllPages();
            }
        } catch (error) {
            console.error('Ошибка загрузки страниц:', error);
        }
    }
    
    function updateSelectedPages() {
        selectedPages = Array.from(document.querySelectorAll('.export-page-check:checked'))
            .map(cb => cb.value);
    }
    
    function selectAllPages() {
        document.querySelectorAll('.export-page-check').forEach(cb => cb.checked = true);
        updateSelectedPages();
    }
    
    function deselectAllPages() {
        document.querySelectorAll('.export-page-check').forEach(cb => cb.checked = false);
        updateSelectedPages();
    }
    
    async function startExport() {
        if (selectedPages.length === 0) {
            showStatus('Выберите хотя бы одну страницу', 'error');
            return;
        }
        
        const includeApi = document.getElementById('exportIncludeApi').checked;
        const includeTracking = document.getElementById('exportIncludeTracking').checked;
        
        showStatus('Подготовка экспорта...', 'info');
        
        const fd = new FormData();
        fd.append('action', 'exportSite');
        fd.append('pages', JSON.stringify(selectedPages));
        fd.append('includeApi', includeApi ? '1' : '0');
        fd.append('includeTracking', includeTracking ? '1' : '0');
        
        try {
            const response = await fetch('/editor/export_api.php', {
                method: 'POST',
                body: fd
            });
            
            if (!response.ok) throw new Error('Ошибка сервера');
            
            // Скачиваем ZIP файл
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `site-export-${Date.now()}.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showStatus('Экспорт завершен!', 'success');
            setTimeout(closeExportModal, 2000);
        } catch (error) {
            showStatus('Ошибка экспорта: ' + error.message, 'error');
        }
    }
    
    function showStatus(message, type = 'info') {
        const status = document.getElementById('exportStatus');
        status.className = 'export-status ' + type;
        status.textContent = message;
        status.style.display = 'block';
    }
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(replaceExportButton, 100);
    });
})();