<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 20 Aug 2026 14:05:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\ProductCategory;

use App\Enums\EnumHelperTrait;

enum FamilyStorageConditionEnum: string
{
    use EnumHelperTrait;

    case STORAGE       = 'storage';
    case SHELF_LIFE    = 'shelf_life';
    case AFTER_OPENING = 'after_opening';

    public static function labels(): array
    {
        return [
            'storage'       => __('Storage'),
            'shelf_life'    => __('Shelf Life'),
            'after_opening' => __('POA (After Opening)'),
        ];
    }

    public static function placeholders(): array
    {
        return [
            'storage'       => __('e.g. Store in a cool, dry place away from direct sunlight and heat.'),
            'shelf_life'    => __('e.g. 24 months from date of manufacture (see batch number).'),
            'after_opening' => __('e.g. Use within 12 months of opening.'),
        ];
    }

    /**
     * @param  array<int, array{key?: string, value?: string}>|null  $saved
     * @return array<int, array{key: string, label: string, placeholder: string, value: string}>
     */
    public static function rows(?array $saved = null): array
    {
        $savedRows = collect($saved ?? [])->keyBy('key');

        return array_map(function (self $case) use ($savedRows) {
            $row = $savedRows->get($case->value, []);

            return [
                'key'         => $case->value,
                'label'       => self::labels()[$case->value],
                'placeholder' => self::placeholders()[$case->value],
                'value'       => (string) data_get($row, 'value', ''),
            ];
        }, self::cases());
    }

    public static function valuesWithLabelsAndPlaceholders(): array
    {
        return array_map(fn (self $case) => [
            'value'       => $case->value,
            'label'       => self::labels()[$case->value],
            'placeholder' => self::placeholders()[$case->value],
        ], self::cases());
    }
}
