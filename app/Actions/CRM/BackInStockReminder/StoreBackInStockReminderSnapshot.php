<?php

/*
 * author eka yudinatha
 * created on 23-12-2025-13h-25m
 * github: https://github.com/ekayudinata
 * copyright 2025
*/

namespace App\Actions\CRM\BackInStockReminder;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBackInStockReminders;
use App\Models\CRM\BackInStockReminderSnapshot;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Support\Arr;

// NOTE: add validator
class StoreBackInStockReminderSnapshot
{
    use AsAction;
    use WithAttributes;
    public function handle(array $modelData): BackInStockReminderSnapshot
    {
        $reminderSnapshot = BackInStockReminderSnapshot::create($modelData);

        CustomerHydrateBackInStockReminders::dispatch(Arr::get($modelData, 'customer_id'))->delay($this->hydratorsDelay);
        // Note need to update this hydrator
        // ProductHydrateCustomersWhoReminded::dispatch(Arr::get($modelData, 'product_id'))->delay($this->hydratorsDelay);
        // ProductHydrateCustomersWhoRemindedInCategories::dispatch(Arr::get($modelData, 'product_id'))->delay($this->hydratorsDelay);

        return $reminderSnapshot;
    }


    public function action(array $modelData): BackInStockReminderSnapshot
    {
        return $this->handle($modelData);
    }
}
