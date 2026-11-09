<?php

/*
 * Author Louis Perez
 * Created on 09-11-2026-11h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\RecordProductDevelopmentActivity;
use App\Actions\OrgAction;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\DetachRawMaterialFromRecipeStep;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItemTask;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\ManufactureTaskSessionShare;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The first step of a trial run a product development asked for: the artisan records the raw materials and
 * quantities they make the sample with. They become the recipe of the SKU's experiment in Production, and a
 * copy is kept on the job order and on the trial run, so the development always knows what it was made from.
 */
class RecordTrialRunFormulation extends OrgAction
{
    private JobOrder $jobOrder;

    private ?ProductDevelopmentSample $sample = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canRecord($request->user(), $this->jobOrder);
    }

    /**
     * The artisan any step of the trial run is for, and whoever runs the production unit or its R&D.
     */
    public static function canRecord(User $user, JobOrder $jobOrder): bool
    {
        if ($user->authTo([
            'org-supervisor.'.$jobOrder->organisation_id,
            "productions_operations.$jobOrder->production_id.orchestrate",
            "productions_rd.$jobOrder->production_id.edit",
        ])) {
            return true;
        }

        $employee = GetUserCurrentEmployee::run($user, $jobOrder->organisation_id);

        return $employee && JobOrderItemTask::where('job_order_item_tasks.job_order_id', $jobOrder->id)
            ->join('job_orders', 'job_orders.id', '=', 'job_order_item_tasks.job_order_id')
            ->join('job_order_items', 'job_order_items.id', '=', 'job_order_item_tasks.job_order_item_id')
            ->whereRaw(JobOrderItemTask::ASSIGNEE_SQL.' = ?', [$employee->id])
            ->exists();
    }

    public static function sampleOf(JobOrder $jobOrder): ?ProductDevelopmentSample
    {
        return ProductDevelopmentSample::where('job_order_id', $jobOrder->id)->with(['item.artefact', 'item.tradeUnit', 'productDevelopment'])->first();
    }

    /**
     * Until the trial run goes for review: from then on the development reviews what it was made from.
     */
    public static function isChangeable(ProductDevelopmentSample $sample): bool
    {
        return $sample->status === ProductDevelopmentSampleStatusEnum::IN_PROGRESS;
    }

    public static function isRecorded(JobOrder $jobOrder): bool
    {
        return isset($jobOrder->data['formulation']);
    }

    /**
     * The formulation is the first step: asked for before anything of the trial run is started, never of a trial
     * run already being made (those started before HELP-4545 carry on as they were).
     */
    public static function isNeededToStart(JobOrder $jobOrder): bool
    {
        return $jobOrder->isRequestedByDevelopment()
            && !self::isRecorded($jobOrder)
            && !ManufactureTaskSession::where('state', '!=', ManufactureTaskSessionStateEnum::VOIDED)
                ->where(fn ($query) => $query
                    ->whereIn('job_order_item_task_id', JobOrderItemTask::where('job_order_id', $jobOrder->id)->select('id'))
                    ->orWhereIn('id', ManufactureTaskSessionShare::whereIn('job_order_item_task_id', JobOrderItemTask::where('job_order_id', $jobOrder->id)->select('id'))->select('manufacture_task_session_id')))
                ->exists();
    }

    public function handle(JobOrder $jobOrder, User $user, array $modelData): JobOrder
    {
        $sample   = $this->sample ?? self::sampleOf($jobOrder);
        $artefact = $sample->item->artefact;

        DB::transaction(function () use ($jobOrder, $user, $modelData, $sample, $artefact) {
            $steps = $artefact->recipeSteps()->with('rawMaterials')->get()->keyBy('id');

            foreach ($modelData['steps'] as $stepData) {
                $recipeStep = $steps->get((int) $stepData['recipe_step_id']);
                $lines      = collect($stepData['raw_materials'] ?? [])->keyBy(fn (array $line) => (int) $line['raw_material_id']);

                foreach ($recipeStep->rawMaterials->whereNotIn('raw_material_id', $lines->keys()) as $removed) {
                    DetachRawMaterialFromRecipeStep::make()->action($recipeStep, RawMaterial::withTrashed()->findOrFail($removed->raw_material_id));
                }

                foreach ($lines as $line) {
                    AttachRawMaterialToRecipeStep::make()->action($recipeStep, [
                        'raw_material_id'   => (int) $line['raw_material_id'],
                        'quantity_per_unit' => $line['quantity_per_unit'],
                    ]);
                }
            }

            $recipe = ProductDevelopmentRecipes::recipe($artefact->fresh());
            $notes  = trim((string) ($modelData['notes'] ?? '')) ?: null;

            $jobOrder->update(['data' => [
                ...($jobOrder->data ?? []),
                'formulation' => [
                    'recipe'         => $recipe,
                    'notes'          => $notes,
                    'recorded_by_id' => $user->id,
                    'recorded_at'    => now()->toIso8601String(),
                ],
            ]]);

            $sample->update(['recipe' => $recipe]);

            RecordProductDevelopmentActivity::record($sample->productDevelopment, RecordProductDevelopmentActivity::TRIAL_RUN_FORMULATED, [], [
                'sample'    => ProductDevelopmentRecipes::label($sample),
                'job_order' => $jobOrder->reference,
            ]);
        });

        return $jobOrder->refresh();
    }

    public function rules(): array
    {
        $organisationId = $this->jobOrder->organisation_id;

        return [
            'steps'                                     => ['required', 'array', 'min:1'],
            'steps.*.recipe_step_id'                    => ['required', 'integer', 'distinct'],
            'steps.*.raw_materials'                     => ['present', 'array'],
            'steps.*.raw_materials.*.raw_material_id'   => ['required', 'integer', Rule::exists('raw_materials', 'id')->where('organisation_id', $organisationId)->whereNull('deleted_at')],
            'steps.*.raw_materials.*.quantity_per_unit' => ['required', 'numeric', 'gt:0'],
            'notes'                                     => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'steps.*.raw_materials.*.raw_material_id.required'   => __('Pick the raw material'),
            'steps.*.raw_materials.*.quantity_per_unit.required' => __('Enter how much of it goes in'),
            'steps.*.raw_materials.*.quantity_per_unit.gt'       => __('Enter how much of it goes in'),
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $sample = $this->sample = self::sampleOf($this->jobOrder);
        if (!$sample || !$this->jobOrder->isRequestedByDevelopment()) {
            $validator->errors()->add('formulation', __('Only a trial run a product development asked for has a formulation'));

            return;
        }

        if (!self::isChangeable($sample)) {
            $validator->errors()->add('formulation', __('This trial run has gone for review: its formulation can no longer change'));

            return;
        }

        $artefact = $sample->item->artefact;
        if (!$artefact || !$this->jobOrder->jobOrderItems()->where('artefact_id', $artefact->id)->exists()) {
            $validator->errors()->add('formulation', __('This job order does not make the trial run of :sku', ['sku' => $sample->item->tradeUnit->code]));

            return;
        }

        $stepIds = $artefact->recipeSteps()->pluck('id')->all();
        foreach ((array) $this->get('steps', []) as $index => $step) {
            if (!in_array((int) Arr::get($step, 'recipe_step_id'), $stepIds, true)) {
                $validator->errors()->add("steps.$index.recipe_step_id", __('This step is not in the recipe of :code', ['code' => $artefact->code]));
            }

            $rawMaterialIds = array_map(fn ($line) => (int) Arr::get($line, 'raw_material_id'), (array) Arr::get($step, 'raw_materials', []));
            if (count($rawMaterialIds) !== count(array_unique($rawMaterialIds))) {
                $validator->errors()->add("steps.$index.raw_materials", __('Each raw material goes in a step once: add up its quantity'));
            }
        }

        if (!collect((array) $this->get('steps', []))->contains(fn ($step) => !empty(Arr::get($step, 'raw_materials')))) {
            $validator->errors()->add('formulation', __('Add the raw materials the sample is made with'));
        }
    }

    public function asController(JobOrder $jobOrder, ActionRequest $request): JobOrder
    {
        $this->jobOrder = $jobOrder;
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $request->user(), $this->validatedData);
    }

    public function action(JobOrder $jobOrder, User $user, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->jobOrder = $jobOrder;
        $this->initialisationFromProduction($jobOrder->production, $modelData);

        return $this->handle($jobOrder, $user, $this->validatedData);
    }

    /**
     * The recipe as the form edits it: every step of the experiment with the raw materials it has now.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function formSteps(Artefact $artefact): array
    {
        return $artefact->recipeSteps()->with(['manufactureTask', 'rawMaterials.rawMaterial'])->get()->map(fn ($step) => [
            'id'            => $step->id,
            'name'          => $step->name(),
            'raw_materials' => $step->rawMaterials->map(fn ($line) => [
                'raw_material_id'   => $line->raw_material_id,
                'code'              => $line->rawMaterial?->code,
                'description'       => $line->rawMaterial?->description,
                'unit'              => $line->rawMaterial?->unit?->value,
                'quantity_per_unit' => (float) $line->quantity_per_unit,
            ])->values()->all(),
        ])->values()->all();
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
