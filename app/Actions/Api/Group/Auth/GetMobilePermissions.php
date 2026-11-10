<?php

namespace App\Actions\Api\Group\Auth;

use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobilePermissions
{
    use AsController;

    /**
     * @return array{user_id: int, permissions: list<string>, roles: list<string>}
     */
    public function handle(User $user): array
    {
        setPermissionsTeamId($user->group_id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [
            'user_id'     => $user->id,
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'roles'       => $user->getRoleNames()->sort()->values()->all(),
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->handle($user));
    }
}
