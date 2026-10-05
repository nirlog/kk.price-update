<?php

namespace KK\PriceUpdate\Pricing;

use Bitrix\Main\Loader;
use KK\Korsac\Pricing\BitrixPricingPolicyProvider;
use KK\PriceUpdate\Exception\PricingException;

final class KorsacPricingPreflight implements KorsacPricingPreflightInterface
{
    /** @var callable|null */
    private $moduleLoader;
    /** @var callable|null */
    private $priceTypeExists;
    /** @var callable|null */
    private $policyProviderFactory;

    public function __construct(
        ?callable $moduleLoader = null,
        ?callable $priceTypeExists = null,
        ?callable $policyProviderFactory = null
    ) {
        $this->moduleLoader = $moduleLoader;
        $this->priceTypeExists = $priceTypeExists;
        $this->policyProviderFactory = $policyProviderFactory;
    }

    public function validate(int $iblockId, array $priceTypeIds): array
    {
        $ids = self::normalizePriceTypeIds($priceTypeIds);
        $available = $this->moduleLoader
            ? (bool)call_user_func($this->moduleLoader)
            : Loader::includeModule('kk.korsac');
        if (!$available) {
            throw new PricingException('korsac_module_not_available', [
                'code' => 'korsac_module_not_available', 'iblockId' => $iblockId,
            ]);
        }

        $provider = $this->policyProviderFactory
            ? call_user_func($this->policyProviderFactory)
            : new BitrixPricingPolicyProvider();
        $failures = [];
        foreach ($ids as $priceTypeId) {
            $exists = $this->priceTypeExists
                ? (bool)call_user_func($this->priceTypeExists, $priceTypeId)
                : (bool)\CCatalogGroup::GetByID($priceTypeId);
            if (!$exists) {
                $failures[] = ['code' => 'catalog_price_type_not_found', 'priceTypeId' => $priceTypeId];
                continue;
            }
            try {
                $policy = $provider->get($iblockId, $priceTypeId);
                if ($policy === null || $policy === false) {
                    throw new PricingException('invalid_pricing_policy');
                }
            } catch (\Throwable $exception) {
                $failures[] = PricingException::fromThrowable($exception, [
                    'iblockId' => $iblockId, 'priceTypeId' => $priceTypeId,
                ])->diagnostic();
            }
        }
        if ($failures) {
            $lines = ['Невозможно начать обновление KORSAC-цен.', '', 'Проблемы:'];
            foreach ($failures as $failure) {
                $lines[] = '- тип цены #' . ($failure['priceTypeId'] ?? '?') . ': '
                    . ($failure['code'] ?? 'korsac_price_calculation_failed');
            }
            throw new PricingException(implode("\n", $lines), ['code' => 'korsac_preflight_failed']);
        }
        return $ids;
    }

    /** @param mixed[] $ids @return int[] */
    public static function normalizePriceTypeIds(array $ids): array
    {
        $normalized = [];
        $seen = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && preg_match('/^\d+$/', $id))) {
                $id = (int)$id;
                if ($id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $normalized[] = $id;
                }
            }
        }
        return $normalized;
    }
}
