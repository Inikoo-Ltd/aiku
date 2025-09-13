<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\DestroyTransaction;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderExtraPacking extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    public function handle(Order $order, array $modelData): Order
    {
        $order = $this->update($order, $modelData);
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::PACKING)->where('state', ChargeStateEnum::ACTIVE)->first();

        if ($charge) {

            $chargeApplies = Arr::get($modelData, 'has_extra_packing', false);
            $chargeTransaction   = null;
            $chargeTransactionID = DB::table('transactions')->where('order_id', $order->id)
                ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
                ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::PACKING->value)->value('transactions.id');

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


    private function storeChargeTransaction(Order $order, Charge $charge, $chargeAmount): Transaction
    {
        return StoreTransaction::run(
            $order,
            $charge->historicAsset,
            [
                'quantity_ordered' => 1,
                'gross_amount'     => $chargeAmount,
                'net_amount'       => $chargeAmount,

            ],
            false
        );
    }


    private function updateChargeTransaction(Transaction $transaction, Charge $charge, $chargeAmount): Transaction
    {
        return UpdateTransaction::run(
            $transaction,
            [
                'model_id'          => $charge->id,
                'asset_id'          => $charge->asset_id,
                'historic_asset_id' => $charge->historicAsset->id,
                'gross_amount'      => $chargeAmount ?? 0,
                'net_amount'        => $chargeAmount ?? 0,
            ],
            false
        );
    }

    public function rules(): array
    {
        return [
            'has_extra_packing' => ['required', 'boolean'],
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
