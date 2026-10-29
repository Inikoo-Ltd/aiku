<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Nov 2025 12:46:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\OrgAction;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;

class CloseCustomerSalesChannelPostActions extends OrgAction
{
    public string $jobQueue = 'urgent';

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        if ($customerSalesChannel->status != CustomerSalesChannelStatusEnum::CLOSED) {
            return;
        }

        foreach ($customerSalesChannel->portfolios as $portfolio) {
            UpdatePortfolio::run($portfolio, [
                'status' => false,
            ]);
        }
    }
}
