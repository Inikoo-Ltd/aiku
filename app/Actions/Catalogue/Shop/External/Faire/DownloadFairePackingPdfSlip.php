<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class DownloadFairePackingPdfSlip extends OrgAction
{
    public string $commandSignature = 'faire:packing-slip-download {shop} {order?}';

    public function handle(Shop $shop, Order $order): void
    {
        $pdfBinary = GetFairePackingPdfSlip::run($shop, $order);

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

    }

    public function asCommand(Command $command): void
    {
        $this->handle(Shop::where('slug', $command->argument('shop'))->first(), Order::where('slug', $command->argument('order'))->first());
    }
}
