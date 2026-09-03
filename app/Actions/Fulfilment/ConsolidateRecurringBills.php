<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Feb 2025 00:20:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\RecurringBill\ConsolidateRecurringBill;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\RecurringBill;
use Illuminate\Console\Command;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ConsolidateRecurringBills
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public string $commandSignature = 'current_recurring_bills:consolidate';

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping())->dontRelease()->expireAfter(3600)];
    }

    /**
     * @throws \Throwable
     */
    public function handle(Command|null $command = null): void
    {
        /** @var RecurringBill $recurringBill */
        foreach (RecurringBill::where('status', RecurringBillStatusEnum::CURRENT)->get() as $recurringBill) {
            $today   = now()->startOfDay();
            $endDate = $recurringBill->end_date->startOfDay();

            if ($recurringBill->start_date->startOfDay()->gte($today)) {
                continue;
            }

            if ($endDate->lte($today)) {
                $command?->info('Consolidating recurring bill '.$recurringBill->id.' '.$recurringBill->reference);
                $invoice = ConsolidateRecurringBill::make()->action($recurringBill);
                $command?->info('Recurring bill Consolidated invoice:'.$invoice->reference);
                $command?->info('');
            }
        }
    }


    /**
     * @throws \Throwable
     */
    public function asCommand($command): void
    {
        $this->handle($command);
    }


}
