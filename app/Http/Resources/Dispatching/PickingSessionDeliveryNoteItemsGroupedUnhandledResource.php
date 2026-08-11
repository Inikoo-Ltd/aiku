<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created: Tue, 11 Aug 2026 10:00:00 Bali, Indonesia
 * Copyright (c) 2026, Vika Aqordi
 */

namespace App\Http\Resources\Dispatching;

/**
 * The grouped rows of a session that is still being picked, holding only the items left to do: the
 * finished ones are read on the handled tab, so the picker is not walked through them again.
 */
class PickingSessionDeliveryNoteItemsGroupedUnhandledResource extends PickingSessionDeliveryNoteItemsGroupedResource
{
    protected function onlyUnhandledItems(): bool
    {
        return true;
    }
}
