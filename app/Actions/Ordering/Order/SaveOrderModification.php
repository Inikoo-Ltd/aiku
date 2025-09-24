<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class SaveOrderModification extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {
        dd("hahahahah");
        $this->orderHydrators($order);

        return $order;
    }

    public function rules(): array
    {
        return [
            'transactions' => ['sometimes', 'array'],
            'products' => ['sometimes', 'array']
        ];
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
