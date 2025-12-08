<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Oct 2025 14:00:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\DestroyTransaction;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderInsurance extends OrgAction
{
    use HasOrderHydrators;
    use WithActionUpdate;
    use WithChargeTransactions;
    use WithOrderingEditAuthorisation;

    public function handle(Order $order, array $modelData): Order
    {
        $order = $this->update($order, $modelData);
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::INSURANCE)->where('state', ChargeStateEnum::ACTIVE)->first();

        if ($charge) {

            $chargeApplies = Arr::get($modelData, 'has_insurance', false);
            $chargeTransaction = null;
            $chargeTransactionID = DB::table('transactions')->where('order_id', $order->id)
                ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
                ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::INSURANCE->value)->value('transactions.id');

            if ($chargeTransactionID) {
                $chargeTransaction = Transaction::find($chargeTransactionID);
            }

            if ($chargeApplies) {
                $chargeAmount = Arr::get($charge->settings, 'amount');
                if ($chargeTransaction) {
                    $this->updateChargeTransaction($chargeTransaction, $charge, $chargeAmount);
                } else {
                    $this->storeChargeTransaction($order, $charge, $chargeAmount);
                }
            } elseif ($chargeTransaction) {
                DestroyTransaction::run($chargeTransaction);
            }

        }

        return $order;
    }

    public function rules(): array
    {
        return [
            'has_insurance' => ['required', 'boolean'],
        ];
    }

    public function action(Order $order, array $modelData): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order, $modelData);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
