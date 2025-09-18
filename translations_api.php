<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$db = dirname(__DIR__) . '/data/zerro_blog.db';
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_REQUEST['action'] ?? '';

switch($action) {
    case 'saveToken':
        $token = $_POST['token'] ?? '';
        $pdo->prepare("INSERT OR REPLACE INTO translation_settings (key, value) VALUES ('deepl_token', ?)")
            ->execute([$token]);
        echo json_encode(['ok' => true]);
        break;
        
    case 'getToken':
        $stmt = $pdo->prepare("SELECT value FROM translation_settings WHERE key = 'deepl_token'");
        $stmt->execute();
        $token = $stmt->fetchColumn() ?: '';
        echo json_encode(['ok' => true, 'token' => $token]);
        break;
        
    case 'deleteToken':
        $pdo->exec("DELETE FROM translation_settings WHERE key = 'deepl_token'");
        echo json_encode(['ok' => true]);
        break;
        
    case 'translate':
        $token = $pdo->query("SELECT value FROM translation_settings WHERE key = 'deepl_token'")->fetchColumn();
        if (!$token) {
            echo json_encode(['ok' => false, 'error' => 'Токен DeepL не настроен']);
            exit;
        }
        
        $targetLangs = json_decode($_POST['languages'] ?? '[]', true);
        $pageId = (int)($_POST['page_id'] ?? 0);
        
        // Получаем данные страницы
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$pageId]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$page) {
            echo json_encode(['ok' => false, 'error' => 'Страница не найдена']);
            exit;
        }
        
        $results = [];
        $data = json_decode($page['data_json'], true);
        
        foreach ($targetLangs as $lang) {
            if ($lang === 'ru') continue; // Пропускаем русский (исходный)
            
            $deeplLang = mapToDeeplLang($lang);
            
            // Переводим meta данные
            if (!empty($page['meta_title'])) {
                $translated = translateText($page['meta_title'], $deeplLang, $token);
                if ($translated) {
                    saveTranslation($pdo, $pageId, 'meta', $lang, 'title', $translated);
                    $results[] = "✓ Title → $lang";
                }
            }
            
            if (!empty($page['meta_description'])) {
                $translated = translateText($page['meta_description'], $deeplLang, $token);
                if ($translated) {
                    saveTranslation($pdo, $pageId, 'meta', $lang, 'description', $translated);
                    $results[] = "✓ Description → $lang";
                }
            }
            
            // Переводим элементы
            foreach ($data['elements'] ?? [] as $element) {
                if ($element['type'] === 'text' && !empty($element['html'])) {
                    // Используем функцию для сохранения HTML-тегов
                    $translated = translateHtmlText($element['html'], $deeplLang, $token);
                    if ($translated) {
                        saveTranslation($pdo, $pageId, $element['id'], $lang, 'html', $translated);
                        $results[] = "✔ Текст {$element['id']} → $lang";
                    }
                } elseif (in_array($element['type'], ['linkbtn', 'filebtn']) && !empty($element['text'])) {
    $translated = translateText($element['text'], $deeplLang, $token);
    if ($translated) {
        saveTranslation($pdo, $pageId, $element['id'], $lang, 'text', $translated);
        $results[] = "✔ Кнопка {$element['id']} → $lang";
    }
}
            }
        }
        
        echo json_encode(['ok' => true, 'results' => $results]);
        break;
        
    case 'deleteTranslations':
        $pdo->exec("DELETE FROM translations");
        echo json_encode(['ok' => true]);
        break;
        
    case 'getTranslations':
        $pageId = (int)($_GET['page_id'] ?? 0);
        $lang = $_GET['lang'] ?? 'ru';
        
        $stmt = $pdo->prepare("SELECT * FROM translations WHERE page_id = ? AND lang = ?");
        $stmt->execute([$pageId, $lang]);
        $translations = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['element_id'] . '_' . $row['field'];
            $translations[$key] = $row['content'];
        }
        
        echo json_encode(['ok' => true, 'translations' => $translations]);
        break;
        
    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}

function mapToDeeplLang($lang) {
    $map = [
        'en' => 'EN-GB',
        'zh-Hans' => 'ZH',
        'es' => 'ES',
        'pt' => 'PT-PT',
        'de' => 'DE',
        'fr' => 'FR',
        'ja' => 'JA',
        'ar' => 'AR',
        'it' => 'IT',
        'ko' => 'KO',
        'nl' => 'NL',
        'pl' => 'PL',
        'tr' => 'TR',
        'cs' => 'CS',
        'da' => 'DA',
        'el' => 'EL',
        'fi' => 'FI',
        'hu' => 'HU',
        'id' => 'ID',
        'no' => 'NB',
        'ro' => 'RO',
        'sv' => 'SV',
        'uk' => 'UK',
        'bg' => 'BG',
        'et' => 'ET',
        'lt' => 'LT',
        'lv' => 'LV',
        'sk' => 'SK',
        'sl' => 'SL'
    ];
    return $map[$lang] ?? strtoupper($lang);
}

function translateText($text, $targetLang, $token) {
    $url = 'https://api-free.deepl.com/v2/translate';
    
    $data = [
        'auth_key' => $token,
        'text' => $text,
        'target_lang' => $targetLang,
        'tag_handling' => 'html',
        'preserve_formatting' => 1
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['translations'][0]['text'] ?? null;
}

function translateHtmlText($html, $targetLang, $token) {
    // Попробуем сначала перевести с сохранением HTML через DeepL
    $url = 'https://api-free.deepl.com/v2/translate';
    
    $data = [
        'auth_key' => $token,
        'text' => $html,
        'target_lang' => $targetLang,
        'tag_handling' => 'html',
        'preserve_formatting' => 1,
        'split_sentences' => 'nonewlines'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['translations'][0]['text'])) {
        $translated = $result['translations'][0]['text'];
        
        // Нормализуем пробелы после перевода
        $translated = normalizeSpacesAroundTags($translated);
        
        return $translated;
    }
    
    // Если не удалось с HTML, используем старый метод с плейсхолдерами
    $tags = [];
    $i = 0;
    
    $textWithPlaceholders = preg_replace_callback('/<[^>]+>/', function($match) use (&$tags, &$i) {
        $placeholder = "___TAG_" . $i . "___";
        $tags[$placeholder] = $match[0];
        $i++;
        return $placeholder;
    }, $html);
    
    if (trim($textWithPlaceholders)) {
        $translated = translateText($textWithPlaceholders, $targetLang, $token);
        
        if ($translated) {
            foreach ($tags as $placeholder => $tag) {
                $translated = str_replace($placeholder, $tag, $translated);
            }
            
            $translated = normalizeSpacesAroundTags($translated);
            return $translated;
        }
    }
    
    return null;
}

function normalizeSpacesAroundTags($html) {
    // Убираем пробелы после закрывающих тегов перед буквой/цифрой
    $html = preg_replace('/<\/(strong|em|b|i|u|span|font|a)>\s+(?=[а-яА-Яa-zA-Z0-9])/ui', '</$1>', $html);
    
    // Убираем пробелы перед открывающими тегами если перед ними буква/цифра
    $html = preg_replace('/(?<=[а-яА-Яa-zA-Z0-9])\s+<(strong|em|b|i|u|span|font|a)([^>]*)>/ui', '<$1$2>', $html);
    
    // Вставляем пробел после закрывающего тега перед буквой/цифрой если его нет
    $html = preg_replace('/<\/(strong|em|b|i|u|span|font|a)>(?=[а-яА-Яa-zA-Z0-9])/ui', '</$1> ', $html);
    
    // Вставляем пробел перед открывающим тегом после буквы/цифры если его нет
    $html = preg_replace('/(?<=[а-яА-Яa-zA-Z0-9])<(strong|em|b|i|u|span|font|a)([^>]*)>/ui', ' <$1$2>', $html);
    
    // Убираем множественные пробелы
    $html = preg_replace('/\s{2,}/', ' ', $html);
    
    // Обрезаем пробелы в начале и конце
    $html = trim($html);
    
    return $html;
}

function saveTranslation($pdo, $pageId, $elementId, $lang, $field, $content) {
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO translations (page_id, element_id, lang, field, content) 
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$pageId, $elementId, $lang, $field, $content]);
}