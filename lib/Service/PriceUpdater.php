<?php

namespace KK\PriceUpdate\Service;

use Bitrix\Highloadblock\HighloadBlockTable;
use Exception;
use KK\PriceUpdate\Pricing\KorsacDefaultCostProvider;
use KK\PriceUpdate\Pricing\KorsacDefaultCostProviderInterface;
use KK\PriceUpdate\Pricing\MinorMoney;
use KK\PriceUpdate\Pricing\MinorPriceAdjustment;
use KK\PriceUpdate\Routing\PropertyMode;
use KK\PriceUpdate\Routing\PropertyModeResolver;

class PriceUpdater
{
    private $modeResolver;
    private $korsacCostProvider;
    private $minorAdjustment;

    public function __construct(?PropertyModeResolver $modeResolver = null, ?KorsacDefaultCostProviderInterface $korsacCostProvider = null, ?MinorPriceAdjustment $minorAdjustment = null)
    {
        $this->modeResolver = $modeResolver ?? new PropertyModeResolver();
        $this->korsacCostProvider = $korsacCostProvider ?? new KorsacDefaultCostProvider();
        $this->minorAdjustment = $minorAdjustment ?? new MinorPriceAdjustment();
    }

    public function handle(array $params): array
    {
        $iblockId = (int)($params['IBLOCK_ID'] ?? 0);
        $propertyId = (int)($params['PROPERTY_ID'] ?? 0);
        $hlBlockId = (int)($params['HL_BLOCK_ID'] ?? 0);
        $step = (string)($params['step'] ?? 'init');
        $currentValueIndex = (int)($params['current_value_index'] ?? 0);
        $processedElements = (int)($params['processed_elements'] ?? 0);
        $totalSuccess = (int)($params['total_success'] ?? 0);
        $totalErrors = (int)($params['total_errors'] ?? 0);
        $totalSkippedNoPriceUpdate = (int)($params['total_skipped_no_price_update'] ?? 0);
        $totalOffersSuccess = (int)($params['total_offers_success'] ?? 0);
        $totalOffersProcessed = (int)($params['total_offers_processed'] ?? 0);

        if ($iblockId <= 0 || $propertyId <= 0 || $hlBlockId <= 0) {
            throw new Exception('Invalid parameters');
        }

        $property = \CIBlockProperty::GetByID($propertyId)->Fetch();
        if (!$property) {
            throw new Exception('Property not found');
        }
        $mode = $this->modeResolver->resolve((string)$property['CODE']);

        $hlBlock = HighloadBlockTable::getById($hlBlockId)->fetch();
        if (!$hlBlock) {
            throw new Exception('Highload block not found');
        }

        $hlEntity = HighloadBlockTable::compileEntity($hlBlock);
        $hlDataClass = $hlEntity->getDataClass();
        $skuInfo = \CCatalogSKU::GetInfoByProductIBlock($iblockId);

        $selectedValues = [];
        $priceTypeIds = [];
        $priceAdjustments = [];
        if (!empty($params['VALUES'])) {
            $selectedValues = json_decode((string)$params['VALUES'], true) ?? [];
        }
        if (!empty($params['PRICE_TYPES'])) {
            $priceTypeIds = array_values(array_filter(array_map('intval', json_decode((string)$params['PRICE_TYPES'], true) ?? [])));
        }
        if (!empty($params['PRICE_ADJUSTMENTS'])) {
            $priceAdjustments = json_decode((string)$params['PRICE_ADJUSTMENTS'], true) ?? [];
        }
        if (empty($priceTypeIds)) {
            throw new Exception('No price types selected');
        }

        $filterValues = $this->getFilterValues($hlDataClass, $selectedValues);
        $logDir = $this->ensureLogDir();

        if ($step === 'init') {
            $errorFilename = 'log-error-' . date('Y-m-d-H-i-s') . '.txt';
            file_put_contents($logDir . '/' . $errorFilename, '');

            return [
                'success' => true,
                'step' => 'init',
                'total_values' => count($filterValues),
                'error_file' => '/upload/kk_price_update/' . $errorFilename,
                'message' => 'Начинаем обработку ' . count($filterValues) . ' значений'
            ];
        }

        if ($step !== 'process_value') {
            throw new Exception('Unknown step');
        }

        if ($currentValueIndex >= count($filterValues)) {
            return [
                'success' => true,
                'step' => 'process_value',
                'current_value_index' => $currentValueIndex,
                'is_completed' => true,
                'logs' => ['Все значения обработаны'],
                'stats' => [
                    'processed_elements' => $processedElements,
                    'total_success' => $totalSuccess,
                    'total_errors' => $totalErrors,
                    'total_skipped_no_price_update' => $totalSkippedNoPriceUpdate,
                    'total_offers_success' => $totalOffersSuccess,
                    'total_offers_processed' => $totalOffersProcessed
                ]
            ];
        }

        $value = $filterValues[$currentValueIndex];
        $processResult = $mode === PropertyMode::KORSAC_DEFAULT
            ? $this->processKorsacValue($value, $property, $iblockId, $propertyId, $skuInfo, $params, $logDir, $priceTypeIds, $priceAdjustments)
            : $this->processLegacyValue($value, $property, $iblockId, $propertyId, $hlDataClass, $skuInfo, $params, $logDir, $priceTypeIds, $priceAdjustments);

        return [
            'success' => true,
            'step' => 'process_value',
            'current_value_index' => $currentValueIndex,
            'current_value_name' => $value['NAME'],
            'is_completed' => ($currentValueIndex + 1) >= count($filterValues),
            'logs' => $processResult['logs'],
            'stats' => [
                'processed_elements' => $processedElements + $processResult['processed_elements'],
                'total_success' => $totalSuccess + $processResult['success_count'],
                'total_errors' => $totalErrors + $processResult['error_count'],
                'total_skipped_no_price_update' => $totalSkippedNoPriceUpdate + $processResult['skipped_no_price_update_count'],
                'total_offers_success' => $totalOffersSuccess + $processResult['offers_success'],
                'total_offers_processed' => $totalOffersProcessed + $processResult['offers_processed'],
                'current_value_success' => $processResult['success_count'],
                'current_value_errors' => $processResult['error_count'],
                'current_value_skipped_no_price_update' => $processResult['skipped_no_price_update_count'],
                'current_value_offers_success' => $processResult['offers_success'],
                'current_value_offers_processed' => $processResult['offers_processed']
            ]
        ];
    }

    private function getFilterValues(string $hlDataClass, array $selectedValues): array
    {
        $query = [
            'select' => ['ID', 'UF_NAME', 'UF_XML_ID'],
            'order' => ['UF_NAME' => 'ASC']
        ];

        if (!empty($selectedValues)) {
            $query['filter'] = ['ID' => $selectedValues];
        }

        $result = [];
        $res = $hlDataClass::getList($query);
        while ($item = $res->fetch()) {
            $result[] = ['ID' => $item['ID'], 'NAME' => $item['UF_NAME'], 'XML_ID' => $item['UF_XML_ID']];
        }

        return $result;
    }

    private function ensureLogDir(): string
    {
        $logDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/kk_price_update';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        return $logDir;
    }

    private function processLegacyValue(array $value, array $property, int $iblockId, int $propertyId, string $hlDataClass, ?array $skuInfo, array $params, string $logDir, array $priceTypeIds, array $priceAdjustments): array
    {
        $logs = ["{$property['NAME']} - {$value['NAME']}, начинаем поиск товаров..."];
        $successCount = 0;
        $errorCount = 0;
        $offersSuccess = 0;
        $offersProcessed = 0;
        $skippedNoPriceUpdateCount = 0;
        $processedElements = 0;
        $errorIds = [];

        $elements = $this->findElementsByXmlId($iblockId, $propertyId, $value['XML_ID']);
        $logs[] = 'Найдено товаров: ' . count($elements);

        if (empty($elements)) {
            $logs[] = '* Товары не найдены';
            return [
                'logs' => $logs,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'offers_success' => $offersSuccess,
                'offers_processed' => $offersProcessed,
                'skipped_no_price_update_count' => $skippedNoPriceUpdateCount,
                'processed_elements' => $processedElements,
            ];
        }

        $logs[] = '* Начинаем обновление цен';

        foreach ($elements as $element) {
            try {
                if ($this->shouldSkipPriceUpdate((int)$element['ID'], $iblockId)) {
                    $logs[] = "    - Товар ID {$element['ID']} пропущен (NO_PRICE_UPDATE = N)";
                    $skippedNoPriceUpdateCount++;
                    continue;
                }

                $elementProperties = $this->getElementHlProperties((int)$element['ID'], $iblockId, $propertyId);
                $currentPropertyPrice = $this->getHlElementPrice($hlDataClass, (int)$value['ID']);
                $elementPrice = $this->calculateElementPrice($elementProperties, $currentPropertyPrice);
                $offers = $skuInfo ? $this->getProductOffers((int)$element['ID'], $skuInfo) : [];

                if (!empty($offers)) {
                    $updateResult = $this->updateOffersPrices($offers, $elementPrice, (int)$skuInfo['IBLOCK_ID'], $priceTypeIds, $priceAdjustments);
                    $offersProcessed += count($offers);
                    if ($updateResult['success']) {
                        $successCount++;
                        $offersSuccess += $updateResult['offers_updated'];
                    } else {
                        $errorCount++;
                        $errorIds[] = $element['ID'];
                    }
                } else {
                    if ($this->updateElementPrices((int)$element['ID'], $elementPrice, $priceTypeIds, $priceAdjustments)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errorIds[] = $element['ID'];
                    }
                }

                $processedElements++;
            } catch (Exception $e) {
                $errorCount++;
                $errorIds[] = $element['ID'];
                $logs[] = "    ✗ Ошибка при обработке товара ID {$element['ID']}: " . $e->getMessage();
            }
        }

        if (!empty($errorIds) && !empty($params['error_file'])) {
            file_put_contents($logDir . '/' . basename((string)$params['error_file']), implode("\n", $errorIds) . "\n", FILE_APPEND);
        }

        $offerText = $offersProcessed > 0 ? " и {$offersSuccess}/{$offersProcessed} ТП" : '';
        $logs[] = "* Обновление цен окончено. {$successCount} товаров{$offerText} - успешно. {$errorCount} товаров - с ошибкой.";

        return [
            'logs' => $logs,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'offers_success' => $offersSuccess,
            'offers_processed' => $offersProcessed,
            'skipped_no_price_update_count' => $skippedNoPriceUpdateCount,
            'processed_elements' => $processedElements,
        ];
    }

    private function processKorsacValue(array $value, array $property, int $iblockId, int $propertyId, ?array $skuInfo, array $params, string $logDir, array $priceTypeIds, array $priceAdjustments): array
    {
        $logs = ["[KORSAC] {$property['NAME']} - {$value['NAME']}, начинаем поиск товаров..."];
        $elements = $this->findElementsByXmlId($iblockId, $propertyId, $value['XML_ID']);
        $logs[] = 'Найдено товаров: ' . count($elements);
        $success = $errors = $skipped = $processed = 0;
        $errorIds = [];

        foreach ($elements as $element) {
            $productId = (int)$element['ID'];
            try {
                if ($this->shouldSkipPriceUpdate($productId, $iblockId)) {
                    $logs[] = "    - Товар ID {$productId} пропущен (NO_PRICE_UPDATE = N)";
                    $skipped++;
                    continue;
                }
                $offers = $skuInfo ? $this->getProductOffers($productId, $skuInfo) : [];
                if ($offers) {
                    throw new Exception('korsac_product_has_offers');
                }
                if (!\CCatalogProduct::GetByID($productId)) {
                    throw new Exception('catalog_product_not_found');
                }

                $costMinor = $this->korsacCostProvider->getDefaultCostMinor($iblockId, $productId);
                $logs[] = "[KORSAC] Товар #{$productId}";
                $logs[] = 'DEFAULT_COMPONENT_COST: ' . MinorMoney::format($costMinor) . ' RUB';
                foreach ($priceTypeIds as $typeId) {
                    $adjustment = (string)($priceAdjustments[$typeId] ?? '');
                    $priceMinor = $this->minorAdjustment->apply($costMinor, $adjustment);
                    $logs[] = "Тип цены #{$typeId}";
                    $logs[] = 'Корректировка: ' . ($adjustment === '' ? 'нет' : $adjustment);
                    $logs[] = 'Новая цена: ' . MinorMoney::format($priceMinor) . ' RUB';
                    if (!$this->setPriceByTypeMinor($productId, (int)$typeId, $priceMinor, 'RUB')) {
                        throw new Exception('catalog_price_update_failed');
                    }
                }
                $success++;
                $processed++;
            } catch (\Throwable $exception) {
                $errors++;
                $processed++;
                $errorIds[] = $productId;
                $logs[] = "    ✗ Ошибка при обработке товара ID {$productId}: " . $exception->getMessage();
            }
        }
        if ($errorIds && !empty($params['error_file'])) {
            file_put_contents($logDir . '/' . basename((string)$params['error_file']), implode("\n", $errorIds) . "\n", FILE_APPEND);
        }
        $logs[] = "* Обновление KORSAC-цен окончено. {$success} товаров - успешно. {$errors} товаров - с ошибкой.";
        return [
            'logs' => $logs, 'success_count' => $success, 'error_count' => $errors,
            'offers_success' => 0, 'offers_processed' => 0,
            'skipped_no_price_update_count' => $skipped, 'processed_elements' => $processed,
        ];
    }

    private function findElementsByXmlId(int $iblockId, int $propertyId, string $xmlId): array { /* same */
        $elements = [];
        $res = \CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'PROPERTY_' . $propertyId => $xmlId, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME', 'IBLOCK_ID']);
        while ($e = $res->Fetch()) { $elements[] = $e; }
        return $elements;
    }
    private function getProductOffers(int $productId, array $skuInfo): array { $offers=[]; $res=\CIBlockElement::GetList(['ID'=>'ASC'],['IBLOCK_ID'=>$skuInfo['IBLOCK_ID'],'PROPERTY_'.$skuInfo['SKU_PROPERTY_ID']=>$productId,'ACTIVE'=>'Y'],false,false,['ID','NAME','IBLOCK_ID']); while($o=$res->Fetch()){$offers[]=$o;} return $offers; }
    private function shouldSkipPriceUpdate(int $elementId, int $iblockId): bool { $res=\CIBlockElement::GetProperty($iblockId,$elementId,[],['CODE'=>'NO_PRICE_UPDATE']); while($p=$res->Fetch()){ $x=trim((string)($p['VALUE_XML_ID']??'')); $v=trim((string)($p['VALUE']??'')); if($x==='N'||$v==='N'){return true;}} return false; }
    private function getElementHlProperties(int $elementId, int $iblockId, ?int $excludePropertyId = null): array { $props=[]; $res=\CIBlockElement::GetProperty($iblockId,$elementId); while($p=$res->Fetch()){ if($p['PROPERTY_TYPE']=='S'&&$p['USER_TYPE']=='directory'&&!empty($p['VALUE'])&&$p['ID']!=$excludePropertyId){$props[]=['PROPERTY_ID'=>$p['ID'],'VALUE'=>$p['VALUE'],'USER_TYPE_SETTINGS'=>$p['USER_TYPE_SETTINGS']];}} return $props; }
    private function getHlElementPrice(string $hlDataClass, int $hlElementId): int { $e=$hlDataClass::getById($hlElementId)->fetch(); return ($e && isset($e['UF_PRICE']) && is_numeric($e['UF_PRICE'])) ? (int)$e['UF_PRICE'] : 0; }
    private function calculateElementPrice(array $properties, int $currentPropertyPrice = 0): int { $total=$currentPropertyPrice; foreach($properties as $p){ try{ if(empty($p['VALUE'])||empty($p['USER_TYPE_SETTINGS'])){continue;} $s=$p['USER_TYPE_SETTINGS']; if(is_string($s)){$s=@unserialize($s); if($s===false)continue;} if(!is_array($s)||empty($s['TABLE_NAME']))continue; $hl=HighloadBlockTable::getList(['filter'=>['=TABLE_NAME'=>$s['TABLE_NAME']],'limit'=>1])->fetch(); if(!$hl)continue; $entity=HighloadBlockTable::compileEntity($hl); $cls=$entity->getDataClass(); $he=$cls::getList(['filter'=>['=UF_XML_ID'=>$p['VALUE']],'select'=>['UF_PRICE'],'limit'=>1])->fetch(); if($he&&isset($he['UF_PRICE'])&&is_numeric($he['UF_PRICE'])){$total+=(int)$he['UF_PRICE'];}}catch(Exception $e){continue;}} return $total; }
    private function updateElementPrices(int $elementId, int $basePrice, array $priceTypeIds, array $priceAdjustments): bool {
        try {
            $product = \CCatalogProduct::GetByID($elementId);
            if (!$product) {
                return false;
            }
            $updated = 0;
            foreach ($priceTypeIds as $typeId) {
                $finalPrice = $this->applyAdjustment($basePrice, (string)($priceAdjustments[$typeId] ?? ''));
                if ($this->setPriceByType($elementId, (int)$typeId, $finalPrice, 'RUB')) {
                    $updated++;
                }
            }
            return $updated === count($priceTypeIds);
        } catch (Exception $e) {
            return false;
        }
    }
    private function updateOffersPrices(array $offers, int $elementPrice, int $offerIblockId, array $priceTypeIds, array $priceAdjustments): array { $updated=0; foreach($offers as $offer){ try{$props=$this->getElementHlProperties((int)$offer['ID'],$offerIblockId); $comp=$this->calculateElementPrice($props); $total=$elementPrice+$comp; $op=\CCatalogProduct::GetByID($offer['ID']); if($op && $this->updateElementPrices((int)$offer['ID'],$total,$priceTypeIds,$priceAdjustments)){$updated++;}}catch(Exception $e){continue;}} return ['success'=>$updated>0,'offers_updated'=>$updated,'total_offers'=>count($offers)]; }
    private function setPriceByType(int $productId, int $priceTypeId, float $price, string $currency): bool {
        $price = max(0, round($price, 2));
        $existing = \CPrice::GetList([], ['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId])->Fetch();
        if ($existing) {
            return (bool)\CPrice::Update((int)$existing['ID'], ['PRICE' => $price, 'CURRENCY' => $currency]);
        }
        return (bool)\CPrice::Add([
            'PRODUCT_ID' => $productId,
            'CATALOG_GROUP_ID' => $priceTypeId,
            'PRICE' => $price,
            'CURRENCY' => $currency
        ]);
    }
    private function setPriceByTypeMinor(int $productId, int $priceTypeId, int $priceMinor, string $currency = 'RUB'): bool {
        $price = MinorMoney::format($priceMinor);
        $existing = \CPrice::GetList([], ['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId])->Fetch();
        if ($existing) {
            return (bool)\CPrice::Update((int)$existing['ID'], ['PRICE' => $price, 'CURRENCY' => $currency]);
        }
        return (bool)\CPrice::Add(['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => $priceTypeId, 'PRICE' => $price, 'CURRENCY' => $currency]);
    }
    private function applyAdjustment(float $basePrice, string $adjustment): float {
        $adjustment = trim($adjustment);
        if ($adjustment === '') {
            return $basePrice;
        }
        if (!preg_match('/^([+-])\\s*(\\d+(?:[\\.,]\\d+)?)\\s*(%)?$/', $adjustment, $m)) {
            return $basePrice;
        }
        $sign = $m[1] === '-' ? -1 : 1;
        $value = (float)str_replace(',', '.', $m[2]);
        $isPercent = !empty($m[3]);
        if ($isPercent) {
            return $basePrice + ($basePrice * ($sign * $value / 100));
        }
        return $basePrice + ($sign * $value);
    }
}
