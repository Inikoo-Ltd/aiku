<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-20 09:10
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Enums\SupplyChain\PackingSpec;

use App\Enums\EnumHelperTrait;

/**
 * Where a packing material sits: on each unit, on each pack (the supplier product's units per pack), or once per
 * master carton.
 */
enum PackingSpecLevelEnum: string
{
    use EnumHelperTrait;

    case PER_UNIT   = 'per_unit';
    case PER_PACK   = 'per_pack';
    case PER_CARTON = 'per_carton';

    public static function labels(): array
    {
        return [
            'per_unit'   => __('Per unit'),
            'per_pack'   => __('Per SKO'),
            'per_carton' => __('Per carton'),
        ];
    }

    public function perCarton(float $quantity, int $unitsPerCarton, float $packsPerCarton): float
    {
        return match ($this) {
            self::PER_UNIT   => $quantity * max(1, $unitsPerCarton),
            self::PER_PACK   => $quantity * $packsPerCarton,
            self::PER_CARTON => $quantity,
        };
    }

    /**
     * The UK packaging class the material is reported in: wraps on the unit are primary; packs and the carton that
     * groups them are secondary, as the UK returns have always reported import cartons.
     */
    public function ukPackagingClass(): string
    {
        return match ($this) {
            self::PER_UNIT   => 'P1',
            self::PER_PACK   => 'P2',
            self::PER_CARTON => 'P2',
        };
    }

    public function defaultFate(): PackingSpecFateEnum
    {
        return $this === self::PER_CARTON ? PackingSpecFateEnum::DISCARDED : PackingSpecFateEnum::PASSED_ON;
    }
}
