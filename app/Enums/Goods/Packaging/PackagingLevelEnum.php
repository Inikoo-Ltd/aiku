<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

enum PackagingLevelEnum: string
{
    use EnumHelperTrait;

    case PRIMARY   = 'primary';
    case GROUPED   = 'grouped';
    case TRANSPORT = 'transport';
    case PALLET    = 'pallet';
    case SERVICE   = 'service';

    public static function labels(): array
    {
        return [
            'primary'   => __('Primary (sales unit)'),
            'grouped'   => __('Secondary (SKO)'),
            'transport' => __('Tertiary (carton)'),
            'pallet'    => __('Pallet / transport aid'),
            'service'   => __('Service (e-commerce mailer)'),
        ];
    }

    public static function fromSheet(?string $text): ?self
    {
        $text = mb_strtolower(trim((string)$text));

        return match (true) {
            str_starts_with($text, 'primary')                                      => self::PRIMARY,
            str_starts_with($text, 'secondary'), str_starts_with($text, 'grouped') => self::GROUPED,
            str_starts_with($text, 'tertiary'), str_starts_with($text, 'transport') => self::TRANSPORT,
            str_starts_with($text, 'pallet')                                       => self::PALLET,
            str_starts_with($text, 'service')                                      => self::SERVICE,
            default                                                                => null,
        };
    }

    /**
     * How much of a component one sales unit carries: a component of an SKO is shared by its units, a carton by
     * every unit in it. Pallet aids are reported apart, not per unit.
     */
    public function quantityPerUnit(float $quantity, int $unitsPerSko, int $unitsPerCarton): float
    {
        return match ($this) {
            self::PRIMARY, self::SERVICE => $quantity,
            self::GROUPED                => $quantity / max(1, $unitsPerSko),
            self::TRANSPORT              => $quantity / max(1, $unitsPerCarton),
            self::PALLET                 => 0,
        };
    }
}
