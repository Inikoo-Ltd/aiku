<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Retina\Dropshipping\Product;

use App\Actions\Retina\Dropshipping\Portfolio\PortfoliosJsonExport;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * Feeds are polled every 5 to 30 minutes and a big one takes seconds to build, so the last build is
 * kept on the data-feeds disk (shared by the web boxes): a poll gets that file and queues a rebuild.
 * A file older than SERVE_STALE_FOR_SECONDS is never served; it is rebuilt inline first.
 */
class StoreDataFeed implements ShouldBeUnique
{
    use AsAction;

    private const int REFRESH_AFTER_SECONDS = 60;

    private const int SERVE_STALE_FOR_SECONDS = 900;

    public string $jobQueue = 'low-priority';

    public int $jobUniqueFor = 300;

    public function getJobUniqueId(CustomerSalesChannel $customerSalesChannel, string $extension): string
    {
        return "$customerSalesChannel->id.$extension";
    }

    public function handle(CustomerSalesChannel $customerSalesChannel, string $extension): string
    {
        $path = $this->path($customerSalesChannel, $extension);

        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        $content = $extension === 'csv'
            ? GetDataFeedsCsv::make()->handle($customerSalesChannel)
            : json_encode(PortfoliosJsonExport::make()->handle($customerSalesChannel), JSON_PRETTY_PRINT);

        $temporaryPath = tempnam(dirname($path), 'feed');
        if (file_put_contents($temporaryPath, $content) !== strlen($content)) {
            @unlink($temporaryPath);

            throw new RuntimeException("Could not write the data feed $path");
        }
        rename($temporaryPath, $path);

        return $path;
    }

    public function storedPath(CustomerSalesChannel $customerSalesChannel, string $extension): string
    {
        $path = $this->path($customerSalesChannel, $extension);
        clearstatcache(true, $path);
        $age = is_file($path) ? time() - filemtime($path) : null;

        if ($age === null || $age > self::SERVE_STALE_FOR_SECONDS) {
            return $this->handle($customerSalesChannel, $extension);
        }

        if ($age > self::REFRESH_AFTER_SECONDS) {
            static::dispatch($customerSalesChannel, $extension);
        }

        return $path;
    }

    private function path(CustomerSalesChannel $customerSalesChannel, string $extension): string
    {
        return Storage::disk('data-feeds')->path("customer_sales_channel/$customerSalesChannel->id.$extension");
    }
}
