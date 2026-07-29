<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DistributeStockDeliveryExtraCost extends OrgAction
{
    public const DISTRIBUTION_EQUALLY = 'equally';
    public const DISTRIBUTION_BY_VALUE = 'by_value';

    private StockDelivery $stockDelivery;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gte:0'],
            'type'   => ['required', Rule::in([self::DISTRIBUTION_EQUALLY, self::DISTRIBUTION_BY_VALUE])],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->stockDelivery->state !== StockDeliveryStateEnum::PLACED || $this->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('You can only distribute extra costs while the costing is in progress'));
        }
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            return $stockDelivery;
        }

        $shares = $this->getShares($items, (int) round((float) $modelData['amount'] * 100), $modelData['type']);

        foreach ($items as $index => $item) {
            $extra = $shares[$index] / 100;

            $item->update([
                'cost_extra' => $extra,
                'cost_total' => (float) $item->cost_items
                    + $extra
                    + (float) $item->cost_shipping
                    + (float) $item->cost_duties
                    + (float) $item->cost_tax,
            ]);
        }

        StockDeliveriesHydrateCosts::run($stockDelivery);

        return $stockDelivery->refresh();
    }

    private function getShares(Collection $items, int $amountInCents, string $type): array
    {
        $weights = $items->map(fn (StockDeliveryItem $item) => max(0, (float) ($item->cost_items ?? $item->net_amount)))->all();

        if ($type === self::DISTRIBUTION_EQUALLY || array_sum($weights) <= 0) {
            $weights = array_fill(0, $items->count(), 1);
        }

        $totalWeight = array_sum($weights);
        $shares      = [];
        $remainders  = [];

        foreach ($weights as $index => $weight) {
            $exact            = $amountInCents * $weight / $totalWeight;
            $shares[$index]   = (int) floor($exact);
            $remainders[$index] = $exact - $shares[$index];
        }

        arsort($remainders);

        $leftover = $amountInCents - array_sum($shares);

        foreach (array_keys($remainders) as $index) {
            if ($leftover <= 0) {
                break;
            }

            $shares[$index]++;
            $leftover--;
        }

        ksort($shares);

        return $shares;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDelivery
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function jsonResponse(StockDelivery $stockDelivery): StockDeliveryResource
    {
        return new StockDeliveryResource($stockDelivery);
    }
}
