<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;

class AcceptFaireOrder extends OrgAction
{
    public function handle(Shop $shop, Order $order): array
    {
        return $shop->acceptFaireOrder($order->external_id);
    }
}
