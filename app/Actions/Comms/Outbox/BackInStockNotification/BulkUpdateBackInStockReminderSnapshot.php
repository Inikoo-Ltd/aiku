<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2025 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\BackInStockNotification;

use App\Actions\CRM\BackInStockReminder\UpdateBackInStockReminderSnapshot;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\BackInStockReminderSnapshot;
use Illuminate\Support\Collection;
use App\Services\QueryBuilder;
use Exception;
use Illuminate\Support\Facades\Log;
use Sentry;

class BulkUpdateBackInStockReminderSnapshot
{
    use AsAction;

    public function handle(array $backInStockReminderIds): void
    {
        $baseQuery = QueryBuilder::for(BackInStockReminderSnapshot::class);
        $baseQuery->whereIn('back_in_stock_reminder_id', $backInStockReminderIds);

        $baseQuery->chunk(
            1000,
            function (Collection $modelsData) {
                foreach ($modelsData as $modelData) {
                    try {
                        $snapshotModeldata = [
                            "reminder_sent_at" => now()
                        ];
                        UpdateBackInStockReminderSnapshot::make()->action($modelData->back_in_stock_reminder_id, $snapshotModeldata);
                    } catch (Exception $e) {
                        Log::info("Failed to update back in stock reminder snapshot: " . $e->getMessage());
                        Sentry::captureMessage("Failed to update back in stock reminder snapshot: " . $e->getMessage());
                    }
                }
            }
        );
    }
}
