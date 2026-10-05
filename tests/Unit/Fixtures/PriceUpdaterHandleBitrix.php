<?php

namespace Bitrix\Highloadblock {
final class HighloadBlockTable {
    public static function getById(int $id): object
    {
        return new class($id) {
            private $id;
            public function __construct(int $id) { $this->id = $id; }
            public function fetch(): array { return ['ID' => $this->id, 'NAME' => 'Fixture']; }
        };
    }

    public static function compileEntity(array $block): object
    {
        return new class {
            public function getDataClass(): string { return \PriceUpdaterHandleHlData::class; }
        };
    }
}
}

namespace {
    final class PriceUpdaterHandleHlData
    {
        public static function getList(array $query): PriceUpdaterFixtureResult
        {
            return new PriceUpdaterFixtureResult([[
                'ID' => 10, 'UF_NAME' => 'Fixture option', 'UF_XML_ID' => 'fixture',
            ]]);
        }
    }

    final class CIBlockProperty
    {
        public static function GetByID(int $propertyId): PriceUpdaterFixtureResult
        {
            return new PriceUpdaterFixtureResult([[
                'ID' => $propertyId, 'CODE' => 'KK_RAM_DEFAULT', 'NAME' => 'KORSAC default',
            ]]);
        }
    }

    final class CCatalogSKU
    {
        public static function GetInfoByProductIBlock(int $iblockId)
        {
            return false;
        }
    }
}
