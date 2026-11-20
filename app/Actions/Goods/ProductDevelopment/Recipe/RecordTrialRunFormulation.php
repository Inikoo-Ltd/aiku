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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The first step of a trial run a product development asked for: the artisan records the raw materials and
 * quantities they make the sample with. They become the recipe of the SKU's experiment in Production, and a
 * copy is kept on the job order and on the trial run, so the development always knows what it was made from.
 * A blend or intermediate is recorded as the share of each ingredient in the sample (HELP-4852, see
 * FormulationPercentages); the finished product per piece, with its packaging.
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
        return in_array($jobOrder->id, self::jobOrdersNeedingFormulation(collect([$jobOrder])), true);
    }

    /**
     * The ids of the job orders among these that cannot start before their formulation is recorded, in one query.
     *
     * @param  Collection<int, JobOrder>  $jobOrders
     * @return array<int, int>
     */
    public static function jobOrdersNeedingFormulation(Collection $jobOrders): array
    {
        $waiting = $jobOrders->filter(fn (JobOrder $jobOrder) => $jobOrder->isRequestedByDevelopment() && !self::isRecorded($jobOrder))->pluck('id')->unique()->values();
        if ($waiting->isEmpty()) {
            return [];
        }

        $tasks   = JobOrderItemTask::whereIn('job_order_id', $waiting);
        $started = JobOrderItemTask::whereIn('job_order_item_tasks.id', (clone $tasks)->select('id'))
            ->where(fn ($query) => $query
                ->whereIn('job_order_item_tasks.id', ManufactureTaskSession::where('state', '!=', ManufactureTaskSessionStateEnum::VOIDED)->whereNotNull('job_order_item_task_id')->select('job_order_item_task_id'))
                ->orWhereIn('job_order_item_tasks.id', ManufactureTaskSessionShare::whereIn('manufacture_task_session_id', ManufactureTaskSession::where('state', '!=', ManufactureTaskSessionStateEnum::VOIDED)->select('id'))->select('job_order_item_task_id')))
            ->distinct()
            ->pluck('job_order_id');

        return $waiting->diff($started)->values()->all();
    }

    public function handle(JobOrder $jobOrder, User $user, array $modelData): JobOrder
    {
        $sample   = $this->sample ?? self::sampleOf($jobOrder);
        $artefact = $sample->workingArtefact();

        DB::transaction(function () use ($jobOrder, $user, $modelData, $sample, $artefact) {
            $formulation = FormulationPercentages::isForSample($sample) ? $this->recordPercentages($jobOrder, $sample, $artefact, $modelData) : null;
            $steps       = $artefact->recipeSteps()->with('rawMaterials')->get()->keyBy('id');

            foreach ($formulation ? [] : $modelData['steps'] as $stepData) {
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

            $recipe        = [...ProductDevelopmentRecipes::recipe($artefact->fresh()), ...($formulation ? ['formulation' => $formulation] : [])];
            $notes         = trim((string) ($modelData['notes'] ?? '')) ?: null;
            $previousNotes = $jobOrder->data['formulation']['notes'] ?? null;

            $jobOrder->update(['data' => [
                ...($jobOrder->data ?? []),
                'formulation' => [
                    'recipe'         => $recipe,
                    'notes'          => $notes,
                    'recorded_by_id' => $user->id,
                    'recorded_at'    => now()->toIso8601String(),
                ],
            ]]);

            $sample->update([
                'recipe' => $recipe,
                'notes'  => self::sampleNotes($sample->notes, $previousNotes, $notes),
                ...($formulation && $formulation['sample_size'] ? ['batch_size' => $formulation['sample_size'], 'batch_unit' => $formulation['sample_unit']] : []),
            ]);

            RecordProductDevelopmentActivity::record($sample->productDevelopment, RecordProductDevelopmentActivity::TRIAL_RUN_FORMULATED, [], [
                'sample'    => ProductDevelopmentRecipes::label($sample),
                'job_order' => $jobOrder->reference,
            ]);
        });

        return $jobOrder->refresh();
    }

    /**
     * Writes the units per kg of blend each share gives into the step's recipe. A line that cannot be converted
     * keeps the units someone entered by hand, on this run or the stage's previous one, while its share and the
     * sample's unit stay the same; otherwise it leaves the recipe until its units are entered again
     * (SetProductDevelopmentSampleUnits). The form starts from the step's recipe, so nothing on it is replaced unseen.
     *
     * @return array<string, mixed> the formulation kept with the trial run
     */
    private function recordPercentages(JobOrder $jobOrder, ProductDevelopmentSample $sample, Artefact $artefact, array $modelData): array
    {
        $recipeStep   = $artefact->recipeSteps()->with('rawMaterials')->findOrFail((int) $modelData['recipe_step_id']);
        $sampleUnit   = $modelData['sample_unit'];
        $sampleSize   = isset($modelData['sample_size']) ? round((float) $modelData['sample_size'], 3) : null;
        $previous     = $jobOrder->data['formulation']['recipe']['formulation'] ?? self::earlierRun($sample)?->recipe['formulation'] ?? null;
        $previousBy   = collect(($previous['sample_unit'] ?? null) === $sampleUnit ? $previous['lines'] : [])->keyBy('raw_material_id');
        $rawMaterials = RawMaterial::with(['orgStock.tradeUnits', 'tradeUnit'])->whereIn('id', collect($modelData['lines'])->pluck('raw_material_id'))->get()->keyBy('id');

        $lines = collect($modelData['lines'])->map(function (array $line) use ($rawMaterials, $sampleUnit, $sampleSize, $previousBy) {
            $rawMaterial = $rawMaterials->get((int) $line['raw_material_id']);
            $percent     = round((float) $line['percent'], 4);
            $kgPerUnit   = FormulationPercentages::kgPerUnit($rawMaterial);
            $unitsPerKg  = FormulationPercentages::unitsPerKg($percent, $sampleUnit, $kgPerUnit);
            $before      = $previousBy->get($rawMaterial->id);
            $keepsManual = $unitsPerKg === null && ($before['manual'] ?? false) && abs((float) $before['percent'] - $percent) < 0.0001;

            return [
                'raw_material_id' => $rawMaterial->id,
                'code'            => $rawMaterial->code,
                'description'     => $rawMaterial->description,
                'unit'            => $rawMaterial->unit->value,
                'percent'         => $percent,
                'sample_quantity' => $sampleSize ? round($percent * $sampleSize / 100, 3) : null,
                'kg_per_unit'     => $kgPerUnit,
                'units_per_kg'    => $keepsManual ? $before['units_per_kg'] : $unitsPerKg,
                'manual'          => $keepsManual,
            ];
        })->values();

        $withUnits = $lines->filter(fn (array $line) => $line['units_per_kg'] !== null)->keyBy('raw_material_id');
        foreach ($recipeStep->rawMaterials->whereNotIn('raw_material_id', $withUnits->keys()) as $removed) {
            DetachRawMaterialFromRecipeStep::make()->action($recipeStep, RawMaterial::withTrashed()->findOrFail($removed->raw_material_id));
        }
        foreach ($withUnits as $line) {
            AttachRawMaterialToRecipeStep::make()->action($recipeStep, ['raw_material_id' => $line['raw_material_id'], 'quantity_per_unit' => $line['units_per_kg']]);
        }

        return [
            'sample_size'    => $sampleSize,
            'sample_unit'    => $sampleUnit,
            'recipe_step_id' => $recipeStep->id,
            'lines'          => $lines->sortByDesc('percent')->values()->all(),
        ];
    }

    public function rules(): array
    {
        $organisationId = $this->jobOrder->organisation_id;

        if ($this->sample && FormulationPercentages::isForSample($this->sample)) {
            return [
                'sample_size'             => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999'],
                'sample_unit'             => ['required', Rule::in(FormulationPercentages::SAMPLE_UNITS)],
                'recipe_step_id'          => ['required', 'integer'],
                'lines'                   => ['required', 'array', 'min:1'],
                'lines.*.raw_material_id' => ['required', 'integer', 'distinct', Rule::exists('raw_materials', 'id')->where('organisation_id', $organisationId)->whereNull('deleted_at')],
                'lines.*.percent'         => ['required', 'numeric', 'gt:0', 'max:100'],
                'notes'                   => ['sometimes', 'nullable', 'string', 'max:2000'],
            ];
        }

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
            'lines.*.raw_material_id.required'                   => __('Pick the raw material'),
            'lines.*.raw_material_id.distinct'                   => __('Each raw material goes in once: add up its share'),
            'lines.*.percent.required'                           => __('Enter its share of the sample'),
            'lines.*.percent.gt'                                 => __('Enter its share of the sample'),
            'lines.required'                                     => __('Add the raw materials the sample is made with'),
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

        $artefact = $sample->workingArtefact();
        if (!$artefact || !$this->jobOrder->jobOrderItems()->where('artefact_id', $artefact->id)->exists()) {
            $validator->errors()->add('formulation', __('This job order does not make the trial run of :sku', ['sku' => $sample->item->tradeUnit->code]));

            return;
        }

        $stepIds = $artefact->recipeSteps()->pluck('id')->all();
        if (FormulationPercentages::isForSample($sample)) {
            if (!in_array((int) $this->get('recipe_step_id'), $stepIds, true)) {
                $validator->errors()->add('formulation', __('This step is not in the recipe of :code', ['code' => $artefact->code]));
            }

            $total = collect((array) $this->get('lines', []))->sum(fn ($line) => (float) Arr::get($line, 'percent'));
            if (abs($total - 100) > FormulationPercentages::TOTAL_TOLERANCE) {
                $validator->errors()->add('formulation', __('The shares add up to :total%: they must make 100%', ['total' => round($total, 2)]));
            }

            return;
        }

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
        $this->sample   = self::sampleOf($jobOrder);
        $this->initialisationFromProduction($jobOrder->production, $request);

        return $this->handle($jobOrder, $request->user(), $this->validatedData);
    }

    public function action(JobOrder $jobOrder, User $user, array $modelData): JobOrder
    {
        $this->asAction = true;
        $this->jobOrder = $jobOrder;
        $this->sample   = self::sampleOf($jobOrder);
        $this->initialisationFromProduction($jobOrder->production, $modelData);

        return $this->handle($jobOrder, $user, $this->validatedData);
    }

    /**
     * The trial run's notes on the formulation with what the artisan wrote: the development team's own notes stay,
     * and the artisan's previous note is replaced when they change the formulation.
     */
    public static function sampleNotes(?string $current, ?string $previousNotes, ?string $notes): ?string
    {
        $current = trim((string) $current);
        if ($previousNotes !== null && str_ends_with($current, $previousNotes)) {
            $current = trim(substr($current, 0, -strlen($previousNotes)));
        }

        return implode("\n\n", array_filter([$current, $notes])) ?: null;
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

    /**
     * The percentage form of a blend's trial run, starting from what was recorded on this job, else the stage's
     * previous run recorded in shares, else what the recipe in Production has now, else a previous run recorded per
     * piece (on the SKU's artefact, before the stage was a blend), each ingredient as its share of the weight.
     *
     * @return array<string, mixed>|null
     */
    public static function formFormulation(ProductDevelopmentSample $sample, ?JobOrder $jobOrder): ?array
    {
        if (!FormulationPercentages::isForSample($sample)) {
            return null;
        }

        $recorded = $jobOrder?->data['formulation']['recipe']['formulation'] ?? null;
        $earlier  = self::earlierRun($sample);
        $previous = $recorded ?? $earlier?->recipe['formulation'] ?? null;
        $step     = $sample->workingArtefact()?->recipeSteps()->with('rawMaterials.rawMaterial.orgStock.tradeUnits', 'rawMaterials.rawMaterial.tradeUnit')->orderBy('position')->first();
        $lines    = $previous['lines'] ?? null;

        if ($lines === null && $step && $step->rawMaterials->isNotEmpty()) {
            $lines = FormulationPercentages::sharesOf($step->rawMaterials->filter(fn ($line) => $line->rawMaterial)->map(fn ($line) => ['raw_material' => $line->rawMaterial, 'quantity' => (float) $line->quantity_per_unit]));
        }
        if ($lines !== null && $step) {
            $listed = collect($lines)->pluck('raw_material_id')->all();
            $lines  = [...$lines, ...collect(FormulationPercentages::sharesOf($step->rawMaterials->filter(fn ($line) => $line->rawMaterial && !in_array($line->raw_material_id, $listed))->map(fn ($line) => ['raw_material' => $line->rawMaterial, 'quantity' => (float) $line->quantity_per_unit])))->map(fn (array $line) => [...$line, 'percent' => null])->all()];
        }
        if ($lines === null && $earlier) {
            $rawMaterials = RawMaterial::with(['orgStock.tradeUnits', 'tradeUnit'])->where('organisation_id', $sample->workingArtefact()?->organisation_id)
                ->whereIn('code', collect($earlier->recipe['ingredients'] ?? [])->pluck('code'))->get()->keyBy('code');
            $lines = FormulationPercentages::sharesOf(collect($earlier->recipe['ingredients'] ?? [])
                ->filter(fn (array $ingredient) => $rawMaterials->has($ingredient['code']))
                ->map(fn (array $ingredient) => ['raw_material' => $rawMaterials->get($ingredient['code']), 'quantity' => (float) $ingredient['quantity']]));
        }

        return [
            'recipe_step_id' => $recorded['recipe_step_id'] ?? $step?->id,
            'sample_size'    => $recorded['sample_size'] ?? ($sample->batch_unit && in_array($sample->batch_unit, FormulationPercentages::SAMPLE_UNITS, true) ? (float) $sample->batch_size : ($previous['sample_size'] ?? null)),
            'sample_unit'    => $recorded['sample_unit'] ?? (in_array($sample->batch_unit, FormulationPercentages::SAMPLE_UNITS, true) ? $sample->batch_unit : ($previous['sample_unit'] ?? 'g')),
            'lines'          => collect($lines ?? [])->map(fn (array $line) => Arr::only($line, ['raw_material_id', 'code', 'description', 'unit', 'percent']))->values()->all(),
        ];
    }

    /**
     * The stage's run before this one that kept what it was made from.
     */
    private static function earlierRun(ProductDevelopmentSample $sample): ?ProductDevelopmentSample
    {
        return ProductDevelopmentSample::where('product_development_formulation_id', $sample->product_development_formulation_id)
            ->where('number', '<', $sample->number)
            ->whereNotNull('recipe')
            ->orderByDesc('number')
            ->first();
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
