<?php

/*
 * author Louis Perez
 * created on 22-09-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\GoodsIn\ReturnDeliveryNote;

use App\Enums\EnumHelperTrait;

/**
 * Why a return exists, which decides where the goods came from and where the ledger sends them.
 *
 * RETURN: the customer sent back goods that were dispatched. Quantities come from
 * quantity_dispatched and the put-away writes RETURN_PICKED.
 *
 * CANCELLATION: the delivery note was cancelled before it ever left, so the goods are picked but
 * never dispatched. Quantities come from quantity_picked and the put-away writes CANCEL_PICKED,
 * the same movement the legacy cancel path fired at cancellation time. There is no invoice and
 * nothing to refund, so this type never resolves to a refund or a replacement.
 */
enum ReturnDeliveryNoteTypeEnum: string
{
    use EnumHelperTrait;

    case RETURN       = 'return';
    case CANCELLATION = 'cancellation';

    public static function labels(): array
    {
        return [
            'return'       => __('Return'),
            'cancellation' => __('Cancellation'),
        ];
    }
}
