<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 13:31
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Brings an archived supplier back into use. Only the org suppliers DeactivateSupplier switched off are switched
 * back on; a supplier archived before that list was kept brings back all of its org suppliers. A supplier merged
 * into another stays archived: its products, orders and org suppliers now belong to the supplier kept.
 */
class ReactivateSupplier
{
    use AsAction;

    public static function wasMerged(Supplier $supplier): bool
    {
        return Arr::get($supplier->data ?? [], 'merged_into_supplier_id') !== null;
    }

    public function handle(Supplier $supplier): Supplier
    {
        if (self::wasMerged($supplier)) {
            throw ValidationException::withMessages([
                'supplier' => __('This supplier was merged into another supplier and can not be unarchived'),
            ]);
        }

        $data                   = $supplier->data ?? [];
        $archivedOrgSupplierIds = Arr::get($data, 'archived_org_supplier_ids');
        Arr::forget($data, 'archived_org_supplier_ids');

        $supplier->update(['status' => true, 'archived_at' => null, 'data' => $data]);

        $orgSuppliers = $supplier->orgSuppliers()->where('status', false)
            ->when(is_array($archivedOrgSupplierIds), fn ($query) => $query->whereIn('id', $archivedOrgSupplierIds))
            ->get();

        foreach ($orgSuppliers as $orgSupplier) {
            UpdateOrgSupplier::make()->action($orgSupplier, ['status' => true]);
        }

        if ($supplier->agent) {
            AgentHydrateSuppliers::dispatch($supplier->agent);
        }
        GroupHydrateSuppliers::dispatch($supplier->group);

        return $supplier;
    }

    public function authorize(ActionRequest $request): bool
    {
        return DeactivateSupplier::canArchive($request->user(), $request->route('supplier'));
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        abort_if($supplier->status, 422, __('The supplier is not archived'));

        return $this->handle($supplier);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
