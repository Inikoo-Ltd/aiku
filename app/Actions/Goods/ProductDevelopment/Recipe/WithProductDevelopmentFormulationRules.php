<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFormulationTypeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentFormulation;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\HumanResources\Employee;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait WithProductDevelopmentFormulationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function formulationRules(ProductDevelopment $productDevelopment, bool $nameRequired): array
    {
        return [
            'name'       => [$nameRequired ? 'required' : 'sometimes', 'string', 'max:120'],
            'artisan_id' => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $productDevelopment->production?->organisation_id ?? 0)],
            'type'       => ['sometimes', Rule::enum(ProductDevelopmentFormulationTypeEnum::class)],
            'brief'      => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * A SKU has at most one finished product stage, and a stage whose trial run is approved keeps its type: an
     * approved blend is already an ingredient of the finished product. A trial run being made keeps the artefact
     * it was raised on (HELP-4838), so a stage can turn into a blend meanwhile; it becomes the finished product
     * only when that run is on the SKU's artefact.
     */
    protected function validateFormulationType(Validator $validator, ProductDevelopmentItem $item, ?ProductDevelopmentFormulation $formulation, ?string $type): void
    {
        if ($type === null || $type === $formulation?->type->value) {
            return;
        }

        if (!ProductDevelopmentRecipes::canChangeFormulations($item->productDevelopment)) {
            $validator->errors()->add('type', __('A stage changes type only while the development is formulating'));

            return;
        }

        if ($type === ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value && $formulation && $formulation->samples()
            ->whereIn('status', [ProductDevelopmentSampleStatusEnum::IN_PROGRESS->value, ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW->value])
            ->where(fn ($query) => $query->whereNull('artefact_id')->orWhere('artefact_id', '!=', $item->artefact_id ?? 0))
            ->exists()) {
            $validator->errors()->add('type', __('This stage has a trial run being made on its own artefact: finish it before making it the finished product'));

            return;
        }

        if ($formulation && $formulation->samples()->where('status', ProductDevelopmentSampleStatusEnum::APPROVED->value)->exists()) {
            $validator->errors()->add('type', __('This stage has an approved trial run: its type can no longer change'));

            return;
        }

        if ($type === ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value && $formulation && $formulation->position < (int) $item->formulations()->max('position')) {
            $validator->errors()->add('type', __('The finished product is the last stage: only the last stage can be it'));

            return;
        }

        if ($type === ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value) {
            $finished = $item->formulations()->where('type', ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value)
                ->when($formulation, fn ($query) => $query->where('id', '!=', $formulation->id))
                ->first();
            if ($finished) {
                $validator->errors()->add('type', __('Stage :position (:name) is already the finished product: change its type first', ['position' => $finished->position, 'name' => $finished->name]));
            }
        }
    }

    /**
     * The working artisans of the development's factory, to propose one for a formulation stage.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function artisanOptions(ProductDevelopment $productDevelopment): array
    {
        $organisationId = $productDevelopment->production?->organisation_id;
        if (!$organisationId) {
            return [];
        }

        return Employee::where('organisation_id', $organisationId)
            ->where('state', EmployeeStateEnum::WORKING)
            ->orderBy('contact_name')
            ->get(['id', 'contact_name'])
            ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->contact_name])
            ->all();
    }
}
