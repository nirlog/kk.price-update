<?php
defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\Loader;
use Bitrix\Main\EventManager;

class KKPriceUpdate
{
    const MODULE_ID = 'kk.price.update';

    public static function OnModuleInstall()
    {
        // Дополнительные действия при установке
    }

    public static function OnModuleUnInstall()
    {
        // Дополнительные действия при удалении
    }
}

// Регистрируем обработчики событий
EventManager::getInstance()->registerEventHandler(
    'main',
    'OnModuleInstall',
    self::MODULE_ID,
    'KKPriceUpdate',
    'OnModuleInstall'
);

EventManager::getInstance()->registerEventHandler(
    'main',
    'OnModuleUnInstall',
    self::MODULE_ID,
    'KKPriceUpdate',
    'OnModuleUnInstall'
);