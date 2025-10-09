<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Nov 2023 21:15:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect;

use App\Actions\OrgAction;
use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Models\CRM\Prospect;
use Illuminate\Support\Carbon;

class UpdateProspectEmailOpened extends OrgAction
{
    public function handle(Prospect $prospect, Carbon $date): Prospect
    {
        $dataToUpdate = [
            'last_opened_at' => $date
        ];

        if ($prospect->state == ProspectStateEnum::NO_CONTACTED || $prospect->state == ProspectStateEnum::CONTACTED) {
            $dataToUpdate['state'] = ProspectStateEnum::CONTACTED;
            if (in_array(
                $prospect->contacted_state,
                [
                    ProspectContactedStateEnum::NA,
                    ProspectContactedStateEnum::SOFT_BOUNCED,
                    ProspectContactedStateEnum::NEVER_OPEN
                ]
            )) {
                $dataToUpdate['contacted_state'] = ProspectContactedStateEnum::OPEN;
            }
        }

        $prospect = UpdateProspect::run(
            $prospect,
            $dataToUpdate
        );

        return $prospect;
    }

    public function action(Prospect $prospect, Carbon $date): Prospect
    {
        $this->initialisation($prospect->organisation, []);

        return $this->handle($prospect, $date);
    }


}
