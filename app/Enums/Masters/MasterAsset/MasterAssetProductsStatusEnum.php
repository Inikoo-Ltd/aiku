<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Oct 2025 20:37:16 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\MasterAsset;

use App\Enums\EnumHelperTrait;

enum MasterAssetProductsStatusEnum: string
{
    use EnumHelperTrait;

    case NA = 'na';
    case FOR_SALE = 'for_sale';
    case NO_FOR_SALE = 'no_for_sale';
    case ORPHAN = 'orphan';

    public static function labels(): array
    {
        return [
            'na' => __('No applicable'),
            'active' => __('Stocking'),
            'discontinuing' => __('Discontinuing'),
            'discontinued' => __('Discontinued'),
            'orphan' => __('Orphan'),
        ];
    }
}
