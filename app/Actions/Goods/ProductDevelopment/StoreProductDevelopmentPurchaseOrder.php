<?php

/*
 * Author Louis Perez
 * Created on 20-10-2026-15h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopment;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Raises new purchase orders to the development's supplier, one per organisation chosen (a purchase order
 * belongs to one organisation), and links them to the development. Their goods cannot ship before the sample is
 * approved, unless the buyer records why on the order. All or nothing: if one organisation
 * cannot raise its order, none are created and the error says which.
 */
class StoreProductDevelopmentPurchaseOrder extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopment $productDevelopment;
    private User $user;

    /**
     * The organisations the person can raise the order in: those buying from the development's supplier
     * where they may edit procurement.
     *
     * @return Collection<int, OrgSupplier>
     */
    public static function orgSuppliers(ProductDevelopment $productDevelopment, User $user): Collection
    {
        if (!$productDevelopment->supplier_id) {
            return collect();
        }

        return OrgSupplier::query()
            ->where('supplier_id', $productDevelopment->supplier_id)
            ->where('group_id', $productDevelopment->group_id)
            ->with('organisation')
            ->orderBy('organisation_id')
            ->get()
            ->filter(fn (OrgSupplier $orgSupplier) => $user->authTo("procurement.{$orgSupplier->organisation_id}.edit"))
            ->values();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(ProductDevelopment $productDevelopment, User $user): array
    {
        return self::orgSuppliers($productDevelopment, $user)
            ->map(fn (OrgSupplier $orgSupplier) => ['value' => $orgSupplier->id, 'label' => $orgSupplier->organisation->name])
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $request->route('productDevelopment');

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canEdit($request->user(), $productDevelopment);
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function handle(ProductDevelopment $productDevelopment, User $user, array $modelData): Collection
    {
        $orgSuppliers = OrgSupplier::whereIn('id', $modelData['org_supplier_ids'])->with('organisation')->get();

        $purchaseOrders = DB::transaction(fn () => $orgSuppliers->map(function (OrgSupplier $orgSupplier) {
            try {
                return UpdatePurchaseOrder::make()->action(StorePurchaseOrder::make()->action($orgSupplier, []), ['sample_required' => true]);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    'org_supplier_ids' => $orgSupplier->organisation->name.': '.collect($exception->errors())->flatten()->first(),
                ]);
            }
        }));

        $productDevelopment->purchaseOrders()->syncWithoutDetaching($purchaseOrders->pluck('id')->all());

        foreach ($purchaseOrders as $purchaseOrder) {
            RecordProductDevelopmentActivity::record(
                $productDevelopment,
                RecordProductDevelopmentActivity::PURCHASE_ORDER_CREATED,
                [],
                ['purchase_order' => $purchaseOrder->reference]
            );
        }

        return $purchaseOrders;
    }

    public function rules(): array
    {
        return [
            'org_supplier_ids'   => ['required', 'array', 'min:1'],
            'org_supplier_ids.*' => ['integer', 'distinct', Rule::in(self::orgSuppliers($this->productDevelopment, $this->user)->pluck('id')->all())],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'org_supplier_ids.required' => __('Choose at least one organisation'),
            'org_supplier_ids.*.in'     => __('Choose organisations that buy from this supplier'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->productDevelopment->supplier_id) {
            $validator->errors()->add('org_supplier_ids', __('Set the supplier of this development first'));
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): Collection
    {
        $this->productDevelopment = $productDevelopment;
        $this->user               = $request->user();
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $user, array $modelData): Collection
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->user               = $user;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
