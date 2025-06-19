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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;

class StoreUserAccessToken extends OrgAction
{
    public function handle(User $user): string
    {
        $plainTextToken = $user->createToken(Str::random(6), [])->plainTextToken;

        $tokenParts = explode('|', $plainTextToken);
        $tokenValue = $tokenParts[1] ?? '';

        $tokenPrefix = substr($tokenValue, 0, 3);

        $tokenName = $tokenParts[0].'|'.$tokenPrefix.'...-'.$user->slug;

        if (!empty($tokenPrefix)) {
            DB::table('personal_access_tokens')->where('id', $tokenParts[0])->update([
                 'name' => $tokenName
             ]);
        }

        $user->auditEvent     = 'create';
        $user->isCustomEvent  = true;
        $user->auditCustomOld = [
            'api_token' => ''
        ];
        $user->auditCustomNew = [
            'api_token' => __('Api token created').' ('.$tokenName.')'
        ];

        UserHydrateApiTokens::run($user);
        Event::dispatch(new AuditCustom($user));


        return $plainTextToken;
    }


    public function action(User $user, array $data): string
    {
        $this->initialisationFromGroup($user->group, $data);

        return $this->handle($user);
    }

    public function jsonResponse(string $token): array
    {
        return [
            'token' => $token
        ];
    }

    public function asController(User $user, ActionRequest $request): string
    {
        $this->initialisationFromGroup($user->group, $request);

        return $this->handle($user);
    }
}
