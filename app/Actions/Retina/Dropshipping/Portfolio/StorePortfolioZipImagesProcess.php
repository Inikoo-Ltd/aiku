<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2025 17:06:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Dropshipping\DownloadPortfolioCustomerSalesChannel;

class StorePortfolioZipImagesProcess
{
    use AsAction;

    public function handle(array $modelData): ?int
    {
        $record = DownloadPortfolioCustomerSalesChannel::create($modelData);
        return $record->id;
    }
}
