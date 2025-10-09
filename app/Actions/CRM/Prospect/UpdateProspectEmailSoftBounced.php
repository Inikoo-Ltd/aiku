<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Nov 2023 22:55:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect;

use App\Actions\OrgAction;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Models\CRM\Prospect;
use Illuminate\Support\Carbon;

class UpdateProspectEmailSoftBounced extends OrgAction
{
    public function handle(Prospect $prospect, Carbon $date): Prospect
    {
        $dataToUpdate = [
            'last_soft_bounced_at' => $date
        ];


        if ($prospect->state == ProspectStateEnum::NO_CONTACTED || $prospect->state == ProspectStateEnum::CONTACTED) {
            $dataToUpdate['state'] = ProspectStateEnum::CONTACTED;

            if ($prospect->contacted_state == ProspectContactedStateEnum::NA) {
                $dataToUpdate['contacted_state'] = ProspectContactedStateEnum::SOFT_BOUNCED;
            }
        }

        return UpdateProspect::run(
            $prospect,
            $dataToUpdate
        );
    }

    public function action(Prospect $prospect, Carbon $date): Prospect
    {
        $this->initialisation($prospect->organisation, []);

        return $this->handle($prospect, $date);
    }
}
