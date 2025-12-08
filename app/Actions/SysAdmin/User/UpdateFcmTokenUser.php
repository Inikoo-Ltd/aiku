<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\SysAdmin\UsersResource;
use App\Models\Notifications\FcmToken;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateFcmTokenUser extends GrpAction
{
    use WithActionUpdate;

    private User $user;

    public function handle(User $user, $modelData): User
    {
        $token = FcmToken::firstOrNew([
            'token_id' => $user->currentAccessToken()->token,
        ]);

        $token->fcm_token = Arr::get($modelData, 'firebase_token');

        $user->fcmToken()->save($token);

        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('sysadmin.edit');

    }

    /*  public function rules(): array
     {
         return [
             'firebase_token' => ['required', 'string']
         ];
     }
 */

    public function asController(ActionRequest $request): User
    {
        $this->user = $request->user();
        $this->initialisation($this->user->group, $request);

        return $this->handle($this->user, $this->validatedData);
    }

    public function action(User $user, $modelData): User
    {
        $this->user = $user;
        $this->asAction = true;
        $this->initialisation($user->group, $modelData);

        return $this->handle($user, $this->validatedData);

    }

    public function jsonResponse(User $user): UsersResource
    {
        return new UsersResource($user);
    }
}
