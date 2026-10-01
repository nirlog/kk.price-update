<?php

namespace KK\PriceUpdate\Pricing;

final class MinorMoney
{
    public static function format(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $absolute = abs($minor);

        return $sign . intdiv($absolute, 100) . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
