(function(){
    'use strict';
    
    let remoteSites = [];
    let selectedSites = [];
    
    // Добавляем кнопку в тулбар
    function addRemoteControlButton() {
        const toolbar = document.querySelector('.topbar');
        if (!toolbar || document.getElementById('btnRemoteControl')) return;
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btnRemoteControl';
        btn.className = 'btn';
        btn.textContent = '🌐 Мои сайты';
        btn.addEventListener('click', openRemoteModal);
        
        const exportBtn = document.getElementById('btnExport');
        if (exportBtn && exportBtn.nextSibling) {
            exportBtn.parentNode.insertBefore(btn, exportBtn.nextSibling);
        } else {
            toolbar.appendChild(btn);
        }
    }
    
    // Создаем модальное окно
    function createRemoteModal() {
        if (document.getElementById('remoteModalBackdrop')) return;
        
        const backdrop = document.createElement('div');
        backdrop.id = 'remoteModalBackdrop';
        backdrop.className = 'remote-backdrop hidden';
        
        const modal = document.createElement('div');
        modal.className = 'remote-modal';
        
        modal.innerHTML = `
            <div class="remote-modal__header">
                <div class="remote-modal__title">🌐 Удаленное управление сайтами</div>
                <button type="button" class="remote-close">×</button>
            </div>
            <div class="remote-modal__body">
                <div class="remote-section">
                    <div class="remote-add-form">
                        <input type="text" class="remote-input" id="remoteDomainInput" placeholder="Введите домен сайта (example.com)">
                        <input type="text" class="remote-input" id="remoteApiKeyInput" placeholder="API ключ" style="width:200px">
                        <button type="button" class="remote-btn primary" id="remoteAddBtn">➕ Добавить</button>
                    </div>
                </div>
                
                <div class="remote-section">
                    <h3 style="color:#9fb2c6;margin:0 0 15px">Подключенные сайты:</h3>
                    <div class="remote-sites-list" id="remoteSitesList">
                        <div style="color:#6b7280;text-align:center;padding:20px">Нет подключенных сайтов</div>
                    </div>
                </div>
                
                <div class="remote-section">
                    <h3 style="color:#9fb2c6;margin:0 0 15px">Массовые операции:</h3>
                    <div class="remote-bulk-actions">
                        <button type="button" class="remote-btn" id="remoteBulkFile">📁 Заменить файл на всех</button>
                        <button type="button" class="remote-btn" id="remoteBulkLink">🔗 Заменить ссылку на всех</button>
                        <button type="button" class="remote-btn" id="remoteBulkTelegram">🔔 Обновить Telegram на всех</button>
                    </div>
                </div>
            </div>
        `;
        
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        
        // Обработчики
        modal.querySelector('.remote-close').addEventListener('click', closeRemoteModal);
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeRemoteModal();
        });
        
        document.getElementById('remoteAddBtn').addEventListener('click', addRemoteSite);
        document.getElementById('remoteBulkFile').addEventListener('click', bulkReplaceFile);
        document.getElementById('remoteBulkLink').addEventListener('click', bulkReplaceLink);
        document.getElementById('remoteBulkTelegram').addEventListener('click', bulkUpdateTelegram);
    }
    
    function openRemoteModal() {
        createRemoteModal();
        loadRemoteSites();
        document.getElementById('remoteModalBackdrop').classList.remove('hidden');
    }
    
    function closeRemoteModal() {
        document.getElementById('remoteModalBackdrop').classList.add('hidden');
    }
    
    async function loadRemoteSites() {
        try {
            const response = await fetch('/editor/remote_control_api.php?action=getSites');
            const data = await response.json();
            
            if (data.ok) {
                remoteSites = data.sites || [];
                renderSitesList();
            }
        } catch (error) {
            console.error('Ошибка загрузки сайтов:', error);
        }
    }
    
    function renderSitesList() {
        const container = document.getElementById('remoteSitesList');
        
        if (remoteSites.length === 0) {
            container.innerHTML = '<div style="color:#6b7280;text-align:center;padding:20px">Нет подключенных сайтов</div>';
            return;
        }
        
        container.innerHTML = remoteSites.map(site => `
            <div class="remote-site-item" data-domain="${site.domain}">
                <input type="checkbox" class="remote-checkbox site-selector" value="${site.domain}">
                <div class="remote-site-info">
                    <div class="remote-site-domain">${site.domain}</div>
                    <div class="remote-site-status ${site.status}">${site.status === 'online' ? '✅ Подключен' : '❌ Недоступен'}</div>
                </div>
                <div class="remote-site-actions">
                    <button class="remote-action-btn" onclick="checkConnection('${site.domain}')">🔌 Проверить</button>
                    <button class="remote-action-btn" onclick="replaceFile('${site.domain}')">📁 Файл</button>
                    <button class="remote-action-btn" onclick="replaceLink('${site.domain}')">🔗 Ссылка</button>
                    <button class="remote-action-btn" onclick="updateTelegram('${site.domain}')">🔔 Telegram</button>
                    <button class="remote-action-btn" style="color:#ef4444" onclick="removeSite('${site.domain}')">🗑️</button>
                </div>
            </div>
        `).join('');
        
        // Добавляем обработчики для чекбоксов
        container.querySelectorAll('.site-selector').forEach(cb => {
            cb.addEventListener('change', updateSelectedSites);
        });
    }
    
    function updateSelectedSites() {
        selectedSites = Array.from(document.querySelectorAll('.site-selector:checked'))
            .map(cb => cb.value);
    }
    
    async function addRemoteSite() {
        const domain = document.getElementById('remoteDomainInput').value.trim();
        const apiKey = document.getElementById('remoteApiKeyInput').value.trim();
        
        if (!domain || !apiKey) {
            alert('Введите домен и API ключ');
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'addSite');
        fd.append('domain', domain);
        fd.append('apiKey', apiKey);
        
        try {
            const response = await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
            
            const data = await response.json();
            if (data.ok) {
                document.getElementById('remoteDomainInput').value = '';
                document.getElementById('remoteApiKeyInput').value = '';
                loadRemoteSites();
            } else {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            alert('Ошибка подключения: ' + error.message);
        }
    }
    
    // Глобальные функции для кнопок
    window.checkConnection = async function(domain) {
        const fd = new FormData();
        fd.append('action', 'checkConnection');
        fd.append('domain', domain);
        
        try {
            const response = await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
            
            const data = await response.json();
            if (data.ok) {
                alert(data.status === 'online' ? '✅ Сайт доступен' : '❌ Сайт недоступен');
                loadRemoteSites();
            }
        } catch (error) {
            alert('Ошибка проверки: ' + error.message);
        }
    };
    
    window.replaceFile = async function(domain) {
        const input = document.createElement('input');
        input.type = 'file';
        input.onchange = async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const oldUrl = prompt('Введите URL старого файла для замены:');
            if (!oldUrl) return;
            
            const fd = new FormData();
            fd.append('action', 'replaceFile');
            fd.append('domain', domain);
            fd.append('file', file);
            fd.append('oldUrl', oldUrl);
            
            try {
                const response = await fetch('/editor/remote_control_api.php', {
                    method: 'POST',
                    body: fd
                });
                
                const data = await response.json();
                if (data.ok) {
                    alert('✅ Файл успешно заменен');
                } else {
                    alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
                }
            } catch (error) {
                alert('Ошибка замены: ' + error.message);
            }
        };
        input.click();
    };
    
    window.replaceLink = async function(domain) {
        const oldUrl = prompt('Введите старую ссылку:');
        if (!oldUrl) return;
        
        const newUrl = prompt('Введите новую ссылку:');
        if (!newUrl) return;
        
        const fd = new FormData();
        fd.append('action', 'replaceLink');
        fd.append('domain', domain);
        fd.append('oldUrl', oldUrl);
        fd.append('newUrl', newUrl);
        
        try {
            const response = await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
            
            const data = await response.json();
            if (data.ok) {
                alert('✅ Ссылки успешно заменены');
            } else {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            alert('Ошибка замены: ' + error.message);
        }
    };
    
    window.updateTelegram = async function(domain) {
        const chatId = prompt('Введите Telegram Chat ID:');
        if (!chatId) return;
        
        const botToken = prompt('Введите Bot Token:');
        if (!botToken) return;
        
        const fd = new FormData();
        fd.append('action', 'updateTelegram');
        fd.append('domain', domain);
        fd.append('chatId', chatId);
        fd.append('botToken', botToken);
        
        try {
            const response = await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
            
            const data = await response.json();
            if (data.ok) {
                alert('✅ Telegram настройки обновлены');
            } else {
                alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            alert('Ошибка обновления: ' + error.message);
        }
    };
    
    window.removeSite = async function(domain) {
        if (!confirm(`Удалить сайт ${domain} из списка?`)) return;
        
        const fd = new FormData();
        fd.append('action', 'removeSite');
        fd.append('domain', domain);
        
        try {
            const response = await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
            
            const data = await response.json();
            if (data.ok) {
                loadRemoteSites();
            }
        } catch (error) {
            alert('Ошибка удаления: ' + error.message);
        }
    };
    
    // Массовые операции
    async function bulkReplaceFile() {
        if (selectedSites.length === 0) {
            alert('Выберите хотя бы один сайт');
            return;
        }
        
        const input = document.createElement('input');
        input.type = 'file';
        input.onchange = async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const oldUrl = prompt('Введите URL старого файла для замены на всех сайтах:');
            if (!oldUrl) return;
            
            for (const domain of selectedSites) {
                await window.replaceFile(domain);
            }
            
            alert(`✅ Файл заменен на ${selectedSites.length} сайтах`);
        };
        input.click();
    }
    
    async function bulkReplaceLink() {
        if (selectedSites.length === 0) {
            alert('Выберите хотя бы один сайт');
            return;
        }
        
        const oldUrl = prompt('Введите старую ссылку:');
        if (!oldUrl) return;
        
        const newUrl = prompt('Введите новую ссылку:');
        if (!newUrl) return;
        
        for (const domain of selectedSites) {
            const fd = new FormData();
            fd.append('action', 'replaceLink');
            fd.append('domain', domain);
            fd.append('oldUrl', oldUrl);
            fd.append('newUrl', newUrl);
            
            await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
        }
        
        alert(`✅ Ссылки заменены на ${selectedSites.length} сайтах`);
    }
    
    async function bulkUpdateTelegram() {
        if (selectedSites.length === 0) {
            alert('Выберите хотя бы один сайт');
            return;
        }
        
        const chatId = prompt('Введите Telegram Chat ID:');
        if (!chatId) return;
        
        const botToken = prompt('Введите Bot Token:');
        if (!botToken) return;
        
        for (const domain of selectedSites) {
            const fd = new FormData();
            fd.append('action', 'updateTelegram');
            fd.append('domain', domain);
            fd.append('chatId', chatId);
            fd.append('botToken', botToken);
            
            await fetch('/editor/remote_control_api.php', {
                method: 'POST',
                body: fd
            });
        }
        
        alert(`✅ Telegram обновлен на ${selectedSites.length} сайтах`);
    }
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        addRemoteControlButton();
    });
})();