<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-16h-33m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\BackInStockReminder;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePendingBackInStockReminders;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\BackInStockReminder;
use Lorisleiva\Actions\ActionRequest;

class DeleteBackInStockReminder extends OrgAction
{
    use WithActionUpdate;

    private BackInStockReminder $backInStockReminder;

    public function handle(BackInStockReminder $backInStockReminder): BackInStockReminder
    {
        $snapshotModelData = [
            'reminder_cancelled_at' => now()
        ];

        UpdateBackInStockReminderSnapshot::make()->action($this->backInStockReminder->id, $snapshotModelData);

        $backInStockReminder->delete();
        ShopHydratePendingBackInStockReminders::dispatch($backInStockReminder->shop);

        return $backInStockReminder;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }


    public function action(BackInStockReminder $backInStockReminder): BackInStockReminder
    {
        $this->backInStockReminder = $backInStockReminder;
        $this->asAction       = true;
        $this->initialisation($backInStockReminder->organisation, []);

        return $this->handle($backInStockReminder);
    }
}
