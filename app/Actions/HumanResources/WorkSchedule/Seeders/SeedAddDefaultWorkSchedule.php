<?php

namespace App\Actions\HumanResources\WorkSchedule\Seeders;

use App\Actions\HumanResources\WorkSchedule\UpdateWorkSchedule;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsCommand;
use App\Models\Catalogue\Shop;

class SeedAddDefaultWorkSchedule
{
    use AsCommand;

    public string $commandSignature = 'hr:schedule
                                        {--org= : The code of the organisation or "all"}
                                        {--shop= : The code of the shop or "all"}
                                        {--start=09:00 : Start time (e.g. 09:00)}
                                        {--end=17:00 : End time (e.g. 17:00)}
                                        {--days=5 : Number of working days (1-7), 5 means Mon-Fri}';

    public string $commandDescription = 'Seed default work schedule for an Organisation or Shop';


    public function handle(Command $command): void
    {
        $organisationCode = $command->option('org');
        $shopCode = $command->option('shop');
        $startTime = $command->option('start');
        $endTime = $command->option('end');
        $days = (int) $command->option('days');

        if ($organisationCode && $shopCode) {
            $command->error('Please provide only one of --organisation or --shop, not both.');
            return;
        }

        if (!$organisationCode && !$shopCode) {
            $command->error('Please provide either --organisation or --shop code (or "all").');
            return;
        }

        $targets = collect();

        if ($organisationCode) {
            if (strtolower($organisationCode) === 'all') {
                $targets = Organisation::all();
                $command->info("Found {$targets->count()} organisations to seed.");
            } else {
                $parent = Organisation::where('code', $organisationCode)->first();
                if (!$parent) {
                    $command->error("Organisation with code {$organisationCode} not found.");
                    return;
                }
                $targets->push($parent);
            }
        } elseif ($shopCode) {
            if (strtolower($shopCode) === 'all') {
                $targets = Shop::all();
                $command->info("Found {$targets->count()} shops to seed.");
            } else {
                $parent = Shop::where('code', $shopCode)->first();
                if (!$parent) {
                    $command->error("Shop with code {$shopCode} not found.");
                    return;
                }
                $targets->push($parent);
            }
        }

        $workingHoursData = [];
        for ($i = 1; $i <= 7; $i++) {
            if ($i <= $days) {
                $workingHoursData[$i] = [
                    's' => $startTime,
                    'e' => $endTime,
                    'b' => []
                ];
            }
        }

        $data = [
            'working_hours' => [
                'data' => $workingHoursData
            ]
        ];

        $bar = $command->getOutput()->createProgressBar($targets->count());
        $bar->start();

        foreach ($targets as $parent) {
            UpdateWorkSchedule::run($parent, $data);
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();
        $command->info("Work schedule seeded successfully for {$targets->count()} entities ({$days} days, {$startTime} - {$endTime}).");
    }
}
