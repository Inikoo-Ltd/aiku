<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 18:59:01 Central Indonesia Time, Sanur, Bali, Indonesia
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

class FetchAuroraNoProductTransactionHasOfferComponents
{
    use AsAction;
    use WithAuroraParsers;

    private SourceOrganisationService $organisationSource;

    public function handle(SourceOrganisationService $organisationSource, int $source_id, Order $order): ?TransactionHasOfferAllowance
    {
        $this->organisationSource = $organisationSource;

        $noProductTransactionHasOfferComponentData = $organisationSource->fetchNoProductTransactionHasOfferComponent(id: $source_id, order: $order);
        if (! $noProductTransactionHasOfferComponentData) {
            return null;
        }

        $transactionHasOfferComponent = TransactionHasOfferAllowance::where('source_alt_id', $noProductTransactionHasOfferComponentData['transaction_has_offer_component']['source_alt_id'])->first();

        if ($transactionHasOfferComponent) {
            $transactionHasOfferComponent = UpdateTransactionHasOfferAllowance::make()->action(
                transactionHasOfferComponent: $transactionHasOfferComponent,
                modelData: $noProductTransactionHasOfferComponentData,
                hydratorsDelay: 5,
                strict: false
            );
        }

        if (! $transactionHasOfferComponent) {
            $transactionHasOfferComponent = StoreTransactionHasOfferAllowance::make()->action(
                transaction: $noProductTransactionHasOfferComponentData['transaction'],
                offerAllowance: $noProductTransactionHasOfferComponentData['offer_allowance'],
                modelData: $noProductTransactionHasOfferComponentData['transaction_has_offer_component'],
                hydratorsDelay: 5,
                strict: false
            );
        }

        return $transactionHasOfferComponent;
    }
}
