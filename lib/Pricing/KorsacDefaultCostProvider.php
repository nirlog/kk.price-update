<?php

namespace KK\PriceUpdate\Pricing;

use Bitrix\Main\Loader;
use KK\PriceUpdate\Exception\PricingException;
use KK\Korsac\Catalog\BitrixCatalogPropertyGateway;
use KK\Korsac\Catalog\ProductConfigurationRepository;
use KK\Korsac\Pricing\DefaultConfigurationCostCalculator;
use KK\Korsac\Pricing\HlOptionPriceProvider;

final class KorsacDefaultCostProvider implements KorsacDefaultCostProviderInterface
{
    /** @var callable|null */
    private $moduleLoader;

    public function __construct(?callable $moduleLoader = null)
    {
        $this->moduleLoader = $moduleLoader;
    }

    public function getDefaultCostMinor(int $iblockId, int $productId): int
    {
        $available = $this->moduleLoader
            ? (bool)call_user_func($this->moduleLoader)
            : Loader::includeModule('kk.korsac');
        if (!$available) {
            throw new PricingException('korsac_module_not_available');
        }

        $repository = new ProductConfigurationRepository(new BitrixCatalogPropertyGateway());
        $configuration = $repository->get($iblockId, $productId);
        $result = (new DefaultConfigurationCostCalculator(new HlOptionPriceProvider()))
            ->calculate($configuration);
        $resultData = $result->toArray();
        if (!is_array($resultData) || !array_key_exists('totalMinor', $resultData)) {
            throw new PricingException('invalid_korsac_default_cost');
        }

        return $this->validate($resultData['totalMinor']);
    }

    private function validate($minor): int
    {
        if (!is_int($minor) || $minor < 0) {
            throw new PricingException('invalid_korsac_default_cost');
        }
        return $minor;
    }
}
