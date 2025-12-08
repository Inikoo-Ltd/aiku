<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 30 Oct 2025 15:32:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UnlinkAndDeleteBulkRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;

    private CustomerSalesChannel $customerSalesChannel;

    public function handle(array $modelData): void
    {
        foreach (Arr::get($modelData, 'portfolios', []) as $portfolioId) {
            $portfolio = Portfolio::find($portfolioId);

            if ($portfolio) {
                UnlinkRetinaPortfolio::run($portfolio);
                DeleteRetinaPortfolio::run($portfolio);
            }
        }
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
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

        $this->handle($this->validatedData);
    }
}
