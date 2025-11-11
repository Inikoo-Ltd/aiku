<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class UnlinkRetinaPortfolio extends RetinaAction
{
    use WithActionUpdate;


    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio): void
    {
        UpdatePortfolio::run($portfolio, [
            'platform_status'               => false,
            'platform_product_variant_id'   => null,
            'exist_in_platform'             => false,
            'has_valid_platform_product_id' => false,
            'platform_product_id'           => null
        ]);
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
