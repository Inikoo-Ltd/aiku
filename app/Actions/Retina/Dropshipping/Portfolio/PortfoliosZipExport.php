<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use ZipStream\ZipStream;

class PortfoliosZipExport
{
    use AsAction;

    public function handle(Customer $customer, CustomerSalesChannel $customerSalesChannel)
    {

        $zipFileName = 'portfolios_' . now()->format('Ymd') . '.zip';
        $zip = new ZipStream(
            outputName: $zipFileName,
            sendHttpHeaders: true,
        );

        $counter = 1;
        $batchSize = 500;

        $customer->portfolios()
            ->where('customer_sales_channel_id', $customerSalesChannel->id)
            ->with(['item.images'])
            ->lazy($batchSize)
            ->each(function ($portfolio) use ($zip, &$counter) {
                if (!$portfolio->item || !$portfolio->item->images) {
                    return;
                }

                foreach ($portfolio->item->images as $image) {
                    try {
                        $disk = Storage::disk($image->disk);

                        if (!$disk->exists($image->getPath())) {
                            continue;
                        }

                        $stream = $disk->readStream($image->getPath());
                        $extension = pathinfo($image->getPath(), PATHINFO_EXTENSION) ?: 'jpg';
                        $fileName = 'image_' . $counter . '.' . $extension;

                        $zip->addFileFromStream($fileName, $stream);
                        $counter++;
                    } catch (\Exception $e) {
                        Log::error('Error adding image to zip stream', [
                            'image_id' => $image->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

        $zip->finish();
    }
}
