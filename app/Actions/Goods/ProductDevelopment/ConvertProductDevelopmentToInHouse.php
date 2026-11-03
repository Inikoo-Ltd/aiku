<?php

/*
 * Author Louis Perez
 * Created on 02-11-2026-11h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\OrgAction;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a sourced development into in-house production before its specs are done, keeping the work already
 * done: commercial terms make way for formulation, the agent's specs go to the production team, the supplier
 * and agent are cleared, and each SKU gets its experiment in the production unit's Development room.
 */
class ConvertProductDevelopmentToInHouse extends OrgAction
{
    public function handle(ProductDevelopment $productDevelopment, User $user, Production $production): ProductDevelopment
    {
        DB::transaction(function () use ($productDevelopment, $production, $user) {
            $old = [
                'kind'        => $productDevelopment->kind->value,
                'supplier_id' => $productDevelopment->supplier_id,
                'agent_id'    => $productDevelopment->agent_id,
            ];

            $productDevelopment->updateQuietly([
                'kind'          => ProductDevelopmentKindEnum::RECIPE,
                'production_id' => $production->id,
                'supplier_id'   => null,
                'agent_id'      => null,
            ]);

            $this->replaceCommercialTermsWithFormulation($productDevelopment);
            $this->adaptChecklist($productDevelopment);

            $productDevelopment->items()->whereNotNull('supplier_product_id')->update(['supplier_product_id' => null]);
            foreach ($productDevelopment->items()->with('tradeUnit')->get() as $item) {
                /** @var ProductDevelopmentItem $item */
                ProductDevelopmentRecipes::linkArtefact($productDevelopment->setRelation('production', $production), $item, $user);
            }

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::CONVERTED_TO_IN_HOUSE, $old, [
                'kind'       => ProductDevelopmentKindEnum::RECIPE->value,
                'production' => $production->name,
            ]);
        });

        $productDevelopment->refresh();
        ProductDevelopmentHydrateChecklist::run($productDevelopment);
        CreateProductDevelopmentTask::make()->syncProgress($productDevelopment, $user);
        CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment, $user);

        return $productDevelopment;
    }

    private function replaceCommercialTermsWithFormulation(ProductDevelopment $productDevelopment): void
    {
        $productDevelopment->stages()->where('stage', ProductDevelopmentStageEnum::COMMERCIAL_TERMS->value)->delete();
        $productDevelopment->stages()->where('stage', ProductDevelopmentStageEnum::PRODUCT_SPECS->value)
            ->update(['role' => ProductDevelopmentStageEnum::PRODUCT_SPECS->roleFor(ProductDevelopmentKindEnum::RECIPE)->value]);

        $formulation = ProductDevelopmentStageEnum::FORMULATION;
        $sibling     = $productDevelopment->stages()->where('position', $formulation->position())->orderBy('sub_position')->first();
        $isCurrent   = $productDevelopment->current_position === $formulation->position();

        $productDevelopment->stages()->create([
            'position'     => $formulation->position(),
            'sub_position' => $formulation->subPosition(),
            'stage'        => $formulation,
            'role'         => $formulation->roleFor(ProductDevelopmentKindEnum::RECIPE),
            'state'        => $isCurrent ? ProductDevelopmentStageStateEnum::IN_PROGRESS : ProductDevelopmentStageStateEnum::PENDING,
            'started_at'   => $isCurrent ? now() : null,
            'due_at'       => $sibling?->due_at,
        ]);
    }

    /**
     * In-house work has no purchasing terms, factory photos or supplier product fields; the agent's specs go to production.
     */
    private function adaptChecklist(ProductDevelopment $productDevelopment): void
    {
        $productDevelopment->checklistItems()->get()->each(function (ProductDevelopmentChecklistItem $checklistItem) {
            $isSourcedOnly = $checklistItem->role === ProductDevelopmentMemberRoleEnum::PURCHASING
                || $checklistItem->key === UploadProductDevelopmentFactoryPhoto::KEY
                || ProductDevelopmentTargetFieldEnum::tryFrom((string) $checklistItem->target_field)?->isOnSupplierProduct();

            if ($isSourcedOnly) {
                $checklistItem->delete();

                return;
            }

            $role = ProductDevelopmentRecipes::roleFor($checklistItem->role, ProductDevelopmentKindEnum::RECIPE);
            if ($role !== $checklistItem->role) {
                $checklistItem->update(['role' => $role]);
            }
        });
    }

    public static function blockedReason(ProductDevelopment $productDevelopment): ?string
    {
        return match (true) {
            $productDevelopment->kind !== ProductDevelopmentKindEnum::PRODUCT => __('This development is already in-house production'),
            $productDevelopment->current_position > ProductDevelopmentStageEnum::specsPosition() => __('Its specs are already done, so it can no longer change to in-house production'),
            ProductDevelopmentPurchaseOrders::rows($productDevelopment) !== [] => __('Unlink its purchase orders first'),
            default => null,
        };
    }





    public function action(ProductDevelopment $productDevelopment, User $user, Production $production): ProductDevelopment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($productDevelopment->group, []);

        return $this->handle($productDevelopment, $user, $production);
    }

}
