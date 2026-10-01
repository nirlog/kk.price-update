<?php

final class PriceUpdaterFixtureResult
{
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = array_values($rows);
    }

    public function Fetch()
    {
        return array_shift($this->rows) ?: false;
    }
}

final class CIBlockElement
{
    public static $elements = [];

    public static function GetList(): PriceUpdaterFixtureResult
    {
        return new PriceUpdaterFixtureResult(self::$elements);
    }

    public static function GetProperty(): PriceUpdaterFixtureResult
    {
        return new PriceUpdaterFixtureResult([]);
    }
}

final class CCatalogProduct
{
    public static function GetByID(int $productId): array
    {
        return ['ID' => $productId];
    }
}

final class CPrice
{
    public static $writes = [];

    public static function GetList(): PriceUpdaterFixtureResult
    {
        return new PriceUpdaterFixtureResult([]);
    }

    public static function Add(array $fields): int
    {
        self::$writes[] = $fields;
        return count(self::$writes);
    }

    public static function Update(int $id, array $fields): bool
    {
        self::$writes[] = $fields + ['ID' => $id];
        return true;
    }
}
