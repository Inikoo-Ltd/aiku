<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 08:26:56 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\WooCommerce\Product;

use Lorisleiva\Actions\Concerns\AsAction;

class BulkUpdateWooPortfolio
{
    use AsAction;


    public string $jobQueue = 'woo';

    public function handle(array $portfolios): void
    {
        foreach ($portfolios as $portfolio) {
            UpdateWooPortfolio::run($portfolio->id);
        }
    }
}
