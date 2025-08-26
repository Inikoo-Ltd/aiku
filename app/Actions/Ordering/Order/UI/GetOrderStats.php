<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrderStats
{
    use AsObject;

    public function handle(Shop|Customer $parent): array
    {
        if ($parent instanceof Shop) {
            $total = DB::table('orders')->where('shop_id', $parent->id)->sum('net_amount');
        } else {
            $total = DB::table('orders')->where('customer_id', $parent->id)->sum('net_amount');
        }

        return [
            'number_orders' => $parent instanceof Shop ? $parent->orderingStats->number_orders : $parent->stats->number_orders,
            'total'         => $total
        ];
    }
}
