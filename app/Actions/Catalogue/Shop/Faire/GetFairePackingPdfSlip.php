<?php

namespace App\Actions\Catalogue\Shop\Faire;

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
    private Order $order;

    public function handle(Shop $shop, Order $order): array|string
    {
        return $shop->getPackingSlip($order->external_id);
    }

    public function htmlResponse($pdfBinary): Response
    {
        return response($pdfBinary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="packing-slip-' . $this->order->slug . '.pdf"');
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): array|string
    {
        $this->order = $order;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $order);
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
