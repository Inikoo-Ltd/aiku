<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class AcceptFaireOrder extends OrgAction
{
    public $commandSignature = 'faire:order_accepted {shop} {order}';

    public function handle(Shop $shop, Order $order): array
    {
        $acceptedOrder = $shop->acceptFaireOrder($order->external_id);

        DownloadFairePackingPdfSlip::run($shop, $order);

        return $acceptedOrder;
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
