<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Aug 2026 10:12:44 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\ProductCategory;

use App\Enums\EnumHelperTrait;

enum FamilyCustomizeEnum: string
{
    use EnumHelperTrait;

    case PACKAGING   = 'packaging';
    case FRAGRANCE   = 'fragrance';
    case COLOUR      = 'colour';
    case FORMULATION = 'formulation';
    case LABELING    = 'labeling';
    case PACK_SIZES  = 'pack_sizes';

    public static function labels(): array
    {
        return [
            'packaging'   => __('Packaging'),
            'fragrance'   => __('Fragrance'),
            'colour'      => __('Colour'),
            'formulation' => __('Formulation'),
            'labeling'    => __('Labeling'),
            'pack_sizes'  => __('Pack Sizes'),
        ];
    }

    public static function icons(): array
    {
        return [
            'packaging'   => 'fal fa-box',
            'fragrance'   => 'fal fa-smoke',
            'colour'      => 'fal fa-tint',
            'formulation' => 'fal fa-flask',
            'labeling'    => 'fal fa-tags',
            'pack_sizes'  => 'fal fa-box-open',
        ];
    }

    /**
     * @param  array<int, array{key?: string, available?: bool, moq?: string, notes?: string}>|null  $saved
     * @return array<int, array{key: string, label: string, icon: string, available: bool, moq: string, notes: string}>
     */
    public static function rows(?array $saved = null): array
    {
        $savedRows = collect($saved ?? [])->keyBy('key');

        return array_map(function (self $case) use ($savedRows) {
            $row = $savedRows->get($case->value, []);

            return [
                'key'       => $case->value,
                'label'     => self::labels()[$case->value],
                'icon'      => self::icons()[$case->value],
                'available' => (bool) data_get($row, 'available', false),
                'moq'       => (string) data_get($row, 'moq', ''),
                'notes'     => (string) data_get($row, 'notes', ''),
            ];
        }, self::cases());
    }

    public static function valuesWithLabelsAndIcons(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => self::labels()[$case->value],
            'icon'  => self::icons()[$case->value],
        ], self::cases());
    }
}
