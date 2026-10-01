<?php

spl_autoload_register(static function (string $class): void {
    $prefix = 'KK\\PriceUpdate\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    require dirname(__DIR__, 2) . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use KK\PriceUpdate\Exception\PricingException;
use KK\PriceUpdate\Pricing\KorsacDefaultCostProvider;
use KK\PriceUpdate\Pricing\KorsacDefaultCostProviderInterface;
use KK\PriceUpdate\Pricing\MinorMoney;
use KK\PriceUpdate\Pricing\MinorPriceAdjustment;
use KK\PriceUpdate\Routing\PropertyMode;
use KK\PriceUpdate\Routing\PropertyModeResolver;

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

$classifier = static function (string $code): ?string {
    $canonical = ['KK_RAM_DEFAULT' => 'DEFAULT', 'KK_RAM_OPTIONS' => 'OPTIONS', 'KK_SOFTWARE_MULTI_OPTIONS' => 'MULTI_OPTIONS'];
    return $canonical[$code] ?? null;
};
$resolver = new PropertyModeResolver(static function (): bool { return true; }, $classifier);
$test('legacy routing', static function () use ($same, $resolver): void { $same(PropertyMode::LEGACY, $resolver->resolve('RAM')); });
$test('KORSAC default routing', static function () use ($same, $resolver): void { $same(PropertyMode::KORSAC_DEFAULT, $resolver->resolve('KK_RAM_DEFAULT')); });
$test('KORSAC options rejected', static function () use ($throws, $resolver): void { $throws('korsac_non_default_property_not_updatable', static function () use ($resolver): void { $resolver->resolve('KK_RAM_OPTIONS'); }); });
$test('KORSAC multi options rejected', static function () use ($throws, $resolver): void { $throws('korsac_non_default_property_not_updatable', static function () use ($resolver): void { $resolver->resolve('KK_SOFTWARE_MULTI_OPTIONS'); }); });
$test('missing KORSAC rejects KORSAC', static function () use ($throws, $classifier): void { $r = new PropertyModeResolver(static function (): bool { return false; }, $classifier); $throws('korsac_module_not_available', static function () use ($r): void { $r->resolve('KK_RAM_DEFAULT'); }); });
$test('missing KORSAC preserves legacy', static function () use ($same, $classifier): void { $r = new PropertyModeResolver(static function (): bool { return false; }, $classifier); $same(PropertyMode::LEGACY, $r->resolve('MATERIAL')); });
$test('default cost adapter returns minor amount', static function () use ($same): void { $provider = new KorsacDefaultCostProvider(static function (): int { return 3099000; }); $same(3099000, $provider->getDefaultCostMinor(2, 4)); });
$test('provider interface supports fake', static function () use ($same): void { $fake = new class implements KorsacDefaultCostProviderInterface { public function getDefaultCostMinor(int $iblockId, int $productId): int { return $iblockId + $productId; } }; $same(6, $fake->getDefaultCostMinor(2, 4)); });

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
