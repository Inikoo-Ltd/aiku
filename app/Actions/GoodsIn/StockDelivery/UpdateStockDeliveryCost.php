<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithStockDeliveryCostingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Models\GoodsIn\StockDeliveryCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockDeliveryCost extends OrgAction
{
    use WithStockDeliveryCostingEditAuthorisation;
    use WithActionUpdate;

    private StockDeliveryCost $stockDeliveryCost;

    public function rules(): array
    {
        return array_merge(StoreStockDeliveryCost::serviceInvoiceRules($this->asAction), [
            'label'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'category'    => ['sometimes', 'nullable', Rule::in(array_column(StockDeliveryServiceInvoiceTypeEnum::extraOptions(), 'value'))],
            'amount'      => StoreStockDeliveryCost::amountRules($this->asAction),
            'received_at' => ['sometimes', 'nullable', 'date'],
            'is_na'       => ['sometimes', 'boolean'],
            'currency_id' => ['sometimes', 'nullable', 'exists:currencies,id'],
            'exchange'    => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ], StoreStockDeliveryCost::waitingRules(), StoreStockDeliveryCost::tariffCodeRules());
    }

    public function getValidationMessages(): array
    {
        return StoreStockDeliveryCost::amountMessages();
    }

    public function afterValidator(Validator $validator): void
    {
        if (!$this->asAction && $this->stockDeliveryCost->stockDelivery->is_costed) {
            $validator->errors()->add('state', __('This stock delivery is costed, an accounting manager can change it with Update costing'));
        }

        if (!$this->asAction && $this->stockDeliveryCost->from_service_invoices && array_diff(array_intersect(array_keys($validator->getData()), array_keys($this->rules())), ['applies_to_tariff_codes'])) {
            $validator->errors()->add('type', __('This cost comes from the service invoices, change the invoice instead'));
        }

        $category = $this->has('category') ? StockDeliveryServiceInvoiceTypeEnum::tryFrom((string) $this->get('category')) : $this->stockDeliveryCost->category;
        StoreStockDeliveryCost::validateTariffCodes($validator, $this->stockDeliveryCost->stockDelivery, $category);
    }

    public function handle(StockDeliveryCost $stockDeliveryCost, array $modelData): StockDeliveryCost
    {
        $hasCurrency   = array_key_exists('currency_id', $modelData);
        $keepsCurrency = !$hasCurrency || $modelData['currency_id'] == $stockDeliveryCost->currency_id;

        if ($keepsCurrency && empty($modelData['exchange'])) {
            unset($modelData['currency_id'], $modelData['exchange']);
        }

        $stockDelivery     = $stockDeliveryCost->stockDelivery;
        $stockDeliveryCost = $this->update($stockDeliveryCost, StoreStockDeliveryCost::withTariffCodes($stockDelivery, StoreStockDeliveryCost::withExchange($stockDelivery, StoreStockDeliveryCost::withWaiting($modelData, $stockDeliveryCost)), $stockDeliveryCost));

        if ($stockDeliveryCost->wasChanged(['amount', 'currency_id', 'exchange', 'is_na', 'received_at', 'category', 'applies_to_item_ids'])) {
            EvaluateStockDeliveryCosting::run($stockDeliveryCost->stockDelivery);
        }

        return $stockDeliveryCost;
    }

    public function asController(StockDeliveryCost $stockDeliveryCost, ActionRequest $request): StockDeliveryCost
    {
        $this->stockDeliveryCost = $stockDeliveryCost;
        $this->initialisation($stockDeliveryCost->organisation, $request);

        return $this->handle($stockDeliveryCost, $this->validatedData);
    }

    public function action(StockDeliveryCost $stockDeliveryCost, array $modelData): StockDeliveryCost
    {
        $this->asAction          = true;
        $this->stockDeliveryCost = $stockDeliveryCost;
        $this->initialisation($stockDeliveryCost->organisation, $modelData);

        return $this->handle($stockDeliveryCost, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
