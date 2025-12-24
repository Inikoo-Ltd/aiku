<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2025 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\BackInStockNotification;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\BackInStockReminder;
use Illuminate\Console\Command;

class BulkDeleteBackInStockReminder
{
    use AsAction;
    public string $commandSignature = 'bulk-delete-back-in-stock-reminder {backInStockReminderIds* : Back In Stock Reminder IDs to delete}';


    public function handle(array $backInStockReminderIds): void
    {
        \Log::info($backInStockReminderIds);
        BulkUpdateBackInStockReminderSnapshot::run($backInStockReminderIds);

        BackInStockReminder::whereIn('id', $backInStockReminderIds)->delete();
    }

    public function asCommand(Command $command): void
    {
        $this->handle($command->argument('backInStockReminderIds'));
    }
}
