<?php

namespace KK\PriceUpdate\Pricing;

interface KorsacDefaultCatalogPriceProviderInterface
{
    public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int;
}
