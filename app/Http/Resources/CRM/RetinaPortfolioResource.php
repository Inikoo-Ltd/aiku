<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 31 May 2026 23:29:01 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $number_current_customer_clients
 */
class RetinaPortfolioResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\Dropshipping\Portfolio $portfolio */
        $portfolio = $this;

        return [
            'item_id'      => $portfolio->item_id,
            'reference'    => $portfolio->reference,
            'product_name' => $portfolio->item->name,
            'product_code' => $portfolio->item->code,
            'slug'         => $portfolio->item->slug,
            'type'         => $portfolio->type,
            'created_at'   => $portfolio->created_at,

        ];
    }
}
