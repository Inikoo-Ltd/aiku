<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\Masters\Competitor $resource
 */
class CompetitorsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'website'         => $this->website,
            'sells_to'        => CompetitorSellsToEnum::labels()[$this->sells_to->value],
            'currency_code'   => $this->currency_code,
            'has_login'       => (bool) $this->has_login,
            'has_search'      => (bool) $this->has_search,
            'status'          => $this->status?->value,
            'last_error'      => $this->last_error,
            'fetched_at'      => $this->fetched_at,
            'number_products' => $this->number_products,
        ];
    }
}
