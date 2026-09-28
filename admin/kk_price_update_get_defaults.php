<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Config\Option;

header('Content-Type: application/json; charset=utf-8');

if (!check_bitrix_sessid()) {
    echo json_encode(['success' => false, 'message' => 'Invalid sessid']);
    exit;
}

$iblockId = (int)($_POST['IBLOCK_ID'] ?? 0);
$settings = json_decode((string)Option::get('kk.price.update', 'default_price_settings', '{}'), true) ?: [];
$defaults = $iblockId > 0 && isset($settings[$iblockId]) && is_array($settings[$iblockId]) ? $settings[$iblockId] : [];

echo json_encode(['success' => true, 'defaults' => $defaults]);
