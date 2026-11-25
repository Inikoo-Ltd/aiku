<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Repairs products filed under the catch-all AWChina records, which hold products of many real suppliers.
 * A product whose code the right supplier does not have yet moves there with its history. When the right
 * supplier already has that code, the duplicate under AWChina is retired: its SKOs are bought from the right
 * supplier's product instead, which takes over as the preferred source where the duplicate was.
 *
 * Temporary: it exists only to empty those two records, and goes once they are empty.
 */
class RepairSupplierProductsSupplier extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public const array REPAIRABLE_SUPPLIER_CODES = ['AWC', 'AWC2'];

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function handle(Supplier $supplier, Collection $supplierProducts): array
    {
        $this->assertCanRepair($supplier, $supplierProducts);

        $mover           = MoveSupplierProductsToSupplier::make();
        $sourceSuppliers = Supplier::whereIn('id', $supplierProducts->pluck('supplier_id')->unique())->get();
        $orgSuppliers    = collect();
        $repaired        = ['moved' => 0, 'merged' => 0];

        DB::transaction(function () use ($supplier, $supplierProducts, $orgSuppliers, $mover, &$repaired) {
            foreach ($supplierProducts as $supplierProduct) {
                $twin = $supplier->supplierProducts()->whereRaw('lower(code) = ?', [mb_strtolower($supplierProduct->code)])->first();

                if ($twin) {
                    $mover->mergeIntoTwin($supplierProduct, $twin);
                    $repaired['merged']++;
                } else {
                    $mover->moveToSupplier($supplierProduct, $supplier, $orgSuppliers);
                    $repaired['moved']++;
                }
            }
        });

        $mover->hydrateSuppliers($sourceSuppliers->push($supplier), $orgSuppliers);

        return $repaired;
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     */
    private function assertCanRepair(Supplier $supplier, Collection $supplierProducts): void
    {
        $message = match (true) {
            $supplierProducts->isEmpty() => __('Choose the products to repair.'),
            !$supplier->agent_id => __('Products can only go to a supplier of the same agent.'),
            in_array($supplier->code, self::REPAIRABLE_SUPPLIER_CODES, true) => __('Choose the real supplier of these products.'),
            $supplierProducts->contains(fn (SupplierProduct $supplierProduct) => !in_array($supplierProduct->supplier?->code, self::REPAIRABLE_SUPPLIER_CODES, true)) => __('Only products under :suppliers can be repaired.', ['suppliers' => implode(', ', self::REPAIRABLE_SUPPLIER_CODES)]),
            $supplierProducts->contains(fn (SupplierProduct $supplierProduct) => $supplierProduct->agent_id !== $supplier->agent_id) => __('Products can only go to a supplier of the same agent.'),
            default => MoveSupplierProductsToSupplier::make()->onAgreedPurchaseOrders($supplierProducts),
        };

        if ($message) {
            throw ValidationException::withMessages(['supplier_product_ids' => $message]);
        }
    }

    /**
     * The agent's own organisation, or one that buys through that agent.
     */
    public static function canRepairIn(Organisation $organisation, Supplier $supplier): bool
    {
        return $supplier->agent_id
            && ($organisation->agent?->id === $supplier->agent_id
                || OrgAgent::where('organisation_id', $organisation->id)->where('agent_id', $supplier->agent_id)->exists());
    }

    public function rules(): array
    {
        return [
            'supplier_product_ids'   => ['required', 'array', 'min:1'],
            'supplier_product_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, Supplier $supplier, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        abort_unless(self::canRepairIn($organisation, $supplier), 404);

        return $this->handle(
            $supplier,
            SupplierProduct::where('agent_id', $supplier->agent_id)
                ->whereIn('id', $this->validatedData['supplier_product_ids'])
                ->with('supplier', 'orgSupplierProducts.orgSupplier')
                ->get()
        );
    }

    /**
     * @param  Collection<int, SupplierProduct>  $supplierProducts
     * @return array{moved: int, merged: int}
     *
     * @throws \Throwable
     */
    public function action(Supplier $supplier, Collection $supplierProducts): array
    {
        $this->asAction = true;

        return $this->handle($supplier, $supplierProducts);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
