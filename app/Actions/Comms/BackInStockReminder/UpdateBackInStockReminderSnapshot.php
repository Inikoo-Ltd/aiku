<?php

/*
 * author eka yudinata
 * created on 15-10-2024-16h-31m
 * github: https://github.com/ekayudinata
 * copyright 2024
*/

namespace App\Actions\Comms\BackInStockReminder;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBackInStockReminders;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\BackInStockReminderSnapshot;

class UpdateBackInStockReminderSnapshot extends OrgAction
{
    use WithActionUpdate;

    private BackInStockReminderSnapshot $backInStockReminderSnapshot;

    public function handle(BackInStockReminderSnapshot $backInStockReminderSnapshot, array $modelData): ?BackInStockReminderSnapshot
    {
        $this->update($backInStockReminderSnapshot, $modelData);

        CustomerHydrateBackInStockReminders::dispatch($backInStockReminderSnapshot->customer_id)->delay($this->hydratorsDelay);
        ProductHydrateCustomersWhoReminded::dispatch($backInStockReminderSnapshot->product)->delay($this->hydratorsDelay);
        ProductHydrateCustomersWhoRemindedInCategories::dispatch($backInStockReminderSnapshot->product)->delay($this->hydratorsDelay);

        return $backInStockReminderSnapshot;
    }

    public function rules(): array
    {
        return [
            'reminder_cancelled_at' => 'nullable|date',
            'reminder_sent_at'      => 'nullable|date',
        ];
    }

    public function action(BackInStockReminderSnapshot|int $backInStockReminderSnapshot, array $modelData, int $hydratorsDelay = 0): ?BackInStockReminderSnapshot
    {
        $this->asAction = true;

        // Handle route binding - if ID is passed, resolve the model
        if (is_int($backInStockReminderSnapshot)) {
            $backInStockReminderSnapshot = BackInStockReminderSnapshot::where('back_in_stock_reminder_id', $backInStockReminderSnapshot)->first();
            if (!$backInStockReminderSnapshot) {
                return null;
            }
        }

        $this->backInStockReminderSnapshot = $backInStockReminderSnapshot;
        $this->hydratorsDelay              = $hydratorsDelay;
        $this->initialisation($backInStockReminderSnapshot->organisation, $modelData);

        return $this->handle($backInStockReminderSnapshot, $this->validatedData);
    }
}
