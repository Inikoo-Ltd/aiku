<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\CRM\WebUser\Hydrators\WebUserHydrateApiTokens;
use App\Actions\CRM\WebUser\Hydrators\WebUserHydrateAudits;
use App\Actions\CRM\WebUser\Hydrators\WebUserHydrateWebUserRequests;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\CRM\WebUser;

class HydrateWebUser
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:web_users {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = WebUser::class;
    }

    public function handle(WebUser $webUser): void
    {
        WebUserHydrateApiTokens::run($webUser);
        WebUserHydrateAudits::run($webUser);
        WebUserHydrateWebUserRequests::run($webUser->id);
    }

}
