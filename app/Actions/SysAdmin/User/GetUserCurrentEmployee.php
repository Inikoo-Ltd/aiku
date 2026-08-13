<?php

namespace App\Actions\SysAdmin\User;

use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUserCurrentEmployee
{
    use AsObject;

    public function handle(User $user, ?int $organisationId = null): ?Employee
    {
        $active = $user->employees()
            ->whereIn('employees.state', ['working', 'leaving'])
            ->when($organisationId, fn ($query) => $query->where('employees.organisation_id', $organisationId))
            ->orderByDesc('employees.id')
            ->first();

        if ($active) {
            return $active;
        }

        return $user->employees()
            ->when($organisationId, fn ($query) => $query->where('employees.organisation_id', $organisationId))
            ->orderByDesc('employees.id')
            ->first();
    }
}
