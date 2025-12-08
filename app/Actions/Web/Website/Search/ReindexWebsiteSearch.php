<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 12:56:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Search;

use App\Actions\HydrateModel;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexWebsiteSearch extends HydrateModel
{
    public string $commandSignature = 'search:websites {organisations?*} {--s|slugs=} ';

    public function handle(Website $website): void
    {
        WebsiteRecordSearch::run($website);
    }

    protected function getModel(string $slug): Website
    {
        return Website::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Website::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Websites');
        $count = Website::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Website::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
