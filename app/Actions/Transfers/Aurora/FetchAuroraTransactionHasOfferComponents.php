<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 18:24:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Discounts\TransactionHasOfferAllowance\StoreTransactionHasOfferAllowance;
use App\Actions\Discounts\TransactionHasOfferAllowance\UpdateTransactionHasOfferAllowance;
use App\Models\Discounts\TransactionHasOfferAllowance;
use App\Models\Ordering\Order;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraTransactionHasOfferComponents
{
    use AsAction;
    use WithAuroraParsers;

    private SourceOrganisationService $organisationSource;

    public function handle(SourceOrganisationService $organisationSource, int $source_id, Order $order): ?TransactionHasOfferAllowance
    {
        $this->organisationSource = $organisationSource;

        $transactionHasOfferComponentData = $organisationSource->fetchTransactionHasOfferComponent(id: $source_id, order: $order);
        if (!$transactionHasOfferComponentData) {
            return null;
        }


        $transactionHasOfferComponent      = TransactionHasOfferAllowance::where('source_id', $transactionHasOfferComponentData['transaction_has_offer_component']['source_id'])->first();

        if ($transactionHasOfferComponent) {
            $transactionHasOfferComponent = UpdateTransactionHasOfferAllowance::make()->action(
                transactionHasOfferComponent: $transactionHasOfferComponent,
                modelData: $transactionHasOfferComponentData,
                hydratorsDelay: 5,
                strict: false
            );
        }

        if (!$transactionHasOfferComponent) {
            $transactionHasOfferComponent = StoreTransactionHasOfferAllowance::make()->action(
                transaction: $transactionHasOfferComponentData['transaction'],
                offerAllowance: $transactionHasOfferComponentData['offer_allowance'],
                modelData: $transactionHasOfferComponentData['transaction_has_offer_component'],
                hydratorsDelay: 5,
                strict: false
            );
        }

        return $transactionHasOfferComponent;
    }



}
