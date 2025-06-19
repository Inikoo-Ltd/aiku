<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\User;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateApiTokens;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Events\AuditCustom;

class DeleteUserAccessToken extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PersonalAccessToken $token): void
    {
        $tokenable = $token->tokenable;
        $token->delete();

        if ($tokenable instanceof User) {
            $user                 = $tokenable;
            $user->auditEvent     = 'delete';
            $user->isCustomEvent  = true;
            $user->auditCustomOld = [
                'api_token' => $token->name
            ];
            $user->auditCustomNew = [
                'api_token' => __('Api token deleted')
            ];
            UserHydrateApiTokens::dispatch($user);
            Event::dispatch(new AuditCustom($user));
        }
    }

    public function action(PersonalAccessToken $token, array $modelData): void
    {
        $this->initialisationFromGroup($token->tokenable->group, $modelData);

        $this->handle($token);
    }

    public function asController(PersonalAccessToken $token, ActionRequest $request)
    {
        $this->initialisationFromGroup($token->tokenable->group, $request);
        $this->handle($token);
    }
}
