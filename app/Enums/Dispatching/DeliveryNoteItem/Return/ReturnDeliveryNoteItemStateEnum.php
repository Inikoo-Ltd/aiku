<?php

namespace App\Enums\Dispatching\DeliveryNoteItem\Return;

use App\Enums\EnumHelperTrait;

enum ReturnDeliveryNoteItemStateEnum: string
{
    use EnumHelperTrait;

    //Block to do
    case UNASSIGNED = 'unassigned';

    // Block Picking
    case HANDLING = 'handling';

    // Block Received
    case NOT_RETURNED = 'not_returned';
    case DAMAGED = 'damaged';
    case LOST = 'lost';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    
    public function label(): string
    {
        return match ($this) {
            SELF::UNASSIGNED    => 'Item is on queue',
            SELF::HANDLING      => 'Item is handled',
            SELF::NOT_RETURNED  => 'Item is not returned',
            SELF::DAMAGED       => 'Item is damaged',
            SELF::LOST          => 'Item is lost',
            SELF::RETURNED      => 'Item is returned',
            SELF::CANCELLED     => 'Item is cancelled',
        };
    }
}
