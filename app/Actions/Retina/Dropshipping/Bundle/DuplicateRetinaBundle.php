<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 10:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Bundle;

use App\Actions\Dropshipping\Bundle\DuplicateBundle;
use App\Actions\RetinaAction;
use App\Models\Dropshipping\Bundle;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class DuplicateRetinaBundle extends RetinaAction
{
    private CustomerSalesChannel $customerSalesChannel;

    private Bundle $bundle;

    /**
     * @throws \Throwable
     */
    public function handle(Bundle $bundle): Bundle
    {
        return DuplicateBundle::make()->action($bundle);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customerSalesChannel->customer_id == $this->customer?->id
            && $this->bundle->customer_sales_channel_id == $this->customerSalesChannel->id;
    }

    /**
     * @throws \Throwable
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, Bundle $bundle, ActionRequest $request): void
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->bundle               = $bundle;
        $this->initialisation($request);

        $this->handle($bundle);
    }
}
