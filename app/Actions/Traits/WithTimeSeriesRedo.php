<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Throwable;

trait WithTimeSeriesRedo
{
    protected function modifyQuery(Builder $query): Builder
    {
        return $query;
    }

    protected function beforeCommand(Command $command): void
    {
    }

    protected function scopeOrganisation(Builder $query, Command $command): Builder
    {
        if ($command->hasOption('organisation') && $command->option('organisation')) {
            $organisation = Organisation::where('slug', $command->option('organisation'))->first();

            if ($organisation) {
                $query->where('organisation_id', $organisation->id);
            }
        }

        return $query;
    }

    protected function scopeShop(Builder $query, Command $command): Builder
    {
        if ($command->hasOption('shop') && $command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->first();

            if ($shop) {
                $query->where('shop_id', $shop->id);
            }
        }

        return $query;
    }

    public function asCommand(Command $command): int
    {
        $this->beforeCommand($command);
        $command->info($command->getName());
        $tableName = (new $this->model())->getTable();
        $query     = $this->modifyQuery($this->scopeShop($this->scopeOrganisation($this->prepareQuery($tableName, $command), $command), $command));
        $count     = $query->count();
        $bar       = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->chunk(1000, function (Collection $modelsData) use ($bar, $command) {
            foreach ($modelsData as $modelId) {
                $model    = (new $this->model());
                $instance = $this->hasSoftDeletes($model)
                    ? $model->withTrashed()->find($modelId->id)
                    : $model->find($modelId->id);

                try {
                    $this->handle($instance->id, $command->option('from'), $command->option('to'), (bool) $command->option('async'));
                } catch (Throwable $e) {
                    $command->error($e->getMessage());
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $command->info('');

        return 0;
    }
}
