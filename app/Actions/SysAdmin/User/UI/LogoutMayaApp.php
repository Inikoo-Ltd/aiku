<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Dec 2023 17:06:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class LogoutMayaApp
{
    use AsAction;
    use WithAttributes;

    private bool $asAction = false;

    public function handle(User $user): ?bool
    {
        return $user->currentAccessToken()->delete();
    }

    public function asController(ActionRequest $request): ?bool
    {
        return $this->handle($request->user());
    }
}
