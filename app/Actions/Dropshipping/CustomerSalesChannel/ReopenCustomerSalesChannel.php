<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 13:05:43 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class ReopenCustomerSalesChannel extends OrgAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel): CustomerSalesChannel
    {
        if ($customerSalesChannel->user) {
            $this->update($customerSalesChannel, [
                'status' => CustomerSalesChannelStatusEnum::OPEN,
                'closed_at' => null
            ]);
        }

        return CheckCustomerSalesChannel::run($customerSalesChannel);
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0): CustomerSalesChannel
    {
        $this->asAction             = true;
        $this->customerSalesChannel = $customerSalesChannel;
        $this->hydratorsDelay       = $hydratorsDelay;
        $this->initialisation($customerSalesChannel->organisation, $modelData);

        return $this->handle($customerSalesChannel);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): CustomerSalesChannel
    {
        $this->customerSalesChannel = $customerSalesChannel;

        $this->initialisationFromShop($customerSalesChannel->shop, $request);

        return $this->handle($customerSalesChannel);
    }


}
