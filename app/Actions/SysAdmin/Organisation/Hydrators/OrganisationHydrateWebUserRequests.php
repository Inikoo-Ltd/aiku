<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Jun 2025 09:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateWebUserRequests implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $organisationID): string
    {
        return $organisationID;
    }

    public function handle(int $organisationID): void
    {
        $organisation = Organisation::findOrFail($organisationID);
        $stats        = [
            'number_web_user_requests' => $organisation->webUserRequests()->count(),
        ];


        $organisation->webStats->update($stats);
    }


}
