<?php

/*
 * Author Louis Perez
 * Created on 02-11-2026-13h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentOverview;
use App\Actions\OrgAction;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistLevelEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentChecklistTemplate;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns an in-house development back into a sourced product before it passes the gate and before anything was
 * made or bought for it: formulation makes way for commercial terms, the production team's specs go back to the
 * agent, the purchasing items return and the components are dropped. Its experiments stay in the Development
 * room, where Production can discard them or a later switch back reuses them.
 */
class ConvertProductDevelopmentToSourced extends OrgAction
{
    public function handle(ProductDevelopment $productDevelopment, User $user): ProductDevelopment
    {
        DB::transaction(function () use ($productDevelopment) {
            $production = $productDevelopment->production;

            $productDevelopment->updateQuietly([
                'kind'          => ProductDevelopmentKindEnum::PRODUCT,
                'production_id' => null,
            ]);

            $this->replaceFormulationWithCommercialTerms($productDevelopment);
            $this->adaptChecklist($productDevelopment);

            $productDevelopment->components()->delete();
            $productDevelopment->items()->whereNotNull('artefact_id')->update(['artefact_id' => null]);

            RecordProductDevelopmentActivity::record($productDevelopment, RecordProductDevelopmentActivity::CONVERTED_TO_SOURCED, [
                'kind'       => ProductDevelopmentKindEnum::RECIPE->value,
                'production' => $production?->name,
            ], [
                'kind' => ProductDevelopmentKindEnum::PRODUCT->value,
            ]);
        });

        $productDevelopment->refresh();
        ProductDevelopmentHydrateChecklist::run($productDevelopment);
        CreateProductDevelopmentTask::make()->syncProgress($productDevelopment, $user);
        CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment, $user);

        return $productDevelopment;
    }

    private function replaceFormulationWithCommercialTerms(ProductDevelopment $productDevelopment): void
    {
        $productDevelopment->stages()->where('stage', ProductDevelopmentStageEnum::FORMULATION->value)->delete();
        $productDevelopment->stages()->where('stage', ProductDevelopmentStageEnum::PRODUCT_SPECS->value)
            ->update(['role' => ProductDevelopmentStageEnum::PRODUCT_SPECS->roleFor(ProductDevelopmentKindEnum::PRODUCT)->value]);

        $commercialTerms = ProductDevelopmentStageEnum::COMMERCIAL_TERMS;
        $sibling         = $productDevelopment->stages()->where('position', $commercialTerms->position())->orderBy('sub_position')->first();
        $isCurrent       = $productDevelopment->current_position === $commercialTerms->position();

        $productDevelopment->stages()->create([
            'position'     => $commercialTerms->position(),
            'sub_position' => $commercialTerms->subPosition(),
            'stage'        => $commercialTerms,
            'role'         => $commercialTerms->roleFor(ProductDevelopmentKindEnum::PRODUCT),
            'state'        => $isCurrent ? ProductDevelopmentStageStateEnum::IN_PROGRESS : ProductDevelopmentStageStateEnum::PENDING,
            'started_at'   => $isCurrent ? now() : null,
            'due_at'       => $sibling?->due_at,
        ]);
    }

    /**
     * The agent's specs, handed to production when it went in-house, go back to the agent; the items only a
     * sourced product has (purchasing terms, factory photos, supplier product fields) are added again.
     */
    private function adaptChecklist(ProductDevelopment $productDevelopment): void
    {
        $productDevelopment->checklistItems()
            ->where('role', ProductDevelopmentMemberRoleEnum::PRODUCTION->value)
            ->whereIn('template_id', ProductDevelopmentChecklistTemplate::where('role', ProductDevelopmentMemberRoleEnum::AGENT->value)->select('id'))
            ->update(['role' => ProductDevelopmentMemberRoleEnum::AGENT->value]);

        $existing = $productDevelopment->checklistItems()->get(['template_id', 'product_development_item_id'])
            ->map(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->template_id.'-'.$checklistItem->product_development_item_id)
            ->flip();

        $templates = ProductDevelopmentChecklistTemplate::where('group_id', $productDevelopment->group_id)
            ->where('category', $productDevelopment->checklist_category)
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->reject(fn (ProductDevelopmentChecklistTemplate $template) => ProductDevelopmentRecipes::templateApplies($template, ProductDevelopmentKindEnum::RECIPE));

        $items = $productDevelopment->items()->get();
        foreach ($templates as $template) {
            $targets = $template->level === ProductDevelopmentChecklistLevelEnum::FAMILY ? [null] : $items->all();
            foreach ($targets as $item) {
                if ($existing->has($template->id.'-'.$item?->id)) {
                    continue;
                }
                $productDevelopment->checklistItems()->create([
                    'product_development_item_id' => $item?->id,
                    'template_id'                 => $template->id,
                    'role'                        => $template->role,
                    'level'                       => $template->level,
                    'key'                         => $template->key,
                    'label'                       => $template->label,
                    'required'                    => $template->required,
                    'target_field'                => $template->target_field,
                ]);
            }
        }
    }

    public static function blockedReason(ProductDevelopment $productDevelopment): ?string
    {
        return match (true) {
            $productDevelopment->kind !== ProductDevelopmentKindEnum::RECIPE => __('This development is already a sourced product'),
            $productDevelopment->current_position > GetProductDevelopmentOverview::GATE_POSITION => __('It has passed the gate, so it can no longer change to a sourced product'),
            $productDevelopment->samples()->exists() => __('It already has trial runs, so it stays in-house production'),
            $productDevelopment->components()->whereNotNull('purchase_confirmed_at')->exists() || $productDevelopment->componentPurchaseOrders()->exists() => __('Some of its components are already bought or on a purchase order'),
            default => null,
        };
    }


    public function action(ProductDevelopment $productDevelopment, User $user): ProductDevelopment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($productDevelopment->group, []);

        return $this->handle($productDevelopment, $user);
    }
}
