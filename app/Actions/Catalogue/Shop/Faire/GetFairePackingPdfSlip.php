<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

class GetFairePackingPdfSlip extends OrgAction
{
    public string $commandSignature = 'faire:packing-slip {shop} {order?}';

    public function handle(Shop $shop, Order $order): Response|ResponseFactory
    {
        $pdfBinary = $shop->getPackingSlip($order->external_id);

        return response($pdfBinary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="packing-slip-'.$order->slug.'.pdf"');
    }

    public function asController(Shop $shop, Order $order): Response|ResponseFactory
    {
        return $this->handle($shop, $order);
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
