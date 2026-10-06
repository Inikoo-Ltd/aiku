<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\SysAdmin\User\SyncUserPseudoOrganisationJobPositions;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMergeJobPositions
{
    use AsAction;

    public string $commandSignature = 'repair:merge_job_positions {from : code of the position going away} {into : code of the position taking its holders} {--N|dry_run}';
    public string $commandDescription = 'Holders of a position that is going away get the position taking over on the same shops';

    public function handle(string $fromCode, string $intoCode, bool $dryRun = false): array
    {
        $repaired = [];

        Employee::where('state', '!=', EmployeeStateEnum::LEFT)
            ->whereHas('jobPositions', fn ($query) => $query->where('code', $fromCode))
            ->with('jobPositions')
            ->each(function (Employee $employee) use ($fromCode, $intoCode, $dryRun, &$repaired) {
                $repaired[] = 'employee '.$employee->organisation->slug.' '.$employee->slug;
                if (!$dryRun) {
                    setPermissionsTeamId($employee->group_id);
                    SyncEmployeeJobPositions::run($employee, $this->mergeScopes($employee->jobPositions, $employee->organisation, $fromCode, $intoCode));
                }
            });

        User::whereHas('pseudoJobPositions', fn ($query) => $query->where('code', $fromCode))
            ->with('pseudoJobPositions')
            ->each(function (User $user) use ($fromCode, $intoCode, $dryRun, &$repaired) {
                $fromOrganisationIds = $user->pseudoJobPositions->where('code', $fromCode)->pluck('organisation_id')->unique();
                foreach ($fromOrganisationIds as $organisationId) {
                    $organisation = Organisation::find($organisationId);
                    $repaired[]   = 'user '.$organisation->slug.' '.$user->username;
                    if (!$dryRun) {
                        SyncUserPseudoOrganisationJobPositions::run(
                            $user,
                            $organisation,
                            $this->mergeScopes($user->pseudoJobPositions->where('organisation_id', $organisationId), $organisation, $fromCode, $intoCode)
                        );
                    }
                }
            });

        return $repaired;
    }

    /**
     * @param  Collection<int, JobPosition>  $jobPositions
     *
     * @return array<int, array<string, array<int>>>
     */
    public function mergeScopes(Collection $jobPositions, Organisation $organisation, string $fromCode, string $intoCode): array
    {
        $scopes = $jobPositions->mapWithKeys(fn (JobPosition $jobPosition) => [$jobPosition->id => $jobPosition->pivot->scopes ?? []])->all();

        $from = $jobPositions->firstWhere('code', $fromCode);
        $into = $organisation->jobPositions()->where('code', $intoCode)->firstOrFail();

        $fromScopes = $scopes[$from->id];
        $intoScopes = $scopes[$into->id] ?? null;
        unset($scopes[$from->id]);

        if ($intoScopes === null) {
            $scopes[$into->id] = $fromScopes;
        } elseif (empty($fromScopes) || empty($intoScopes)) {
            $scopes[$into->id] = [];
        } else {
            $scopes[$into->id] = [
                'Shop' => array_values(array_unique(array_merge($intoScopes['Shop'] ?? [], $fromScopes['Shop'] ?? [])))
            ];
        }

        return $scopes;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $repaired = $this->handle($command->argument('from'), $command->argument('into'), $command->option('dry_run'));
        foreach ($repaired as $line) {
            $command->line($line);
        }
        $command->info(($command->option('dry_run') ? 'Would repair ' : 'Repaired ').count($repaired));

        return 0;
    }
}
