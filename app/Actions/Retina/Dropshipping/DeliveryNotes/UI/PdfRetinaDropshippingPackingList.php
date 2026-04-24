<?php

namespace App\Actions\Retina\Dropshipping\DeliveryNotes\UI;

use App\Actions\Dispatching\DeliveryNote\PdfPackingList;
use App\Actions\RetinaAction;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class PdfRetinaDropshippingPackingList extends RetinaAction
{
    public function handle(DeliveryNote $deliveryNote): Response
    {
        return PdfPackingList::run($deliveryNote);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->customer->id == $request->route()->parameter('deliveryNote')->customer_id;
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($deliveryNote);
    }
}
