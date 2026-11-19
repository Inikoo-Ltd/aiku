<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;

/**
 * A dispatched delivery note is never finalised or dispatched again outside repair: a stale page or a
 * second click would resend the channel tracking and the dispatched emails and overwrite dispatched_at.
 * Called inside the action's transaction, the row lock makes a concurrent second request wait for the
 * first and then see it dispatched.
 */
trait WithAlreadyDispatchedGuard
{
    protected function isDispatchedUnderLock(DeliveryNote $deliveryNote): bool
    {
        return DeliveryNote::whereKey($deliveryNote->id)->lockForUpdate()->first()?->state === DeliveryNoteStateEnum::DISPATCHED;
    }
}
