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
use App\Actions\Production\Artefact\StoreArtefact;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopmentChecklistTemplate;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\SysAdmin\User;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\RecipeStepRawMaterial;

/**
 * An in-house development makes each SKU as an artefact of its production unit: the recipe lives there and
 * the development reads it, so nothing is entered twice.
 */
class ProductDevelopmentRecipes
{
    /**
     * Links the SKU to an experiment of the production unit: a new artefact in process, or one already in
     * process for its trade unit. A launched product, or another product holding the code, is never taken
     * over: the development would change a recipe the factory makes for sale. That SKU is left without an
     * artefact and the Formulation tab says why.
     */
    public static function linkArtefact(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): ?Artefact
    {
        $production = $productDevelopment->production;
        if (!$production) {
            return null;
        }

        $tradeUnit = $item->tradeUnit;
        if (self::blockingArtefact($productDevelopment, $item)) {
            return null;
        }

        $artefact = $production->artefacts()->where('trade_unit_id', $tradeUnit->id)->where('state', ArtefactStateEnum::IN_PROCESS)->first()
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
     * The artefact that stops the SKU from getting an experiment: a launched or cancelled one for its trade
     * unit, or any artefact of another trade unit already using its code.
     */
    public static function blockingArtefact(ProductDevelopment $productDevelopment, ProductDevelopmentItem $item): ?Artefact
    {
        $production = $productDevelopment->production;
        $tradeUnit  = $item->tradeUnit;
        if (!$production || !$tradeUnit) {
            return null;
        }

        return $production->artefacts()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('trade_unit_id', $tradeUnit->id)->where('state', '!=', ArtefactStateEnum::IN_PROCESS))
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
        $artefact->loadMissing('manufactureTasks');
        $linesByStep = RecipeStepRawMaterial::whereIn('artefact_manufacture_task_id', $artefact->manufactureTasks->pluck('pivot.id'))
            ->with('rawMaterial')
            ->orderBy('id')
            ->get()
            ->groupBy('artefact_manufacture_task_id');

        $steps = $artefact->manufactureTasks->map(fn (ManufactureTask $task) => [
            'position'      => $task->pivot->position,
            'name'          => $task->name,
            'raw_materials' => $linesByStep->get($task->pivot->id, collect())->map(fn (RecipeStepRawMaterial $line) => [
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
        return __(':sku trial run :number', ['sku' => $sample->item->tradeUnit->code, 'number' => $sample->reference()]);
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
        return $productDevelopment->samples()->where('status', 'approved')->distinct()->count('product_development_item_id');
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
