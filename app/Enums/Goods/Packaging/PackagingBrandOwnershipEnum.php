<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

/**
 * Whose brand the packaging carries, which decides the EPR activity it is reported under.
 */
enum PackagingBrandOwnershipEnum: string
{
    use EnumHelperTrait;

    case OWN_BRAND   = 'own_brand';
    case THIRD_PARTY = 'third_party';
    case UNBRANDED   = 'unbranded';
    case UNKNOWN     = 'unknown';

    public static function labels(): array
    {
        return [
            'own_brand'   => __('Own brand'),
            'third_party' => __('Third-party brand'),
            'unbranded'   => __('Unbranded'),
            'unknown'     => __('Unknown'),
        ];
    }
}
