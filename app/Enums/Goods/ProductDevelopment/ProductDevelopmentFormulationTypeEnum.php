<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;

enum ProductDevelopmentFormulationTypeEnum: string
{
    use EnumHelperTrait;

    case BASE_BLEND       = 'base_blend';
    case INTERMEDIATE     = 'intermediate';
    case FINISHED_PRODUCT = 'finished_product';

    public static function labels(): array
    {
        return [
            'base_blend'       => __('Base blend'),
            'intermediate'     => __('Intermediate formulation'),
            'finished_product' => __('Finished product'),
        ];
    }

    /**
     * A blend or an intermediate is made on its own artefact, which the finished product's recipe then uses as an
     * ingredient; the finished product is the SKU's own artefact.
     */
    public function hasOwnArtefact(): bool
    {
        return $this !== self::FINISHED_PRODUCT;
    }
}
