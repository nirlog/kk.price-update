<?php
defined('B_PROLOG_INCLUDED') || die;

use Bitrix\Main\Loader;

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

Loader::registerNamespace('KK\\PriceUpdate', __DIR__ . '/lib');
