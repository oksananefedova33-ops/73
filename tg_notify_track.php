<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$db = __DIR__ . '/data/zerro_blog.db';
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_REQUEST['action'] ?? '';

if ($action === 'getSettings') {
    $stmt = $pdo->query("SELECT key, value FROM tg_notify_settings");
    $settings = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    
    echo json_encode(['ok' => true, 'settings' => $settings]);
    exit;
}

if ($action === 'track') {
    $type = $_POST['type'] ?? '';
    $settings = getSettings($pdo);
    
    if (empty($settings['chat_id']) || empty($settings['bot_token'])) {
        echo json_encode(['ok' => false]);
        exit;
    }
    
    // Получаем информацию о посетителе
    $visitorInfo = getVisitorInfo();
    
    // Формируем сообщение в зависимости от типа
    $message = formatMessage($type, $visitorInfo, $_POST);
    
    // Отправляем в Telegram
    sendTelegramMessage($settings['bot_token'], $settings['chat_id'], $message);
    
    echo json_encode(['ok' => true]);
    exit;
}

function getSettings($pdo) {
    $stmt = $pdo->query("SELECT key, value FROM tg_notify_settings");
    $settings = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    
    return $settings;
}

function getVisitorInfo() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] 
        ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
        ?? $_SERVER['REMOTE_ADDR'] 
        ?? 'Unknown';
    
    // Получаем User-Agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Определяем устройство и браузер
    $deviceInfo = parseUserAgent($userAgent);
    
    // Получаем геолокацию по IP
    $geoInfo = getGeoLocation($ip);
    
    return [
        'ip' => $ip,
        'country' => $geoInfo['country'] ?? 'Unknown',
        'city' => $geoInfo['city'] ?? 'Unknown',
        'device' => $deviceInfo['device'],
        'os' => $deviceInfo['os'],
        'browser' => $deviceInfo['browser'],
        'referrer' => $_POST['referrer'] ?? 'Прямой заход',
        'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
        'timezone' => date_default_timezone_get(),
        'time' => date('H:i:s'),
        'date' => date('d.m.Y')
    ];
}

function parseUserAgent($ua) {
    $device = 'Desktop';
    $os = 'Unknown';
    $browser = 'Unknown';
    
    // Определяем устройство
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $ua)) {
        $device = 'Tablet';
    } elseif (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $ua)) {
        $device = 'Mobile';
    }
    
    // Определяем ОС
    if (preg_match('/windows/i', $ua)) $os = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $os = 'macOS';
    elseif (preg_match('/linux/i', $ua)) $os = 'Linux';
    elseif (preg_match('/android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iphone|ipod|ipad/i', $ua)) $os = 'iOS';
    
    // Определяем браузер
    if (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/chrome/i', $ua) && !preg_match('/edge/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/edge/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/opera|opr/i', $ua)) $browser = 'Opera';
    
    // Версия браузера
    if (preg_match('/chrome\/([0-9.]+)/i', $ua, $matches)) {
        $browser = 'Chrome ' . explode('.', $matches[1])[0];
    }
    
    return [
        'device' => $device,
        'os' => $os,
        'browser' => $browser
    ];
}

function getGeoLocation($ip) {
    // Используем бесплатный API ipapi.co
    $url = "https://ipapi.co/{$ip}/json/";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    return [
        'country' => $data['country_name'] ?? 'Unknown',
        'city' => $data['city'] ?? 'Unknown'
    ];
}

function formatMessage($type, $info, $data) {
    $emoji = [
        'visit' => '👁',
        'download' => '📥',
        'link' => '🔗'
    ];
    
    $typeText = [
        'visit' => 'Посещение сайта',
        'download' => 'Скачивание файла',
        'link' => 'Переход по ссылке'
    ];
    
    $message = $emoji[$type] . " *" . $typeText[$type] . "*\n\n";
    
    // Основная информация о посетителе
    $message .= "IP: `" . $info['ip'] . "`\n";
    $message .= "Страна: " . $info['country'] . "\n";
    $message .= "Устройство: " . $info['device'] . "\n";
    $message .= "ОС: " . $info['os'] . "\n";
    $message .= "Браузер: " . $info['browser'] . "\n";
    
    // Источник
    $referrer = $data['referrer'] ?? 'Прямой заход';
    if ($referrer && $referrer !== 'Прямой заход') {
        $referrer = parse_url($referrer, PHP_URL_HOST) ?: $referrer;
    }
    $message .= "Источник: " . $referrer . "\n";
    
    $message .= "Язык: " . explode(',', $info['language'])[0] . "\n";
    $message .= "Часовой пояс: UTC" . date('P') . "\n";
    $message .= "Время: " . $info['time'] . "\n";
    $message .= "Дата: " . $info['date'] . "\n\n";
    
    // Дополнительная информация в зависимости от типа
    $message .= "━━━━━━━━━━━━━━━━━━\n";
    
    if ($type === 'visit') {
        $message .= "📄 *Страница:* " . ($data['page_title'] ?? 'Главная') . "\n";
        $message .= "🔗 *URL:* `" . $data['url'] . "`\n";
    } elseif ($type === 'download') {
        $message .= "📁 *Файл:* " . ($data['file_name'] ?? 'unknown') . "\n";
        $message .= "📄 *Страница:* " . ($data['page_title'] ?? '') . "\n";
    } elseif ($type === 'link') {
        $message .= "🔗 *Ссылка:* " . ($data['link_url'] ?? '') . "\n";
        $message .= "📝 *Текст кнопки:* " . ($data['link_text'] ?? '') . "\n";
        $message .= "📄 *Страница:* " . ($data['page_title'] ?? '') . "\n";
    }
    
    return $message;
}

function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    curl_exec($ch);
    curl_close($ch);
}