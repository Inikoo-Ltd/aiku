<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 08:30:00 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\SysAdmin\CleanUserCaches;
use App\Actions\SysAdmin\User\SyncRolesFromJobPositions;
use App\Actions\UI\Grp\BreakUserUiProps;
use App\Enums\HumanResources\JobPosition\JobPositionScopeEnum;
use App\Models\HumanResources\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEmptyProductionPositionScopes
{
    use AsAction;

    public string $commandSignature = 'repair:empty_production_position_scopes {--N|dry_run}';
    public string $commandDescription = 'Production job positions saved without a factory scope grant no production role; scope them to every factory of the employee organisation and resync roles';

    public function handle(bool $dryRun = false): array
    {
        $repaired = [];

        Employee::whereHas('jobPositions', function ($query) {
            $query->where('scope', JobPositionScopeEnum::PRODUCTIONS);
        })->with('jobPositions')->each(function (Employee $employee) use ($dryRun, &$repaired) {
            $productionIds = $employee->organisation->productions()->pluck('id')->all();

            foreach ($employee->jobPositions as $jobPosition) {
                if ($jobPosition->scope != JobPositionScopeEnum::PRODUCTIONS || Arr::get($jobPosition->pivot->scopes, 'Production') || !$productionIds) {
                    continue;
                }

                $repaired[] = $employee->slug.' '.$jobPosition->code;
                if ($dryRun) {
                    continue;
                }

                $employee->jobPositions()->updateExistingPivot($jobPosition->id, ['scopes' => ['Production' => $productionIds]]);
                foreach ($employee->users as $user) {
                    setPermissionsTeamId($user->group_id);
                    SyncRolesFromJobPositions::run($user);
                    CleanUserCaches::run($user);
                    BreakUserUiProps::dispatch($user);
                }
            }
        });

        return $repaired;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $repaired = $this->handle($command->option('dry_run'));
        foreach ($repaired as $line) {
            $command->line($line);
        }
        $command->info(($command->option('dry_run') ? 'Would repair ' : 'Repaired ').count($repaired));

        return 0;
    }
}
