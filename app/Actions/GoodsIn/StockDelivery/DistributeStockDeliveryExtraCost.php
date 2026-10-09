<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 28 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Actions\GoodsIn\StockDelivery\Hydrators\StockDeliveriesHydrateCosts;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Http\Resources\Procurement\StockDeliveryResource;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DistributeStockDeliveryExtraCost extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;
    public const DISTRIBUTION_EQUALLY = 'equally';
    public const DISTRIBUTION_BY_VALUE = 'by_value';
    public const DISTRIBUTION_BY_WEIGHT = 'by_weight';

    private const LEFT_OUT_STATES = [StockDeliveryItemStateEnum::CANCELLED->value, StockDeliveryItemStateEnum::NOT_RECEIVED->value];

    private StockDelivery $stockDelivery;

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
        self::distribute($stockDelivery, 'cost_extra', (float) $modelData['amount'], $modelData['type']);

        if (Arr::has($stockDelivery->data, 'costing_hand_split.cost_extra')) {
            $stockDelivery->update(['data' => Arr::except($stockDelivery->data, 'costing_hand_split.cost_extra')]);
        }

        StockDeliveriesHydrateCosts::run($stockDelivery);

        return $stockDelivery->refresh();
    }

    public static function distribute(StockDelivery $stockDelivery, string $field, float $amount, string $type = self::DISTRIBUTION_BY_VALUE): void
    {
        self::clearLeftOutItems($stockDelivery, $field);

        $items = self::sharingItems($stockDelivery)->with('orgStock.stock')->orderBy('id')->get();

        if ($items->isEmpty()) {
            return;
        }

        if ($type === self::DISTRIBUTION_BY_WEIGHT && self::itemsWithoutWeight($items)->isNotEmpty()) {
            $type = self::DISTRIBUTION_BY_VALUE;
        }

        $shares = self::getShares($items, (int) round($amount * 100), $type);

        foreach ($items as $index => $item) {
            self::setItemCost($item, $field, $shares[$index] / 100);
        }
    }

    /**
     * @return array{basis: string, missing_count: int, missing_codes: array<int, string>}
     */
    public static function shippingBasis(StockDelivery $stockDelivery): array
    {
        $missing = self::itemsWithoutWeight(self::sharingItems($stockDelivery)->with('orgStock.stock')->orderBy('id')->get());

        return [
            'basis'         => $missing->isEmpty() ? self::DISTRIBUTION_BY_WEIGHT : self::DISTRIBUTION_BY_VALUE,
            'missing_count' => $missing->count(),
            'missing_codes' => $missing->map(fn (StockDeliveryItem $item) => $item->orgStock?->code)->filter()->unique()->take(5)->values()->all(),
        ];
    }

    private static function sharingItems(StockDelivery $stockDelivery): HasMany
    {
        return $stockDelivery->items()->whereNotIn('state', self::LEFT_OUT_STATES);
    }

    private static function clearLeftOutItems(StockDelivery $stockDelivery, string $field): void
    {
        $stockDelivery->items()->whereIn('state', self::LEFT_OUT_STATES)->get()
            ->each(fn (StockDeliveryItem $item) => self::setItemCost($item, $field, 0));
    }

    private static function setItemCost(StockDeliveryItem $item, string $field, float $share): void
    {
        $costs         = [
            'cost_extra'    => (float) $item->cost_extra,
            'cost_shipping' => (float) $item->cost_shipping,
            'cost_duties'   => (float) $item->cost_duties,
        ];
        $costs[$field] = $share;

        $item->update([
            $field       => $share,
            'cost_total' => (float) $item->cost_items
                + $costs['cost_extra']
                + $costs['cost_shipping']
                + $costs['cost_duties']
                + (float) $item->cost_tax,
        ]);
    }

    private static function itemsWithoutWeight(Collection $items): Collection
    {
        return $items->filter(fn (StockDeliveryItem $item) => (float) $item->orgStock?->stock?->gross_weight <= 0);
    }

    private static function getWeight(StockDeliveryItem $item): float
    {
        $unitQuantity = $item->checked_at ? (float) $item->unit_quantity_checked : (float) $item->unit_quantity;

        return $unitQuantity / $item->unitsPerSko() * (float) $item->orgStock?->stock?->gross_weight;
    }

    private static function getShares(Collection $items, int $amountInCents, string $type): array
    {
        $weights = $type === self::DISTRIBUTION_BY_WEIGHT
            ? $items->map(fn (StockDeliveryItem $item) => max(0, self::getWeight($item)))->all()
            : [];

        if (array_sum($weights) <= 0) {
            $weights = $items->map(fn (StockDeliveryItem $item) => max(0, (float) ($item->cost_items ?? $item->net_amount)))->all();
        }

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
