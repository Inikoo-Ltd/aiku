<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

trait WithStepDiscountColumn
{
    public function getStepDiscountColumn(): Expression
    {
        $type  = OfferTypeEnum::PRODUCT_QUANTITY_ORDERED->value;
        $state = OfferStateEnum::ACTIVE->value;

        return DB::raw(
            "(SELECT jsonb_build_object('label', COALESCE(offers.label, offers.name), 'steps', offer_allowances.data->'steps')
                FROM offers
                INNER JOIN offer_allowances ON offer_allowances.offer_id = offers.id
                    AND offer_allowances.status = true
                    AND offer_allowances.deleted_at IS NULL
                WHERE offers.trigger_type = 'Product'
                    AND offers.trigger_id = products.id
                    AND offers.type = '$type'
                    AND offers.state = '$state'
                    AND offers.status = true
                    AND offers.deleted_at IS NULL
                LIMIT 1) as step_discount_data"
        );
    }
}
