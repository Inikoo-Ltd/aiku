<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris\Portfolio;

use App\Actions\CRM\BackInStockReminder\UpdateBackInStockReminder;
use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\BackInStockReminder;
use App\Models\Dropshipping\Portfolio;
use Lorisleiva\Actions\ActionRequest;

class UpdateIrisBackInStockReminder extends IrisAction
{
    use WithActionUpdate;

    private Portfolio $portfolio;

    public function handle(BackInStockReminder $backInStockReminder, array $modelData): void
    {
        UpdateBackInStockReminder::make()->action($backInStockReminder, $modelData);
    }

    public function asController(BackInStockReminder $backInStockReminder, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($backInStockReminder, $this->validatedData);
    }
}
