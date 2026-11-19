<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFormulationTypeEnum;
use App\Models\Goods\ProductDevelopmentFormulation;
use App\Models\Goods\ProductDevelopmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Adds a named formulation stage after the SKU's last one (e.g. the finished product after the essential oil
 * blend), with the artisan the project manager proposes for its trial runs.
 */
class StoreProductDevelopmentFormulation extends OrgAction
{
    use WithProductDevelopmentAuthorisation;
    use WithProductDevelopmentFormulationRules;

    private ProductDevelopmentItem $item;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->item->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canEdit($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentItem $item, array $modelData): ProductDevelopmentFormulation
    {
        /** @var ProductDevelopmentFormulation $formulation */
        $formulation = DB::transaction(function () use ($item, $modelData) {
            ProductDevelopmentItem::whereKey($item->id)->lockForUpdate()->first();
            $type = ProductDevelopmentFormulationTypeEnum::from($modelData['type'] ?? $this->defaultType($item)->value);

            return $item->formulations()->create([
                'product_development_id' => $item->product_development_id,
                'position'               => $this->positionFor($item, $type),
                'name'                   => trim($modelData['name']),
                'artisan_id'             => $modelData['artisan_id'] ?? null,
                'type'                   => $type->value,
                'brief'                  => trim((string) ($modelData['brief'] ?? '')) ?: null,
            ]);
        });

        RecordProductDevelopmentActivity::record($item->productDevelopment, RecordProductDevelopmentActivity::FORMULATION_ADDED, [], [
            'sku'         => $item->tradeUnit->code,
            'formulation' => $formulation->name,
        ]);

        return $formulation;
    }

    public function rules(): array
    {
        return $this->formulationRules($this->item->productDevelopment, true);
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentRecipes::canChangeFormulations($this->item->productDevelopment)) {
            $validator->errors()->add('name', __('Stages can only be added to an in-house development while it is formulating'));

            return;
        }

        $this->validateFormulationType($validator, $this->item, null, $this->get('type'));
    }

    /**
     * The finished product is always the last stage: a blend or intermediate added later goes in before it, and
     * the stages after it move down one.
     */
    private function positionFor(ProductDevelopmentItem $item, ProductDevelopmentFormulationTypeEnum $type): int
    {
        $finished = $item->formulations()->where('type', ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value)->first();
        if (!$type->hasOwnArtefact() || !$finished) {
            return (int) $item->formulations()->max('position') + 1;
        }

        $item->formulations()->reorder()->where('position', '>=', $finished->position)->orderByDesc('position')->get()
            ->each(fn (ProductDevelopmentFormulation $formulation) => $formulation->update(['position' => $formulation->position + 1]));

        return $finished->position;
    }

    /**
     * A new stage is the finished product when the SKU has none yet, otherwise an intermediate formulation.
     */
    private function defaultType(ProductDevelopmentItem $item): ProductDevelopmentFormulationTypeEnum
    {
        return $item->formulations()->where('type', ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value)->exists()
            ? ProductDevelopmentFormulationTypeEnum::INTERMEDIATE
            : ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT;
    }

    public function asController(ProductDevelopmentItem $productDevelopmentItem, ActionRequest $request): ProductDevelopmentFormulation
    {
        $this->item = $productDevelopmentItem;
        $this->initialisationFromGroup($productDevelopmentItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentItem, $this->validatedData);
    }

    public function action(ProductDevelopmentItem $item, array $modelData): ProductDevelopmentFormulation
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
