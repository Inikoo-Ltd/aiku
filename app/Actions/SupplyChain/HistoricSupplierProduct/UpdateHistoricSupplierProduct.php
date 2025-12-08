<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 14:28:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\HistoricSupplierProduct;

use App\Actions\GrpAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SupplyChain\HistoricSupplierProduct;

class UpdateHistoricSupplierProduct extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    public function handle(HistoricSupplierProduct $historicSupplierProduct, array $modelData): HistoricSupplierProduct
    {
        return $this->update($historicSupplierProduct, $modelData);
    }

    public function rules(): array
    {
        $rules = [];
        if (! $this->strict) {
            $rules['units_per_pack'] = ['sometimes', 'required', 'numeric'];
            $rules['units_per_carton'] = ['sometimes', 'required', 'numeric'];
            $rules['cbm'] = ['sometimes', 'required', 'numeric'];

            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(HistoricSupplierProduct $historicSupplierProduct, array $modelData, int $hydratorsDelay = 0, bool $strict = true): HistoricSupplierProduct
    {
        $this->strict = $strict;
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisation($historicSupplierProduct->group, $modelData);

        return $this->handle($historicSupplierProduct, $this->validatedData);
    }
}
