<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

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

    public function asCommand(Command $command): int
    {
        $this->beforeCommand($command);
        $command->info($command->getName());
        $tableName = (new $this->model())->getTable();
        $query     = $this->modifyQuery($this->prepareQuery($tableName, $command));
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
