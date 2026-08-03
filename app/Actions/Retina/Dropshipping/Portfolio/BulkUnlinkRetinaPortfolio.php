<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class BulkUnlinkRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        foreach (Arr::get($modelData, 'portfolios', []) as $portfolioId) {
            $portfolio = Portfolio::where('customer_sales_channel_id', $customerSalesChannel->id)->find($portfolioId);

            if ($portfolio) {
                UnlinkRetinaPortfolio::run($portfolio);
            }
        }
    }

    public function rules(): array
    {
        return [
            'portfolios'   => ['required', 'array'],
            'portfolios.*' => ['required', 'integer'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customerSalesChannel->customer_id == $this->customer->id;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
