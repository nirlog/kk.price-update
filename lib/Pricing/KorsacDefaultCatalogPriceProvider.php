<?php

namespace KK\PriceUpdate\Pricing;

use Bitrix\Main\Loader;
use KK\PriceUpdate\Exception\PricingException;
use KK\Korsac\Catalog\BitrixCatalogPropertyGateway;
use KK\Korsac\Catalog\ProductConfigurationRepository;
use KK\Korsac\Pricing\BitrixPricingPolicyProvider;
use KK\Korsac\Pricing\DefaultCatalogPriceCalculator;
use KK\Korsac\Pricing\HlOptionPriceProvider;
use KK\Korsac\Pricing\RetailOptionPriceProvider;

final class KorsacDefaultCatalogPriceProvider implements KorsacDefaultCatalogPriceProviderInterface
{
    /** @var callable|null */
    private $moduleLoader;

    public function __construct(?callable $moduleLoader = null)
    {
        $this->moduleLoader = $moduleLoader;
    }

    public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int
    {
        $available = $this->moduleLoader
            ? (bool)call_user_func($this->moduleLoader)
            : Loader::includeModule('kk.korsac');
        if (!$available) {
            throw new PricingException('korsac_module_not_available');
        }

        try {
            $configuration = (new ProductConfigurationRepository(new BitrixCatalogPropertyGateway()))
                ->get($iblockId, $productId);
            $policy = (new BitrixPricingPolicyProvider())->get($iblockId, $priceTypeId);
            $retailPrices = new RetailOptionPriceProvider(new HlOptionPriceProvider(), $policy);
            $result = (new DefaultCatalogPriceCalculator($retailPrices, $policy))->calculate($configuration);
            $resultData = $result->toArray();
        } catch (PricingException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new PricingException($this->diagnosticCodeFromThrowable($exception), (int)$exception->getCode(), $exception);
        }

        if (!is_array($resultData) || !array_key_exists('totalMinor', $resultData)
            || !is_int($resultData['totalMinor']) || $resultData['totalMinor'] < 0) {
            throw new PricingException('invalid_korsac_default_catalog_price');
        }

        return $resultData['totalMinor'];
    }

    private function diagnosticCodeFromThrowable(\Throwable $exception): string
    {
        if (method_exists($exception, 'diagnostic')) {
            try {
                $diagnostic = $exception->diagnostic();
                if (is_array($diagnostic) && array_key_exists('code', $diagnostic)) {
                    $code = $this->safeDiagnosticValue($diagnostic['code']);
                    if ($code !== null) {
                        return $code;
                    }
                }
            } catch (\Throwable $ignored) {
                // Fall through to the safe message/fallback boundary below.
            }
        }

        return $this->safeDiagnosticValue($exception->getMessage())
            ?? 'korsac_price_calculation_failed';
    }

    private function safeDiagnosticValue($value): ?string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '' || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            return null;
        }

        return $value;
    }
}
