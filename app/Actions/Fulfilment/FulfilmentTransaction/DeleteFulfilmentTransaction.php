<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Jul 2024 23:36:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentTransaction;

use App\Actions\Fulfilment\PalletDelivery\CalculatePalletDeliveryNet;
use App\Actions\Fulfilment\PalletDelivery\Hydrators\PalletDeliveryHydrateTransactions;
use App\Actions\Fulfilment\PalletReturn\CalculatePalletReturnNet;
use App\Actions\Fulfilment\PalletReturn\Hydrators\PalletReturnHydrateTransactions;
use App\Actions\Fulfilment\RecurringBillTransaction\DeleteRecurringBillTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Models\Fulfilment\FulfilmentTransaction;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class DeleteFulfilmentTransaction extends OrgAction
{
    use WithActionUpdate;
    use WithFulfilmentShopEditAuthorisation;


    private FulfilmentTransaction $fulfilmentTransaction;

    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentTransaction $fulfilmentTransaction): void
    {
        $recurringBillTransaction = $fulfilmentTransaction->recurringBillTransaction;

        $fulfilmentTransaction->delete();


        if ($recurringBillTransaction) {
            DeleteRecurringBillTransaction::make()->action($recurringBillTransaction);
        }

        if ($fulfilmentTransaction->parent_type == 'PalletDelivery') {
            PalletDeliveryHydrateTransactions::run($fulfilmentTransaction->parent);
            CalculatePalletDeliveryNet::run($fulfilmentTransaction->parent);
        } else {
            PalletReturnHydrateTransactions::run($fulfilmentTransaction->parent);
            CalculatePalletReturnNet::run($fulfilmentTransaction->parent);
        }
    }


    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $recurringBillTransaction = $this->fulfilmentTransaction->recurringBillTransaction;
        if ($recurringBillTransaction && $recurringBillTransaction->recurringBill->status != RecurringBillStatusEnum::CURRENT) {
            $validator->errors()->add('recurring_bill_state', 'Cannot delete transaction when associated recurring bill has been invoiced');
        }
    }


    /**
     * @throws \Throwable
     */
    public function asController(FulfilmentTransaction $fulfilmentTransaction, ActionRequest $actionRequest): void
    {
        $this->fulfilmentTransaction = $fulfilmentTransaction;
        $this->initialisationFromFulfilment($fulfilmentTransaction->fulfilment, $actionRequest);

        $this->handle($fulfilmentTransaction);
    }


    /**
     * @throws \Throwable
     */
    public function action(FulfilmentTransaction $fulfilmentTransaction): void
    {
        $this->asAction              = true;
        $this->fulfilmentTransaction = $fulfilmentTransaction;
        $this->initialisationFromFulfilment($fulfilmentTransaction->fulfilment, []);

        $this->handle($fulfilmentTransaction);
    }

}
