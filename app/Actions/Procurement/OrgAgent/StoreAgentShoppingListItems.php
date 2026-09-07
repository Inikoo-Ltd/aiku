<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 31 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent;

use App\Actions\OrgAction;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreAgentShoppingListItems extends OrgAction
{
    use WithProcurementEditAuthorisation;

    /**
     * @return array{added: int}
     */
    public function handle(OrgAgent $orgAgent, array $modelData): array
    {
        $orgSupplierProducts = OrgSupplierProduct::query()
            ->where('org_agent_id', $orgAgent->id)
            ->whereIn('id', collect($modelData['lines'])->pluck('org_supplier_product_id'))
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($modelData, $orgSupplierProducts) {
            $added = 0;

            foreach ($modelData['lines'] as $line) {
                $orgSupplierProduct = $orgSupplierProducts->get($line['org_supplier_product_id']);

                if (!$orgSupplierProduct) {
                    continue;
                }

                StoreShoppingListItem::make()->action($orgSupplierProduct, [
                    'quantity_units' => $line['quantity_units'],
                    'notes'          => $line['notes'] ?? null,
                ]);

                $added++;
            }

            return ['added' => $added];
        });
    }

    public function rules(): array
    {
        return [
            'lines'                          => ['required', 'array', 'min:1'],
            'lines.*.org_supplier_product_id' => ['required', 'integer'],
            'lines.*.quantity_units'          => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes'                   => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent, $this->validatedData);
    }
}
