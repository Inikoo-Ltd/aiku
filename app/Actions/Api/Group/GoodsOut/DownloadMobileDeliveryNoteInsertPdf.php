<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteLeaflet\PrintDeliveryNoteLeafletInBrowser;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\Response;

class DownloadMobileDeliveryNoteInsertPdf
{
    use AsController;
    use WithMobilePickingGuards;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, DeliveryNoteLeaflet $deliveryNoteLeaflet, ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        abort_unless($deliveryNoteLeaflet->delivery_note_id === $deliveryNote->id, 404);

        if (!$deliveryNoteLeaflet->isPrintable()) {
            throw ValidationException::withMessages(['insert' => __('This insert has no uploaded file to print.')]);
        }

        try {
            return PrintDeliveryNoteLeafletInBrowser::make()->handle($deliveryNoteLeaflet);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['insert' => collect($exception->errors())->flatten()->first()]);
        }
    }
}
