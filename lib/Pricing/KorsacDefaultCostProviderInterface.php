<?php

namespace KK\PriceUpdate\Pricing;

interface KorsacDefaultCostProviderInterface
{
    public function getDefaultCostMinor(int $iblockId, int $productId): int;
}
