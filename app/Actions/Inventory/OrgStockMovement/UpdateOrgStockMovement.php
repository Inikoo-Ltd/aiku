<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Sept 2024 13:22:58 Taipei Standard Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockMovement;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Inventory\OrgStockMovement;

class UpdateOrgStockMovement extends OrgAction
{
    use WithActionUpdate;


    public function handle(OrgStockMovement $orgStockMovement, array $modelData): OrgStockMovement
    {
        return $this->update($orgStockMovement, $modelData, ['data']);
    }


    public function rules(): array
    {
        $rules = [];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['note']            = ['sometimes', 'nullable', 'string', 'max:1024'];
        }

        return $rules;
    }

    public function action(OrgStockMovement $orgStockMovement, array $modelData, int $hydratorsDelay = 0, bool $strict = true): OrgStockMovement
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($orgStockMovement->organisation, $modelData);

        return $this->handle($orgStockMovement, $this->validatedData);
    }


}
