<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-16h-50m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Models\Goods\ProductDevelopmentComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Changes a component's quantity or note, or replaces it with another one. A replaced component goes back
 * to pending approval, so the new one is approved before purchasing orders it.
 */
class UpdateProductDevelopmentComponent extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentComponent $component;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->component->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canManageSamples($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentComponent $component, array $modelData): ProductDevelopmentComponent
    {
        $old = ['component' => $component->tradeUnit->code, 'quantity' => (float) $component->quantity];

        if (array_key_exists('note', $modelData)) {
            $modelData['note'] = trim((string) $modelData['note']) ?: null;
        }
        if (isset($modelData['trade_unit_id']) && (int) $modelData['trade_unit_id'] !== $component->trade_unit_id) {
            $modelData = [...$modelData, 'status' => ProductDevelopmentComponentStatusEnum::PENDING, 'approved_by_id' => null, 'approved_at' => null];
        }
        $component->update($modelData);
        $component->refresh();

        RecordProductDevelopmentActivity::record($component->productDevelopment, RecordProductDevelopmentActivity::COMPONENT_CHANGED, $old, [
            'component' => $component->tradeUnit->code,
            'sku'       => $component->item->tradeUnit->code,
            'quantity'  => (float) $component->quantity,
        ]);

        return $component;
    }

    public function rules(): array
    {
        $item = $this->component->item;

        return [
            'trade_unit_id' => ['sometimes', 'integer', Rule::exists('trade_units', 'id')->where('group_id', $this->component->productDevelopment->group_id), Rule::notIn([$item->trade_unit_id]), Rule::unique('product_development_components', 'trade_unit_id')->where('product_development_item_id', $item->id)->ignore($this->component->id)],
            'quantity'      => ['sometimes', 'numeric', 'gt:0', 'max:9999999'],
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

    public function asController(ProductDevelopmentComponent $productDevelopmentComponent, ActionRequest $request): ProductDevelopmentComponent
    {
        $this->component = $productDevelopmentComponent;
        $this->initialisationFromGroup($productDevelopmentComponent->productDevelopment->group, $request);

        return $this->handle($productDevelopmentComponent, $this->validatedData);
    }

    public function action(ProductDevelopmentComponent $component, array $modelData): ProductDevelopmentComponent
    {
        $this->asAction  = true;
        $this->component = $component;
        $this->initialisationFromGroup($component->productDevelopment->group, $modelData);

        return $this->handle($component, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
