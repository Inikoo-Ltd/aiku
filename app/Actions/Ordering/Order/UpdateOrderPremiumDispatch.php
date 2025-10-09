<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
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

class UpdateOrderPremiumDispatch extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;
    use WithChargeTransactions;


    public function handle(Order $order, array $modelData): void
    {
        $order = $this->update($order, $modelData);
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::PREMIUM)->where('state', ChargeStateEnum::ACTIVE)->first();

        if ($charge) {

            $chargeApplies = Arr::get($modelData, 'is_premium_dispatch', false);
            $chargeTransaction   = null;
            $chargeTransactionID = DB::table('transactions')->where('order_id', $order->id)
                ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
                ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::PREMIUM->value)->value('transactions.id');

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

    }



    public function rules(): array
    {
        return [
            'is_premium_dispatch' => ['required', 'boolean'],
        ];
    }


    public function action(Order $order, array $modelData): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        $this->handle($order, $modelData);
    }


    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);

        $this->handle($order, $this->validatedData);
    }
}
