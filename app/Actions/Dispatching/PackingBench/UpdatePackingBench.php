<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PackingBench;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\PackingBench;
use App\Models\Inventory\Warehouse;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdatePackingBench extends OrgAction
{
    use WithActionUpdate;
    use WithWarehouseEditAuthorisation;

    private PackingBench $packingBench;

    public function handle(PackingBench $packingBench, array $modelData): PackingBench
    {
        return $this->update($packingBench, $modelData);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'required',
                'max:64',
                'alpha_dash',
                new IUnique(
                    table: 'packing_benches',
                    extraConditions: [
                        ['column' => 'warehouse_id', 'value' => $this->packingBench->warehouse_id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'operator' => '!=', 'value' => $this->packingBench->id],
                    ]
                ),
            ],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Warehouse $warehouse, PackingBench $packingBench, ActionRequest $request): PackingBench
    {
        $this->packingBench = $packingBench;
        $this->initialisationFromWarehouse($packingBench->warehouse, $request);

        return $this->handle($packingBench, $this->validatedData);
    }
}
