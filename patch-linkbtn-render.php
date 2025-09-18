<?php
$F = __DIR__ . '/../index.php';
if (!file_exists($F)) { fwrite(STDERR, "index.php не найден\n"); exit(1); }
$s = file_get_contents($F);

// 1) убедимся, что стили модуля подключены
if (strpos($s, 'ui/modules/button-link/button-link.css') === false) {
  $s = preg_replace('~</head>~i',
    "    <link rel=\"stylesheet\" href=\"/ui/modules/button-link/button-link.css?v=".time()."\" />\n</head>",
    $s, 1);
}

// 2) нормализуем возможное сравнение типа
$s = preg_replace("~elseif\\s*\\(\\s*\\$type\\s*===\\s*[\\'\\\"]linkbtn[\\'\\\"]\\s*\\)~i",
  "elseif (strtolower(\$type)==='linkbtn')", $s);

// 3) добавим обработчик linkbtn, если его нет
if (strpos($s, "strtolower(\$type)==='linkbtn'") === false) {
  $insert = <<<'EOT'
  elseif (strtolower($type)==='linkbtn'):
    $text = htmlspecialchars($e['text'] ?? 'Кнопка', ENT_QUOTES, 'UTF-8');
    $url  = htmlspecialchars($e['url'] ?? '#', ENT_QUOTES, 'UTF-8');
    $bg   = trim((string)($e['bg'] ?? '#3b82f6'));
    $color= trim((string)($e['color'] ?? '#ffffff'));
    $anim = preg_replace('~[^a-z]~','', (string)($e['anim'] ?? 'none'));
    echo '<div class="el" style="'.$style.'"><a class="bl-linkbtn bl-anim-'.($anim?:'none').'" href="'.$url.'" style="--bl-bg:'.$bg.';--bl-color:'.$color.'">'.$text.'</a></div>';
EOT;
  // Вставим перед закрытием большого блока элементов
  $s = preg_replace('~(\n\s*endif;\s*\nendforeach;\s*\?>)~', "\n".$insert."\n  \\1", $s, 1);
}

file_put_contents($F, $s);
echo "index.php: linkbtn-рендер пропатчен\n";
