<?php

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;

class DownloadFairePackingPdfSlip extends OrgAction
{
    public string $commandSignature = 'faire:packing-slip-download {order}';

    public function handle(Order $order): void
    {
        $shop = $order->shop;
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
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order);
    }
}
