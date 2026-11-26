<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\UI;

use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectLegacyStaffChat
{
    use AsAction;

    /**
     * Verba moved from /chat/staff to /vx; old bookmarks, notification links and synced links land on the same page.
     */
    public function asController(ActionRequest $request): RedirectResponse
    {
        $path = trim((string) $request->route('path'), '/');
        $query = $request->getQueryString();

        return redirect()->to(url('/vx'.($path !== '' ? '/'.$path : '').($query ? '?'.$query : '')), 301);
    }
}
