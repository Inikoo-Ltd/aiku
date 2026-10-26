<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-00h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentKindEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundEnum;
use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Actions\Goods\ProductDevelopment\Recipe\ProductDevelopmentRecipes;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistItemStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentChecklistItem;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\Goods\ProductDevelopmentStage;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductDevelopmentOverview
{
    use AsAction;

    public const array ROLE_CARDS = [
        ProductDevelopmentMemberRoleEnum::BUYER,
        ProductDevelopmentMemberRoleEnum::AGENT,
        ProductDevelopmentMemberRoleEnum::PURCHASING,
        ProductDevelopmentMemberRoleEnum::COMPLIANCE,
        ProductDevelopmentMemberRoleEnum::DESIGNER,
    ];

    public const int GATE_POSITION = 2;

    public const int WAITING_ON_LIMIT = 8;

    private bool $canSeeCosts = true;

    private function isHiddenCost(ProductDevelopmentChecklistItem $item): bool
    {
        return !$this->canSeeCosts && (bool) ProductDevelopmentTargetFieldEnum::tryFrom((string) $item->target_field)?->isCost();
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, bool $canSeeCosts = true): array
    {
        $this->canSeeCosts = $canSeeCosts;
        $productDevelopment->loadMissing(['stages', 'items.tradeUnit', 'items.supplierProduct']);
        $checklistItems = $productDevelopment->checklistItems()->with('item.tradeUnit')->get();
        $required       = $checklistItems->where('required', true);

        return [
            'strip'      => $this->strip($productDevelopment, $required),
            'roles'      => $this->roleCards($productDevelopment, $required),
            'products'   => $this->products($productDevelopment, $required),
            'waiting_on' => $this->waitingOn($productDevelopment, $required),
        ];
    }

    /**
     * @param Collection<int, ProductDevelopmentChecklistItem> $items
     *
     * @return array{done: int, total: int, missing: int}
     */
    private function tally(Collection $items): array
    {
        $missing = $items->filter(fn (ProductDevelopmentChecklistItem $item) => $item->state === ProductDevelopmentChecklistItemStateEnum::MISSING)->count();

        return ['done' => $items->count() - $missing, 'total' => $items->count(), 'missing' => $missing];
    }

    /**
     * The designer's work, open from day one: the concept, then one artwork per SKU in each design section.
     *
     * @return array{done: int, total: int, missing: int}
     */
    private function designTally(ProductDevelopment $productDevelopment): array
    {
        $itemIds  = $productDevelopment->items()->pluck('id');
        $total    = 1 + $productDevelopment->designSections()->count() * $itemIds->count();
        $done     = (ProductDevelopmentDesignRounds::isApproved($productDevelopment, ProductDevelopmentDesignRoundEnum::CONCEPT) ? 1 : 0)
            + $productDevelopment->artworks()->whereIn('product_development_item_id', $itemIds)->where('status', ProductDevelopmentArtworkStatusEnum::APPROVED->value)->count();

        return ['done' => $done, 'total' => $total, 'missing' => $total - $done];
    }

    /**
     * @param array{done: int, total: int, missing: int} ...$tallies
     *
     * @return array{done: int, total: int, missing: int}
     */
    private function sumTallies(array ...$tallies): array
    {
        return [
            'done'    => array_sum(array_column($tallies, 'done')),
            'total'   => array_sum(array_column($tallies, 'total')),
            'missing' => array_sum(array_column($tallies, 'missing')),
        ];
    }

    /**
     * How many SKUs of an in-house development have an approved sample.
     *
     * @return array{done: int, total: int, missing: int}
     */
    private function sampleTally(ProductDevelopment $productDevelopment): array
    {
        $total = $productDevelopment->items->count();
        $done  = ProductDevelopmentRecipes::approvedItemCount($productDevelopment);

        return ['done' => $done, 'total' => $total, 'missing' => $total - $done];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function strip(ProductDevelopment $productDevelopment, Collection $required): array
    {
        $stageLabels = ProductDevelopmentStageEnum::labels();
        $segments    = [];

        $stages = $productDevelopment->stages->values();
        foreach ($stages as $index => $stage) {
            /** @var ProductDevelopmentStage $stage */
            $tally = match (true) {
                $stage->stage === ProductDevelopmentStageEnum::FORMULATION => $this->sampleTally($productDevelopment),
                $stage->position <= self::GATE_POSITION => $this->tally($required->where('role', $stage->role)),
                default => null,
            };

            $segments[] = [
                'id'        => $stage->id,
                'key'       => $stage->stage->value,
                'number'    => $stage->position.($stage->sub_position ?? ''),
                'label'     => $stageLabels[$stage->stage->value],
                'state'     => $stage->state->value,
                'is_late'   => $stage->state === ProductDevelopmentStageStateEnum::IN_PROGRESS && $stage->due_at?->isPast() && !$stage->due_at->isToday(),
                'due_at'    => $stage->due_at?->toDateString(),
                'progress'  => $tally,
            ];

            if ($stage->position === self::GATE_POSITION && ($stages->get($index + 1)?->position ?? PHP_INT_MAX) > self::GATE_POSITION) {
                $gateOpen   = $productDevelopment->current_position > self::GATE_POSITION;
                $segments[] = [
                    'key'      => 'gate',
                    'number'   => '—',
                    'label'    => __('Gate'),
                    'state'    => $gateOpen ? ProductDevelopmentStageStateEnum::COMPLETED->value : 'locked',
                    'is_late'  => false,
                    'due_at'   => null,
                    'progress' => null,
                ];
            }
        }

        return $segments;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roleCards(ProductDevelopment $productDevelopment, Collection $required): array
    {
        $roleLabels   = ProductDevelopmentMemberRoleEnum::labels();
        $lastReminder = $this->lastReminder($productDevelopment);

        $roles = $productDevelopment->kind === ProductDevelopmentKindEnum::RECIPE
            ? [ProductDevelopmentMemberRoleEnum::BUYER, ProductDevelopmentMemberRoleEnum::PRODUCTION, ProductDevelopmentMemberRoleEnum::COMPLIANCE, ProductDevelopmentMemberRoleEnum::DESIGNER]
            : self::ROLE_CARDS;

        return collect($roles)->map(function (ProductDevelopmentMemberRoleEnum $role) use ($productDevelopment, $required, $roleLabels, $lastReminder) {
            $tally           = match ($role) {
                ProductDevelopmentMemberRoleEnum::DESIGNER => $this->designTally($productDevelopment),
                ProductDevelopmentMemberRoleEnum::PRODUCTION => $this->sumTallies($this->tally($required->where('role', $role)), $this->sampleTally($productDevelopment)),
                default => $this->tally($required->where('role', $role)),
            };
            $startsAfterGate = false;

            return [
                'role'              => $role->value,
                'label'             => $roleLabels[$role->value],
                'starts_after_gate' => $startsAfterGate,
                'reminded'          => $lastReminder && in_array($role->value, $lastReminder['roles'], true) ? ['at' => $lastReminder['at'], 'by' => $lastReminder['by']] : null,
                ...$tally,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(ProductDevelopment $productDevelopment, Collection $required): array
    {
        $waitingForGate = $productDevelopment->current_position <= self::GATE_POSITION;
        $stageLabel     = ProductDevelopmentStageEnum::positionLabel($productDevelopment->current_position);
        $specsRole      = ProductDevelopmentStageEnum::PRODUCT_SPECS->roleFor($productDevelopment->kind);

        return $productDevelopment->items->map(function (ProductDevelopmentItem $item) use ($required, $waitingForGate, $stageLabel, $specsRole) {
            $itemRequired = $required->where('product_development_item_id', $item->id);

            return [
                'id'               => $item->id,
                'code'             => $item->tradeUnit->code,
                'name'             => $item->tradeUnit->name,
                'route'            => [
                    'name'       => 'grp.trade_units.units.show',
                    'parameters' => ['tradeUnit' => $item->tradeUnit->slug],
                ],
                'supplier_product' => $item->supplierProduct?->code,
                'specs'            => $this->tally($itemRequired->where('role', $specsRole)),
                'compliance'       => $this->tally($itemRequired->where('role', ProductDevelopmentMemberRoleEnum::COMPLIANCE)),
                'label_status'     => $waitingForGate ? __('Waiting for gate') : $stageLabel,
                'still_missing'    => $itemRequired
                    ->filter(fn (ProductDevelopmentChecklistItem $checklistItem) => $checklistItem->state === ProductDevelopmentChecklistItemStateEnum::MISSING && !$this->isHiddenCost($checklistItem))
                    ->pluck('label')
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function waitingOn(ProductDevelopment $productDevelopment, Collection $required): array
    {
        $roleLabels   = ProductDevelopmentMemberRoleEnum::labels();
        $lastReminder = $this->lastReminder($productDevelopment);
        $roleDueAt    = $productDevelopment->stages
            ->groupBy(fn (ProductDevelopmentStage $stage) => $stage->role->value)
            ->map(fn (Collection $stages) => $stages->sortBy('position')->first()?->due_at);

        return $required
            ->filter(fn (ProductDevelopmentChecklistItem $item) => $item->state === ProductDevelopmentChecklistItemStateEnum::MISSING && !$this->isHiddenCost($item))
            ->groupBy(fn (ProductDevelopmentChecklistItem $item) => $item->role->value.'|'.$item->key)
            ->map(function (Collection $items) use ($roleLabels, $roleDueAt, $lastReminder) {
                /** @var ProductDevelopmentChecklistItem $first */
                $first = $items->first();
                $dueAt = $roleDueAt->get($first->role->value);
                $skus  = $items->map(fn (ProductDevelopmentChecklistItem $item) => $item->item?->tradeUnit?->code)->filter()->values();

                return [
                    'label'   => $first->label,
                    'role'    => $roleLabels[$first->role->value],
                    'skus'    => $skus->count() > 3 ? __(':count SKUs', ['count' => $skus->count()]) : $skus->implode(', '),
                    'due_at'  => $dueAt?->toDateString(),
                    'is_late' => $dueAt?->isPast() && !$dueAt->isToday(),
                    'tab'     => $first->role === ProductDevelopmentMemberRoleEnum::COMPLIANCE ? 'compliance' : ($first->level->value === 'family' ? 'brief' : 'products'),
                    'target'  => 'pd-check-'.$first->id,
                    'reminded' => $lastReminder && in_array($first->role->value, $lastReminder['roles'], true),
                ];
            })
            ->sortBy(fn (array $row) => $row['due_at'] ?? '9999-12-31')
            ->take(self::WAITING_ON_LIMIT)
            ->values()
            ->all();
    }

    /**
     * The last "Send reminders": when, by whom and which roles it went to.
     *
     * @return array{at: string, by: string, roles: array<int, string>}|null
     */
    private function lastReminder(ProductDevelopment $productDevelopment): ?array
    {
        $sent = Arr::get($productDevelopment->data ?? [], 'reminders_sent');
        if (!$sent) {
            return null;
        }

        return [
            'at'    => $sent['at'],
            'by'    => $sent['by'] ?? '',
            'roles' => $sent['role_keys'] ?? array_keys(array_intersect(ProductDevelopmentMemberRoleEnum::labels(), $sent['roles'] ?? [])),
        ];
    }
}
