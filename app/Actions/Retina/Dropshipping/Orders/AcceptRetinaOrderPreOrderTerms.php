<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\PreOrder\AcceptBasketPreOrderTerms;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithCustomerPurchasableProduct;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class AcceptRetinaOrderPreOrderTerms extends RetinaAction
{
    use WithCustomerPurchasableProduct;

    public function handle(Order $order, array $modelData): Order
    {
        return AcceptBasketPreOrderTerms::run($order, (bool) ($modelData['hold_together'] ?? false));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('order')->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'accept_terms'  => ['required', 'accepted'],
            'hold_together' => ['sometimes', 'boolean'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);
        $this->ensureCustomerCanChangeOrder($order);

        return $this->handle($order, $this->validatedData);
    }
}
