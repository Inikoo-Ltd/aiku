<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-16h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\Goods\ProductDevelopmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Adds a packaging item or other component to an in-house SKU, with the quantity needed to start production.
 * It waits for approval before purchasing can order it.
 */
class StoreProductDevelopmentComponent extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentItem $item;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->item->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canManageSamples($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentItem $item, array $modelData): ProductDevelopmentComponent
    {
        /** @var ProductDevelopmentComponent $component */
        $component = $item->components()->create([
            'product_development_id' => $item->product_development_id,
            'trade_unit_id'          => $modelData['trade_unit_id'],
            'quantity'               => $modelData['quantity'],
            'note'                   => trim((string) ($modelData['note'] ?? '')) ?: null,
            'status'                 => ProductDevelopmentComponentStatusEnum::PENDING,
        ]);

        RecordProductDevelopmentActivity::record($item->productDevelopment, RecordProductDevelopmentActivity::COMPONENT_ADDED, [], [
            'component' => $component->tradeUnit->code,
            'sku'       => $item->tradeUnit->code,
            'quantity'  => (float) $component->quantity,
        ]);

        return $component;
    }

    public function rules(): array
    {
        return [
            'trade_unit_id' => ['required', 'integer', Rule::exists('trade_units', 'id')->where('group_id', $this->item->productDevelopment->group_id), Rule::notIn([$this->item->trade_unit_id]), Rule::unique('product_development_components', 'trade_unit_id')->where('product_development_item_id', $this->item->id)],
            'quantity'      => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'note'          => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'trade_unit_id.unique' => __('This component is already on the SKU'),
            'trade_unit_id.not_in' => __('A SKU cannot be its own component'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->item->productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE) {
            $validator->errors()->add('trade_unit_id', __('Components are for in-house developments only'));
        }
    }

    public function asController(ProductDevelopmentItem $productDevelopmentItem, ActionRequest $request): ProductDevelopmentComponent
    {
        $this->item = $productDevelopmentItem;
        $this->initialisationFromGroup($productDevelopmentItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentItem, $this->validatedData);
    }

    public function action(ProductDevelopmentItem $item, array $modelData): ProductDevelopmentComponent
    {
        $this->asAction = true;
        $this->item     = $item;
        $this->initialisationFromGroup($item->productDevelopment->group, $modelData);

        return $this->handle($item, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
