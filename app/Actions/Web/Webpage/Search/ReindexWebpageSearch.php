<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Webpage\Search;

use App\Actions\HydrateModel;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexWebpageSearch extends HydrateModel
{
    public string $commandSignature = 'search:webpages {organisations?*} {--s|slugs=} ';

    public function handle(Webpage $webpage): void
    {
        WebpageRecordSearch::run($webpage);
    }

    protected function getModel(string $slug): Webpage
    {
        return Webpage::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return Webpage::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Websites');
        $count = Webpage::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        Webpage::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
