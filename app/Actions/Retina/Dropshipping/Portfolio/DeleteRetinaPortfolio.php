<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\CRM\Customer\DeletePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio): void
    {
        DeletePortfolio::make()->action($portfolio);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->portfolio->customer_id == $this->customer->id;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request): void
    {
        $this->portfolio = $portfolio;
        $this->initialisation($request);

        $this->handle($portfolio);
    }
}
