<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 19-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgAgent\Search;

use App\Actions\HydrateModel;
use App\Models\Procurement\OrgAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexOrgAgentSearch extends HydrateModel
{
    public string $commandSignature = 'search:org_agents {organisations?*} {--s|slugs=} ';

    public function handle(OrgAgent $orgAgent): void
    {
        OrgAgentRecordSearch::run($orgAgent);
    }

    protected function getModel(string $slug): OrgAgent
    {
        return OrgAgent::where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return OrgAgent::all();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Org Agents');
        $count = OrgAgent::count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        OrgAgent::chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
