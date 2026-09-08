<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleStaffTeamMember
{
    use AsAction;

    public function handle(User $user, User $member): bool
    {
        $result = $user->teamMembers()->toggle([$member->id]);

        return count($result['attached']) > 0;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('group_id', request()->user()->group_id), Rule::notIn([request()->user()->id])],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $member = User::findOrFail($request->validated('user_id'));

        return ['in_team' => $this->handle($request->user(), $member)];
    }
}
