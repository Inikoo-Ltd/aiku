<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 15:13:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class ToggleCustomerSalesChannel extends OrgAction
{
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $currentStatus = $customerSalesChannel->status;

        if ($currentStatus === CustomerSalesChannelStatusEnum::OPEN) {
            $newStatus = CustomerSalesChannelStatusEnum::CLOSED;
        } else {
            $newStatus = CustomerSalesChannelStatusEnum::OPEN;
        }

        UpdateCustomerSalesChannel::run($customerSalesChannel, [
            'status' => $newStatus
        ]);
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData, int $hydratorsDelay = 0): void
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($customerSalesChannel->organisation, $modelData);

        $this->handle($customerSalesChannel);
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($customerSalesChannel->organisation, $request);
        $this->handle($customerSalesChannel);
    }
}
