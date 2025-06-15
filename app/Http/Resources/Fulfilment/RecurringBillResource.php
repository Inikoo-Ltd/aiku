<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 18:24:37 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Models\Fulfilment\RecurringBill;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringBillResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        /** @var RecurringBill $recurringBill */
        $recurringBill = $this;


        return [
            "id"            => $recurringBill->id,
            "reference"     => $recurringBill->reference,
            "slug"          => $recurringBill->slug,
            "order_summary" => [
                [
                    [
                        "label"       => __("tax"),
                        "quantity"    => 0000000,
                        "price_base"  => 55555555,
                        "price_total" => $recurringBill->tax,
                        "information" => 'Tax is based on 10% of total order.',
                    ],
                ],
                [
                    [
                        "label" => __("total"),
                        // "quantity" => 0000000,
                        // "price_base" => 55555555,
                        "price_total" => $recurringBill->total,
                        // "information" => 777777,
                    ],
                ],
            ],
        ];
    }
}
