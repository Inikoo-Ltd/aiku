<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderPremiumDispatch extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;
    use WithChargeTransactions;


    public function handle(Order $order, array $modelData, bool $charged = true): void
    {
        $order = $this->update($order, $modelData);
        if ($charged) {
            $this->syncOrderOptionCharge($order, ChargeTypeEnum::PREMIUM, (bool) Arr::get($modelData, 'is_premium_dispatch', false));
        }

        $order->deliveryNotes()
            ->whereNotIn('state', [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED])
            ->update(['is_premium_dispatch' => $order->is_premium_dispatch]);

    }



    public function rules(): array
    {
        return [
            'is_premium_dispatch' => ['required', 'boolean'],
        ];
    }


    public function action(Order $order, array $modelData, bool $charged = true): void
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, $modelData);

        $this->handle($order, $modelData, $charged);
    }


    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisationFromShop($order->shop, $request);

        $this->handle($order, $this->validatedData);
    }
}
