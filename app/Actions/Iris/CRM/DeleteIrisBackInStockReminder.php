<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sept 2025 12:07:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CRM;

use App\Actions\CRM\BackInStockReminder\DeleteBackInStockReminder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\BackInStockReminder;
use Lorisleiva\Actions\ActionRequest;

class DeleteIrisBackInStockReminder extends RetinaAction
{
    use WithActionUpdate;


    public function handle(BackInStockReminder $backInStockReminder): void
    {
        DeleteBackInStockReminder::make()->action($backInStockReminder);
    }

    public function authorize(ActionRequest $request): bool
    {
        $backInStockReminder = $request->route()->parameter('backInStockReminder');
        if ($backInStockReminder->customer_id !== $this->customer->id) {
            return false;
        }
        return true;
    }

    public function asController(BackInStockReminder $backInStockReminder, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($backInStockReminder);
    }
}
