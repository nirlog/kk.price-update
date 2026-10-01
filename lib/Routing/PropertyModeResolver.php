<?php

namespace KK\PriceUpdate\Routing;

use Bitrix\Main\Loader;
use KK\PriceUpdate\Exception\PricingException;
use KK\Korsac\Catalog\PropertyCodeParser;

final class PropertyModeResolver
{
    /** @var callable|null */
    private $moduleLoader;
    /** @var PropertyCodeParser|object|null */
    private $parser;

    public function __construct(?callable $moduleLoader = null, $parser = null)
    {
        $this->moduleLoader = $moduleLoader;
        $this->parser = $parser;
    }

    public function resolve(string $propertyCode): string
    {
        if (!preg_match('/^KK_[A-Z0-9_]+_(DEFAULT|OPTIONS|MULTI_OPTIONS)$/', $propertyCode, $match)) {
            return PropertyMode::LEGACY;
        }
        if (!$this->loadKorsac()) {
            throw new PricingException('korsac_module_not_available');
        }

        $parsed = $this->parseCanonicalCode($propertyCode);
        if ($parsed === null) {
            return PropertyMode::LEGACY;
        }
        $role = strtoupper((string)($parsed['role'] ?? ''));
        if ($role === 'DEFAULT') {
            return PropertyMode::KORSAC_DEFAULT;
        }
        if ($role === 'OPTIONS' || $role === 'MULTI_OPTIONS') {
            throw new PricingException('korsac_non_default_property_not_updatable');
        }
        return PropertyMode::LEGACY;
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

    private function parseCanonicalCode(string $code): ?array
    {
        $parser = $this->parser ?? new PropertyCodeParser();
        try {
            $parsed = $parser->parse($code);
        } catch (\Throwable $exception) {
            return null;
        }
        return is_array($parsed) ? $parsed : null;
    }
}
