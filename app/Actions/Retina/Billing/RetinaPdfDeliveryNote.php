<?php

namespace App\Actions\Retina\Billing;

use App\Actions\Dispatching\DeliveryNote\PdfDeliveryNote;
use App\Actions\RetinaAction;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class RetinaPdfDeliveryNote extends RetinaAction
{
    public function handle(DeliveryNote $deliveryNote) : Response
    {
        return PdfDeliveryNote::run($deliveryNote);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request) : Response
    {
        $this->initialisation($request);

        return $this->handle($deliveryNote);
    }
}