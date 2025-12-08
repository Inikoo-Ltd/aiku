<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 22:13:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\Payment;

use App\Actions\Retina\Dropshipping\Orders\WithBasketStateWarning;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class PlaceOrderPayByCashOnDelivery extends RetinaAction
{
    use WithBasketStateWarning;
    use WithPlaceOrderByPaymentMethod;
    use WithRetinaOrderPlacedRedirection;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer): array
    {
        return $this->placeOrderByPaymentMethod($customer, OrderToBePaidByEnum::CASH_ON_DELIVERY);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }
}
