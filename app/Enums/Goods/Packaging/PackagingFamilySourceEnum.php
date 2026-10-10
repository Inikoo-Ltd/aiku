<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

/**
 * Where a packaging family's weights came from.
 */
enum PackagingFamilySourceEnum: string
{
    use EnumHelperTrait;

    case MEASURED       = 'measured';
    case SUPPLIER       = 'supplier';
    case LEGACY_UK_2026 = 'legacy_uk_2026';
    case ESTIMATE       = 'estimate';

    public static function labels(): array
    {
        return [
            'measured'       => __('Measured'),
            'supplier'       => __('Supplier declaration'),
            'legacy_uk_2026' => __('UK packaging sheet (2026)'),
            'estimate'       => __('Estimate'),
        ];
    }
}
