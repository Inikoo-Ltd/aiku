<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\SupplierProduct;

use App\Enums\EnumHelperTrait;

/**
 * The unit a supplier quotes and invoices in when it is not our unit, e.g. incense sold by the kg
 * that we count in 500 g bags. Quantities stay stored in our units; this is only how the supplier speaks.
 */
enum SupplierUnitEnum: string
{
    use EnumHelperTrait;

    case KG = 'kg';
    case G = 'g';
    case LITRE = 'l';
    case METRE = 'm';
    case PACK = 'pack';
    case SET = 'set';
    case DOZEN = 'dozen';
    case PIECE = 'piece';

    public static function labels(): array
    {
        return [
            'kg'    => __('kg'),
            'g'     => __('g'),
            'l'     => __('litre'),
            'm'     => __('metre'),
            'pack'  => __('pack'),
            'set'   => __('set'),
            'dozen' => __('dozen'),
            'piece' => __('piece'),
        ];
    }

    /**
     * Grams in one supplier unit, for units whose size follows from the trade unit weight.
     */
    public function grams(): ?float
    {
        return match ($this) {
            self::KG => 1000,
            self::G  => 1,
            default  => null,
        };
    }
}
