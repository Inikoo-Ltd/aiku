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
     * ponytail: closeness = shares an authorised organisation with me; refine with warehouse/shop scope when pickers ask for it
     */
    public function asController(ActionRequest $request): array
    {
        $me          = $request->user();
        $query       = mb_strtolower(trim((string) $request->validated('q', '')));
        $myOrgIds    = $me->authorisedOrganisations()->pluck('organisations.id')->all();

        $users = User::query()
            ->where('group_id', $me->group_id)
            ->where('id', '!=', $me->id)
            ->where('status', true)
            ->with('authorisedOrganisations:id')
            ->get(['id', 'username', 'contact_name', 'image_id', 'language_id'])
            ->filter(fn (User $user) => $query === '' || str_contains(mb_strtolower($user->contact_name.' '.$user->username), $query))
            ->map(fn (User $user) => [
                'id'       => $user->id,
                'name'     => $user->contact_name ?: $user->username,
                'avatar'   => $user->image_id ? $user->imageSources(0, 48) : null,
                'is_close' => $user->authorisedOrganisations->pluck('id')->intersect($myOrgIds)->isNotEmpty(),
            ])
            ->sortBy([['is_close', 'desc'], ['name', 'asc']])
            ->values();

        return ['data' => $users->all()];
    }
}
