<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Feb 2025 17:10:31 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class CanVisit extends OrgAction
{
    public function handle(User $user, array $modelData): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'route_name' => ['sometimes', 'string'],
            'route_parameters' => ['sometimes', 'array'],

        ];
    }

    public function asController(ActionRequest $request): bool
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($request->user(), $this->validatedData);
    }
}
