<?php

/*
 * Author Louis Perez
 * Created on 23-10-2026-16h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Marks a SKU's compliance text field "Include on label", or takes it off. Only the fields marked go to
 * the Label & Packaging view and to the designers.
 */
class UpdateProductDevelopmentChecklistItemOnLabel extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentChecklistItem $checklistItem;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->checklistItem->productDevelopment)
            && $this->productDevelopmentAccess()->canEditChecklistItem($request->user(), $this->checklistItem);
    }

    public function handle(ProductDevelopmentChecklistItem $checklistItem, array $modelData): ProductDevelopmentItem
    {
        $item  = $checklistItem->item;
        $field = self::labelField($checklistItem);

        $onLabel = collect(GetProductDevelopmentLabelContent::onLabel($item))
            ->reject(fn (string $key) => $key === $field)
            ->when($modelData['include'], fn ($fields) => $fields->push($field))
            ->sort()
            ->values()
            ->all();

        $item->update(['label_fields' => $onLabel]);

        return $item;
    }

    private static function labelField(ProductDevelopmentChecklistItem $checklistItem): ?string
    {
        return ProductDevelopmentTargetFieldEnum::tryFrom((string) $checklistItem->target_field)?->labelField();
    }

    public function rules(): array
    {
        return [
            'include' => ['required', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->checklistItem->product_development_item_id || !self::labelField($this->checklistItem)) {
            $validator->errors()->add('include', __('This field does not go on the label'));
        }
    }

    public function asController(ProductDevelopmentChecklistItem $productDevelopmentChecklistItem, ActionRequest $request): ProductDevelopmentItem
    {
        $this->checklistItem = $productDevelopmentChecklistItem;
        $this->initialisationFromGroup($productDevelopmentChecklistItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentChecklistItem, $this->validatedData);
    }

    public function action(ProductDevelopmentChecklistItem $checklistItem, array $modelData): ProductDevelopmentItem
    {
        $this->asAction      = true;
        $this->checklistItem = $checklistItem;
        $this->initialisationFromGroup($checklistItem->productDevelopment->group, $modelData);

        return $this->handle($checklistItem, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
