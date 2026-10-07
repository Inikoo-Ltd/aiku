<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\Media\DetachAttachmentFromModel;
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

class UpdateOrderGiftMessage extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;
    use WithChargeTransactions;


    public function handle(Order $order, array $modelData): Order
    {
        $order = $this->update($order, $modelData);
        $charge = $order->shop->charges()->where('type', ChargeTypeEnum::GIFT_MESSAGE)->where('state', ChargeStateEnum::ACTIVE)->first();

        if ($charge) {

            $chargeApplies = Arr::get($modelData, 'has_gift_message', false);
            $chargeTransaction   = null;
            $chargeTransactionID = DB::table('transactions')->where('order_id', $order->id)
                ->leftJoin('charges', 'transactions.model_id', '=', 'charges.id')
                ->where('model_type', 'Charge')->where('charges.type', ChargeTypeEnum::GIFT_MESSAGE->value)->value('transactions.id');

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

        if (!Arr::get($modelData, 'has_gift_message', false)) {
            $order->update(['gift_message' => null]);
            foreach ($order->attachments()->wherePivot('scope', 'GiftMessage')->get() as $attachment) {
                DetachAttachmentFromModel::run($order, $attachment);
            }
        }

        return $order;
    }



    public function rules(): array
    {
        return [
            'has_gift_message' => ['required', 'boolean'],
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
