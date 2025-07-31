<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLastOrders extends OrgAction
{
    use WithOrderingAuthorisation;
    use AsObject;

    public function handle(Shop $shop): array
    {
       $orders = [];

        foreach (OrderStateEnum::cases() as $state) {
            $query = Order::where('shop_id', $shop->id)
                          ->where('state', $state);

            // Apply ordering based on timeline rules
            if ($state === OrderStateEnum::CREATING) {
                $query->orderBy('created_at', 'desc');
            } elseif ($state === OrderStateEnum::HANDLING_BLOCKED) {
                $query->orderBy('updated_at', 'desc');
            } else {
                $timestampColumn = $state->snake() . '_at';
                $query->whereNotNull($timestampColumn)
                      ->orderBy($timestampColumn, 'desc');
            }

            $stateOrders = $query->take(5)->get();
            
            $orders[$state->value] = OrderResource::collection($stateOrders)->resolve();
        }

        return $orders;
    }

}
