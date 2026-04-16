<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Oct 2023 12:34:29 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\SysAdmin\WithLogRequest;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogWebUserFailLogin
{
    use AsAction;
    use WithLogRequest;

    public function handle(Website $website, array $credentials, string $ip, string $userAgent, Carbon $datetime): void
    {

        $webUser = WebUser::withTrashed()->where('username', Arr::get($credentials, 'username'))->where('website_id', $website->id)->first();

        $this->logFail($datetime, $ip, $userAgent, Arr::get($credentials, 'username'), $webUser);


        if ($webUser) {
            $stats = [
                'number_failed_logins' => $webUser->stats->number_failed_logins + 1,
                'last_failed_login_ip' => $ip,
                'last_failed_login_at' => $datetime
            ];

            $webUser->stats()->update($stats);
        }
    }



}
