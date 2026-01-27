<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Jan 2026 11:38:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Ordering;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property mixed $gross_amount
 * @property mixed $net_amount
 * @property mixed $id
 * @property mixed $description
 * @property mixed $historic_asset_id
 */
class GetChargesInOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'transaction_id'    => $this->id,
            'gross_amount'      => $this->gross_amount,
            'net_amount'        => $this->net_amount,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'historic_asset_id' => $this->historic_asset_id,

        ];
    }
}
