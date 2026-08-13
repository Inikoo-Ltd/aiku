<?php

namespace App\Actions\SysAdmin\User;

use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsObject;

class GetUserCurrentEmployee
{
    use AsObject;

    public function handle(User $user, int|string|null $organisationScope = null): ?Employee
    {
        $active = $this->scopedQuery($user, $organisationScope)
            ->whereIn('employees.state', ['working', 'leaving'])
            ->first();

        if ($active) {
            return $active;
        }

        return $this->scopedQuery($user, $organisationScope)->first();
    }

    public static function fromRequest(Request $request): ?Employee
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $organisationScope = $request->input('organisation') ?? $request->route('organisation');
        if (is_object($organisationScope)) {
            $organisationScope = $organisationScope->slug ?? $organisationScope->id ?? null;
        }

        if ($organisationScope) {
            $employee = static::run($user, $organisationScope);
            if ($employee) {
                return $employee;
            }
        }

        return static::run($user);
    }

    private function scopedQuery(User $user, int|string|null $organisationScope)
    {
        $query = $user->employees()->orderByDesc('employees.id');

        if ($organisationScope === null || $organisationScope === '') {
            return $query;
        }

        if (is_int($organisationScope)) {
            return $query->where('employees.organisation_id', $organisationScope);
        }

        $isNumericOrganisationId = ctype_digit($organisationScope);

        return $query->whereHas('organisation', function ($organisationQuery) use ($organisationScope, $isNumericOrganisationId) {
            $organisationQuery->where('slug', $organisationScope);

            if ($isNumericOrganisationId) {
                $organisationQuery->orWhere('id', (int) $organisationScope);
            }
        });
    }
}
