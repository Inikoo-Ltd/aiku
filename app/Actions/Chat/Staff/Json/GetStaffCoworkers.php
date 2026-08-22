<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffCoworkers
{
    use AsAction;

    public function rules(): array
    {
        return ['q' => ['sometimes', 'nullable', 'string', 'max:64']];
    }

    /**
     * ponytail: closeness = shares an organisation with me (employee records or authorised); refine with warehouse/shop scope when pickers ask for it
     */
    protected function organisationIds(User $user): array
    {
        return $user->employees->pluck('organisation_id')
            ->merge($user->authorisedOrganisations->pluck('id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function asController(ActionRequest $request): array
    {
        $me          = $request->user();
        $query       = mb_strtolower(trim((string) $request->validated('q', '')));
        $me->load(['employees:id,organisation_id', 'authorisedOrganisations:id', 'teamMembers:id']);
        $myOrgIds    = $this->organisationIds($me);
        $teamIds     = $me->teamMembers->pluck('id')->all();

        $users = User::query()
            ->where('group_id', $me->group_id)
            ->where('id', '!=', $me->id)
            ->where('status', true)
            ->with(['authorisedOrganisations:id', 'employees:id,organisation_id'])
            ->get(['id', 'username', 'contact_name', 'image_id', 'language_id'])
            ->filter(fn (User $user) => $query === '' || str_contains(mb_strtolower($user->contact_name.' '.$user->username), $query))
            ->map(fn (User $user) => [
                'id'       => $user->id,
                'name'     => $user->contact_name ?: $user->username,
                'avatar'   => $user->image_id ? $user->imageSources(0, 48) : null,
                'is_close' => count(array_intersect($this->organisationIds($user), $myOrgIds)) > 0,
                'in_team'  => in_array($user->id, $teamIds),
            ])
            ->sortBy([['in_team', 'desc'], ['is_close', 'desc'], ['name', 'asc']])
            ->values();


        return ['data' => $users->all()];
    }
}
