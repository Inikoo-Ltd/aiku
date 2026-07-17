<?php

namespace App\Enums\Inventory\OrgStockMovement;

use App\Enums\EnumHelperTrait;

enum OrgStockMovementReasonEnum: string
{
    use EnumHelperTrait;

    case COUNT_GAIN      = 'count_gain';
    case COUNT_SHORT     = 'count_short';
    case RECOUNT         = 'recount';
    case DAMAGED         = 'damaged';
    case EXPIRED         = 'expired';
    case DESTROYED       = 'destroyed';
    case DEFECTIVE       = 'defective';
    case LOST            = 'lost';
    case THEFT           = 'theft';
    case SHOWROOM        = 'showroom';
    case SAMPLE          = 'sample';
    case PHOTO           = 'photo';
    case TRADE_SHOW      = 'trade_show';
    case INFLUENCER      = 'influencer';
    case TESTING         = 'testing';
    case QC              = 'qc';
    case TRAINING        = 'training';
    case OFFICE_USE      = 'office_use';
    case INTERNAL_USE    = 'internal_use';
    case WARRANTY        = 'warranty';
    case GOODWILL        = 'goodwill';
    case SUPPLIER_SHORT  = 'supplier_short';
    case SUPPLIER_OVER   = 'supplier_over';
    case SUPPLIER_DAMAGE = 'supplier_damage';
    case PICK_ERROR      = 'pick_error';

    case TRANSFER        = 'transfer';
    case BIN_ERROR       = 'bin_error';
    
    case RETURN_TO_STOCK = 'return_to_stock';
    case BARCODE         = 'barcode';
    case UOM             = 'uom';
    case DATA_FIX        = 'data_fix';
    case MANUAL          = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::COUNT_GAIN      => 'Count Gain (Stock Found)',
            self::COUNT_SHORT     => 'Count Shortage',
            self::RECOUNT         => 'Recount Correction',
            self::DAMAGED         => 'Damaged Stock',
            self::EXPIRED         => 'Expired Product',
            self::DESTROYED       => 'Destroyed / Disposed',
            self::DEFECTIVE       => 'Manufacturing Defect',
            self::LOST            => 'Lost / Missing',
            self::THEFT           => 'Theft / Suspected Theft',
            self::SHOWROOM        => 'Showroom Display',
            self::SAMPLE          => 'Marketing Sample',
            self::PHOTO           => 'Photography / Video Sample',
            self::TRADE_SHOW      => 'Trade Show / Exhibition',
            self::INFLUENCER      => 'Influencer Sample',
            self::TESTING         => 'Product Testing',
            self::QC              => 'Quality Control Testing',
            self::TRAINING        => 'Staff Training',
            self::OFFICE_USE      => 'Office Use',
            self::INTERNAL_USE    => 'Internal Consumption',
            self::WARRANTY        => 'Warranty Replacement',
            self::GOODWILL        => 'Goodwill Replacement',
            self::SUPPLIER_SHORT  => 'Supplier Short Delivery',
            self::SUPPLIER_OVER   => 'Supplier Over Delivery',
            self::SUPPLIER_DAMAGE => 'Supplier Damaged Goods',
            self::PICK_ERROR      => 'Picking Error Correction',
            self::BIN_ERROR       => 'Wrong Bin Location',
            self::TRANSFER        => 'Warehouse Transfer Adjustment',
            self::RETURN_TO_STOCK => 'Returned to Saleable Stock',
            self::BARCODE         => 'Barcode / SKU Correction',
            self::UOM             => 'Unit of Measure Correction',
            self::DATA_FIX        => 'System Data Correction',
            self::MANUAL          => 'Manual Management Adjustment',
        };
    }

    public static function increaseReason(): array
    {
        return self::valuesExcept([
            self::COUNT_SHORT,
            self::DAMAGED,
            self::EXPIRED,
            self::DESTROYED,
            self::DEFECTIVE,
            self::LOST,
            self::THEFT,
            self::SHOWROOM,
            self::SAMPLE,
            self::PHOTO,
            self::TRADE_SHOW,
            self::INFLUENCER,
            self::TESTING,
            self::QC,
            self::TRAINING,
            self::OFFICE_USE,
            self::INTERNAL_USE,
            self::WARRANTY,
            self::GOODWILL,
            self::SUPPLIER_SHORT,
            self::SUPPLIER_DAMAGE,
            self::BIN_ERROR,
            self::TRANSFER,
        ]);
    }

    public static function decreaseReason(): array
    {
        return self::valuesExcept([
            self::COUNT_GAIN,
            self::SUPPLIER_OVER,
            self::RETURN_TO_STOCK,
            self::BIN_ERROR,
            self::TRANSFER,
        ]);
    }

    public static function transferReason(): array
    {
        return self::valuesExcept([
            self::COUNT_GAIN,
            self::COUNT_SHORT,
            self::RECOUNT,
            self::DAMAGED,
            self::EXPIRED,
            self::DESTROYED,
            self::DEFECTIVE,
            self::LOST,
            self::THEFT,
            self::SHOWROOM,
            self::SAMPLE,
            self::PHOTO,
            self::TRADE_SHOW,
            self::INFLUENCER,
            self::TESTING,
            self::QC,
            self::TRAINING,
            self::OFFICE_USE,
            self::INTERNAL_USE,
            self::WARRANTY,
            self::GOODWILL,
            self::SUPPLIER_SHORT,
            self::SUPPLIER_OVER,
            self::SUPPLIER_DAMAGE,
            self::PICK_ERROR,
            self::RETURN_TO_STOCK,
            self::BARCODE,
            self::UOM,
            self::DATA_FIX,
            self::MANUAL,
        ]);
    }

    public static function withLabels(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn (string $value) => [
                $value => self::from($value)->label(),
            ])
            ->all();
    }
}
