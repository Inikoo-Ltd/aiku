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
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\HumanResources\Employee;
use App\Models\Production\JobOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Asks the factory for trial runs of one or more in-house SKUs: one trial-run job order per SKU, which the
 * floor works like any other job order and hands to the development office. Each SKU gets its next trial
 * run record linked to its job order.
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
        $items      = $productDevelopment->items()->with(['tradeUnit', 'artefact'])->whereIn('id', $modelData['item_ids'])->orderBy('position')->get();
        $employee   = Arr::get($modelData, 'employee_id') ? Employee::find($modelData['employee_id']) : null;
        $notes      = trim((string) ($modelData['instructions'] ?? ''));

        return DB::transaction(function () use ($productDevelopment, $requester, $modelData, $items, $employee, $notes) {
            $jobOrders = [];
            foreach ($items as $item) {
                $jobOrder = StoreTrialRunJobOrder::make()->action($productDevelopment->production, [
                    'artefact_id' => $item->artefact_id,
                    'quantity'    => (int) $modelData['quantity'],
                    'employee_id' => $employee?->id,
                    'notes'       => trim($productDevelopment->code.($notes !== '' ? ': '.$notes : '')),
                ]);

                $open = $item->samples()->whereIn('status', [ProductDevelopmentSampleStatusEnum::IN_PROGRESS->value, ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW->value])->first();
                if ($open) {
                    $open->update(['job_order_id' => $jobOrder->id, 'made_by_id' => $employee?->user_id]);
                } else {
                    StoreProductDevelopmentSample::make()->action($item, $requester, ['job_order_id' => $jobOrder->id, 'made_by_id' => $employee?->user_id]);
                }

                $jobOrders[] = $jobOrder;
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::SAMPLE_JOB_CREATED, [], [
                'assignee'     => $employee?->contact_name,
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
            'employee_id'  => ['sometimes', 'nullable', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->productDevelopment->production?->organisation_id)->where('state', EmployeeStateEnum::WORKING->value)],
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
