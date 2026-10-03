<?php

namespace KK\PriceUpdate\Pricing;

final class KorsacDiagnosticFormatter
{
    /** @param array<string, mixed> $diagnostic */
    public function format(array $diagnostic): string
    {
        $code = isset($diagnostic['code']) && is_scalar($diagnostic['code'])
            ? (string)$diagnostic['code'] : 'korsac_price_calculation_failed';
        $details = [];
        if (!empty($diagnostic['propertyCode'])) {
            $details[] = 'свойство ' . $diagnostic['propertyCode'];
        } elseif (!empty($diagnostic['property'])) {
            $details[] = 'свойство ' . $diagnostic['property'];
        }
        if (!empty($diagnostic['group'])) {
            $details[] = 'группа ' . $diagnostic['group'];
        }
        if (!empty($diagnostic['xmlId'])) {
            $details[] = 'значение ' . $diagnostic['xmlId'];
        }
        if (in_array($code, ['pricing_policy_not_configured', 'invalid_pricing_policy'], true)) {
            if (isset($diagnostic['iblockId'])) {
                $details[] = 'инфоблок #' . $diagnostic['iblockId'];
            }
            if (isset($diagnostic['priceTypeId'])) {
                $details[] = 'тип цены #' . $diagnostic['priceTypeId'];
            }
        }

        return $code . ($details ? ' — ' . implode(', ', $details) : '');
    }
}
