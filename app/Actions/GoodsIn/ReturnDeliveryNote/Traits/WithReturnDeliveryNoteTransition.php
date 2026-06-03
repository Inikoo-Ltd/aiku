<?php

/*
 * author Louis Perez
 * created on 02-06-2026
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\Traits;

use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Validation\ValidationException;

trait WithReturnDeliveryNoteTransition
{
    /**
     * @throws ValidationException
     */
    protected function validateReturnDeliveryNoteState(ReturnDeliveryNote $returnDeliveryNote, ReturnDeliveryNoteStateEnum $expectedState, string $message = 'Delivery note can not be handled.'): void
    {
        if ($returnDeliveryNote->state !== $expectedState) {
            throw ValidationException::withMessages([
                'message' => __($message).' ['.__('Invalid state').': '.$returnDeliveryNote->state->value.']',
            ]);
        }
    }
}
