<?php

namespace KK\PriceUpdate\Pricing;

use KK\PriceUpdate\Exception\PricingException;

final class MinorPriceAdjustment
{
    public function apply(int $baseMinor, string $adjustment): int
    {
        $adjustment = trim($adjustment);
        if ($adjustment === '') {
            return $this->validateResult($baseMinor);
        }
        if (!preg_match('/^([+-])\s*(\d+)(?:[\.,](\d+))?\s*(%)?$/', $adjustment, $match)) {
            throw new PricingException('invalid_price_adjustment');
        }

        $negative = $match[1] === '-';
        $fraction = $match[3] ?? '';
        if (!empty($match[4])) {
            $scale = $this->powerOfTen(strlen($fraction));
            $numerator = $this->decimalDigitsToInt($match[2] . $fraction);
            $denominator = $this->checkedMultiply(100, $scale);
            $delta = $this->roundedMultiplyDivide($baseMinor, $numerator, $denominator);
        } else {
            if (strlen($fraction) > 2) {
                throw new PricingException('invalid_price_adjustment');
            }
            $minorDigits = $match[2] . str_pad($fraction, 2, '0');
            $delta = $this->decimalDigitsToInt($minorDigits);
        }

        if ($negative) {
            $delta = -$delta;
        }
        if (($delta > 0 && $baseMinor > PHP_INT_MAX - $delta) || ($delta < 0 && $baseMinor < PHP_INT_MIN - $delta)) {
            throw new PricingException('price_overflow');
        }

        return $this->validateResult($baseMinor + $delta);
    }

    private function roundedMultiplyDivide(int $value, int $multiplier, int $divisor): int
    {
        if ($value < 0 || $multiplier < 0 || $divisor <= 0) {
            throw new PricingException('invalid_price_adjustment');
        }
        $whole = intdiv($value, $divisor);
        $remainder = $value % $divisor;
        $wholePart = $this->checkedMultiply($whole, $multiplier);
        $remainderProduct = $this->checkedMultiply($remainder, $multiplier);
        $fractionPart = intdiv($remainderProduct, $divisor);
        if (($remainderProduct % $divisor) >= intdiv($divisor, 2) + ($divisor % 2)) {
            $fractionPart++;
        }
        if ($wholePart > PHP_INT_MAX - $fractionPart) {
            throw new PricingException('price_overflow');
        }
        return $wholePart + $fractionPart;
    }

    private function decimalDigitsToInt(string $digits): int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }
        if (strlen($digits) > strlen((string)PHP_INT_MAX) || (strlen($digits) === strlen((string)PHP_INT_MAX) && strcmp($digits, (string)PHP_INT_MAX) > 0)) {
            throw new PricingException('price_overflow');
        }
        return (int)$digits;
    }

    private function powerOfTen(int $power): int
    {
        $result = 1;
        for ($i = 0; $i < $power; $i++) {
            $result = $this->checkedMultiply($result, 10);
        }
        return $result;
    }

    private function checkedMultiply(int $left, int $right): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw new PricingException('price_overflow');
        }
        return $left * $right;
    }

    private function validateResult(int $result): int
    {
        if ($result < 0) {
            throw new PricingException('negative_catalog_price');
        }
        return $result;
    }
}
