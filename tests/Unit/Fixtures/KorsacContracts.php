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

final class DefaultConfigurationCostCalculator
{
    public static $receivedProvider;
    public static $receivedConfiguration;

    public function __construct(HlOptionPriceProvider $provider)
    {
        self::$receivedProvider = $provider;
    }

    public function calculate(array $configuration): DefaultCostResult
    {
        self::$receivedConfiguration = $configuration;
        return new DefaultCostResult();
    }
}

final class DefaultCostResult
{
    public function toArray(): array
    {
        return ['totalMinor' => 3099000];
    }
}
