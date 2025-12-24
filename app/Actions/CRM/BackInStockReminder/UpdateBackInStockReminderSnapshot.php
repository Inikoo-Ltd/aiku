<?php

/*
 * author eka yudinata
 * created on 15-10-2024-16h-31m
 * github: https://github.com/ekayudinata
 * copyright 2024
*/

namespace App\Actions\CRM\BackInStockReminder;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBackInStockReminders;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\BackInStockReminder;
use App\Models\CRM\BackInStockReminderSnapshot;
use Lorisleiva\Actions\ActionRequest;

class UpdateBackInStockReminderSnapshot extends OrgAction
{
    use WithActionUpdate;

    private BackInStockReminder $backInStockReminder;

    public function handle(BackInStockReminder $backInStockReminder, array $modelData): ?BackInStockReminderSnapshot
    {

        $backInStockReminderSnapshot = BackInStockReminderSnapshot::where('back_in_stock_reminder_id', $backInStockReminder->id)->first();

        if ($backInStockReminderSnapshot) {

            $this->update($backInStockReminderSnapshot, $modelData);
        }

        CustomerHydrateBackInStockReminders::dispatch($backInStockReminder->customer_id)->delay($this->hydratorsDelay);
        // Note need to update this hydrator
        // ProductHydrateCustomersWhoReminded::dispatch($backInStockReminder->product)->delay($this->hydratorsDelay);
        // ProductHydrateCustomersWhoRemindedInCategories::dispatch($backInStockReminder->product)->delay($this->hydratorsDelay);

        return $backInStockReminderSnapshot;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        // Note: update rules
        $rules = [];

        return $rules;
    }

    public function action(BackInStockReminder $backInStockReminder, array $modelData, int $hydratorsDelay = 0, bool $strict = true): ?BackInStockReminderSnapshot
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->backInStockReminder       = $backInStockReminder;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($backInStockReminder->organisation, $modelData);

        return $this->handle($backInStockReminder, $this->validatedData);
    }
}
