<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 12:42:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\Search;

use App\Actions\HydrateModel;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReindexUserSearch extends HydrateModel
{
    public string $commandSignature = 'search:users {organisations?*} {--s|slugs=}';

    public function handle(User $user): void
    {
        UserRecordSearch::run($user);
    }

    protected function getModel(string $slug): User
    {
        return User::withTrashed()->where('slug', $slug)->first();
    }

    protected function getAllModels(): Collection
    {
        return User::withTrashed()->get();
    }

    protected function loopAll(Command $command): void
    {
        $command->info('Reindex Users');
        $count = User::withTrashed()->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        User::withTrashed()->chunk(1000, function (Collection $models) use ($bar) {
            foreach ($models as $model) {
                $this->handle($model);
                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');
    }
}
