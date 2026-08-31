<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Aug 2026 13:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ebay;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishAllRetinaEbayDraftPortfolios extends RetinaAction
{
    use AsAction;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $customerSalesChannel->portfolios()
            ->where('status', true)
            ->where('data->is_platform_draft', true)
            ->each(function ($portfolio) {
                PublishRetinaEbayPortfolio::dispatch($portfolio);
            });
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($customerSalesChannel);
    }
}
