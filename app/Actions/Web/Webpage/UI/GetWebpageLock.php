<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebpageLock
{
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(Webpage $webpage, ?User $user): array
    {
        $canManage = $webpage->canManageLockBy($user);
        $users     = User::where('group_id', $webpage->group_id)->where('status', true)->orderBy('contact_name')->get(['id', 'username', 'contact_name']);
        $names     = $users->keyBy('id');

        return [
            'is_locked'    => $webpage->isLocked(),
            'owner'        => $webpage->lockedBy?->contact_name ?? $webpage->lockedBy?->username,
            'is_owner'     => $webpage->locked_by_user_id && $user && $webpage->locked_by_user_id == $user->id,
            'locked_at'    => $webpage->locked_at,
            'reason'       => Arr::get($webpage->lock_data, 'reason'),
            'note'         => Arr::get($webpage->lock_data, 'note'),
            'editors'      => collect(Arr::get($webpage->lock_data, 'editors', []))->map(fn (array $grant) => array_merge($grant, [
                'name' => $names->get($grant['user_id'])?->contact_name ?? $names->get($grant['user_id'])?->username,
            ]))->values()->all(),
            'can_edit'     => $webpage->canBeEditedBy($user),
            'can_manage'   => $canManage,
            'message'      => $webpage->isLocked() ? $webpage->lockMessage() : null,
            'users'        => $canManage ? $users->map(fn (User $candidate) => [
                'value' => $candidate->id,
                'label' => $candidate->contact_name ?: $candidate->username,
            ])->values()->all() : [],
            'lock_route'   => ['name' => 'grp.models.webpage.lock', 'parameters' => ['webpage' => $webpage->id]],
            'unlock_route' => ['name' => 'grp.models.webpage.unlock', 'parameters' => ['webpage' => $webpage->id]],
        ];
    }
}
