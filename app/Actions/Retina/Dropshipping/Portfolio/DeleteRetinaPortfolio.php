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
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;

    public function handle(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio): void
    {
        DeletePortfolio::run($customerSalesChannel, $portfolio);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->portfolio->customer_id == $this->customer->id;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->portfolio = $portfolio;
        $this->initialisation($request);

        $this->handle($customerSalesChannel, $portfolio);
    }
}
