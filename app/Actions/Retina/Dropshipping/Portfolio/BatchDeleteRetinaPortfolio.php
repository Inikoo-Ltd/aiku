<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\DeletePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class BatchDeleteRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;


    private CustomerSalesChannel $customerSalesChannel;

    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): void
    {
        foreach (Arr::get($modelData, 'portfolios') as $portfolioId) {
            $portfolio = Portfolio::find($portfolioId);

            if ($portfolio) {
                DeletePortfolio::run($customerSalesChannel, $portfolio);
            }
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customerSalesChannel->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'portfolios' => ['required', 'array'],
            'portfolios.*' => ['required', 'integer', Rule::exists('portfolios', 'id')],
        ];
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $this->validatedData);
    }
}
