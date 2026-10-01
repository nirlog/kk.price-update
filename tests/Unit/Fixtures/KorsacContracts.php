<?php

namespace KK\Korsac\Catalog;

final class BitrixCatalogPropertyGateway
{
}

final class PropertyCodeParser
{
    public function parse(string $code): ?array
    {
        $canonical = [
            'KK_RAM_DEFAULT' => ['group' => 'RAM', 'role' => 'DEFAULT', 'mode' => 'SINGLE', 'type' => 'OPTIONS'],
            'KK_RAM_OPTIONS' => ['group' => 'RAM', 'role' => 'OPTIONS', 'mode' => 'SINGLE'],
            'KK_SOFTWARE_MULTI_OPTIONS' => ['group' => 'SOFTWARE', 'role' => 'MULTI_OPTIONS', 'mode' => 'MULTIPLE'],
        ];
        return $canonical[$code] ?? null;
    }
}

final class ProductConfigurationRepository
{
    public static $receivedGateway;
    public static $receivedArguments;

    public function __construct(BitrixCatalogPropertyGateway $gateway)
    {
        self::$receivedGateway = $gateway;
    }

    public function get(int $iblockId, int $productId): array
    {
        self::$receivedArguments = [$iblockId, $productId];
        return ['iblockId' => $iblockId, 'productId' => $productId];
    }
}

namespace KK\Korsac\Pricing;

final class HlOptionPriceProvider
{
}

final class BitrixPricingPolicyProvider
{
    public static $receivedArguments;
    public static $exception;

    public function get(int $iblockId, int $priceTypeId): array
    {
        self::$receivedArguments = [$iblockId, $priceTypeId];
        if (self::$exception instanceof \Throwable) {
            $exception = self::$exception;
            self::$exception = null;
            throw $exception;
        }
        return ['iblockId' => $iblockId, 'priceTypeId' => $priceTypeId];
    }
}

final class ConfigurationPricingException extends \RuntimeException
{
    private $diagnosticData;

    public function __construct(array $diagnostic)
    {
        $this->diagnosticData = $diagnostic;
        parent::__construct((string)json_encode($diagnostic));
    }

    public function diagnostic(): array
    {
        return $this->diagnosticData;
    }
}

final class RetailOptionPriceProvider
{
    public static $receivedRawProvider;
    public static $receivedPolicy;

    public function __construct(HlOptionPriceProvider $rawProvider, array $policy)
    {
        self::$receivedRawProvider = $rawProvider;
        self::$receivedPolicy = $policy;
    }
}

final class DefaultCatalogPriceCalculator
{
    public static $receivedProvider;
    public static $receivedPolicy;
    public static $receivedConfiguration;

    public function __construct(RetailOptionPriceProvider $provider, array $policy)
    {
        self::$receivedProvider = $provider;
        self::$receivedPolicy = $policy;
    }

    public function calculate(array $configuration): DefaultCatalogPriceResult
    {
        self::$receivedConfiguration = $configuration;
        return new DefaultCatalogPriceResult();
    }
}

final class DefaultCatalogPriceResult
{
    public function toArray(): array
    {
        return ['totalMinor' => 3698800];
    }
}
