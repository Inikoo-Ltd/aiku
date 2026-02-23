<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class CancelFaireOrder extends OrgAction
{
    public $commandSignature = 'faire:order_cancel {shop} {order}';

    public function handle(Shop $shop, Order $order): array
    {
        return $shop->cancelFaireOrder($order->external_id, [
            'reason' => 'OTHER',
            'note' => $order->customer_notes
        ]);
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
