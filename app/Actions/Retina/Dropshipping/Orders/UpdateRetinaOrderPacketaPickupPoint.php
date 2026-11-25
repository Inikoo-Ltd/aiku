<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UpdateOrderPacketaPickupPoint;
use App\Actions\RetinaAction;
use App\Actions\Retina\Traits\WithRetinaBasketOnlyOrder;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderPacketaPickupPoint extends RetinaAction
{
    use WithRetinaBasketOnlyOrder;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, array $modelData): Order
    {
        return UpdateOrderPacketaPickupPoint::run($order, $modelData['pickup_point_id'] ?? null);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('order')->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'pickup_point_id' => ['present', 'nullable', 'string', 'regex:/^\d{1,8}$/'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
