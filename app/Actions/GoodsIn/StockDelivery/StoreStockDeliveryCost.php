<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCost;
use App\Models\Helpers\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class StoreStockDeliveryCost extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;

    private StockDelivery $stockDelivery;

    public function rules(): array
    {
        return array_merge(self::serviceInvoiceRules($this->asAction), [
            'type'        => ['required', Rule::enum(StockDeliveryCostTypeEnum::class)],
            'category'    => ['sometimes', 'nullable', Rule::in(array_column(StockDeliveryServiceInvoiceTypeEnum::extraOptions(), 'value'))],
            'label'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount'      => self::amountRules($this->asAction),
            'received_at' => ['sometimes', 'nullable', 'date'],
            'is_na'       => ['sometimes', 'boolean'],
            'currency_id' => ['sometimes', 'nullable', 'exists:currencies,id'],
            'exchange'    => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'stock_delivery_item_id' => ['sometimes', 'nullable', 'integer', Rule::exists('stock_delivery_items', 'id')->where('stock_delivery_id', $this->stockDelivery->id)],
        ], self::waitingRules());
    }

    public static function waitingRules(): array
    {
        return [
            'is_waiting_for_invoice'   => ['sometimes', 'boolean'],
            'invoice_expected_in_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
        ];
    }

    /**
     * A row waits for its invoice until the user says it arrived or marks it not applicable. An amount typed
     * while it waits is an estimate: it counts in the costing, so the delivery can be costed, and stays flagged
     * until the real invoice replaces it. The days the user expects the invoice in are kept as a date.
     */
    public static function withWaiting(array $modelData, ?StockDeliveryCost $cost = null): array
    {
        $hasDays = array_key_exists('invoice_expected_in_days', $modelData);
        $days    = $modelData['invoice_expected_in_days'] ?? null;
        unset($modelData['invoice_expected_in_days']);

        if (!empty($modelData['is_na']) || ($modelData['is_waiting_for_invoice'] ?? null) === false) {
            $retractsEstimate = empty($modelData['is_na']) && $cost?->is_estimate && !array_key_exists('amount', $modelData) && !array_key_exists('received_at', $modelData);

            return array_merge($modelData, ['is_waiting_for_invoice' => false, 'invoice_expected_at' => null, 'is_estimate' => false], $retractsEstimate ? ['received_at' => null] : []);
        }

        if ($cost?->is_na && !array_key_exists('is_na', $modelData)) {
            return array_merge($modelData, ['is_waiting_for_invoice' => false]);
        }

        if (!($modelData['is_waiting_for_invoice'] ?? $cost?->is_waiting_for_invoice)) {
            return $modelData;
        }

        if ($hasDays) {
            $modelData['invoice_expected_at'] = $days === null ? null : now()->addDays((int) $days)->toDateString();
        }

        $amount                     = array_key_exists('amount', $modelData) ? $modelData['amount'] : $cost?->amount;
        $modelData['is_estimate']   = $amount !== null;

        if ($amount !== null && !array_key_exists('received_at', $modelData) && !$cost?->received_at) {
            $modelData['received_at'] = now();
        }

        if ($amount === null && array_key_exists('amount', $modelData)) {
            $modelData['received_at'] = null;
        }

        return $modelData;
    }

    /**
     * Only the service invoices sync marks the rows it writes.
     */
    /**
     * A person types at most cents: 6.938 is a mistyped 6,938, never an amount (HELP-4329).
     */
    public static function amountRules(bool $asAction): array
    {
        return ['sometimes', 'nullable', 'numeric', 'gte:0', ...($asAction ? [] : ['decimal:0,2'])];
    }

    public static function amountMessages(): array
    {
        return [
            'amount.decimal' => __('An amount has at most 2 decimals. Type thousands without a dot or comma: 6938, not 6.938'),
        ];
    }

    public function getValidationMessages(): array
    {
        return self::amountMessages();
    }

    public static function serviceInvoiceRules(bool $asAction): array
    {
        return $asAction ? [
            'from_service_invoices'             => ['sometimes', 'boolean'],
            'stock_delivery_service_invoice_id' => ['sometimes', 'nullable', 'exists:stock_delivery_service_invoices,id'],
        ] : [];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && $this->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('This stock delivery is costed, an accounting manager can change it with Update costing'));
        }

        $type = StockDeliveryCostTypeEnum::tryFrom($this->get('type'));

        if (!$this->asAction && $type === StockDeliveryCostTypeEnum::EXTRA && !$this->get('category')) {
            $validator->errors()->add('category', __('Choose what kind of expense this is'));
        }

        if ($type && $type !== StockDeliveryCostTypeEnum::EXTRA && $this->stockDelivery->costs()->where('type', $type)->exists()) {
            $validator->errors()->add('type', __('This cost is already recorded in this stock delivery'));
        }
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDeliveryCost
    {
        $cost = $stockDelivery->costs()->create(
            array_merge(self::withExchange($stockDelivery, self::withWaiting($modelData)), [
                'group_id'        => $stockDelivery->group_id,
                'organisation_id' => $stockDelivery->organisation_id,
            ])
        );

        EvaluateStockDeliveryCosting::run($stockDelivery);

        return $cost;
    }

    public static function withExchange(StockDelivery $stockDelivery, array $modelData): array
    {
        if (!array_key_exists('currency_id', $modelData)) {
            return $modelData;
        }

        if (!$modelData['currency_id'] || $modelData['currency_id'] == $stockDelivery->currency_id) {
            return array_merge($modelData, ['currency_id' => null, 'exchange' => null]);
        }

        if (empty($modelData['exchange'])) {
            $modelData['exchange'] = GetCurrencyExchange::run(Currency::find($modelData['currency_id']), $stockDelivery->currency);
        }

        if (!$modelData['exchange']) {
            throw ValidationException::withMessages(['exchange' => __('The exchange rate could not be found, please enter it')]);
        }

        return $modelData;
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDeliveryCost
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, array $modelData): StockDeliveryCost
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
