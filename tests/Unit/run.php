<?php

spl_autoload_register(static function (string $class): void {
    $prefix = 'KK\\PriceUpdate\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    require dirname(__DIR__, 2) . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});
require __DIR__ . '/Fixtures/KorsacContracts.php';
require __DIR__ . '/Fixtures/PriceUpdaterBitrix.php';

use KK\PriceUpdate\Exception\PricingException;
use KK\PriceUpdate\Pricing\KorsacCatalogPricePlan;
use KK\PriceUpdate\Pricing\KorsacDefaultCatalogPriceProvider;
use KK\PriceUpdate\Pricing\KorsacDefaultCatalogPriceProviderInterface;
use KK\PriceUpdate\Pricing\MinorMoney;
use KK\PriceUpdate\Pricing\MinorPriceAdjustment;
use KK\PriceUpdate\Routing\PropertyMode;
use KK\PriceUpdate\Routing\PropertyModeResolver;
use KK\PriceUpdate\Service\PriceUpdater;

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void { $tests[$name] = $callback; };
$same = static function ($expected, $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};
$throws = static function (string $message, callable $callback): void {
    try { $callback(); } catch (PricingException $exception) {
        if ($exception->getMessage() === $message) { return; }
        throw new RuntimeException("Expected {$message}, got {$exception->getMessage()}");
    }
    throw new RuntimeException("Expected exception {$message}");
};

$resolver = new PropertyModeResolver(static function (): bool { return true; });
$test('legacy routing', static function () use ($same, $resolver): void { $same(PropertyMode::LEGACY, $resolver->resolve('RAM')); });
$test('parser role routes canonical KORSAC default', static function () use ($same, $resolver): void { $same(PropertyMode::KORSAC_DEFAULT, $resolver->resolve('KK_RAM_DEFAULT')); });
$test('KORSAC options rejected', static function () use ($throws, $resolver): void { $throws('korsac_non_default_property_not_updatable', static function () use ($resolver): void { $resolver->resolve('KK_RAM_OPTIONS'); }); });
$test('KORSAC multi options rejected', static function () use ($throws, $resolver): void { $throws('korsac_non_default_property_not_updatable', static function () use ($resolver): void { $resolver->resolve('KK_SOFTWARE_MULTI_OPTIONS'); }); });
$test('non-canonical KORSAC-shaped property stays legacy', static function () use ($same, $resolver): void { $same(PropertyMode::LEGACY, $resolver->resolve('KK_UNKNOWN_DEFAULT')); });
$test('missing KORSAC rejects KORSAC', static function () use ($throws): void { $r = new PropertyModeResolver(static function (): bool { return false; }); $throws('korsac_module_not_available', static function () use ($r): void { $r->resolve('KK_RAM_DEFAULT'); }); });
$test('missing KORSAC preserves legacy', static function () use ($same): void { $r = new PropertyModeResolver(static function (): bool { return false; }); $same(PropertyMode::LEGACY, $r->resolve('MATERIAL')); });
$test('provider uses KORSAC policy and default catalog calculator', static function () use ($same): void {
    $provider = new KorsacDefaultCatalogPriceProvider(static function (): bool { return true; });
    $same(3698800, $provider->getDefaultCatalogPriceMinor(2, 4, 2));
    $same(true, \KK\Korsac\Catalog\ProductConfigurationRepository::$receivedGateway instanceof \KK\Korsac\Catalog\BitrixCatalogPropertyGateway);
    $same([2, 4], \KK\Korsac\Catalog\ProductConfigurationRepository::$receivedArguments);
    $same([2, 2], \KK\Korsac\Pricing\BitrixPricingPolicyProvider::$receivedArguments);
    $same(true, \KK\Korsac\Pricing\RetailOptionPriceProvider::$receivedRawProvider instanceof \KK\Korsac\Pricing\HlOptionPriceProvider);
    $same(['iblockId' => 2, 'priceTypeId' => 2], \KK\Korsac\Pricing\RetailOptionPriceProvider::$receivedPolicy);
    $same(true, \KK\Korsac\Pricing\DefaultCatalogPriceCalculator::$receivedProvider instanceof \KK\Korsac\Pricing\RetailOptionPriceProvider);
    $same(['iblockId' => 2, 'priceTypeId' => 2], \KK\Korsac\Pricing\DefaultCatalogPriceCalculator::$receivedPolicy);
    $same(['iblockId' => 2, 'productId' => 4], \KK\Korsac\Pricing\DefaultCatalogPriceCalculator::$receivedConfiguration);
});
$test('provider preserves missing pricing policy diagnostic code', static function () use ($throws): void {
    \KK\Korsac\Pricing\BitrixPricingPolicyProvider::$exception = new \KK\Korsac\Pricing\ConfigurationPricingException([
        'code' => 'pricing_policy_not_configured',
    ]);
    $provider = new KorsacDefaultCatalogPriceProvider(static function (): bool { return true; });
    $throws('pricing_policy_not_configured', static function () use ($provider): void {
        $provider->getDefaultCatalogPriceMinor(2, 4, 3);
    });
});
$test('provider preserves invalid pricing policy diagnostic code', static function () use ($throws): void {
    \KK\Korsac\Pricing\BitrixPricingPolicyProvider::$exception = new \KK\Korsac\Pricing\ConfigurationPricingException([
        'code' => 'invalid_pricing_policy',
    ]);
    $provider = new KorsacDefaultCatalogPriceProvider(static function (): bool { return true; });
    $throws('invalid_pricing_policy', static function () use ($provider): void {
        $provider->getDefaultCatalogPriceMinor(2, 4, 2);
    });
});
$test('provider uses safe generic exception message as fallback', static function () use ($throws): void {
    \KK\Korsac\Pricing\BitrixPricingPolicyProvider::$exception = new RuntimeException('some_safe_message');
    $provider = new KorsacDefaultCatalogPriceProvider(static function (): bool { return true; });
    $throws('some_safe_message', static function () use ($provider): void {
        $provider->getDefaultCatalogPriceMinor(2, 4, 2);
    });
});
$test('provider interface forwards iblock, product and price type', static function () use ($same): void {
    $received = [];
    $fake = new class($received) implements KorsacDefaultCatalogPriceProviderInterface {
        private $received;
        public function __construct(array &$received) { $this->received = &$received; }
        public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int { $this->received[] = [$iblockId, $productId, $priceTypeId]; return 3698800; }
    };
    $same([2 => 3698800], (new KorsacCatalogPricePlan($fake))->calculate(2, 4, [2]));
    $same([[2, 4, 2]], $received);
});
$test('KORSAC plan calculates distinct prices and ignores local adjustments', static function () use ($same): void {
    $fake = new class implements KorsacDefaultCatalogPriceProviderInterface {
        public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int { return [2 => 3698800, 3 => 3550000][$priceTypeId]; }
    };
    $dangerousAdjustments = [2 => '+999%', 3 => '-1000'];
    $prices = (new KorsacCatalogPricePlan($fake))->calculate(2, 4, array_keys($dangerousAdjustments));
    $same([2 => 3698800, 3 => 3550000], $prices);
});
$test('KORSAC plan fails before caller can write partial prices', static function () use ($same, $throws): void {
    $fake = new class implements KorsacDefaultCatalogPriceProviderInterface {
        public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int {
            if ($priceTypeId === 3) { throw new PricingException('pricing_policy_not_configured'); }
            return 3698800;
        }
    };
    $writes = [];
    $throws('pricing_policy_not_configured', static function () use ($fake, &$writes): void {
        $prices = (new KorsacCatalogPricePlan($fake))->calculate(2, 4, [2, 3]);
        foreach ($prices as $typeId => $price) { $writes[] = [$typeId, $price]; }
    });
    $same([], $writes);
});
$test('missing KORSAC provider dependency fails explicitly', static function () use ($throws): void {
    $provider = new KorsacDefaultCatalogPriceProvider(static function (): bool { return false; });
    $throws('korsac_module_not_available', static function () use ($provider): void { $provider->getDefaultCatalogPriceMinor(2, 4, 2); });
});

$invokeKorsacUpdater = static function (KorsacDefaultCatalogPriceProviderInterface $provider, array $priceTypeIds, array $priceAdjustments): array {
    \CIBlockElement::$elements = [['ID' => 4, 'NAME' => 'Fixture product', 'IBLOCK_ID' => 2]];
    \CPrice::$writes = [];
    $updater = new PriceUpdater(new PropertyModeResolver(static function (): bool { return true; }), $provider, new MinorPriceAdjustment());
    $method = new ReflectionMethod(PriceUpdater::class, 'processKorsacValue');
    $method->setAccessible(true);
    return $method->invoke(
        $updater,
        ['ID' => 10, 'NAME' => 'Fixture option', 'XML_ID' => 'fixture'],
        ['NAME' => 'KORSAC default'],
        2,
        20,
        null,
        [],
        sys_get_temp_dir(),
        $priceTypeIds,
        $priceAdjustments
    );
};
$test('PriceUpdater ignores KORSAC PRICE_ADJUSTMENTS', static function () use ($same, $invokeKorsacUpdater): void {
    $provider = new class implements KorsacDefaultCatalogPriceProviderInterface {
        public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int { return 3698800; }
    };
    $result = $invokeKorsacUpdater($provider, [2], [2 => '+999%']);
    $same(1, $result['success_count']);
    $same(0, $result['error_count']);
    $same([[
        'PRODUCT_ID' => 4,
        'CATALOG_GROUP_ID' => 2,
        'PRICE' => '36988.00',
        'CURRENCY' => 'RUB',
    ]], \CPrice::$writes);
});
$test('PriceUpdater makes no partial write when a KORSAC policy fails', static function () use ($same, $invokeKorsacUpdater): void {
    $provider = new class implements KorsacDefaultCatalogPriceProviderInterface {
        public function getDefaultCatalogPriceMinor(int $iblockId, int $productId, int $priceTypeId): int {
            if ($priceTypeId === 3) { throw new PricingException('pricing_policy_not_configured'); }
            return 3698800;
        }
    };
    $result = $invokeKorsacUpdater($provider, [2, 3], []);
    $same(0, $result['success_count']);
    $same(1, $result['error_count']);
    $same([], \CPrice::$writes);
    $same(true, strpos(implode("\n", $result['logs']), 'pricing_policy_not_configured') !== false);
});

$adjust = new MinorPriceAdjustment();
$test('fixed +1000', static function () use ($same, $adjust): void { $same(1200000 + 100000, $adjust->apply(1200000, '+1000')); });
$test('fixed -2500.50', static function () use ($same, $adjust): void { $same(949950, $adjust->apply(1200000, '-2500.50')); });
$test('percentage +20%', static function () use ($same, $adjust): void { $same(14400000, $adjust->apply(12000000, '+20%')); });
$test('percentage -10%', static function () use ($same, $adjust): void { $same(10800000, $adjust->apply(12000000, '-10%')); });
$test('decimal percentage +12.5%', static function () use ($same, $adjust): void { $same(13500000, $adjust->apply(12000000, '+12.5%')); });
$test('percentage half-up', static function () use ($same, $adjust): void { $same(102, $adjust->apply(101, '+0.5%')); });
$test('comma fixed amount', static function () use ($same, $adjust): void { $same(749950, $adjust->apply(1000000, '-2500,50')); });
$test('invalid adjustment rejected', static function () use ($throws, $adjust): void { $throws('invalid_price_adjustment', static function () use ($adjust): void { $adjust->apply(100, '+abc%'); }); });
$test('negative final rejected', static function () use ($throws, $adjust): void { $throws('negative_catalog_price', static function () use ($adjust): void { $adjust->apply(100, '-2'); }); });
$test('overflow rejected', static function () use ($throws, $adjust): void { $throws('price_overflow', static function () use ($adjust): void { $adjust->apply(PHP_INT_MAX, '+0.01'); }); });
$test('minor formatter', static function () use ($same): void { $same('1234.56', MinorMoney::format(123456)); $same('0.01', MinorMoney::format(1)); $same('0.00', MinorMoney::format(0)); });

$failed = 0;
foreach ($tests as $name => $callback) {
    try { $callback(); echo "[OK] {$name}\n"; } catch (Throwable $exception) { $failed++; fwrite(STDERR, "[FAIL] {$name}: {$exception->getMessage()}\n"); }
}
echo sprintf("%d tests, %d failed\n", count($tests), $failed);
exit($failed === 0 ? 0 : 1);
