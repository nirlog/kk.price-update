<?php
defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$menu = [
    [
        'parent_menu' => 'global_menu_content',
        'sort' => 1,
        'text' => Loc::getMessage('KK_PRICE_UPDATE_MENU_TITLE'),
        'title' => Loc::getMessage('KK_PRICE_UPDATE_MENU_TITLE'),
        'url' => 'kk_price_update.php',
        'icon' => 'sale_menu_icon_statistics',
        'page_icon' => 'sale_page_icon_statistics',
        'items_id' => 'menu_kk_price_update',
        'more_url' => [
            'kk_price_update.php',
            'kk_price_update_settings.php',
            'get_properties.php',
            'get_property_values.php',
            'start_update.php',
            'kk_price_update_get_defaults.php'
        ],
        'items' => [
            [
                'text' => 'Настройки по умолчанию',
                'title' => 'Настройки по умолчанию',
                'url' => 'kk_price_update_settings.php',
            ]
        ]
    ]
];

return $menu;
