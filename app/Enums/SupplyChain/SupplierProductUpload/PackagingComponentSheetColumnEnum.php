<?php

namespace App\Enums\SupplyChain\SupplierProductUpload;

/**
 * The columns of the v7 "Packaging components" tab: one row per packaging component, per packaging level, per part.
 * Matched by heading text, like the Product data columns.
 */
enum PackagingComponentSheetColumnEnum: string
{
    case PART_REFERENCE            = 'part_reference';
    case PACKAGING_LEVEL           = 'packaging_level';
    case COMPONENT                 = 'name';
    case MATERIAL                  = 'material';
    case MATERIAL_CODE             = 'material_id_code';
    case WEIGHT                    = 'weight_g';
    case QUANTITY                  = 'quantity';
    case RECYCLED_CONTENT          = 'recycled_content_pct';
    case RECYCLED_CONTENT_EVIDENCE = 'recycled_content_evidence';
    case RECYCLABILITY             = 'recyclability';
    case SEPARABLE                 = 'separable';
    case MARKS                     = 'marks';
    case NATIONAL_MARKS            = 'national_marks';
    case ARTWORK_OWNER             = 'artwork_owner';
    case NOTES                     = 'notes';

    public const string SHEET = 'packaging components';

    public function heading(): string
    {
        return match ($this) {
            self::PART_REFERENCE            => 'Part reference',
            self::PACKAGING_LEVEL           => 'Packaging level',
            self::COMPONENT                 => 'Component',
            self::MATERIAL                  => 'Material',
            self::MATERIAL_CODE             => 'Material code',
            self::WEIGHT                    => 'Weight (g)',
            self::QUANTITY                  => 'Quantity at this level',
            self::RECYCLED_CONTENT          => 'Recycled content %',
            self::RECYCLED_CONTENT_EVIDENCE => 'Recycled content evidence',
            self::RECYCLABILITY             => 'Recyclability',
            self::SEPARABLE                 => 'Separable',
            self::MARKS                     => 'Marks on the packaging',
            self::NATIONAL_MARKS            => 'National marks',
            self::ARTWORK_OWNER             => 'Artwork owner',
            self::NOTES                     => 'Notes',
        };
    }

    public static function fromHeading(mixed $heading): ?self
    {
        $heading = SupplierProductSheetColumnEnum::normaliseHeading($heading);
        foreach (self::cases() as $column) {
            if (SupplierProductSheetColumnEnum::normaliseHeading($column->heading()) === $heading) {
                return $column;
            }
        }

        return null;
    }
}
