<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\Procurement\OrgSupplier\UpdateOrgSupplier;
use App\Actions\SupplyChain\Agent\Hydrators\AgentHydrateSuppliers;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSuppliers;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Takes a supplier out of use in every organisation, once nothing is still being ordered from it. The record
 * and its history stay, and its products are left as they are. The org suppliers switched off here are kept
 * in data.archived_org_supplier_ids so ReactivateSupplier switches back on only those.
 */
class DeactivateSupplier
{
    use AsAction;

    public function handle(Supplier $supplier): Supplier
    {
        $openOrders = $this->openPurchaseOrderReferences($supplier);

        if ($openOrders->isNotEmpty()) {
            throw ValidationException::withMessages([
                'supplier' => __('The supplier still has open purchase orders: :references', ['references' => $openOrders->implode(', ')]),
            ]);
        }

        $activeOrgSuppliers = $supplier->orgSuppliers()->where('status', true)->get();

        $data = $supplier->data ?? [];
        Arr::set($data, 'archived_org_supplier_ids', $activeOrgSuppliers->pluck('id')->all());

        $supplier->update(['status' => false, 'archived_at' => now(), 'data' => $data]);

        foreach ($activeOrgSuppliers as $orgSupplier) {
            UpdateOrgSupplier::make()->action($orgSupplier, ['status' => false]);
        }

        if ($supplier->agent) {
            AgentHydrateSuppliers::dispatch($supplier->agent);
        }
        GroupHydrateSuppliers::dispatch($supplier->group);

        return $supplier;
    }

    /**
     * @return Collection<int, string>
     */
    public function openPurchaseOrderReferences(Supplier $supplier): Collection
    {
        return $supplier->purchaseOrders()
            ->whereIn('state', PurchaseOrderStateEnum::live())
            ->pluck('reference');
    }

    /**
     * Group admins, and the org admins of any organisation that buys from the supplier.
     */
    public static function canArchive(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole(RolesEnum::GROUP_ADMIN->value)) {
            return true;
        }

        return $supplier->orgSuppliers()->pluck('organisation_id')
            ->contains(fn (int $organisationId) => $user->authTo("org-admin.$organisationId"));
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canArchive($request->user(), $request->route('supplier'));
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        abort_unless($supplier->status, 422, __('The supplier is already archived'));

        return $this->handle($supplier);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
