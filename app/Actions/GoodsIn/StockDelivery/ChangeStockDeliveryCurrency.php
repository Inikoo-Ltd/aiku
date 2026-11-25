<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Helpers\Currency;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Moves a delivery to the currency its supplier invoiced in. What every line and cost is worth in the
 * organisation's and the group's currency stays as it was: the amounts in the delivery's currency are converted
 * and the exchange rates follow. A cost typed in the old currency keeps its amount and currency, with the rate
 * to the new one. The purchase orders stay in the currency they were placed in.
 */
class ChangeStockDeliveryCurrency
{
    use AsAction;

    private const array ITEM_AMOUNTS = ['net_amount', 'gross_amount', 'cost_items', 'cost_extra', 'cost_shipping', 'cost_duties', 'cost_tax', 'cost_total'];

    public function handle(StockDelivery $stockDelivery, Currency $currency): StockDelivery
    {
        if ($stockDelivery->currency_id == $currency->id) {
            return $stockDelivery;
        }

        if ($blockers = self::blockers($stockDelivery)) {
            throw ValidationException::withMessages(['currency' => implode(' ', $blockers)]);
        }

        $oldCurrency = $stockDelivery->currency;
        $orgExchange = (float) $stockDelivery->org_exchange;
        $exchange    = $currency->id == $stockDelivery->organisation->currency_id && $orgExchange > 0
            ? $orgExchange
            : (float) GetCurrencyExchange::run($oldCurrency, $currency);

        if ($exchange <= 0) {
            throw ValidationException::withMessages(['currency' => __('The exchange rate from :from to :to could not be found.', ['from' => $oldCurrency->code, 'to' => $currency->code])]);
        }

        DB::transaction(function () use ($stockDelivery, $currency, $oldCurrency, $exchange) {
            $exchanges = [
                'org_exchange' => $stockDelivery->org_exchange === null ? null : (float) $stockDelivery->org_exchange / $exchange,
                'grp_exchange' => $stockDelivery->grp_exchange === null ? null : (float) $stockDelivery->grp_exchange / $exchange,
            ];

            $stockDelivery->items()->update([
                ...$exchanges,
                ...Arr::mapWithKeys(self::ITEM_AMOUNTS, fn (string $field) => [$field => DB::raw("round($field * $exchange, 2)")]),
            ]);

            $stockDelivery->costs()->get()->each(fn (StockDeliveryCost $cost) => $cost->update(match (true) {
                !$cost->currency_id                => ['currency_id' => $oldCurrency->id, 'exchange' => $exchange],
                $cost->currency_id == $currency->id => ['currency_id' => null, 'exchange' => null],
                default                            => ['exchange' => $cost->exchange === null ? null : (float) $cost->exchange * $exchange],
            }));

            $stockDelivery->update([
                'currency_id' => $currency->id,
                'data'        => Arr::except($stockDelivery->data, 'costing_hand_split'),
                ...$exchanges,
            ]);

            EvaluateStockDeliveryCosting::run($stockDelivery);
        });

        $stockDelivery->refresh();

        $stockDelivery->auditEvent     = 'currency_changed';
        $stockDelivery->isCustomEvent  = true;
        $stockDelivery->auditCustomOld = ['currency' => $oldCurrency->code];
        $stockDelivery->auditCustomNew = ['currency' => $currency->code, 'exchange' => $exchange];
        Event::dispatch(new AuditCustom($stockDelivery));
        $stockDelivery->isCustomEvent  = false;
        $stockDelivery->auditCustomOld = $stockDelivery->auditCustomNew = [];

        return $stockDelivery;
    }

    /**
     * Money already recorded in the delivery's currency that a change of currency would leave saying something
     * else: it has to be undone by hand first.
     *
     * @return array<int, string>
     */
    public static function blockers(StockDelivery $stockDelivery): array
    {
        return array_keys(array_filter([
            __('This stock delivery is costed.')               => (bool) $stockDelivery->is_costed,
            __('Payments to the supplier are recorded on it.') => $stockDelivery->supplierPayments()->exists(),
            __('Payments to the agent are recorded on it.')    => $stockDelivery->agentLedgerEntries()->exists(),
            __('It has an agent invoice.')                     => $stockDelivery->agentInvoice()->exists(),
            __('A supplier invoice is already entered on it.') => $stockDelivery->supplierInvoice()->exists(),
            __('It has claims.')                               => $stockDelivery->claims()->exists(),
        ]));
    }
}
