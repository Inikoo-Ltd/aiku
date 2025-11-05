<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 16:01:17 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\TransactionHasOfferAllowance;

use App\Actions\Discounts\Offer\Hydrators\OfferHydrateOrders;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOrders;
use App\Actions\Discounts\OfferAllowance\Hydrators\OfferAllowanceHydrateOrders;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateOffers;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Discounts\OfferAllowance;
use App\Models\Discounts\TransactionHasOfferAllowance;
use App\Models\Ordering\Transaction;

class StoreTransactionHasOfferAllowance extends OrgAction
{
    use WithNoStrictRules;

    public function handle(Transaction $transaction, OfferAllowance $offerAllowance, array $modelData): TransactionHasOfferAllowance
    {
        data_set($modelData, 'offer_campaign_id', $offerAllowance->offer_campaign_id);
        data_set($modelData, 'offer_id', $offerAllowance->offer_id);
        data_set($modelData, 'offer_allowance_id', $offerAllowance->id);

        data_set($modelData, 'model_type', $transaction->model_type);
        data_set($modelData, 'model_id', $transaction->model_id);

        data_set($modelData, 'order_id', $transaction->order_id);
        data_set($modelData, 'transaction_id', $transaction->id);



        $transactionHasOfferAllowance = TransactionHasOfferAllowance::create($modelData);

        OfferAllowanceHydrateOrders::dispatch($transactionHasOfferAllowance->offerAllowance);
        OfferHydrateOrders::dispatch($transactionHasOfferAllowance->offer);
        OfferCampaignHydrateOrders::dispatch($transactionHasOfferAllowance->offerCampaign);
        OrderHydrateOffers::dispatch($transaction->order);


        return $transactionHasOfferAllowance;
    }

    public function rules(): array
    {
        $rules = [
            'is_pinned'             => ['sometimes', 'boolean'],
            'info'                  => ['sometimes', 'nullable', 'string', 'max:10000'],
            'data'                  => ['sometimes', 'nullable', 'array'],
            'discounted_amount'     => ['sometimes', 'nullable', 'numeric'],
            'discounted_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'precursor'             => ['sometimes', 'nullable', 'string'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Transaction $transaction, OfferAllowance $offerAllowance, array $modelData, int $hydratorsDelay = 0, bool $strict = true): TransactionHasOfferAllowance
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($offerAllowance->shop, $modelData);

        return $this->handle($transaction, $offerAllowance, $modelData);
    }
}
