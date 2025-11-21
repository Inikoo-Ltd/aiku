<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2025 17:06:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Dropshipping\DownloadPortfolioCustomerSalesChannel;

class UpdatePortfolioZipImagesProcess
{
    use AsAction;

    public function handle(array $modelData): bool
    {
        $record = DownloadPortfolioCustomerSalesChannel::where('id', $modelData['id'])->update($modelData);
        return $record > 0;
    }
}
