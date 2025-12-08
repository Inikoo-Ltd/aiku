<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Aug 2024 11:59:42 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\UI;

use App\Http\Resources\HasSelfCall;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Http\Resources\Json\JsonResource;

class LoggedShopifyUserResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var ShopifyUser $shopifyUser */
        $shopifyUser = $this;

        return [
            'id' => $shopifyUser->id,
            'name' => $shopifyUser->name,
            'customer_id' => $shopifyUser->customer_id,
        ];
    }
}
