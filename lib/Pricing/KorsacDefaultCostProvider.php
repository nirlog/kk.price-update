<?php

namespace KK\PriceUpdate\Pricing;

use Bitrix\Main\Loader;
use KK\PriceUpdate\Exception\PricingException;

final class KorsacDefaultCostProvider implements KorsacDefaultCostProviderInterface
{
    /** @var callable|null */
    private $calculator;

    public function __construct(?callable $calculator = null)
    {
        $this->calculator = $calculator;
    }

    public function getDefaultCostMinor(int $iblockId, int $productId): int
    {
        if ($this->calculator) {
            return $this->validate(call_user_func($this->calculator, $iblockId, $productId));
        }
        if (!Loader::includeModule('kk.korsac')) {
            throw new PricingException('korsac_module_not_available');
        }

        $repositoryClass = $this->firstClass([
            'KK\\Korsac\\Repository\\ProductConfigurationRepository',
            'KK\\Korsac\\ProductConfigurationRepository',
        ]);
        $providerClass = $this->firstClass([
            'KK\\Korsac\\Pricing\\HlOptionPriceProvider',
            'KK\\Korsac\\HlOptionPriceProvider',
        ]);
        $calculatorClass = $this->firstClass([
            'KK\\Korsac\\Pricing\\DefaultConfigurationCostCalculator',
            'KK\\Korsac\\DefaultConfigurationCostCalculator',
        ]);
        if (!$repositoryClass || !$providerClass || !$calculatorClass) {
            throw new PricingException('korsac_pricing_api_not_available');
        }

        $repository = new $repositoryClass();
        $configuration = $this->invokeFirst($repository, ['getByProductId', 'findByProductId', 'get'], [$iblockId, $productId]);
        $priceProvider = new $providerClass();
        $calculator = new $calculatorClass($priceProvider);
        $result = $this->invokeFirst($calculator, ['calculate', 'calculateDefaultCost'], [$configuration]);
        if (is_object($result)) {
            foreach (['getTotalMinor', 'totalMinor'] as $method) {
                if (method_exists($result, $method)) {
                    $result = $result->$method();
                    break;
                }
            }
        }
        return $this->validate($result);
    }

    private function firstClass(array $classes): ?string
    {
        foreach ($classes as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }
        return null;
    }

    private function invokeFirst(object $object, array $methods, array $arguments)
    {
        foreach ($methods as $method) {
            if (method_exists($object, $method)) {
                return $object->$method(...$arguments);
            }
        }
        throw new PricingException('korsac_pricing_api_not_available');
    }

    private function validate($minor): int
    {
        if (!is_int($minor) || $minor < 0) {
            throw new PricingException('invalid_korsac_default_cost');
        }
        return $minor;
    }
}
