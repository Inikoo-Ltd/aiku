<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AcceptFaireOrder extends OrgAction
{
    public $commandSignature = 'faire:order_accepted {order}';

    public function handle(Order $order): array
    {
        $shop = $order->shop;
        $acceptedOrder = $shop->acceptFaireOrder(
            $order->external_id,
            [
                'expected_ship_date' => Carbon::now()->addDays(6)->toIso8601String()
            ]
        );
        DownloadFairePackingPdfSlip::dispatch($order);

        return $acceptedOrder;
    }

    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order);
    }
}
