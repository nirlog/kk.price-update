<?php

namespace KK\PriceUpdate\Routing;

use Bitrix\Main\Loader;
use KK\PriceUpdate\Exception\PricingException;

final class PropertyModeResolver
{
    /** @var callable|null */
    private $moduleLoader;
    /** @var callable|null */
    private $canonicalClassifier;

    public function __construct(?callable $moduleLoader = null, ?callable $canonicalClassifier = null)
    {
        $this->moduleLoader = $moduleLoader;
        $this->canonicalClassifier = $canonicalClassifier;
    }

    public function resolve(string $propertyCode): string
    {
        if (!preg_match('/^KK_[A-Z0-9_]+_(DEFAULT|OPTIONS|MULTI_OPTIONS)$/', $propertyCode, $match)) {
            return PropertyMode::LEGACY;
        }
        if (!$this->loadKorsac()) {
            throw new PricingException('korsac_module_not_available');
        }

        $kind = $this->classifyCanonicalCode($propertyCode);
        if ($kind === null) {
            return PropertyMode::LEGACY;
        }
        if ($kind !== 'DEFAULT') {
            throw new PricingException('korsac_non_default_property_not_updatable');
        }
        return PropertyMode::KORSAC_DEFAULT;
    }

    public function classifyForUi(string $propertyCode): string
    {
        try {
            return $this->resolve($propertyCode);
        } catch (PricingException $exception) {
            if ($exception->getMessage() === 'korsac_non_default_property_not_updatable') {
                return PropertyMode::KORSAC_NON_DEFAULT;
            }
            throw $exception;
        }
    }

    private function loadKorsac(): bool
    {
        return $this->moduleLoader ? (bool)call_user_func($this->moduleLoader) : Loader::includeModule('kk.korsac');
    }

    private function classifyCanonicalCode(string $code): ?string
    {
        if ($this->canonicalClassifier) {
            $result = call_user_func($this->canonicalClassifier, $code);
            return $result === null ? null : strtoupper((string)$result);
        }

        // KORSAC remains the source of truth: only a code accepted by its public parser/schema is routed.
        $parserClasses = [
            'KK\\Korsac\\Catalog\\PropertyCodeParser',
            'KK\\Korsac\\Schema\\PropertyCodeParser',
            'KK\\Korsac\\PropertyCodeParser',
        ];
        foreach ($parserClasses as $parserClass) {
            if (!class_exists($parserClass)) {
                continue;
            }
            foreach (['parse', 'fromCode', 'parseCode'] as $method) {
                if (!is_callable([$parserClass, $method])) {
                    continue;
                }
                try {
                    $parsed = $parserClass::$method($code);
                    if ($parsed === null || $parsed === false) {
                        return null;
                    }
                    if (is_string($parsed)) {
                        return strtoupper($parsed);
                    }
                    if (is_array($parsed)) {
                        return strtoupper((string)($parsed['type'] ?? $parsed['kind'] ?? $parsed['suffix'] ?? '')) ?: null;
                    }
                    foreach (['getType', 'getKind', 'getSuffix'] as $getter) {
                        if (is_object($parsed) && method_exists($parsed, $getter)) {
                            return strtoupper((string)$parsed->$getter());
                        }
                    }
                } catch (\Throwable $exception) {
                    return null;
                }
            }
        }
        throw new PricingException('korsac_schema_api_not_available');
    }
}
