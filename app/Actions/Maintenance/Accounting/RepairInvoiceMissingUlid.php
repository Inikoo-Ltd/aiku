<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 May 2025 11:05:58 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Models\Accounting\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Helper\ProgressBar;

class RepairInvoiceMissingUlid
{
    use AsAction;

    public function handle(Invoice $invoice): void
    {
        $invoice->update([
            'ulid' => Str::ulid(),
        ]);

    }

    public string $commandSignature = 'repair:invoices_ulid';

    public function asCommand(Command $command): void
    {
        $count = Invoice::whereNull('ulid')->count();

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('aiku_eta');
        $bar->start();

        Invoice::orderBy('id', 'desc')->whereNull('ulid')
            ->chunk(100, function (Collection $models) use ($bar) {
                foreach ($models as $model) {
                    $this->handle($model);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();
        $command->info("Repaired $count invoices.");
    }

}
