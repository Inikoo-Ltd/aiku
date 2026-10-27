<?php

/*
 * Author Louis Perez
 * Created on 20-10-2026-10h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\ActionRequest;

/**
 * The purchase orders that can be linked to a development by hand: its supplier's, newest first.
 */
class SearchProductDevelopmentPurchaseOrders extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const int LIMIT = 20;

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function handle(ProductDevelopment $productDevelopment, string $query): array
    {
        return PurchaseOrder::query()
            ->where('group_id', $productDevelopment->group_id)
            ->when($productDevelopment->supplier_id, fn (Builder $builder) => $builder->where('supplier_id', $productDevelopment->supplier_id))
            ->when($query !== '', fn (Builder $builder) => $builder->whereStartWith('reference', $query))
            ->orderByDesc('date')
            ->limit(self::LIMIT)
            ->with('organisation:id,name')
            ->get(['id', 'organisation_id', 'reference', 'state', 'date'])
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'value' => $purchaseOrder->id,
                'label' => trim($purchaseOrder->reference.' · '.$purchaseOrder->organisation?->name.' · '.(PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? '').' · '.$purchaseOrder->date?->format('j M Y'), ' ·'),
            ])
            ->all();
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): array
    {
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, (string) ($this->validatedData['q'] ?? ''));
    }
}
