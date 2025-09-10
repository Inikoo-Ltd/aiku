<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Comms\Email\SendNewOrderEmailToCustomer;
use App\Actions\Comms\Email\SendNewOrderEmailToSubscribers;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTrafficSource;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateBasket;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderPremiumDispatch extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): Order
    {
        $order = $this->update($order, $modelData);

        return $order;
    }

    public function rules(): array
    {
        return [
            'is_premium_dispatch' => ['required', 'boolean'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, array $modelData): Order
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order, $modelData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
