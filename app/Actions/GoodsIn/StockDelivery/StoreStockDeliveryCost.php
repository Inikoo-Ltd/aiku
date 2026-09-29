<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
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
    use WithProcurementEditAuthorisation;

    private StockDelivery $stockDelivery;

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::enum(StockDeliveryCostTypeEnum::class)],
            'label'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount'      => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'received_at' => ['sometimes', 'nullable', 'date'],
            'is_na'       => ['sometimes', 'boolean'],
            'currency_id' => ['sometimes', 'nullable', 'exists:currencies,id'],
            'exchange'    => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $type = StockDeliveryCostTypeEnum::tryFrom($this->get('type'));

        if ($type && $type !== StockDeliveryCostTypeEnum::EXTRA && $this->stockDelivery->costs()->where('type', $type)->exists()) {
            $validator->errors()->add('type', __('This cost is already recorded in this stock delivery'));
        }
    }

    public function handle(StockDelivery $stockDelivery, array $modelData): StockDeliveryCost
    {
        $cost = $stockDelivery->costs()->create(
            array_merge(self::withExchange($stockDelivery, $modelData), [
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
