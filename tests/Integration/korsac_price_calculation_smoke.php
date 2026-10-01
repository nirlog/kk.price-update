<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n"); exit(2);
}
$options = getopt('', ['iblock:', 'product:', 'price-type:']);
$iblockId = (int)($options['iblock'] ?? 0);
$productId = (int)($options['product'] ?? 0);
$priceTypeId = (int)($options['price-type'] ?? 0);
if ($iblockId <= 0 || $productId <= 0 || $priceTypeId <= 0) {
    fwrite(STDERR, "Usage: php korsac_price_calculation_smoke.php --iblock=ID --product=ID --price-type=ID\n"); exit(2);
}

$documentRoot = getenv('BITRIX_DOCUMENT_ROOT') ?: dirname(__DIR__, 5);
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$prolog = $documentRoot . '/bitrix/modules/main/include/prolog_before.php';
if (!is_file($prolog)) {
    fwrite(STDERR, "Bitrix prolog not found; set BITRIX_DOCUMENT_ROOT or run the installed module copy.\n");
    exit(2);
}
require $prolog;
if (!\Bitrix\Main\Loader::includeModule('kk.price.update')) {
    throw new RuntimeException('kk.price.update_module_not_available');
}

$provider = new \KK\PriceUpdate\Pricing\KorsacDefaultCatalogPriceProvider();
$catalogMinor = $provider->getDefaultCatalogPriceMinor($iblockId, $productId, $priceTypeId);
echo json_encode([
    'ok' => true,
    'iblockId' => $iblockId,
    'productId' => $productId,
    'priceTypeId' => $priceTypeId,
    'defaultCatalogPriceMinor' => $catalogMinor,
    'defaultCatalogPrice' => \KK\PriceUpdate\Pricing\MinorMoney::format($catalogMinor),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
