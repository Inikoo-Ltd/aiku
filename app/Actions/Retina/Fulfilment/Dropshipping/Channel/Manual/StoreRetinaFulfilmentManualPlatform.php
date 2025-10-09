<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 11 Jul 2024 10:16:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\Dropshipping\Channel\Manual;

use App\Actions\Dropshipping\Aiku\StoreRetinaManualPlatform;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class StoreRetinaFulfilmentManualPlatform extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData): CustomerSalesChannel
    {
        return StoreRetinaManualPlatform::run($customer, $modelData);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:customer_sales_channels,name']
        ];
    }

    public function htmlResponse(CustomerSalesChannel $customerSalesChannel): Response
    {
        return Inertia::location(route('retina.fulfilment.dropshipping.customer_sales_channels.show', [
            'customerSalesChannel' => $customerSalesChannel->slug
        ]));
    }

    public function asController(ActionRequest $request): CustomerSalesChannel
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $this->validatedData);
    }
}
