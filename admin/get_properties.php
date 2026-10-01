<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use KK\PriceUpdate\Exception\PricingException;
use KK\PriceUpdate\Routing\PropertyMode;
use KK\PriceUpdate\Routing\PropertyModeResolver;

global $APPLICATION, $USER;
if (!$USER->IsAdmin() || !check_bitrix_sessid()) {
    die('Access denied.');
}

if (!Loader::includeModule('iblock') || !Loader::includeModule('highloadblock')) {
    die('Modules iblock and highloadblock are required.');
}
if (!Loader::includeModule('kk.price.update')) {
    die('Module kk.price.update is required.');
}

$iblockId = intval($_REQUEST['IBLOCK_ID']);
if ($iblockId <= 0) {
    die('Invalid IBLOCK_ID.');
}

try {
    // Получаем все свойства инфоблока типа "Привязка к элементам из высоконагруженных блоков"
    $properties = PropertyTable::getList([
        'filter' => [
            '=IBLOCK_ID' => $iblockId,
            '=PROPERTY_TYPE' => 'S',
            '=USER_TYPE' => 'directory'
        ],
        'select' => ['ID', 'NAME', 'CODE', 'USER_TYPE_SETTINGS']
    ])->fetchAll();

    $highloadProperties = [];
    $modeResolver = new PropertyModeResolver();

    foreach ($properties as $property) {
        try {
            $mode = $modeResolver->classifyForUi((string)$property['CODE']);
        } catch (PricingException $exception) {
            // Without optional KORSAC its reserved properties are not offered; legacy properties keep working.
            if (strpos((string)$property['CODE'], 'KK_') === 0) {
                continue;
            }
            $mode = PropertyMode::LEGACY;
        }
        if ($mode === PropertyMode::KORSAC_NON_DEFAULT) {
            continue;
        }
        $settings = unserialize($property['USER_TYPE_SETTINGS'], ['allowed_classes' => false]);
        if (isset($settings['TABLE_NAME'])) {
            // Получаем Highload-блок
            $hlBlock = HighloadBlockTable::getList([
                'filter' => ['=TABLE_NAME' => $settings['TABLE_NAME']]
            ])->fetch();
            
            if ($hlBlock) {
                // Безопасная проверка наличия поля UF_PRICE
                if (hasHlBlockPriceField($hlBlock['ID'])) {
                    $highloadProperties[] = [
                        'ID' => $property['ID'],
                        'NAME' => $property['NAME'],
                        'CODE' => $property['CODE'],
                        'HL_BLOCK_ID' => $hlBlock['ID'],
                        'MODE' => $mode
                    ];
                }
            }
        }
    }

    if (empty($highloadProperties)) {
        echo '<p>Не найдено свойств типа Highload-блок с полем UF_PRICE</p>';
    } else {
        echo '<label for="property-select" style="display: block; margin-bottom: 5px; font-weight: bold;">';
        echo 'Выберите свойство для фильтрации:</label>';
        echo '<select id="property-select" name="PROPERTY_SELECT" style="min-width: 300px; padding: 5px;">';
        echo '<option value="">- Выберите свойство -</option>';
        
        foreach ($highloadProperties as $property) {
            echo '<option value="' . $property['ID'] . '" data-hl-block="' . $property['HL_BLOCK_ID'] . '">';
            echo htmlspecialcharsbx($property['NAME']) . ' (' . htmlspecialcharsbx($property['CODE']) . ')';
            if ($property['MODE'] === PropertyMode::KORSAC_DEFAULT) {
                echo ' [KORSAC]';
            }
            echo '</option>';
        }
        
        echo '</select>';
    }

} catch (Exception $e) {
    echo '<p style="color: red;">Ошибка: ' . htmlspecialcharsbx($e->getMessage()) . '</p>';
}

/**
 * Безопасно проверяет наличие поля UF_PRICE в Highload-блоке
 */
function hasHlBlockPriceField($hlBlockId) {
    try {
        $hlBlock = HighloadBlockTable::getById($hlBlockId)->fetch();
        if (!$hlBlock) {
            return false;
        }
        
        $entity = HighloadBlockTable::compileEntity($hlBlock);
        $entityClass = $entity->getDataClass();
        
        // Пробуем получить одно значение для проверки наличия поля
        $testItem = $entityClass::getList([
            'select' => ['ID', 'UF_PRICE'],
            'limit' => 1
        ])->fetch();
        
        // Если запрос выполнился без ошибок и вернул данные (или пустой результат) - поле существует
        return true;
        
    } catch (Exception $e) {
        // Если произошла ошибка "unknown field" - поля нет
        if (strpos($e->getMessage(), 'Unknown field definition') !== false) {
            return false;
        }
        // Другие ошибки - прокидываем дальше
        throw $e;
    }
}
