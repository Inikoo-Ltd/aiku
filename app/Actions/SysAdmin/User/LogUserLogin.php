<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Oct 2023 12:26:29 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Traits\WithLogUserableLogin;
use App\Enums\Elasticsearch\ElasticsearchUserRequestTypeEnum;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class LogUserLogin
{
    use AsAction;
    use WithLogUserableLogin;

    public function handle(User $user, string $ip, string $userAgent, Carbon $datetime): void
    {
        $this->logUserableLogin(
            config('elasticsearch.index_prefix').'web_users_requests',
            ElasticsearchUserRequestTypeEnum::LOGIN->value,
            $datetime,
            $ip,
            $userAgent,
            $user
        );

        $stats = [
            'last_login_at' => $datetime,
            'last_login_ip' => $ip,
            'number_logins' => $user->stats->number_logins + 1,
        ];

        $user->stats()->update($stats);

    }
}
