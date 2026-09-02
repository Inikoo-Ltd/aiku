<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\CRM\Customer;
use App\Models\Helpers\WebsiteSearchLog;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Backfill for searches logged by staff accounts before StoreWebsiteSearchLog started refusing them.
 */
class PurgeStaffWebsiteSearchLogs
{
    use AsAction;

    public string $commandSignature = 'search:purge_staff_logs';

    public function handle(): int
    {
        return WebsiteSearchLog::whereIn(
            'customer_id',
            Customer::query()->where('is_staff', true)->select('customers.id')
        )->delete();
    }

    public function asCommand(Command $command): int
    {
        $command->info('Deleted '.$this->handle().' staff search logs');

        return 0;
    }
}
