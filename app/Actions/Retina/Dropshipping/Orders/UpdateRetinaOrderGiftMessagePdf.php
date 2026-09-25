<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Helpers\Media\DetachAttachmentFromModel;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderGiftMessagePdf extends RetinaAction
{
    public function handle(Order $order, array $modelData): Order
    {
        foreach ($order->attachments()->wherePivot('scope', 'GiftMessage')->get() as $attachment) {
            DetachAttachmentFromModel::run($order, $attachment);
        }

        $file = Arr::get($modelData, 'gift_message_pdf');

        SaveModelAttachment::make()->action(
            $order,
            [
                'path'         => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'scope'        => 'GiftMessage',
                'caption'      => 'Gift message',
                'extension'    => $file->getClientOriginalExtension(),
            ]
        );

        $order->update(['gift_message' => null]);

        return $order->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'gift_message_pdf' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
