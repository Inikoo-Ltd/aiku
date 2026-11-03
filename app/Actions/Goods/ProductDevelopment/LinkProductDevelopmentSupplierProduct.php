<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-21h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class LinkProductDevelopmentSupplierProduct extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ?ProductDevelopmentItem $item = null;

    private function target(): ProductDevelopmentItem
    {
        return $this->item ?? request()->route('productDevelopmentItem');
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->target()->productDevelopment;
        $access             = $this->productDevelopmentAccess();
        $roles              = $access->memberRoles($request->user(), $productDevelopment);

        return $access->canView($request->user(), $productDevelopment)
            && $access->isOpenForChanges($productDevelopment)
            && ($access->canEdit($request->user(), $productDevelopment)
                || in_array(ProductDevelopmentMemberRoleEnum::AGENT, $roles, true)
                || $access->isAgentOf($request->user(), $productDevelopment));
    }

    /**
     * @throws ValidationException
     */
    public function handle(ProductDevelopmentItem $item, User $user, array $modelData): ProductDevelopmentItem
    {
        $productDevelopment = $item->productDevelopment;

        if ($supplierProductId = Arr::get($modelData, 'supplier_product_id')) {
            $supplierProduct = SupplierProduct::findOrFail($supplierProductId);
            if (!$supplierProduct->tradeUnits()->where('trade_units.id', $item->trade_unit_id)->exists()) {
                SyncSupplierProductTradeUnits::run($supplierProduct, [
                    ...$supplierProduct->tradeUnits()->get()->mapWithKeys(fn ($tradeUnit) => [$tradeUnit->id => ['quantity' => $tradeUnit->pivot->quantity]])->all(),
                    $item->trade_unit_id => ['quantity' => $supplierProduct->units_per_pack ?: 1],
                ]);
            }
        } else {
            $supplierProduct = StoreSupplierProduct::make()->action($productDevelopment->supplier, [
                'code'        => $modelData['code'],
                'name'        => $item->tradeUnit->name ?? $modelData['code'],
                'cost'             => $modelData['cost'],
                'units_per_pack'   => $modelData['units_per_pack'],
                'units_per_carton' => $modelData['units_per_carton'],
                'trade_units'      => [$item->trade_unit_id],
            ]);
        }

        $item->update(['supplier_product_id' => $supplierProduct->id]);

        RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::SUPPLIER_PRODUCT_LINKED, [], [
            'sku'              => $item->tradeUnit->code,
            'supplier_product' => $supplierProduct->code,
            'created'          => !Arr::get($modelData, 'supplier_product_id'),
        ]);

        ProductDevelopmentHydrateChecklist::run($productDevelopment);
        CreateProductDevelopmentTask::make()->syncProgress($productDevelopment, $user);

        return $item->refresh();
    }

    public function rules(): array
    {
        $productDevelopment = $this->target()->productDevelopment;

        return [
            'supplier_product_id' => ['nullable', 'integer', Rule::exists('supplier_products', 'id')->where('supplier_id', $productDevelopment->supplier_id)],
            'code'                => ['required_without:supplier_product_id', 'nullable', 'string', 'max:64'],
            'cost'                => ['required_without:supplier_product_id', 'nullable', 'numeric', 'min:0'],
            'units_per_pack'      => ['required_without:supplier_product_id', 'nullable', 'integer', 'min:1'],
            'units_per_carton'    => ['required_without:supplier_product_id', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->target()->productDevelopment->supplier_id) {
            $validator->errors()->add('supplier_product_id', __('Set the supplier of this development first'));
        }
    }

    /**
     * @throws ValidationException
     */
    public function asController(ProductDevelopmentItem $productDevelopmentItem, ActionRequest $request): ProductDevelopmentItem
    {
        $this->item = $productDevelopmentItem;
        $this->initialisationFromGroup($productDevelopmentItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentItem, $request->user(), $this->validatedData);
    }

    /**
     * @throws ValidationException
     */
    public function action(ProductDevelopmentItem $item, User $user, array $modelData): ProductDevelopmentItem
    {
        $this->asAction = true;
        $this->item     = $item;
        $this->initialisationFromGroup($item->productDevelopment->group, $modelData);

        return $this->handle($item, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
