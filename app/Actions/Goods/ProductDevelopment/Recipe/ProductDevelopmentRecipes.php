<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-12h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Recipe;

use App\Actions\Goods\ProductDevelopment\NotifyProductDevelopmentUsers;
use App\Actions\Goods\ProductDevelopment\ProductDevelopmentAccess;
use App\Actions\Production\Artefact\BranchArtefact;
use App\Actions\Production\Artefact\AttachRawMaterialToRecipeStep;
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentFormulationTypeEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentSampleStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopmentChecklistTemplate;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentFormulation;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\SysAdmin\User;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\RawMaterial;
use App\Models\Production\RecipeStepRawMaterial;

/**
 * An in-house development makes each SKU as an artefact of its production unit: the recipe lives there and
 * the development reads it, so nothing is entered twice.
 */
class ProductDevelopmentRecipes
{
    /**
     * Links the SKU to an experiment of the production unit: a new artefact in process, or one already in
     * process for its trade unit. A launched product is never changed directly: the development gets a branch
     * of it (a copy of its recipe), which is merged back into it when the redesign is done. A cancelled
     * product, or another product holding the code, leaves the SKU without an artefact and the Formulation
     * tab says why.
     */
    public static function linkArtefact(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item, ?User $user = null): ?Artefact
    {
        $production = $productDevelopment->production;
        if (!$production) {
            return null;
        }

        $tradeUnit = $item->tradeUnit;
        if (self::blockingArtefact($productDevelopment, $item)) {
            return null;
        }

        $launched = self::launchedArtefact($productDevelopment, $item);

        $artefact = $production->artefacts()->where('trade_unit_id', $tradeUnit->id)->where('state', ArtefactStateEnum::IN_PROCESS)->first()
            ?? $launched?->branches()->where('state', ArtefactStateEnum::IN_PROCESS)
                ->whereNotIn('id', ProductDevelopmentItem::whereNotNull('artefact_id')->where('id', '!=', $item->id)->select('artefact_id'))
                ->first()
            ?? ($launched ? BranchArtefact::make()->action($launched, $user) : null)
            ?? StoreArtefact::make()->action($production, [
                'code'          => $tradeUnit->code,
                'name'          => $tradeUnit->name,
                'trade_unit_id' => $tradeUnit->id,
                'state'         => ArtefactStateEnum::IN_PROCESS->value,
            ]);

        $item->update(['artefact_id' => $artefact->id]);

        return $artefact;
    }

    /**
     * The product of the factory the SKU redesigns: its launched artefact, which the development branches.
     */
    public static function launchedArtefact(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): ?Artefact
    {
        if (!$productDevelopment->production || !$item->tradeUnit) {
            return null;
        }

        return $productDevelopment->production->artefacts()
            ->where('trade_unit_id', $item->tradeUnit->id)
            ->whereIn('state', ArtefactStateEnum::launched())
            ->orderBy('id')
            ->first();
    }

    /**
     * The artefact that stops the SKU from getting an experiment: a cancelled one for its trade unit (unless a
     * launched one is there to branch), or any artefact of another trade unit already using its code.
     */
    public static function blockingArtefact(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): ?Artefact
    {
        $production = $productDevelopment->production;
        $tradeUnit  = $item->tradeUnit;
        if (!$production || !$tradeUnit) {
            return null;
        }

        $isLaunched = (bool) self::launchedArtefact($productDevelopment, $item);

        return $production->artefacts()
            ->where(fn ($query) => $query
                ->when(!$isLaunched, fn ($query) => $query->orWhere(fn ($query) => $query->where('trade_unit_id', $tradeUnit->id)->where('state', '!=', ArtefactStateEnum::IN_PROCESS)))
                ->orWhere(fn ($query) => $query->where('organisation_id', $production->organisation_id)
                    ->whereRaw('lower(code) = ?', [strtolower($tradeUnit->code)])
                    ->where(fn ($query) => $query->whereNull('trade_unit_id')->orWhere('trade_unit_id', '!=', $tradeUnit->id))))
            ->first();
    }

    /**
     * The artefact's recipe as it is now: its steps with the raw materials each uses per unit, and the same
     * raw materials as one ingredient list with each one's share of the total in its unit (kg with kg,
     * litres with litres).
     *
     * @return array{steps: array<int, array<string, mixed>>, ingredients: array<int, array<string, mixed>>}
     */
    public static function recipe(Artefact $artefact): array
    {
        $recipeSteps = $artefact->recipeSteps()->with('manufactureTask')->get();
        $linesByStep = RecipeStepRawMaterial::whereIn('artefact_manufacture_task_id', $recipeSteps->pluck('id'))
            ->with('rawMaterial')
            ->orderBy('id')
            ->get()
            ->groupBy('artefact_manufacture_task_id');

        $steps = $recipeSteps->map(fn (ArtefactManufactureTask $step) => [
            'position'      => $step->position,
            'name'          => $step->name(),
            'raw_materials' => $linesByStep->get($step->id, collect())->map(fn (RecipeStepRawMaterial $line) => [
                'code'        => $line->rawMaterial->code,
                'description' => $line->rawMaterial->description,
                'unit'        => $line->rawMaterial->unit->value,
                'quantity'    => (float) $line->quantity_per_unit,
            ])->values()->all(),
        ])->values();

        $ingredients = $steps->pluck('raw_materials')->flatten(1)
            ->groupBy(fn (array $line) => $line['code'].'|'.$line['unit'])
            ->map(fn ($lines) => [...$lines->first(), 'quantity' => round($lines->sum('quantity'), 6)])
            ->values();
        $totals = $ingredients->groupBy('unit')->map(fn ($lines) => $lines->sum('quantity'));

        return [
            'steps'       => $steps->all(),
            'ingredients' => $ingredients->map(fn (array $line) => [
                ...$line,
                'percent' => $totals[$line['unit']] > 0 ? round($line['quantity'] / $totals[$line['unit']] * 100, 2) : null,
            ])->sortByDesc('quantity')->values()->all(),
        ];
    }

    /**
     * "LAVC-01 sample 02", how a sample is named in messages and activity.
     */
    public static function label(ProductDevelopmentSample $sample): string
    {
        $formulation = $sample->formulation;
        if ($formulation && $formulation->position > 1) {
            return __(':sku :formulation trial run :number', ['sku' => $sample->item->tradeUnit->code, 'formulation' => $formulation->name, 'number' => $sample->reference()]);
        }

        return __(':sku trial run :number', ['sku' => $sample->item->tradeUnit->code, 'number' => $sample->reference()]);
    }

    /**
     * The SKU's formulation stages in order; a SKU always has at least one.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductDevelopmentFormulation>
     */
    public static function formulations(ProductDevelopmentItem $item): \Illuminate\Database\Eloquent\Collection
    {
        return $item->formulations()->get();
    }

    /**
     * Every SKU starts with one stage; made when the SKU is created.
     */
    public static function createFirstFormulation(ProductDevelopmentItem $item): void
    {
        $item->formulations()->firstOrCreate(['position' => 1], [
            'product_development_id' => $item->product_development_id,
            'name'                   => __('Formulation'),
        ]);
    }

    /**
     * Stages are added or removed only while the development is formulating, never after it moved past it.
     */
    public static function canChangeFormulations(ProductDevelopment $productDevelopment): bool
    {
        return $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE
            && $productDevelopment->current_position <= \App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum::specsPosition();
    }

    /**
     * The approved trial run that is the SKU's final formulation: that of the stage typed as the finished product,
     * once every stage has an approved trial run. Never picked by position: a SKU with no finished product stage
     * has no final formulation.
     */
    public static function finalSample(ProductDevelopmentItem $item): ?ProductDevelopmentSample
    {
        return self::finalOf($item->formulations()->get(), $item->samples()->where('status', 'approved')->get());
    }

    /**
     * @param \Illuminate\Support\Collection<int, ProductDevelopmentFormulation> $formulations
     * @param \Illuminate\Support\Collection<int, ProductDevelopmentSample> $samples
     */
    public static function finalOf(\Illuminate\Support\Collection $formulations, \Illuminate\Support\Collection $samples): ?ProductDevelopmentSample
    {
        $approved = $samples->filter(fn (ProductDevelopmentSample $sample) => $sample->status === ProductDevelopmentSampleStatusEnum::APPROVED)->sortByDesc('number');
        if ($formulations->isEmpty()) {
            return $approved->first();
        }

        $byStage  = $approved->groupBy('product_development_formulation_id');
        $finished = self::finishedFormulation($formulations);
        if (!$finished || $formulations->contains(fn (ProductDevelopmentFormulation $formulation) => !$byStage->has($formulation->id))) {
            return null;
        }

        return $byStage->get($finished->id)->first();
    }

    /**
     * @param \Illuminate\Support\Collection<int, ProductDevelopmentFormulation> $formulations
     */
    public static function finishedFormulation(\Illuminate\Support\Collection $formulations): ?ProductDevelopmentFormulation
    {
        return $formulations->first(fn (ProductDevelopmentFormulation $formulation) => $formulation->type === ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT);
    }

    /**
     * The artefact a stage's trial runs are made on. The finished product is the SKU's own artefact; a blend or an
     * intermediate gets its own experiment in the factory the first time it is needed, coded after the SKU and
     * stage (ACAS-01-S1), so its recipe is kept apart and the finished product's recipe can use it as an
     * ingredient once it is approved.
     */
    public static function stageArtefact(ProductDevelopmentFormulation $formulation): ?Artefact
    {
        $item = $formulation->item;
        if (!$formulation->type->hasOwnArtefact()) {
            return $item->artefact;
        }
        if ($formulation->artefact && $formulation->artefact->state !== ArtefactStateEnum::CANCELLED) {
            return $formulation->artefact;
        }

        $production  = $formulation->productDevelopment->production;
        $skuArtefact = $item->artefact;
        if (!$production || !$skuArtefact) {
            return null;
        }

        $artefact = StoreArtefact::make()->action($production, array_filter([
            'code'               => self::stageArtefactCode($production->organisation_id, $item->tradeUnit->code, $formulation->position),
            'name'               => mb_substr($formulation->name.' – '.$item->tradeUnit->name, 0, 255),
            'state'              => ArtefactStateEnum::IN_PROCESS->value,
            'artefact_family_id' => $skuArtefact->artefact_family_id,
        ]));
        $formulation->update(['artefact_id' => $artefact->id]);
        $formulation->setRelation('artefact', $artefact);
        self::seedFromEarlierRun($formulation, $artefact);

        return $artefact;
    }

    /**
     * A new blend artefact starts from the latest run of the stage recorded in shares on another artefact (e.g. one
     * since cancelled), with the units per kg of blend it worked out. A run recorded per piece, on the SKU's artefact
     * before the stage was a blend (HELP-4838), is never copied: its quantities are per piece, and on an artefact whose
     * unit is 1 kg of blend they became 300 units of oil (HELP-4852).
     */
    private static function seedFromEarlierRun(ProductDevelopmentFormulation $formulation, Artefact $artefact): void
    {
        $earlierRun = $formulation->samples()->reorder()->where('artefact_id', '!=', $artefact->id)->whereNotNull('recipe->formulation')->orderByDesc('number')->first();
        $recipeStep = ArtefactManufactureTask::where('artefact_id', $artefact->id)->orderBy('position')->first();
        if (!$earlierRun || !$recipeStep) {
            return;
        }

        foreach ($earlierRun->recipe['formulation']['lines'] as $line) {
            $rawMaterial = RawMaterial::where('organisation_id', $artefact->organisation_id)->find($line['raw_material_id']);
            if ($rawMaterial && $line['units_per_kg'] !== null && $rawMaterial->artefact_id !== $artefact->id) {
                AttachRawMaterialToRecipeStep::make()->action($recipeStep, ['raw_material_id' => $rawMaterial->id, 'quantity_per_unit' => (float) $line['units_per_kg']]);
            }
        }
    }

    private static function stageArtefactCode(int $organisationId, string $skuCode, int $position): string
    {
        $base = $skuCode.'-S'.$position;
        $code = $base;
        for ($suffix = 2; Artefact::withTrashed()->where('organisation_id', $organisationId)->whereRaw('lower(code) = ?', [strtolower($code)])->exists(); $suffix++) {
            $code = $base.'-'.$suffix;
        }

        return $code;
    }

    /**
     * The stage being worked on: the first without an approved trial run, or the last one when all are done.
     */
    public static function currentFormulation(ProductDevelopmentItem $item): ProductDevelopmentFormulation
    {
        $formulations = self::formulations($item);
        if ($formulations->isEmpty()) {
            self::createFirstFormulation($item);
            $formulations = self::formulations($item);
        }
        $approved     = $item->samples()->where('status', 'approved')->pluck('product_development_formulation_id')->all();

        return $formulations->first(fn (ProductDevelopmentFormulation $formulation) => !in_array($formulation->id, $approved)) ?? $formulations->last();
    }

    public static function notifyReviewers(ProductDevelopmentSample $sample, User $actor): void
    {
        $productDevelopment = $sample->productDevelopment;
        $notifier           = NotifyProductDevelopmentUsers::make();
        $reviewers          = collect(ProductDevelopmentAccess::DESIGN_APPROVER_ROLES)
            ->flatMap(fn (ProductDevelopmentMemberRoleEnum $role) => $notifier->usersWithRole($productDevelopment, $role))
            ->push($productDevelopment->owner);

        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            $reviewers,
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            __(':sample of :code is ready for review', ['sample' => self::label($sample), 'code' => $productDevelopment->code]),
            $sample->notes ?: __('Open the Formulation & trial runs tab to approve it or ask for changes.'),
            tab: 'formulation',
            except: $actor,
        );
    }

    public static function notifyProduction(ProductDevelopmentSample $sample, User $actor): void
    {
        $productDevelopment = $sample->productDevelopment;
        $people             = NotifyProductDevelopmentUsers::make()->usersWithRole($productDevelopment, ProductDevelopmentMemberRoleEnum::PRODUCTION)
            ->push($sample->madeBy);
        $approved = $sample->status->value === 'approved';

        NotifyProductDevelopmentUsers::run(
            $productDevelopment,
            $people,
            UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK,
            $approved
                ? __(':sample of :code is approved', ['sample' => self::label($sample), 'code' => $productDevelopment->code])
                : __(':sample of :code needs changes', ['sample' => self::label($sample), 'code' => $productDevelopment->code]),
            $sample->review_comment ?: __('Open the Formulation & trial runs tab for details.'),
            tab: 'formulation',
            except: $actor,
        );
    }

    public static function approvedItemCount(ProductDevelopment $productDevelopment): int
    {
        return self::approvedItems($productDevelopment)->count();
    }

    /**
     * The SKUs whose every formulation stage has an approved trial run, one of them being the finished product.
     */
    public static function approvedItems(ProductDevelopment $productDevelopment): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $productDevelopment->items()
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('product_development_samples as approved')
                ->whereColumn('approved.product_development_item_id', 'product_development_items.id')
                ->where('approved.status', 'approved'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('product_development_formulations as finished')
                ->whereColumn('finished.product_development_item_id', 'product_development_items.id')
                ->where('finished.type', ProductDevelopmentFormulationTypeEnum::FINISHED_PRODUCT->value))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('product_development_formulations as formulation')
                ->whereColumn('formulation.product_development_item_id', 'product_development_items.id')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('product_development_samples as stage_sample')
                    ->whereColumn('stage_sample.product_development_formulation_id', 'formulation.id')
                    ->where('stage_sample.status', 'approved')));
    }

    public static function isEverySampleApproved(ProductDevelopment $productDevelopment): bool
    {
        $total = $productDevelopment->items()->count();

        return $total > 0 && self::approvedItemCount($productDevelopment) >= $total;
    }

    /**
     * Gives a new SKU the components picked on the create form; each SKU's list can be changed on its own later.
     *
     * @param array<int, array{trade_unit_id: int, quantity: float|int|string, note?: string|null}> $components
     */
    public static function addComponents(ProductDevelopmentItem $item, array $components): void
    {
        foreach ($components as $component) {
            if ((int) $component['trade_unit_id'] === $item->trade_unit_id) {
                continue;
            }
            $item->components()->create([
                'product_development_id' => $item->product_development_id,
                'trade_unit_id'          => $component['trade_unit_id'],
                'quantity'               => $component['quantity'],
                'note'                   => trim((string) ($component['note'] ?? '')) ?: null,
                'status'                 => ProductDevelopmentComponentStatusEnum::PENDING,
            ]);
        }
    }

    /**
     * Whether a checklist template applies to a development of this kind. An in-house development buys
     * nothing from a supplier, so it skips the commercial terms, everything saved on a supplier product and
     * the factory photos.
     */
    public static function templateApplies(ProductDevelopmentChecklistTemplate $template, ProductDevelopmentKindEnum $kind): bool
    {
        if ($kind !== ProductDevelopmentKindEnum::RECIPE) {
            return true;
        }

        return $template->role !== ProductDevelopmentMemberRoleEnum::PURCHASING
            && $template->key !== UploadProductDevelopmentFactoryPhoto::KEY
            && !ProductDevelopmentTargetFieldEnum::tryFrom((string) $template->target_field)?->isOnSupplierProduct();
    }

    /**
     * Who fills in an item for a development of this kind: the agent's specs go to the production team in-house.
     */
    public static function roleFor(ProductDevelopmentMemberRoleEnum $role, ProductDevelopmentKindEnum $kind): ProductDevelopmentMemberRoleEnum
    {
        return $kind === ProductDevelopmentKindEnum::RECIPE && $role === ProductDevelopmentMemberRoleEnum::AGENT ? ProductDevelopmentMemberRoleEnum::PRODUCTION : $role;
    }
}
