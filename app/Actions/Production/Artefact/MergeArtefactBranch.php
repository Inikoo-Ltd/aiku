<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentFormulation;
use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\GenerateJobOrderItemTasks;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The end of a redesign: the branch's recipe replaces the recipe of the artefact it was branched from, which keeps
 * its code, SKO, products, batches and job orders. The recipe it had is kept in its data as recipe_history, and the
 * branch ends merged, never launched or revived.
 *
 * Open job orders of the original follow the new recipe like they follow any recipe edit: pay targets and raw
 * materials are read from the live recipe, so there is no older recipe for them to keep. Steps with work recorded
 * keep their count, and whoever merges is told which job orders change and confirms first.
 */
class MergeArtefactBranch extends OrgAction
{
    private Artefact $branch;

    public function handle(Artefact $branch, ?User $user): Artefact
    {
        $original = $branch->branchedFrom;
        $by       = [
            'user_id' => $user?->id,
            'name'    => $user ? ($user->contact_name ?: $user->username) : null,
            'at'      => now()->toIso8601String(),
        ];

        DB::transaction(function () use ($branch, $original, $by, $user) {
            $original->update([
                'data' => array_merge($original->data ?? [], [
                    'recipe_history' => [
                        ...($original->data['recipe_history'] ?? []),
                        [...$by, 'replaced_by_branch' => ['id' => $branch->id, 'code' => $branch->code], 'steps' => BranchArtefact::recipeOf($original)],
                    ],
                ]),
            ]);

            BranchArtefact::copyRecipe($branch, $original);

            $branch->update([
                'state' => ArtefactStateEnum::MERGED,
                'data'  => array_merge($branch->data ?? [], [
                    'merge' => [...$by, 'into_id' => $original->id, 'into_code' => $original->code],
                ]),
            ]);

            $this->completeRedesigns($branch, $user);
        });

        SetArtefactState::hydrateParents($branch);

        return $original;
    }

    /**
     * An in-house development redesigning the original ends when its last branch is merged back: every SKU has
     * an artefact and none is still in process. Its open tasks are closed as done.
     */
    private function completeRedesigns(Artefact $branch, ?User $user): void
    {
        ProductDevelopment::where('kind', ProductDevelopmentKindEnum::RECIPE)
            ->whereIn('status', [ProductDevelopmentStatusEnum::ACTIVE, ProductDevelopmentStatusEnum::ON_HOLD])
            ->whereHas('items', fn ($query) => $query->where('artefact_id', $branch->id))
            ->whereDoesntHave('items', fn ($query) => $query->whereNull('artefact_id'))
            ->whereDoesntHave('items.artefact', fn ($query) => $query->where('state', ArtefactStateEnum::IN_PROCESS))
            ->get()
            ->each(function (ProductDevelopment $productDevelopment) use ($user) {
                $productDevelopment->update(['status' => ProductDevelopmentStatusEnum::COMPLETED]);
                if ($user) {
                    CreateProductDevelopmentTask::make()->syncProgress($productDevelopment, $user);
                }
            });
    }

    /**
     * The SKU of an open development on this branch whose last approved trial run is missing or was made with
     * another recipe, so the recipe going on sale was never approved.
     */
    private function itemWithoutApprovedRecipe(Artefact $branch): ?ProductDevelopmentItem
    {
        $ingredients = ProductDevelopmentRecipes::recipe($branch)['ingredients'];

        return ProductDevelopmentItem::where('artefact_id', $branch->id)
            ->whereHas('productDevelopment', fn ($query) => $query->whereIn('status', [ProductDevelopmentStatusEnum::ACTIVE, ProductDevelopmentStatusEnum::ON_HOLD]))
            ->with('productDevelopment')
            ->get()
            ->first(function (ProductDevelopmentItem $item) use ($ingredients) {
                $approved = $item->samples()->where('status', ProductDevelopmentSampleStatusEnum::APPROVED)->orderByDesc('number')->first();

                return !$approved || !GetProductDevelopmentFormulation::sameIngredients($approved->recipe['ingredients'] ?? [], $ingredients);
            });
    }

    public function rules(): array
    {
        return [
            'confirm_open_jobs' => ['sometimes', 'boolean'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $branch   = $this->branch;
        $original = $branch->branchedFrom;

        if ($branch->production_id !== $this->production->id) {
            $validator->errors()->add('merge', __('This artefact is not made in this factory'));

            return;
        }

        if ($branch->state !== ArtefactStateEnum::IN_PROCESS || !$original) {
            $validator->errors()->add('merge', __(':code is not a branch in development', ['code' => $branch->code]));

            return;
        }

        if (!in_array($original->state, ArtefactStateEnum::launched(), true)) {
            $validator->errors()->add('merge', __(':code, the artefact it was branched from, is no longer launched', ['code' => $original->code]));

            return;
        }

        $openTrialRuns = $branch->jobOrderItems()
            ->whereHas('jobOrder', fn ($query) => $query->whereIn('state', JobOrderStateEnum::open()))
            ->with('jobOrder:id,reference')
            ->get()
            ->pluck('jobOrder.reference')
            ->unique();

        if ($openTrialRuns->isNotEmpty()) {
            $validator->errors()->add('merge', __('Receive or cancel its open trial runs first: :references', ['references' => $openTrialRuns->implode(', ')]));

            return;
        }

        $unapproved = $this->itemWithoutApprovedRecipe($branch);
        if ($unapproved) {
            $validator->errors()->add('merge', __(':code: a trial run in :development must be approved with this recipe before it replaces the recipe of :original', [
                'code'        => $branch->code,
                'development' => $unapproved->productDevelopment->code,
                'original'    => $original->code,
            ]));

            return;
        }

        $steps        = collect(BranchArtefact::recipeOf($branch));
        $currentUnits = $original->manufactureTasks()->get()->mapWithKeys(fn (ManufactureTask $manufactureTask) => [$manufactureTask->id => $manufactureTask->pivot->units_per_artefact]);
        $kgTaskIds    = ManufactureTask::whereIn('id', $steps->pluck('manufacture_task_id'))->where('is_recorded_in_kg', true)->pluck('id');

        $countsPackAgain = $steps->contains(
            fn (array $step) => (float) ($currentUnits[$step['manufacture_task_id']] ?? 0) !== (float) $step['units_per_artefact']
                && ArtefactManufactureTask::countsSkoPackAgain($original, $kgTaskIds->contains($step['manufacture_task_id']), $step['units_per_artefact'])
        );
        if ($countsPackAgain) {
            $validator->errors()->add('merge', ArtefactManufactureTask::skoPackCountedAgainMessage(collect([$original])));

            return;
        }

        if ($this->asAction || filter_var($this->get('confirm_open_jobs'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $message = GenerateJobOrderItemTasks::openJobsConfirmationMessage([
            GenerateJobOrderItemTasks::make()->openJobsAffectedBy(
                $original,
                $steps->mapWithKeys(fn (array $step) => [$step['manufacture_task_id'] => $step['units_per_artefact']])->all(),
                $currentUnits->keys()->diff($steps->pluck('manufacture_task_id'))->values()->all()
            ),
        ]);

        if ($message) {
            $validator->errors()->add('confirm_open_jobs', $message);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_rd.{$this->production->id}.edit",
        ]);
    }

    public function action(Artefact $branch, ?User $user = null): Artefact
    {
        $this->asAction = true;
        $this->branch   = $branch;
        $this->initialisationFromProduction($branch->production, []);

        return $this->handle($branch, $user);
    }

    public function asController(Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->branch = $artefact;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
