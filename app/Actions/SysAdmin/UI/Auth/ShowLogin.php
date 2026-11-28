<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI\Auth;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Lorisleiva\Actions\Concerns\AsController;

class ShowLogin
{
    use AsController;


    public function handle(): Response
    {
        return Inertia::render('SysAdmin/Login', [
            'connectingAssistant' => $this->connectingAssistant(),
        ]);
    }

    private function connectingAssistant(): ?string
    {
        $intended = (string) session('url.intended');
        if (parse_url($intended, PHP_URL_PATH) !== '/oauth/authorize') {
            return null;
        }

        parse_str((string) parse_url($intended, PHP_URL_QUERY), $query);
        $clientId = (string) ($query['client_id'] ?? '');

        return (Str::isUuid($clientId) ? Client::query()->whereKey($clientId)->value('name') : null) ?? __('your AI assistant');
    }

}
