<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Jun 2025 10:07:40 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Hydrators;

use App\Models\CRM\WebUser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WebUserHydrateWebUserRequests implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'analytics';

    public function getJobUniqueId(int $webUserID): string
    {
        return $webUserID;
    }

    public function handle(int $webUserID): void
    {
        $webUser = WebUser::findOrFail($webUserID);
        $stats = [
            'number_web_user_requests' => $webUser->webUserRequests()->count(),
        ];


        $webUser->stats->update($stats);
    }

}
