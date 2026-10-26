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
use App\Actions\UI\Grp\BreakUserUiProps;
use App\Actions\Tasks\StoreStaffTask;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Asks someone in the production team to make samples of one or more in-house SKUs. The job is an Aiku task
 * on the development, so it shows in their task list, and each SKU gets its next sample linked to it. The
 * person joins the development with the Production role so they can open it.
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

    public function handle(ProductDevelopment $productDevelopment, User $requester, array $modelData): StaffTask
    {
        $items    = $productDevelopment->items()->with('tradeUnit')->whereIn('id', $modelData['item_ids'])->orderBy('position')->get();
        $assignee = User::findOrFail($modelData['assignee_id']);
        $codes    = $items->map(fn (ProductDevelopmentItem $item) => $item->tradeUnit->code)->all();

        return DB::transaction(function () use ($productDevelopment, $requester, $modelData, $items, $assignee, $codes) {
            $member = $productDevelopment->members()->firstOrCreate([
                'user_id' => $assignee->id,
                'role'    => ProductDevelopmentMemberRoleEnum::PRODUCTION->value,
            ]);
            if ($member->wasRecentlyCreated) {
                BreakUserUiProps::dispatch($assignee)->afterCommit();
            }

            $task = StoreStaffTask::run($requester, [
                'subject'     => __('Make samples of :skus (:code)', ['skus' => implode(', ', $codes), 'code' => $productDevelopment->code]),
                'description' => trim((string) ($modelData['instructions'] ?? '')) ?: __('Open the Formulation & samples tab of :code for the brief, components and recipe.', ['code' => $productDevelopment->code]),
                'assignee_id' => $assignee->id,
                'due_at'      => $modelData['due_at'] ?? null,
                'priority'    => $modelData['priority'] ?? ChatPriorityEnum::NORMAL->value,
                'model_type'  => 'ProductDevelopment',
                'model_id'    => $productDevelopment->id,
                'data'        => [
                    'product_development_event' => self::EVENT,
                    'product_development_items' => $items->pluck('id')->all(),
                ],
            ]);

            foreach ($items as $item) {
                $open = $item->samples()->whereIn('status', [ProductDevelopmentSampleStatusEnum::IN_PROGRESS->value, ProductDevelopmentSampleStatusEnum::READY_FOR_REVIEW->value])->first();
                if ($open) {
                    $open->update(['staff_task_id' => $task->id, 'made_by_id' => $assignee->id]);
                    continue;
                }
                StoreProductDevelopmentSample::make()->action($item, $requester, ['staff_task_id' => $task->id, 'made_by_id' => $assignee->id]);
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::SAMPLE_JOB_CREATED, [], [
                'assignee'     => $assignee->contact_name ?: $assignee->username,
                'skus'         => $codes,
                'instructions' => $modelData['instructions'] ?? null,
            ]);

            return $task;
        });
    }

    public function rules(): array
    {
        $productDevelopmentId = $this->productDevelopment->id;

        return [
            'item_ids'     => ['required', 'array', 'min:1'],
            'item_ids.*'   => ['integer', Rule::exists('product_development_items', 'id')->where('product_development_id', $productDevelopmentId)->whereNotNull('artefact_id')],
            'assignee_id'  => ['required', 'integer', Rule::exists('users', 'id')->where('group_id', $this->productDevelopment->group_id)->where('status', true)],
            'due_at'       => ['sometimes', 'nullable', 'date'],
            'priority'     => ['sometimes', Rule::enum(ChatPriorityEnum::class)],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'item_ids.required' => __('Pick at least one SKU'),
            'assignee_id.required' => __('Pick who makes the samples'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE) {
            $validator->errors()->add('item_ids', __('Sample jobs are for in-house developments only'));
        }
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): StaffTask
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $requester, array $modelData): StaffTask
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
