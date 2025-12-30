<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;

class GetFairePackingPdfSlip extends OrgAction
{
    public string $commandSignature = 'faire:packing-slip {shop} {order?}';

    public function handle(Shop $shop, Order $order, $savePdf = false): Response|ResponseFactory|array
    {
        $pdfBinary = $shop->getPackingSlip($order->external_id);

        if ($savePdf) {
            Storage::disk('public')->put('packing-slips/'.$order->slug.'.pdf', $pdfBinary);

            $url = Storage::disk('public')->url('packing-slips/'.$order->slug.'.pdf');

            return [
                'status' => 'success',
                'modelData' => [
                    'tracking' => $url,
                    'combined_label_url' => $url
                ]
            ];
        }

        return response($pdfBinary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="packing-slip-'.$order->slug.'.pdf"');
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Response|ResponseFactory
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $order);
    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
