<?php

/*
 * author Louis Perez
 * created on 02-06-2026
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\Traits;

use App\Models\GoodsIn\ReturnDeliveryNote;
use Lorisleiva\Actions\ActionRequest;

trait WithReturnDeliveryNoteController
{
    public function asController(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->initialisationFromWarehouse($returnDeliveryNote->warehouse, $request);

        return $this->handle($returnDeliveryNote, $this->validatedData ?? []);
    }
}
