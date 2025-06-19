<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Sept 2024 17:14:21 Malaysia Time, Cyberjaya, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\SysAdmin\User\Hydrators\UserHydrateApiTokens;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateAudits;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateModels;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\SysAdmin\User;

class HydrateUser
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:users';

    public function __construct()
    {
        $this->model = User::class;
    }

    public function handle(User $user): void
    {
        UserHydrateAudits::run($user);
        SetUserAuthorisedModels::run($user);
        UserHydrateModels::run($user);
        UserHydrateApiTokens::run($user);
    }

}
