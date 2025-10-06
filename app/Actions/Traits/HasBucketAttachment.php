<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 09:46:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Masters\MasterAsset;

trait HasBucketAttachment
{
    public function getAttachmentData(MasterAsset|Product|TradeUnit $model): array
    {
        return [
            [
                'label'        => __('IFRA'),
                'scope'        => 'IFRA',
                'id'           => null,
                'file'         => null,
                'size'         => '2kb',
            ],
            [
                'label'        => __('SDS'),
                'scope'        => 'SDS',
                'id'           => null,
                'file'         => null,
                'size'         => '2kb',
            ],
            [
                'label'        => __('Allergen Declarations'),
                'scope'        => 'allergen_declarations',
                'id'           => null,
                'file'         => null,
                'size'         => '2kb',
            ],
            [
                'label'        => __('Declaration of Conformity'),
                'scope'        => 'declaration_of_conformity',
                'id'           => null,
                'file'         => null,
                'size'         => '2kb',
            ],
            [
                'label'        => __('CPSR '),
                'scope'        => 'CPSR ',
                'id'           => null,
                'file'         => null,
                'size'         => '2kb',
            ],
        ];
    }
}
