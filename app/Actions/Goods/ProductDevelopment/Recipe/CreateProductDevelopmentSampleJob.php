<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-14h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\StoreTrialRunJobOrder;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Asks the factory for trial runs of one or more in-house SKUs: one draft trial-run job order per SKU, which
 * a production manager assigns and releases to the floor. Each SKU gets its next trial run record, in the
 * formulation stage being worked on, linked to its job order; the stage's proposed artisan goes with the job
 * for the production manager to accept or change.
 */
class CreateProductDevelopmentSampleJob extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const string EVENT = 'sample_job';

    private ProductDevelopment $productDevelopment;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->productDevelopment)
            && $this->productDevelopmentAccess()->canEdit($request->user(), $this->productDevelopment);
    }

    /**
     * @return array<int, JobOrder>
     */
    public function handle(ProductDevelopment $productDevelopment, User $requester, array $modelData): array
    {
        $items = $productDevelopment->items()->with(['tradeUnit', 'artefact'])->whereIn('id', $modelData['item_ids'])->orderBy('position')->get();
        $notes = trim((string) ($modelData['instructions'] ?? ''));

        return DB::transaction(function () use ($productDevelopment, $requester, $modelData, $items, $notes) {
            $jobOrders = [];
            foreach ($items as $item) {
                $open        = $item->samples()->whereIn('status', [ProductDevelopmentSampleStatusEnum::IN_PROGRESS->value, ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW->value])->first();
                $formulation = $open?->formulation ?? ProductDevelopmentRecipes::currentFormulation($item);
                $artefact    = $open?->workingArtefact() ?? ProductDevelopmentRecipes::stageArtefact($formulation);

                $jobOrder = StoreTrialRunJobOrder::make()->action($productDevelopment->production, [
                    'artefact_id'          => $artefact->id,
                    'quantity'             => (int) $modelData['quantity'],
                    'notes'                => trim($productDevelopment->code.' '.$formulation->name.($notes !== '' ? ': '.$notes : '')),
                    'product_development'  => $productDevelopment->code,
                    'proposed_employee_id' => $formulation->artisan_id,
                ]);

                if ($open) {
                    $open->update(['job_order_id' => $jobOrder->id, 'made_by_id' => null]);
                } else {
                    StoreProductDevelopmentSample::make()->action($item, $requester, ['job_order_id' => $jobOrder->id, 'made_by_id' => null, 'product_development_formulation_id' => $formulation->id]);
                }

                $jobOrders[] = $jobOrder;
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::SAMPLE_JOB_CREATED, [], [
                'skus'         => $items->map(fn (ProductDevelopmentItem $item) => $item->tradeUnit->code)->all(),
                'instructions' => $notes ?: null,
            ]);

            return $jobOrders;
        });
    }

    public function rules(): array
    {
        $productDevelopmentId = $this->productDevelopment->id;

        return [
            'item_ids'     => ['required', 'array', 'min:1'],
            'item_ids.*'   => ['integer', Rule::exists('product_development_items', 'id')->where('product_development_id', $productDevelopmentId)->whereNotNull('artefact_id')],
            'quantity'     => ['required', 'integer', 'min:1'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'item_ids.required' => __('Pick at least one SKU'),
            'quantity.required' => __('How many units should each trial run make?'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE || !$this->productDevelopment->production) {
            $validator->errors()->add('item_ids', __('Trial runs are for in-house developments only'));
        }
    }

    /**
     * @return array<int, JobOrder>
     */
    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): array
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    /**
     * @return array<int, JobOrder>
     */
    public function action(ProductDevelopment $productDevelopment, User $requester, array $modelData): array
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $requester, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
