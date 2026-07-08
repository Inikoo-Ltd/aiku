<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;

class GeneratePasskeyRegisterOptions
{
    use AsController;

    public function handle(ActionRequest $request): string
    {
        /** @var User $user */
        $user = $request->user();

        return app(GeneratePasskeyRegisterOptionsAction::class)->execute($user);
    }

    public function asController(ActionRequest $request): string
    {
        return $this->handle($request);
    }
}
