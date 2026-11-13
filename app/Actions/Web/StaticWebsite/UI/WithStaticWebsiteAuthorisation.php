<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use Lorisleiva\Actions\ActionRequest;

/**
 * Info websites live under the group's marketing, so anyone who may see marketing for a shop of the
 * group, or the group's webmasters, may look at them.
 */
trait WithStaticWebsiteAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        $user = $request->user();

        if ($user->authTo('group-webmaster.view')) {
            return true;
        }

        foreach ($this->group->shops()->pluck('id') as $shopId) {
            if ($user->authTo("marketing.$shopId.view")) {
                return true;
            }
        }

        return false;
    }
}
