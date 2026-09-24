<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOverlappingJobPositionGrades
{
    use AsAction;

    public string $commandSignature = 'repair:overlapping_job_position_grades {--N|dry_run}';
    public string $commandDescription = 'Employees holding a supervisor and a worker position of the same department on the same shop/warehouse keep only the supervisor one';

    public function handle(bool $dryRun = false): array
    {
        $repaired = [];

        Employee::where('state', '!=', EmployeeStateEnum::LEFT)->has('jobPositions', '>', 1)->with('jobPositions')
            ->each(function (Employee $employee) use ($dryRun, &$repaired) {
                $jobPositions = $employee->jobPositions->mapWithKeys(fn (JobPosition $jobPosition) => [$jobPosition->id => $jobPosition->pivot->scopes])->all();
                $cleaned      = DropLowerGradeJobPositionScopes::run($jobPositions);
                if ($cleaned == $jobPositions) {
                    return;
                }

                $repaired[] = $employee->organisation->slug.' '.$employee->slug;
                if (!$dryRun) {
                    setPermissionsTeamId($employee->group_id);
                    SyncEmployeeJobPositions::run($employee, $cleaned);
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
