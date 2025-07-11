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
        if (ob_get_level()) {
            ob_end_clean();
        }

        $zipFileName = 'portfolios_' . now()->format('Ymd') . '.zip';
        $zip = new ZipStream(
            outputName: $zipFileName,
            sendHttpHeaders: true,
        );

        $counter = 1;
        $batchSize = 100;
        $processedImages = [];

        try {
            $customer->portfolios()
                ->where('customer_sales_channel_id', $customerSalesChannel->id)
                ->lazy($batchSize)
                ->each(function ($portfolio) use ($zip, &$counter, &$processedImages) {

                    $portfolio->load('item.images');

                    if (!$portfolio->item || !$portfolio->item->images) {
                        return;
                    }

                    foreach ($portfolio->item->images as $image) {

                        if (in_array($image->id, $processedImages)) {
                            continue;
                        }

                        $stream = null;
                        try {
                            $disk = Storage::disk($image->disk);

                            if (!$disk->exists($image->getPath())) {
                                Log::warning('Image file not found', [
                                    'image_id' => $image->id,
                                    'path' => $image->getPath()
                                ]);
                                \Sentry\captureMessage('Image file not found:' . $image->id . ', Path:'. $image->getPath());
                                continue;
                            }

                            $stream = $disk->readStream($image->getPath());
                            if (!$stream) {
                                Log::error('Failed to open stream for image', [
                                    'image_id' => $image->id,
                                    'path' => $image->getPath()
                                ]);
                                \Sentry\captureMessage('Failed to open stream for image:' . $image->id . ', Path:'. $image->getPath());
                                continue;
                            }

                            $extension = pathinfo($image->getPath(), PATHINFO_EXTENSION) ?: 'jpg';
                            $fileName = sprintf('image_%06d.%s', $counter, $extension);

                            $zip->addFileFromStream($fileName, $stream);
                            $processedImages[] = $image->id;
                            $counter++;

                        } catch (\Exception $e) {
                            Log::error('Error adding image to zip stream', [
                                'image_id' => $image->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            \Sentry\captureMessage('Error adding image to zip stream:' . $image->id . ', Error:'. $e->getMessage());
                        } finally {

                            if ($stream && is_resource($stream)) {
                                fclose($stream);
                            }
                        }
                    }

                    $portfolio->unsetRelation('item');
                });

        } catch (\Exception $e) {
            Log::error('Fatal error in portfolio zip export', [
                'customer_id' => $customer->id,
                'sales_channel_id' => $customerSalesChannel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            \Sentry\captureMessage('Fatal error in portfolio zip export:' . $customer->id . ', Error:'. $e->getMessage());
            throw $e;
        } finally {
            $zip->finish();
        }
    }
}
