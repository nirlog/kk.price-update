<?php

namespace KK\PriceUpdate\Pricing;

/** Builds the complete per-price-type plan before the caller performs any writes. */
final class KorsacCatalogPricePlan
{
    private $provider;

    public function __construct(KorsacDefaultCatalogPriceProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function calculate(int $iblockId, int $productId, array $priceTypeIds): array
    {
        $prices = [];
        foreach ($priceTypeIds as $priceTypeId) {
            $priceTypeId = (int)$priceTypeId;
            $prices[$priceTypeId] = $this->provider->getDefaultCatalogPriceMinor(
                $iblockId,
                $productId,
                $priceTypeId
            );
        }

        return $prices;
    }
}
