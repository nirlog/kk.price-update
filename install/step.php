<?php
use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) {
    return;
}

Loc::loadMessages(__FILE__);
?>

<div style="padding: 20px;">
    <p><?= Loc::getMessage("KK_PRICE_UPDATE_INSTALL_SUCCESS") ?></p>
    
    <form action="<?= $APPLICATION->GetCurPage() ?>">
        <input type="hidden" name="lang" value="<?= LANG ?>">
        <input type="submit" name="" value="<?= Loc::getMessage("KK_PRICE_UPDATE_INSTALL_BACK") ?>">
    </form>
</div>