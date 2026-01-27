<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Jan 2025 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoTimeSeries
{
    use AsAction;

    public string $commandSignature = 'time-series:redo {organisations?*} {--f|frequency=all : The frequency for time series (all, daily, weekly, monthly, quarterly, yearly)} {--a|async : Run asynchronously}';

    public function handle(array $organisations = [], ?string $frequency = 'all', bool $async = true): void
    {
        try {
            if ($frequency === 'all') {
                $frequencies = TimeSeriesFrequencyEnum::cases();
            } else {
                $frequencies = [TimeSeriesFrequencyEnum::from($frequency)];
            }
        } catch (Throwable) {
            $frequencies = TimeSeriesFrequencyEnum::cases();
        }

        $commandArgs = [];
        if (!empty($organisations)) {
            $commandArgs = $organisations;
        }

        $commandOptions = [
            '--frequency' => $frequency,
            '--async' => $async,
        ];

        $commands = [
            'organisations:redo_time_series',
            'shops:redo_time_series',
            'invoice-categories:redo_time_series',
            'platforms:redo_time_series',
            'families:redo_time_series',
            'departments:redo_time_series',
            'sub_departments:redo_time_series',
            'products:redo_time_series',
            'collections:redo_time_series',
            'offers:redo_time_series',
            'master_families:redo_time_series',
            'master_departments:redo_time_series',
            'master_sub_departments:redo_time_series',
            'master_assets:redo_time_series',
            'master_collections:redo_time_series',
        ];

        foreach ($commands as $command) {
            Artisan::call($command, array_merge($commandArgs, $commandOptions));
        }
    }

    public function asCommand(Command $command): int
    {
        $command->info('Starting time series redo for all entities...');
        $command->info('');

        $organisations = $command->argument('organisations') ?? [];
        $frequency = $command->option('frequency') ?? 'all';
        $async = $command->option('async');

        try {
            if ($frequency === 'all') {
                $frequencies = TimeSeriesFrequencyEnum::cases();
            } else {
                $frequencies = [TimeSeriesFrequencyEnum::from($frequency)];
            }
        } catch (Throwable $e) {
            $command->error($e->getMessage());
            return 1;
        }

        $frequencyList = implode(', ', array_map(fn ($f) => $f->value, $frequencies));
        $command->info("Frequencies: {$frequencyList}");
        $command->info("Mode: " . ($async ? 'Asynchronous (queued)' : 'Synchronous'));
        $command->info('');

        $commands = [
            ['name' => 'Organisations', 'command' => 'organisations:redo_time_series'],
            ['name' => 'Shops', 'command' => 'shops:redo_time_series'],
            ['name' => 'Invoice Categories', 'command' => 'invoice-categories:redo_time_series'],
            ['name' => 'Platforms', 'command' => 'platforms:redo_time_series'],
            ['name' => 'Families', 'command' => 'families:redo_time_series'],
            ['name' => 'Departments', 'command' => 'departments:redo_time_series'],
            ['name' => 'Sub Departments', 'command' => 'sub_departments:redo_time_series'],
            ['name' => 'Products', 'command' => 'products:redo_time_series'],
            ['name' => 'Collections', 'command' => 'collections:redo_time_series'],
            ['name' => 'Offers', 'command' => 'offers:redo_time_series'],
            ['name' => 'Master Families', 'command' => 'master_families:redo_time_series'],
            ['name' => 'Master Departments', 'command' => 'master_departments:redo_time_series'],
            ['name' => 'Master Sub Departments', 'command' => 'master_sub_departments:redo_time_series'],
            ['name' => 'Master Assets', 'command' => 'master_assets:redo_time_series'],
            ['name' => 'Master Collections', 'command' => 'master_collections:redo_time_series'],
        ];

        foreach ($commands as $commandInfo) {
            $command->info("Running: {$commandInfo['name']}...");

            $commandArgs = !empty($organisations) ? $organisations : [];
            $commandOptions = [
                '--frequency' => $frequency,
            ];

            if ($async) {
                $commandOptions['--async'] = true;
            }

            try {
                Artisan::call($commandInfo['command'], array_merge($commandArgs, $commandOptions));
                $command->line(Artisan::output());
            } catch (Throwable $e) {
                $command->error("Error running {$commandInfo['name']}: " . $e->getMessage());
            }

            $command->info('');
        }

        $command->info('All time series redo commands completed!');

        return 0;
    }
}
