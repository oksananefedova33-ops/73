<?php
declare(strict_types=1);
ini_set('memory_limit', '256M');
set_time_limit(300);

$action = $_REQUEST['action'] ?? '';

if ($action === 'exportSite') {
    $pages = json_decode($_POST['pages'] ?? '[]', true);
    $includeApi = $_POST['includeApi'] === '1';
    $includeTracking = $_POST['includeTracking'] === '1';
    
    if (empty($pages)) {
        http_response_code(400);
        echo json_encode(['error' => 'Не выбраны страницы']);
        exit;
    }
    
    // Создаем временную директорию
    $tempDir = sys_get_temp_dir() . '/export_' . uniqid();
    mkdir($tempDir, 0777, true);
    
    // Копируем основные файлы
    exportMainFiles($tempDir, $pages, $includeApi, $includeTracking);
    
    // Создаем ZIP архив
    $zipFile = $tempDir . '.zip';
    createZip($tempDir, $zipFile);
    
    // Отправляем файл
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="site-export.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    
    // Очистка
    deleteDirectory($tempDir);
    unlink($zipFile);
    exit;
}

function exportMainFiles($dir, $pages, $includeApi, $includeTracking) {
    $db = dirname(__DIR__) . '/data/zerro_blog.db';
    $pdo = new PDO('sqlite:' . $db);
    
    // Создаем структуру директорий
    mkdir($dir . '/data', 0777, true);
    mkdir($dir . '/editor/uploads', 0777, true);
    mkdir($dir . '/ui', 0777, true);
    
    // Экспортируем базу данных (только выбранные страницы)
    $exportDb = $dir . '/data/site.db';
    $exportPdo = new PDO('sqlite:' . $exportDb);
    
    // Копируем структуру таблиц
    $exportPdo->exec("CREATE TABLE pages AS SELECT * FROM pages WHERE 0");
    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='pages'");
    $createSql = $stmt->fetchColumn();
    $exportPdo->exec($createSql);
    
    // Копируем выбранные страницы
    $placeholders = implode(',', array_fill(0, count($pages), '?'));
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id IN ($placeholders)");
    $stmt->execute($pages);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cols = array_keys($row);
        $values = array_values($row);
        $sql = "INSERT INTO pages (" . implode(',', $cols) . ") VALUES (" . 
               implode(',', array_fill(0, count($cols), '?')) . ")";
        $exportPdo->prepare($sql)->execute($values);
        
        // Копируем связанные файлы из uploads
        copyPageFiles($row, $dir);
    }
    
    // Создаем index.php
    createIndexFile($dir, $includeTracking);
    
    // Создаем .htaccess
    createHtaccess($dir);
    
    // Если включен API, создаем remote_api.php
    if ($includeApi) {
        createRemoteApi($dir);
    }
}

function copyPageFiles($pageData, $dir) {
    // Парсим JSON и находим все файлы из /editor/uploads
    $data = json_decode($pageData['data_json'] ?? '{}', true);
    
    foreach ($data['elements'] ?? [] as $element) {
        if ($element['type'] === 'image' && isset($element['src'])) {
            copyUploadedFile($element['src'], $dir);
        }
        if ($element['type'] === 'video' && isset($element['src'])) {
            copyUploadedFile($element['src'], $dir);
        }
        if ($element['type'] === 'filebtn' && isset($element['fileUrl'])) {
            copyUploadedFile($element['fileUrl'], $dir);
        }
    }
}

function copyUploadedFile($url, $dir) {
    if (strpos($url, '/editor/uploads/') === 0) {
        $filename = basename($url);
        $source = dirname(__DIR__) . '/uploads/' . $filename;
        $dest = $dir . '/editor/uploads/' . $filename;
        
        if (file_exists($source)) {
            copy($source, $dest);
        }
    }
}

function createIndexFile($dir, $includeTracking) {
    $indexContent = <<<'PHP'
<?php
$db = __DIR__ . '/data/site.db';
$pdo = new PDO('sqlite:' . $db);

// Получаем запрошенную страницу
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$slug = trim($path, '/');

// Определяем страницу
if (empty($slug)) {
    $stmt = $pdo->query("SELECT * FROM pages ORDER BY id ASC LIMIT 1");
} else {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
    $stmt->execute([$slug]);
}

$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 - Страница не найдена</h1>";
    exit;
}

// Выводим страницу
echo renderPage($page);

function renderPage($page) {
    $data = json_decode($page['data_json'] ?? '{}', true);
    $title = htmlspecialchars($page['meta_title'] ?? $page['name'], ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES, 'UTF-8');
    
    $html = '<!DOCTYPE html><html lang="ru"><head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $html .= '<title>' . $title . '</title>';
    $html .= '<meta name="description" content="' . $description . '">';
    $html .= '<style>body{margin:0;background:#0e141b;color:#e6f0fa}.wrap{position:relative;min-height:100vh}.el{position:absolute;box-sizing:border-box}</style>';
    $html .= '</head><body><div class="wrap">';
    
    // Рендерим элементы
    foreach ($data['elements'] ?? [] as $element) {
        $html .= renderElement($element);
    }
    
    $html .= '</div>';
PHP;

    if ($includeTracking) {
        $indexContent .= <<<'PHP'
    
    // Добавляем трекинг
    $html .= '<script src="/tracking.js"></script>';
PHP;
    }

    $indexContent .= <<<'PHP'
    
    $html .= '</body></html>';
    return $html;
}

function renderElement($e) {
    // Здесь логика рендеринга элементов
    return '';
}
PHP;
    
    file_put_contents($dir . '/index.php', $indexContent);
}

function createHtaccess($dir) {
    $htaccess = <<<'HTACCESS'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?slug=$1 [QSA,L]
HTACCESS;
    
    file_put_contents($dir . '/.htaccess', $htaccess);
}

function createRemoteApi($dir) {
    // Проверяем наличие шаблона
    $templatePath = __DIR__ . '/templates/remote_api.php';
    
    // Генерируем уникальный API ключ
    $apiKey = bin2hex(random_bytes(32));
    
    if (file_exists($templatePath)) {
        // Если шаблон существует, копируем и заменяем ключ
        $content = file_get_contents($templatePath);
        $content = str_replace('YOUR_UNIQUE_API_KEY_HERE', $apiKey, $content);
        file_put_contents($dir . '/remote_api.php', $content);
    } else {
        // Если шаблона нет, создаем полный API файл
        $apiContent = <<<PHP
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// Уникальный API ключ для этого сайта
define('API_KEY', '$apiKey');

// Проверка API ключа
\$apiKey = \$_SERVER['HTTP_X_API_KEY'] ?? '';
if (\$apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

\$action = \$_POST['action'] ?? '';
\$db = __DIR__ . '/data/site.db';

try {
    \$pdo = new PDO('sqlite:' . \$db);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception \$e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

switch(\$action) {
    case 'getStatus':
        echo json_encode(['status' => 'ok', 'version' => '1.0', 'site' => \$_SERVER['HTTP_HOST']]);
        break;
        
    case 'replaceFile':
        handleFileReplace(\$pdo);
        break;
        
    case 'replaceLink':
        handleLinkReplace(\$pdo);
        break;
        
    case 'updateTelegram':
        handleTelegramUpdate(\$pdo);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}

function handleFileReplace(\$pdo) {
    \$oldUrl = \$_POST['oldUrl'] ?? '';
    
    if (!isset(\$_FILES['file'])) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
        return;
    }
    
    // Создаем директорию если не существует
    \$uploadDir = __DIR__ . '/editor/uploads/';
    if (!is_dir(\$uploadDir)) {
        mkdir(\$uploadDir, 0777, true);
    }
    
    \$newFileName = uniqid('file_') . '_' . basename(\$_FILES['file']['name']);
    \$newPath = \$uploadDir . \$newFileName;
    
    if (move_uploaded_file(\$_FILES['file']['tmp_name'], \$newPath)) {
        \$newUrl = '/editor/uploads/' . \$newFileName;
        
        // Обновляем все ссылки в базе данных
        \$stmt = \$pdo->query("SELECT * FROM pages");
        \$updated = 0;
        
        while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
            \$data = json_decode(\$row['data_json'], true);
            \$changed = false;
            
            foreach (\$data['elements'] ?? [] as &\$element) {
                // Проверяем файлы в кнопках-файлах
                if (isset(\$element['fileUrl']) && \$element['fileUrl'] === \$oldUrl) {
                    \$element['fileUrl'] = \$newUrl;
                    \$changed = true;
                    \$updated++;
                }
                // Проверяем изображения
                if (isset(\$element['src']) && \$element['src'] === \$oldUrl) {
                    \$element['src'] = \$newUrl;
                    \$changed = true;
                    \$updated++;
                }
            }
            
            if (\$changed) {
                \$updateStmt = \$pdo->prepare("UPDATE pages SET data_json = ? WHERE id = ?");
                \$updateStmt->execute([json_encode(\$data, JSON_UNESCAPED_UNICODE), \$row['id']]);
            }
        }
        
        // Удаляем старый файл если он локальный
        if (strpos(\$oldUrl, '/editor/uploads/') === 0) {
            \$oldPath = __DIR__ . \$oldUrl;
            if (file_exists(\$oldPath)) {
                unlink(\$oldPath);
            }
        }
        
        echo json_encode(['ok' => true, 'updated' => \$updated, 'newUrl' => \$newUrl]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    }
}

function handleLinkReplace(\$pdo) {
    \$oldUrl = \$_POST['oldUrl'] ?? '';
    \$newUrl = \$_POST['newUrl'] ?? '';
    
    if (empty(\$oldUrl) || empty(\$newUrl)) {
        echo json_encode(['ok' => false, 'error' => 'URLs required']);
        return;
    }
    
    \$stmt = \$pdo->query("SELECT * FROM pages");
    \$updated = 0;
    
    while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
        \$data = json_decode(\$row['data_json'], true);
        \$changed = false;
        
        foreach (\$data['elements'] ?? [] as &\$element) {
            // Проверяем кнопки-ссылки
            if (isset(\$element['url']) && \$element['url'] === \$oldUrl) {
                \$element['url'] = \$newUrl;
                \$changed = true;
                \$updated++;
            }
        }
        
        if (\$changed) {
            \$updateStmt = \$pdo->prepare("UPDATE pages SET data_json = ? WHERE id = ?");
            \$updateStmt->execute([json_encode(\$data, JSON_UNESCAPED_UNICODE), \$row['id']]);
        }
    }
    
    echo json_encode(['ok' => true, 'updated' => \$updated]);
}

function handleTelegramUpdate(\$pdo) {
    \$chatId = \$_POST['chatId'] ?? '';
    \$botToken = \$_POST['botToken'] ?? '';
    
    if (empty(\$chatId) || empty(\$botToken)) {
        echo json_encode(['ok' => false, 'error' => 'Chat ID and Bot Token required']);
        return;
    }
    
    // Создаем директорию для конфигов если не существует
    \$configDir = __DIR__ . '/data/';
    if (!is_dir(\$configDir)) {
        mkdir(\$configDir, 0777, true);
    }
    
    // Сохраняем настройки Telegram
    \$configFile = \$configDir . 'telegram_config.json';
    \$config = [
        'chat_id' => \$chatId,
        'bot_token' => \$botToken,
        'enabled' => true,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if (file_put_contents(\$configFile, json_encode(\$config, JSON_PRETTY_PRINT))) {
        echo json_encode(['ok' => true, 'message' => 'Telegram settings updated']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to save settings']);
    }
}
PHP;
        
        file_put_contents($dir . '/remote_api.php', $apiContent);
    }
    
    // Сохраняем API ключ в отдельный файл для пользователя
    $keyInfo = "================================================\n";
    $keyInfo .= "         ВАЖНО! СОХРАНИТЕ ЭТУ ИНФОРМАЦИЮ\n";
    $keyInfo .= "================================================\n\n";
    $keyInfo .= "Домен сайта: [ваш_домен.com]\n";
    $keyInfo .= "API ключ: " . $apiKey . "\n\n";
    $keyInfo .= "Этот ключ необходим для удаленного управления\n";
    $keyInfo .= "сайтом из панели редактора.\n\n";
    $keyInfo .= "Как использовать:\n";
    $keyInfo .= "1. Загрузите файлы на хостинг\n";
    $keyInfo .= "2. В редакторе нажмите '🌐 Мои сайты'\n";
    $keyInfo .= "3. Введите домен и этот API ключ\n\n";
    $keyInfo .= "================================================\n";
    
    file_put_contents($dir . '/API_KEY.txt', $keyInfo);
}

function createZip($source, $destination) {
    $zip = new ZipArchive();
    $zip->open($destination, ZipArchive::CREATE);
    
    $source = realpath($source);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        $file = realpath($file);
        if (is_dir($file)) {
            $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
        } else if (is_file($file)) {
            $zip->addFile($file, str_replace($source . '/', '', $file));
        }
    }
    
    $zip->close();
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    
    return rmdir($dir);
}