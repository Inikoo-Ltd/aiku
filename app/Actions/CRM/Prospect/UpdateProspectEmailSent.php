<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Nov 2023 21:21:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect;

use App\Actions\OrgAction;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Models\CRM\Prospect;

class UpdateProspectEmailSent extends OrgAction
{
    public function handle(Prospect $prospect): Prospect
    {
        if ($prospect->state == ProspectStateEnum::NO_CONTACTED || $prospect->state == ProspectStateEnum::CONTACTED) {
            $dataToUpdate = [
                'state' => ProspectStateEnum::CONTACTED,
                'last_contacted_at' => now()
            ];

            if (!$prospect->contacted_at) {
                $dataToUpdate['contacted_at'] = now();
            }
            if (in_array(
                $prospect->contacted_state,
                [
                    ProspectContactedStateEnum::NA,
                    ProspectContactedStateEnum::SOFT_BOUNCED,
                ]
            )) {
                $dataToUpdate['contacted_state'] = ProspectContactedStateEnum::NEVER_OPEN;
            }


            $prospect = UpdateProspect::run($prospect, $dataToUpdate);
        }

        return $prospect;
    }

    public function action(Prospect $prospect): Prospect
    {
        $this->initialisation($prospect->organisation, []);

        return $this->handle($prospect);
    }
}
