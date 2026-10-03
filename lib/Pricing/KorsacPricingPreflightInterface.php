<?php

namespace KK\PriceUpdate\Pricing;

interface KorsacPricingPreflightInterface
{
    /**
     * @param mixed[] $priceTypeIds
     * @return int[] normalized IDs
     */
    public function validate(int $iblockId, array $priceTypeIds): array;
}
