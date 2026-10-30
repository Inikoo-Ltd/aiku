<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-30 14:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Enums\GoodsIn\StockDelivery;

/**
 * The columns a container sheet is read by, found by their heading in any position. Each heading is matched whole,
 * so "Units/SKO" is never taken for "Units". The quantity is read from the first of units, SKOs or cartons present.
 */
enum StockDeliveryItemsSheetColumnEnum: string
{
    case CODE           = 'code';
    case UNITS          = 'units';
    case SKOS           = 'skos';
    case CARTONS        = 'cartons';
    case PURCHASE_ORDER = 'purchase_order';
    case BARCODE        = 'barcode';
    case UNITS_PER_SKO  = 'units_per_sko';
    case DESCRIPTION    = 'description';

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return match ($this) {
            self::CODE           => ['code', 'sku', 'sku code', 'product code', 'item code', 'part reference', 'supplier code', 'supplier product code', 'suppliers product code', 'kode', 'kode barang'],
            self::UNITS          => ['total units', 'units', 'total unit', 'unit quantity', 'quantity units', 'qty units', 'qty', 'quantity', 'total qty', 'total quantity', 'pcs', 'total pcs', 'jumlah', 'jumlah unit'],
            self::SKOS           => ['total skos', 'total sko', 'skos', 'sko quantity', 'qty sko', 'quantity skos'],
            self::CARTONS        => ['total ctn', 'total cartons', 'total carton', 'cartons', 'ctn', 'carton quantity', 'qty ctn', 'qty cartons'],
            self::PURCHASE_ORDER => ['po', 'po reference', 'po number', 'po no', 'purchase order', 'purchase order reference', 'order reference', 'proforma'],
            self::BARCODE        => ['barcode', 'unit barcode', 'ean', 'ean13'],
            self::UNITS_PER_SKO  => ['units/sko', 'units per sko', 'unit/sko', 'units/pack', 'units per pack'],
            self::DESCRIPTION    => ['unit description', 'description', 'name', 'product name', 'item name', 'nama barang'],
        };
    }

    public static function normaliseHeading(mixed $heading): string
    {
        $heading = preg_replace("/['`´‘’‛′ʼ＇.:]/u", '', (string) $heading);
        $heading = preg_replace('/\s*\/\s*/u', '/', $heading);

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($heading)));
    }

    public static function fromHeading(mixed $heading): ?self
    {
        $heading = self::normaliseHeading($heading);
        if ($heading === '') {
            return null;
        }

        foreach (self::cases() as $column) {
            if (in_array($heading, $column->headings(), true)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return list<self>
     */
    public static function quantityColumns(): array
    {
        return [self::UNITS, self::SKOS, self::CARTONS];
    }
}
