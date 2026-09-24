<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Wix;

use App\Actions\Dropshipping\Wix\Product\CreateNewBulkPortfoliosToWix;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithRetinaCustomerOwnedRouteModels;
use App\Models\Dropshipping\CustomerSalesChannel;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CreateRetinaNewBulkPortfoliosToWix extends RetinaAction
{
    use WithRetinaCustomerOwnedRouteModels;
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $attributes): void
    {
        CreateNewBulkPortfoliosToWix::dispatch($customerSalesChannel, $attributes);
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
