<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Jan 2026 11:38:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $code
 * @property string $name
 * @property mixed $gross_amount
 * @property mixed $net_amount
 * @property mixed $id
 * @property mixed $description
 * @property mixed $historic_asset_id
 * @property mixed $type
 * @property mixed $offers_data
 * @property mixed $transaction_label
 */
class GetChargesInOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $isDiscretionary    = $this->type == ChargeTypeEnum::DISCRETIONARY->value;
        $percentageDiscount = null;

        if (!$isDiscretionary) {
            if (Arr::has($this->offers_data, 'o.p')) {
                $percentageDiscount = Arr::get($this->offers_data, 'o.p');
            }
        }


        return [
            'transaction_id'      => $this->id,
            'gross_amount'        => $this->gross_amount,
            'net_amount'          => $this->net_amount,
            'code'                => $this->code,
            'name'                => $this->name,
            'description'         => $this->description,
            'historic_asset_id'   => $this->historic_asset_id,
            'type'                => $this->type,
            'is_discretionary'    => $isDiscretionary,
            'percentage_discount' => $percentageDiscount,
            'offers_data'         => $this->offers_data,
            'transaction_label'   => $this->transaction_label,

        ];
    }
}
