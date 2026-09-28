<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

global $APPLICATION, $USER;

// Проверяем авторизацию администратора
if (!$USER->IsAdmin()) {
    die('Access denied: Not admin');
}

// Проверяем sessid
if (!check_bitrix_sessid()) {
    die('Access denied: Invalid sessid');
}

if (!CModule::IncludeModule("highloadblock")) {
    die('Module highloadblock is required.');
}

header('Content-Type: text/html; charset=utf-8');

$hlBlockId = intval($_POST['HL_BLOCK_ID'] ?? 0);
if ($hlBlockId <= 0) {
    die('Invalid HL_BLOCK_ID.');
}

try {
    // Получаем Highload-блок
    $hlBlock = Bitrix\Highloadblock\HighloadBlockTable::getById($hlBlockId)->fetch();
    if (!$hlBlock) {
        echo '<p>Highload block not found.</p>';
        exit();
    }

    // Получаем entity класса
    $entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlBlock);
    $entityClass = $entity->getDataClass();

    // Безопасно получаем элементы, проверяя наличие полей
    $selectFields = ['ID', 'UF_NAME', 'UF_XML_ID'];
    
    // Проверяем наличие UF_PRICE перед добавлением в SELECT
    try {
        $testItem = $entityClass::getList([
            'select' => ['UF_PRICE'],
            'limit' => 1
        ])->fetch();
        
        // Если дошли сюда без ошибки - поле существует
        $selectFields[] = 'UF_PRICE';
        $hasPriceField = true;
    } catch (Exception $e) {
        // Если поле не найдено - работаем без него
        $hasPriceField = false;
    }

    // Получаем все элементы Highload-блока
    $elements = $entityClass::getList([
        'select' => $selectFields,
        'order' => ['UF_NAME' => 'ASC']
    ])->fetchAll();

    if (empty($elements)) {
        echo '<p>Нет доступных значений в выбранном Highload-блоке</p>';
    } else {
        echo '<label for="values-select" style="display: block; margin-bottom: 5px; font-weight: bold;">';
        echo 'Выберите значения для фильтрации:</label>';
        echo '<select id="values-select" name="VALUES[]" multiple style="min-width: 300px; min-height: 200px; padding: 5px;">';
        echo '<option value="">- Все значения -</option>';
        
        foreach ($elements as $element) {
            $displayName = htmlspecialcharsbx($element['UF_NAME']);
            
            // Добавляем цену только если поле существует
            if ($hasPriceField && isset($element['UF_PRICE'])) {
                $price = intval($element['UF_PRICE']);
                if ($price > 0) {
                    $displayName .= " ({$price} руб.)";
                }
            }
            
            echo '<option value="' . $element['ID'] . '">' . $displayName . '</option>';
        }
        
        echo '</select>';
        echo '<p style="margin-top: 5px; color: #666; font-size: 12px;">';
        echo 'Для множественного выбора удерживайте Ctrl (или Cmd на Mac)</p>';
        echo '<p style="margin-top: 5px; color: #666; font-size: 12px;">';
        echo 'Всего значений: ' . count($elements) . '</p>';
        
        if (!$hasPriceField) {
            echo '<p style="margin-top: 5px; color: orange; font-size: 12px;">';
            echo 'Внимание: В этом Highload-блоке отсутствует поле UF_PRICE. Цены не будут отображаться.</p>';
        }
    }

} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка загрузки значений: ' . htmlspecialcharsbx($e->getMessage()) . '</p>';
}