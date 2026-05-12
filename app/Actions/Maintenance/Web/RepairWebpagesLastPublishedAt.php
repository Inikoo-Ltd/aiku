<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 May 2026 14:37:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;

class RepairWebpagesLastPublishedAt
{
    use WithActionUpdate;

    public string $commandSignature = 'repair:webpages_last_published_at {--webpage_id=}';

    protected function handle(Webpage $webpage): void
    {
        $lastPublishedAt = $webpage->snapshots()->latest()->first()?->published_at;

        $webpage->updateQuietly([
            'last_published_at' => $lastPublishedAt,
        ]);
    }

    public function asCommand(Command $command): void
    {
        $query = Webpage::query()
            ->when(
                $command->option('webpage_id'),
                fn ($query, $webpageId) => $query->where('id', $webpageId)
            )
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $command->info('No webpages to process.');

            return;
        }

        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat('debug');
        $progressBar->start();

        $query->chunkById(1000, function ($webpages) use ($progressBar) {
            foreach ($webpages as $webpage) {
                $this->handle($webpage);
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
    }
}
