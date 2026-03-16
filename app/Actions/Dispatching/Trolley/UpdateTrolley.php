<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:35:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\Trolley;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateTrolley extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithWarehouseEditAuthorisation;

    private Trolley $trolley;

    public function handle(Trolley $trolley, array $modelData): Trolley
    {
        return $this->update($trolley, $modelData);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'max:64',
                $this->strict ? 'alpha_dash' : 'string',
                new IUnique(
                    table: 'trolleys',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->trolley->warehouse_id],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->trolley->id],
                    ]
                ),
            ],
        ];


    }

    public function action(Trolley $trolley, array $modelData, bool $strict = true): Trolley
    {
        $this->asAction = true;
        $this->strict = $strict;
        $this->trolley = $trolley;
        $this->initialisation($trolley->organisation, $modelData);

        return $this->handle($trolley, $this->validatedData);
    }

    public function asController(Trolley $trolley, ActionRequest $request): Trolley
    {
        $this->trolley = $trolley;
        $this->initialisationFromWarehouse($trolley->warehouse, $request);

        return $this->handle($trolley, $this->validatedData);
    }
}
