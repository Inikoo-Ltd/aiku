<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\Helpers\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

trait WithStockDeliveryServiceInvoiceAllocations
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->organisation->type === OrganisationTypeEnum::AGENT) {
            return false;
        }

        return $request->user()->authTo([
            "procurement.{$this->organisation->id}.edit",
            "accounting.{$this->organisation->id}.edit",
            "org-supervisor.{$this->organisation->id}.accounting",
        ]);
    }

    protected function allocationRules(): array
    {
        return [
            'allocations'                     => ['sometimes', 'array', 'min:1', 'max:50'],
            'allocations.*.stock_delivery_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('stock_deliveries', 'id')->where('organisation_id', $this->organisation->id)->whereNull('deleted_at'),
            ],
            'allocations.*.amount'            => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:999999999999'],
            'attachments'                     => ['sometimes', 'array', 'max:10'],
            'attachments.*'                   => ['file', 'max:50000'],
        ];
    }

    /**
     * Hand split amounts must cover the whole bill; a bill on a costed delivery waits for Update costing.
     *
     * @param array<int> $currentStockDeliveryIds
     */
    protected function validateAllocations(Validator $validator, array $currentStockDeliveryIds = [], ?float $total = null): void
    {
        $allocations = $this->get('allocations');
        if (is_array($allocations)) {
            $amounts = array_filter(array_column($allocations, 'amount'), fn ($amount) => $amount !== null && $amount !== '');
            if ($amounts && count($amounts) !== count($allocations)) {
                $validator->errors()->add('allocations', __('Enter the amount of every delivery, or of none to split it by value'));
            } elseif ($amounts && $total !== null && abs(array_sum($amounts) - $total) > 0.005) {
                $validator->errors()->add('allocations', __('The amounts of the deliveries must add up to the total of the invoice'));
            }
        }

        if ($this->asAction) {
            return;
        }

        $stockDeliveryIds = array_merge($currentStockDeliveryIds, array_column(is_array($allocations) ? $allocations : [], 'stock_delivery_id'));
        if ($stockDeliveryIds && StockDelivery::whereIn('id', $stockDeliveryIds)->where('is_costed', true)->exists()) {
            $validator->errors()->add('allocations', __('A delivery of this invoice is costed, an accounting manager can change it with Update costing'));
        }
    }

    protected function orgExchange(int $currencyId, ?float $exchange): float
    {
        if ($currencyId === $this->organisation->currency_id) {
            return 1;
        }

        $exchange = $exchange ?: GetCurrencyExchange::run(Currency::find($currencyId), $this->organisation->currency);
        if (!$exchange) {
            throw ValidationException::withMessages(['exchange' => __('The exchange rate could not be found, please enter it')]);
        }

        return $exchange;
    }

    /**
     * Without hand amounts the bill is split by the value of each delivery's goods in the organisation currency,
     * equally when no delivery has a value yet.
     *
     * @param array<int, array{stock_delivery_id: int, amount?: float|null}> $allocations
     * @return array<int, array{amount: float}>
     */
    public static function splitAllocations(array $allocations, float $total): array
    {
        $ids = array_map('intval', array_column($allocations, 'stock_delivery_id'));

        if ($allocations && collect($allocations)->every(fn (array $allocation) => isset($allocation['amount']) && $allocation['amount'] !== '')) {
            return collect($allocations)->mapWithKeys(fn (array $allocation) => [(int) $allocation['stock_delivery_id'] => ['amount' => round((float) $allocation['amount'], 2)]])->all();
        }

        $values = StockDelivery::whereIn('id', $ids)
            ->withSum(['items as goods_value' => fn (Builder $query) => $query->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)], 'net_amount')
            ->get()
            ->mapWithKeys(fn (StockDelivery $stockDelivery) => [$stockDelivery->id => max(0, (float) $stockDelivery->goods_value * ((float) $stockDelivery->org_exchange ?: 1))]);

        return self::largestRemainderSplit($ids, $values->all(), $total);
    }

    /**
     * Splits in whole cents by weight; the cents lost to rounding go to the largest remainders, so no share is negative.
     *
     * @param array<int> $ids
     * @param array<int, float> $weights
     * @return array<int, array{amount: float}>
     */
    public static function largestRemainderSplit(array $ids, array $weights, float $total): array
    {
        $weights = array_map(fn (int $id) => max(0, (float) ($weights[$id] ?? 0)), array_combine($ids, $ids));
        $sum     = array_sum($weights);
        if ($sum <= 0) {
            $weights = array_fill_keys($ids, 1.0);
            $sum     = count($ids);
        }

        $cents      = (int) round($total * 100);
        $shares     = [];
        $remainders = [];
        foreach ($weights as $id => $weight) {
            $exact           = $cents * $weight / $sum;
            $shares[$id]     = (int) floor($exact);
            $remainders[$id] = $exact - $shares[$id];
        }
        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, $cents - array_sum($shares)) as $id) {
            $shares[$id]++;
        }

        $split = [];
        foreach ($ids as $id) {
            $split[$id] = ['amount' => round($shares[$id] / 100, 2)];
        }

        return $split;
    }

    protected function saveAttachments(StockDeliveryServiceInvoice $serviceInvoice, array $modelData): void
    {
        foreach (Arr::get($modelData, 'attachments', []) as $file) {
            SaveModelAttachment::make()->action($serviceInvoice, [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => $file->getClientOriginalExtension(),
                'scope'        => PurchaseOrderAttachmentScopeEnum::INVOICE->value,
                'caption'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            ]);
        }
    }

    /**
     * @param array<int> $stockDeliveryIds
     */
    protected function syncStockDeliveries(array $stockDeliveryIds): void
    {
        foreach (StockDelivery::whereIn('id', array_unique($stockDeliveryIds))->get() as $stockDelivery) {
            SyncStockDeliveryServiceInvoiceCosts::run($stockDelivery);
        }
    }
}
