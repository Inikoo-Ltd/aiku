<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 May 2025 11:28:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Dropshipping;

use Lorisleiva\Actions\ActionRequest;

trait WithInCustomerSalesChannelAuthorisation
{
    public function authorise(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }
}
