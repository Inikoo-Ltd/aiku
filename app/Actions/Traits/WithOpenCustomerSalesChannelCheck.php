<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Validation\ValidationException;

trait WithOpenCustomerSalesChannelCheck
{
    public function isCustomerSalesChannelOpen(?CustomerSalesChannel $customerSalesChannel): bool
    {
        return $customerSalesChannel && $customerSalesChannel->status == CustomerSalesChannelStatusEnum::OPEN;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertCustomerSalesChannelIsOpen(?CustomerSalesChannel $customerSalesChannel): void
    {
        if ($this->isCustomerSalesChannelOpen($customerSalesChannel)) {
            return;
        }

        throw ValidationException::withMessages([
            'customer_sales_channel_id' => __('This sales channel is closed, it can not be modified')
        ]);
    }
}
