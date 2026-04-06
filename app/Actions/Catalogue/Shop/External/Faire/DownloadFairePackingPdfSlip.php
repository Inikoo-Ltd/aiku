<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 04 Apr 2026 13:05:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class DownloadFairePackingPdfSlip extends OrgAction implements ShouldBeUnique
{
    public string $commandSignature = 'faire:packing-slip-download {order}';


    public string $jobQueue = 'low-priority';

    public int $jobTries = 3;
    public int $jobBackoff = 3600;


    public function getJobUniqueId(?int $orderId): string
    {
        return $orderId ?? 'empty';
    }


    /**
     * @throws \Exception
     */
    public function handle(?int $orderId): void
    {

        if (!$orderId) {
            return;
        }
        $order = Order::find($orderId);
        if (!$order) {
            return;
        }


        $pdfBinary = GetFairePackingPdfSlip::run($order);

        if (!is_string($pdfBinary)) {
            throw new \Exception('Failed to download packing slip PDF from Faire');
        }


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


    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): void
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        $this->handle($order->id);
    }
}
