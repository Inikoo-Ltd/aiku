<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-16h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentArtworks;
use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductDevelopmentStageGate
{
    use AsAction;

    /**
     * @return array{can_move: bool, reason: string|null, missing: array<string, int>}
     */
    public function handle(ProductDevelopment $productDevelopment): array
    {
        if ($productDevelopment->status !== ProductDevelopmentStatusEnum::ACTIVE) {
            return ['can_move' => false, 'reason' => __('The development is not active'), 'missing' => []];
        }

        if ($productDevelopment->current_position > ProductDevelopmentStageEnum::lastPosition()) {
            return ['can_move' => false, 'reason' => __('All stages are done'), 'missing' => []];
        }

        if ($productDevelopment->current_position === ProductDevelopmentStageEnum::CONCEPT->position()
            && !ProductDevelopmentDesignRounds::isSettled($productDevelopment, ProductDevelopmentDesignRoundEnum::CONCEPT)) {
            return ['can_move' => false, 'reason' => __('Approve a version of the concept first'), 'missing' => []];
        }

        if ($productDevelopment->current_position === ProductDevelopmentStageEnum::ARTWORK->position() && !ProductDevelopmentArtworks::isAllApproved($productDevelopment)) {
            return ['can_move' => false, 'reason' => __('Approve the artwork of every SKU in every design section first'), 'missing' => []];
        }

        if ($productDevelopment->current_position === ProductDevelopmentStageEnum::FINAL_APPROVAL->position() && !SignOffProductDevelopment::isFullySigned($productDevelopment)) {
            $roleLabels = ProductDevelopmentMemberRoleEnum::labels();
            $waiting    = collect(SignOffProductDevelopment::signOffs($productDevelopment))->filter(fn ($signOff) => empty($signOff))->keys()->map(fn (string $role) => $roleLabels[$role]);

            return ['can_move' => false, 'reason' => __('Waiting for sign-off: :roles', ['roles' => $waiting->implode(', ')]), 'missing' => []];
        }

        $gatedRoles = $this->rolesDueBy($productDevelopment->current_position);

        $missing = $productDevelopment->checklistItems()
            ->get()
            ->filter(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->isMissing() && in_array($checklistItem->role, $gatedRoles, true))
            ->countBy(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->role->value)
            ->all();

        if ($missing === []) {
            if ($productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE
                && $productDevelopment->current_position === ProductDevelopmentStageEnum::FORMULATION->position()
                && !ProductDevelopmentRecipes::isEverySampleApproved($productDevelopment)) {
                return ['can_move' => false, 'reason' => __('Approve a trial run of every SKU first (:done of :total)', ['done' => ProductDevelopmentRecipes::approvedItemCount($productDevelopment), 'total' => $productDevelopment->items()->count()]), 'missing' => []];
            }

            return ['can_move' => true, 'reason' => null, 'missing' => []];
        }

        $roleLabels = ProductDevelopmentMemberRoleEnum::labels();
        $parts      = collect($missing)->map(fn (int $count, string $role) => $roleLabels[$role].' '.$count)->implode(', ');

        return [
            'can_move' => false,
            'reason'   => __(':count required items missing (:parts)', ['count' => array_sum($missing), 'parts' => $parts]),
            'missing'  => $missing,
        ];
    }

    /**
     * @return array<int, ProductDevelopmentMemberRoleEnum>
     */
    private function rolesDueBy(int $position): array
    {
        return collect(ProductDevelopmentStageEnum::cases())
            ->filter(fn (ProductDevelopmentStageEnum $stage) => $stage->position() <= $position)
            ->map(fn (ProductDevelopmentStageEnum $stage) => $stage->role())
            ->unique(fn (ProductDevelopmentMemberRoleEnum $role) => $role->value)
            ->values()
            ->all();
    }
}
