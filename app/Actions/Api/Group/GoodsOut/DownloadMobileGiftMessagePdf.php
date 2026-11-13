<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Ordering\Order\PdfOrderGiftMessage;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\Response;

class DownloadMobileGiftMessagePdf
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanView($deliveryNote, $user);

        $order = $deliveryNote->orders()->first();
        abort_unless($deliveryNote->has_gift_message && $order, 404);

        $response = PdfOrderGiftMessage::make()->handle($order);

        if (!$response->headers->has('Content-Disposition')) {
            $response->headers->set('Content-Disposition', 'inline; filename="gift-message-'.$order->slug.'.pdf"');
        }

        return $response;
    }
}
