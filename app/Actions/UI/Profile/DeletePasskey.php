<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeletePasskey
{
    use AsController;

    public function handle(ActionRequest $request, int $passkey): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->passkeys()->where('id', $passkey)->delete();

        return redirect()->back();
    }

    public function asController(ActionRequest $request, int $passkey): RedirectResponse
    {
        return $this->handle($request, $passkey);
    }
}
