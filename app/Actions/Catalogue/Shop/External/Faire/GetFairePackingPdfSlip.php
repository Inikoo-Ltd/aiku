<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;

class GetFairePackingPdfSlip extends OrgAction
{
    public string $commandSignature = 'faire:packing-slip {shop} {order?}';

    public function handle(Order $order): array|string
    {
        $shop = $order->shop;
        return $shop->getPackingSlip($order->external_id);
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Order::where('slug', $command->argument('order'))->first());
    }
}
