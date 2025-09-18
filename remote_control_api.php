<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$db = dirname(__DIR__) . '/data/zerro_blog.db';
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создаем таблицу для удаленных сайтов
$pdo->exec("CREATE TABLE IF NOT EXISTS remote_sites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT UNIQUE NOT NULL,
    api_key TEXT NOT NULL,
    status TEXT DEFAULT 'offline',
    last_check TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$action = $_REQUEST['action'] ?? '';

switch($action) {
    case 'getSites':
        getSites($pdo);
        break;
        
    case 'addSite':
        addSite($pdo);
        break;
        
    case 'removeSite':
        removeSite($pdo);
        break;
        
    case 'checkConnection':
        checkConnection($pdo);
        break;
        
    case 'replaceFile':
        replaceFile($pdo);
        break;
        
    case 'replaceLink':
        replaceLink($pdo);
        break;
        
    case 'updateTelegram':
        updateTelegram($pdo);
        break;
        
    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}

function getSites($pdo) {
    $stmt = $pdo->query("SELECT * FROM remote_sites ORDER BY domain");
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'sites' => $sites]);
}

function addSite($pdo) {
    $domain = $_POST['domain'] ?? '';
    $apiKey = $_POST['apiKey'] ?? '';
    
    if (!$domain || !$apiKey) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        return;
    }
    
    // Проверяем подключение
    $status = testConnection($domain, $apiKey) ? 'online' : 'offline';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO remote_sites (domain, api_key, status) VALUES (?, ?, ?)");
        $stmt->execute([$domain, $apiKey, $status]);
        echo json_encode(['ok' => true, 'status' => $status]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Сайт уже добавлен']);
    }
}

function removeSite($pdo) {
    $domain = $_POST['domain'] ?? '';
    
    $stmt = $pdo->prepare("DELETE FROM remote_sites WHERE domain = ?");
    $stmt->execute([$domain]);
    
    echo json_encode(['ok' => true]);
}

function checkConnection($pdo) {
    $domain = $_POST['domain'] ?? '';
    
    $stmt = $pdo->prepare("SELECT api_key FROM remote_sites WHERE domain = ?");
    $stmt->execute([$domain]);
    $apiKey = $stmt->fetchColumn();
    
    $status = testConnection($domain, $apiKey) ? 'online' : 'offline';
    
    $stmt = $pdo->prepare("UPDATE remote_sites SET status = ?, last_check = CURRENT_TIMESTAMP WHERE domain = ?");
    $stmt->execute([$status, $domain]);
    
    echo json_encode(['ok' => true, 'status' => $status]);
}

function testConnection($domain, $apiKey) {
    $url = "https://{$domain}/remote_api.php";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'getStatus']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: {$apiKey}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return isset($data['status']) && $data['status'] === 'ok';
    }
    
    return false;
}

function replaceFile($pdo) {
    $domain = $_POST['domain'] ?? '';
    $oldUrl = $_POST['oldUrl'] ?? '';
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['ok' => false, 'error' => 'Файл не загружен']);
        return;
    }
    
    // Получаем API ключ
    $stmt = $pdo->prepare("SELECT api_key FROM remote_sites WHERE domain = ?");
    $stmt->execute([$domain]);
    $apiKey = $stmt->fetchColumn();
    
    // Загружаем файл на удаленный сервер
    $url = "https://{$domain}/remote_api.php";
    
    $postData = [
        'action' => 'replaceFile',
        'oldUrl' => $oldUrl,
        'file' => new CURLFile($_FILES['file']['tmp_name'], $_FILES['file']['type'], $_FILES['file']['name'])
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: {$apiKey}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo json_encode($data);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Ошибка подключения к сайту']);
    }
}

function replaceLink($pdo) {
    $domain = $_POST['domain'] ?? '';
    $oldUrl = $_POST['oldUrl'] ?? '';
    $newUrl = $_POST['newUrl'] ?? '';
    
    // Получаем API ключ
    $stmt = $pdo->prepare("SELECT api_key FROM remote_sites WHERE domain = ?");
    $stmt->execute([$domain]);
    $apiKey = $stmt->fetchColumn();
    
    // Отправляем запрос на удаленный сервер
    $url = "https://{$domain}/remote_api.php";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'replaceLink',
        'oldUrl' => $oldUrl,
        'newUrl' => $newUrl
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: {$apiKey}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
}

function updateTelegram($pdo) {
    $domain = $_POST['domain'] ?? '';
    $chatId = $_POST['chatId'] ?? '';
    $botToken = $_POST['botToken'] ?? '';
    
    // Получаем API ключ
    $stmt = $pdo->prepare("SELECT api_key FROM remote_sites WHERE domain = ?");
    $stmt->execute([$domain]);
    $apiKey = $stmt->fetchColumn();
    
    // Отправляем запрос на удаленный сервер
    $url = "https://{$domain}/remote_api.php";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'updateTelegram',
        'chatId' => $chatId,
        'botToken' => $botToken
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Api-Key: {$apiKey}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
}