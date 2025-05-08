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
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteRetinaPortfolio extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Portfolio $portfolio)
    {
        return DeletePortfolio::make()->action($portfolio);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Portfolio $portfolio, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($portfolio);
    }
}
