<?php

namespace App\Actions\Catalogue\Shop\Faire;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;

class GetFairePackingPdfSlip extends OrgAction
{
    public string $commandSignature = 'faire:packing-slip {shop} {order?}';

    public function handle(Shop $shop, Order $order, bool $saveFile = false): Response|ResponseFactory|array
    {
        $pdfBinary = $shop->getPackingSlip($order->external_id);

        if ($saveFile) {
            $tempPath = tempnam(sys_get_temp_dir(), 'packing-slip-');
            file_put_contents($tempPath, $pdfBinary);

            $attachmentData = [
                'path' => $tempPath,
                'originalName' => 'packing-slip-' . $order->slug . '.pdf',
                'scope' => 'packing-slip',
                'caption' => 'Packing Slip for Order ' . $order->slug,
                'extension' => 'pdf'
            ];

            SaveModelAttachment::make()->action($order, $attachmentData);

            // Clean up temporary file
            @unlink($tempPath);

            return $attachmentData;
        }

        return response($pdfBinary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="packing-slip-' . $order->slug . '.pdf"');
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
