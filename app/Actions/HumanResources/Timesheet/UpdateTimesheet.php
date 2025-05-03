<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 15:53:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\HumanResources\Timesheet;

class UpdateTimesheet extends OrgAction
{
    use WithActionUpdate;


    public function handle(Timesheet $timesheet, array $modelData): Timesheet
    {
        return $this->update($timesheet, $modelData, ['data']);
    }



    public function rules(): array
    {
        $rules = [];
        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(Timesheet $timesheet, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Timesheet
    {
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($timesheet->organisation, $modelData);

        return $this->handle($timesheet, $this->validatedData);
    }


}
