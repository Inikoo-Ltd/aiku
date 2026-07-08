<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowPasskey
{
    use AsController;

    public function handle(ActionRequest $request): Response
    {
        $user = $request->user();

        return Inertia::render('SysAdmin/Passkey', [
            'title'    => __('Passkeys'),
            'pageHead' => [
                'title' => __('Passkeys'),
            ],
            'passkeys' => [
                'value'       => $user->passkeys()->orderByDesc('id')->get(['id', 'name', 'last_used_at']),
                'deleteRoute' => [
                    'name'   => 'grp.profile.passkey.delete',
                    'method' => 'delete',
                ],
            ],
        ]);
    }
}
