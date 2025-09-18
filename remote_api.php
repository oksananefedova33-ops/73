<?php
// /templates/remote_api.php - шаблон для экспортированных сайтов
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

// ВАЖНО: Измените этот ключ на уникальный для каждого сайта
define('API_KEY', 'YOUR_UNIQUE_API_KEY_HERE');

// Проверка API ключа
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = __DIR__ . '/data/site.db';
$pdo = new PDO('sqlite:' . $db);

switch($action) {
    case 'getStatus':
        echo json_encode(['status' => 'ok', 'version' => '1.0']);
        break;
        
    case 'replaceFile':
        handleFileReplace($pdo);
        break;
        
    case 'replaceLink':
        handleLinkReplace($pdo);
        break;
        
    case 'updateTelegram':
        handleTelegramUpdate($pdo);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}

function handleFileReplace($pdo) {
    $oldUrl = $_POST['oldUrl'] ?? '';
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
        return;
    }
    
    // Сохраняем новый файл
    $uploadDir = __DIR__ . '/editor/uploads/';
    $newFileName = uniqid('file_') . '_' . basename($_FILES['file']['name']);
    $newPath = $uploadDir . $newFileName;
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $newPath)) {
        $newUrl = '/editor/uploads/' . $newFileName;
        
        // Обновляем все ссылки в базе данных
        $stmt = $pdo->query("SELECT * FROM pages");
        $updated = 0;
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data = json_decode($row['data_json'], true);
            $changed = false;
            
            foreach ($data['elements'] ?? [] as &$element) {
                if (isset($element['fileUrl']) && $element['fileUrl'] === $oldUrl) {
                    $element['fileUrl'] = $newUrl;
                    $changed = true;
                    $updated++;
                }
                if (isset($element['src']) && $element['src'] === $oldUrl) {
                    $element['src'] = $newUrl;
                    $changed = true;
                    $updated++;
                }
            }
            
            if ($changed) {
                $updateStmt = $pdo->prepare("UPDATE pages SET data_json = ? WHERE id = ?");
                $updateStmt->execute([json_encode($data), $row['id']]);
            }
        }
        
        echo json_encode(['ok' => true, 'updated' => $updated, 'newUrl' => $newUrl]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    }
}

function handleLinkReplace($pdo) {
    $oldUrl = $_POST['oldUrl'] ?? '';
    $newUrl = $_POST['newUrl'] ?? '';
    
    $stmt = $pdo->query("SELECT * FROM pages");
    $updated = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data = json_decode($row['data_json'], true);
        $changed = false;
        
        foreach ($data['elements'] ?? [] as &$element) {
            if (isset($element['url']) && $element['url'] === $oldUrl) {
                $element['url'] = $newUrl;
                $changed = true;
                $updated++;
            }
        }
        
        if ($changed) {
            $updateStmt = $pdo->prepare("UPDATE pages SET data_json = ? WHERE id = ?");
            $updateStmt->execute([json_encode($data), $row['id']]);
        }
    }
    
    echo json_encode(['ok' => true, 'updated' => $updated]);
}

function handleTelegramUpdate($pdo) {
    $chatId = $_POST['chatId'] ?? '';
    $botToken = $_POST['botToken'] ?? '';
    
    // Сохраняем настройки Telegram
    $configFile = __DIR__ . '/data/telegram_config.json';
    $config = [
        'chat_id' => $chatId,
        'bot_token' => $botToken,
        'enabled' => true
    ];
    
    file_put_contents($configFile, json_encode($config));
    
    echo json_encode(['ok' => true]);
}