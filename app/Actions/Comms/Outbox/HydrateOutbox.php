<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateEmailBulkRuns;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateEmails;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateIntervals;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateSubscribers;
use App\Actions\HydrateModel;
use App\Models\Comms\Outbox;
use Illuminate\Console\Command;

class HydrateOutbox extends HydrateModel
{
    public function handle(Outbox $outbox): void
    {
        OutboxHydrateEmailBulkRuns::run($outbox);
        OutboxHydrateEmails::run($outbox);
        OutboxHydrateMailshots::run($outbox);
        OutboxHydrateIntervals::run($outbox);
        OutboxHydrateSubscribers::run($outbox);

    }

    public string $commandSignature = 'hydrate:outboxes {organisations?*} {--s|slugs=} ';

    protected function getModel(string $slug): Outbox
    {
        return Outbox::where('id', $slug)->first();
    }

    public function asCommand(Command $command): int
    {
        $command->info('Hydrating Outboxes');
        $count = Outbox::count();
        $bar   = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();
        Outbox::chunk(1000, function (\Illuminate\Database\Eloquent\Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });
        $bar->finish();
        $command->info("");

        return 0;
    }

}
