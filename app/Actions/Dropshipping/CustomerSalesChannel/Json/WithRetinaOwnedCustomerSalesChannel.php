<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel\Json;

use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

trait WithRetinaOwnedCustomerSalesChannel
{
    public function inRetina(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): ?array
    {
        abort_unless($customerSalesChannel->customer_id === $request->user()?->customer_id, 403);

        return $this->asController($customerSalesChannel, $request);
    }
}
