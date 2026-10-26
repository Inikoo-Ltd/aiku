<?php

/*
 * Author Louis Perez
 * Created on 26-10-2026-17h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentComponentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentComponent;
use App\Models\SysAdmin\User;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What purchasing needs to start producing an in-house development: every component across its SKUs, how
 * many are needed, whether it is approved for purchasing, and whether purchasing has confirmed it is bought.
 * Aiku does not track these purchases, so the confirmation is the record.
 */
class GetProductDevelopmentBuyersList
{
    use AsAction;
    use WithProductDevelopmentAuthorisation;

    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, User $viewer): array
    {
        $canConfirm = $this->productDevelopmentAccess()->canApproveComponents($viewer, $productDevelopment);
        $components = $productDevelopment->components()->with(['tradeUnit', 'item.tradeUnit', 'purchaseConfirmedBy'])->get();

        $rows = $components->groupBy('trade_unit_id')->map(function (Collection $lines) use ($canConfirm) {
            /** @var ProductDevelopmentComponent $first */
            $first     = $lines->first();
            $tradeUnit = $first->tradeUnit;
            $approved  = $lines->every(fn (ProductDevelopmentComponent $line) => $line->status === ProductDevelopmentComponentStatusEnum::APPROVED);
            $confirmed = $lines->every(fn (ProductDevelopmentComponent $line) => $line->purchase_confirmed_at !== null);

            return [
                'trade_unit_id' => $tradeUnit->id,
                'code'          => $tradeUnit->code,
                'name'          => $tradeUnit->name,
                'route'         => ['name' => 'grp.trade_units.units.show', 'parameters' => ['tradeUnit' => $tradeUnit->slug]],
                'skus'          => $lines->map(fn (ProductDevelopmentComponent $line) => $line->item->tradeUnit->code)->unique()->values()->all(),
                'required'      => (float) $lines->sum('quantity'),
                'approved'      => $approved,
                'status'        => ProductDevelopmentComponentStatusEnum::labels()[$approved ? 'approved' : 'pending'],
                'purchase'      => $confirmed ? [
                    'confirmed_at' => $first->purchase_confirmed_at?->toIso8601String(),
                    'confirmed_by' => $first->purchaseConfirmedBy?->contact_name ?: $first->purchaseConfirmedBy?->username,
                    'note'         => $first->purchase_note,
                ] : null,
                'can_confirm'   => $canConfirm && $approved,
                'can_undo'      => $canConfirm && $confirmed,
            ];
        })->sortBy('code')->values();

        return [
            'rows'   => $rows->all(),
            'totals' => [
                'components' => $rows->count(),
                'approved'   => $rows->where('approved', true)->count(),
                'confirmed'  => $rows->filter(fn (array $row) => $row['purchase'] !== null)->count(),
            ],
            'route'  => ['name' => 'grp.models.product_development.component_purchase', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
        ];
    }
}
