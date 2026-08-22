<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * ponytail: closeness = shares an organisation with me (employee records or authorised); refine with warehouse/shop scope when pickers ask for it.
     * Raw pivot reads on purpose: eager loading these polymorphic pivots into models costs seconds for 160 users.
     *
     * @return array<int, int[]> user id => organisation ids
     */
    protected function organisationIdsByUser(array $userIds): array
    {
        $rows = DB::table('user_has_models')
            ->join('employees', 'employees.id', '=', 'user_has_models.model_id')
            ->where('user_has_models.model_type', 'Employee')
            ->whereIn('user_has_models.user_id', $userIds)
            ->select('user_has_models.user_id', 'employees.organisation_id as organisation_id')
            ->union(
                DB::table('user_has_authorised_models')
                    ->where('model_type', 'Organisation')
                    ->whereIn('user_id', $userIds)
                    ->select('user_id', 'model_id as organisation_id')
            )
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->organisation_id) {
                $map[$row->user_id][] = (int) $row->organisation_id;
            }
        }

        return $map;
    }

    public function asController(ActionRequest $request): array
    {
        $me    = $request->user();
        $query = mb_strtolower(trim((string) $request->validated('q', '')));

        $users = User::query()
            ->where('group_id', $me->group_id)
            ->where('id', '!=', $me->id)
            ->where('status', true)
            ->when($query !== '', fn ($builder) => $builder->where(function ($builder) use ($query) {
                $builder->whereRaw('lower(contact_name) like ?', ['%'.$query.'%'])
                    ->orWhereRaw('lower(username) like ?', ['%'.$query.'%'])
                    ->orWhereRaw('lower(nickname) like ?', ['%'.$query.'%']);
            }))
            ->get(['id', 'username', 'contact_name', 'nickname', 'image_id', 'language_id']);

        $orgIds   = $this->organisationIdsByUser($users->pluck('id')->push($me->id)->all());
        $myOrgIds = $orgIds[$me->id] ?? [];
        $teamIds  = DB::table('user_has_team_members')->where('user_id', $me->id)->pluck('member_user_id')->all();
        $lastActive = Cache::many($users->map(fn (User $user) => 'staff-last-active:'.$user->id)->all());

        $data = $users
            ->map(fn (User $user) => [
                'id'       => $user->id,
                'name'     => $user->chatName(),
                'avatar'   => $user->image_id
                    ? Cache::remember('staff-avatar:'.$user->id.':'.$user->image_id, now()->addDay(), fn () => $user->imageSources(0, 48))
                    : null,
                'is_close' => count(array_intersect($orgIds[$user->id] ?? [], $myOrgIds)) > 0,
                'in_team'  => in_array($user->id, $teamIds),
                'last_active_at' => $lastActive['staff-last-active:'.$user->id] ?? null,
            ])
            ->sortBy([['in_team', 'desc'], ['is_close', 'desc'], ['name', 'asc']])
            ->values();

        return ['data' => $data->all()];
    }
}
