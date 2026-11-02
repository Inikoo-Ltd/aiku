<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-13h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Starts the next sample of an in-house SKU, numbered after the last one. A SKU makes one sample at a time.
 */
class StoreProductDevelopmentSample extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const array BATCH_UNITS = ['kg', 'g', 'L', 'ml', 'units'];

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

    public function handle(ProductDevelopmentItem $item, User $user, array $modelData): ProductDevelopmentSample
    {
        /** @var ProductDevelopmentSample $sample */
        $sample = $item->samples()->create([
            'product_development_id' => $item->product_development_id,
            'number'                 => (int) $item->samples()->max('number') + 1,
            'status'                 => ProductDevelopmentSampleStatusEnum::IN_PROGRESS,
            'batch_size'             => $modelData['batch_size'] ?? null,
            'batch_unit'             => $modelData['batch_unit'] ?? null,
            'notes'                  => trim((string) ($modelData['notes'] ?? '')) ?: null,
            'job_order_id'           => $modelData['job_order_id'] ?? null,
            'made_by_id'             => array_key_exists('made_by_id', $modelData) ? $modelData['made_by_id'] : $user->id,
        ]);

        RecordProductDevelopmentActivity::record($item->productDevelopment, RecordProductDevelopmentActivity::SAMPLE_STARTED, [], [
            'sample' => ProductDevelopmentRecipes::label($sample->setRelation('item', $item)),
        ]);

        return $sample;
    }

    public function rules(): array
    {
        return [
            'batch_size'    => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
            'batch_unit'    => ['sometimes', 'nullable', Rule::in(self::BATCH_UNITS)],
            'notes'         => ['sometimes', 'nullable', 'string', 'max:5000'],
            'job_order_id'  => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('job_order_items', 'job_order_id')->where('artefact_id', $this->item->artefact_id),
            ],
            'made_by_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('group_id', $this->item->productDevelopment->group_id)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->item->productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE || !$this->item->artefact_id) {
            $validator->errors()->add('item', __('Trial runs are made for in-house SKUs only'));

            return;
        }

        if ($this->item->samples()->whereIn('status', [ProductDevelopmentSampleStatusEnum::IN_PROGRESS->value, ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW->value])->exists()) {
            $validator->errors()->add('item', __('This SKU already has a trial run in progress or in review'));
        }
    }

    public function asController(ProductDevelopmentItem $productDevelopmentItem, ActionRequest $request): ProductDevelopmentSample
    {
        $this->item = $productDevelopmentItem;
        $this->initialisationFromGroup($productDevelopmentItem->productDevelopment->group, $request);

        return $this->handle($productDevelopmentItem, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentItem $item, User $user, array $modelData): ProductDevelopmentSample
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
