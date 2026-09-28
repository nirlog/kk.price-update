<?php
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;

Loc::loadMessages(__FILE__);

class kk_price_update extends CModule
{
    public $MODULE_ID = 'kk.price.update';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('KK_PRICE_UPDATE_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('KK_PRICE_UPDATE_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('KK_PRICE_UPDATE_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('KK_PRICE_UPDATE_PARTNER_URI');
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallFiles();
    }

    public function DoUninstall()
    {
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
        
        // Создаем папку для логов
        $logDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/kk_price_update';
        if (!Directory::isDirectoryExists($logDir)) {
            Directory::createDirectory($logDir);
        }
        
        return true;
    }

    public function UnInstallFiles()
    {
        DeleteDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin'
        );
        
        return true;
    }
}