<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreProfileApiToken extends OrgAction
{
    use AsAction;

    public function handle(User $user, string $name): array
    {
        return [
            'token' => $user->createToken($name)->plainTextToken
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64']
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($request->user(), $this->get('name'));
    }
}
