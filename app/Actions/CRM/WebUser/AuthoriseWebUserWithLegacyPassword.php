<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 11:46:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\CRM\WebUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthoriseWebUserWithLegacyPassword
{
    use AsAction;

    public function handle(WebUser $webUser, array $credentials): bool
    {

        $legacyPassword =  Arr::get($webUser->data, 'legacy_password');
        if (!$legacyPassword) {
            return false;
        }

        if (is_null($plain = $credentials['password'])) {
            return false;
        }

        if ($webUser->auth_type != WebUserAuthTypeEnum::AURORA) {
            return false;
        }

        if (!$webUser->status) {
            return false;
        }


        if (hash('sha256', $plain) == $legacyPassword) {
            $webUser = UpdateWebUser::run(
                $webUser,
                [
                    'password'        => $plain,
                    'auth_type'       => UserAuthTypeEnum::DEFAULT
                ]
            );
            $data = $webUser->data;
            Arr::forget($data, 'legacy_password');
            $webUser->data = $data;
            $webUser->save();

            return true;
        }

        return false;
    }

}
