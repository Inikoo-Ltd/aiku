<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Hydrators;

use App\Models\CRM\WebUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebUserHydrateApiTokens implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(WebUser $webUser): string
    {
        return $webUser->id;
    }

    public function handle(WebUser $webUser): void
    {
        $webUser->update(
            [
                'number_api_tokens' => $webUser->tokens->count()
            ]
        );
    }



}
