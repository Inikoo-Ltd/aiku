<?php

/*
 * author eka yudinatha
 * created on 23-12-2025-13h-25m
 * github: https://github.com/ekayudinata
 * copyright 2025
*/

namespace App\Actions\Comms\BackInStockReminder;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBackInStockReminders;
use App\Models\Comms\BackInStockReminderSnapshot;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreBackInStockReminderSnapshot
{
    use AsAction;
    use WithAttributes;
    public function handle(array $modelData): BackInStockReminderSnapshot
    {
        $reminderSnapshot = BackInStockReminderSnapshot::create($modelData);

        CustomerHydrateBackInStockReminders::dispatch($reminderSnapshot->customer_id);
        ProductHydrateCustomersWhoReminded::dispatch($reminderSnapshot->product);
        ProductHydrateCustomersWhoRemindedInCategories::dispatch($reminderSnapshot->product);

        return $reminderSnapshot;
    }


    public function action(array $modelData): BackInStockReminderSnapshot
    {
        return $this->handle($modelData);
    }
}
