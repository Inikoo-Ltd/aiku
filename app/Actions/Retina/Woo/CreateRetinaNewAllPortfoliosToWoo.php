<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 26 Aug 2024 14:04:18 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Woo;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;

class CreateRetinaNewAllPortfoliosToWoo extends RetinaAction
{
    use WithActionUpdate;

    /**
     * @throws \Exception
     */
    public function handle(CustomerSalesChannel $customerSalesChannel): void
    {
        $portfolios = $customerSalesChannel
            ->portfolios()
            ->where('status', true)
            ->where('platform_status', false)
            ->pluck('id');

        $payload = [
            'portfolios' => $portfolios->toArray(),
        ];

        CreateRetinaNewBulkPortfoliosToWoo::run($customerSalesChannel, $payload);
    }
    /**
     * @throws \Exception
     */
    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
