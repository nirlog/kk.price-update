<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

header('Content-Type: application/json; charset=utf-8');

global $USER;

if (!$USER->IsAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Not admin']);
    exit;
}

if (!check_bitrix_sessid()) {
    echo json_encode(['success' => false, 'message' => 'Invalid sessid']);
    exit;
}

if (!CModule::IncludeModule('iblock') || !CModule::IncludeModule('catalog') || !CModule::IncludeModule('highloadblock')) {
    echo json_encode(['success' => false, 'message' => 'Modules not found']);
    exit;
}
if (!\Bitrix\Main\Loader::includeModule('kk.price.update')) {
    echo json_encode(['success' => false, 'message' => 'Module kk.price.update not found']);
    exit;
}

try {
    $service = new \KK\PriceUpdate\Service\PriceUpdater();
    $result = $service->handle($_POST);
    echo json_encode($result);
} catch (\Throwable $e) {
    if ($e instanceof \KK\PriceUpdate\Exception\PricingException) {
        $diagnostic = $e->diagnostic();
        $message = ($diagnostic['code'] ?? '') === 'korsac_preflight_failed'
            ? $e->getMessage()
            : (new \KK\PriceUpdate\Pricing\KorsacDiagnosticFormatter())->format($diagnostic);
    } else {
        $safeApplicationMessages = [
            'Invalid parameters',
            'Property not found',
            'Highload block not found',
            'No price types selected',
            'Unknown step',
        ];
        $message = in_array($e->getMessage(), $safeApplicationMessages, true)
            ? $e->getMessage()
            : 'internal_update_error';
    }
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $message
    ]);
}
