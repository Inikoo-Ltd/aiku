<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Retina\SysAdmin;

use App\Models\Billables\ModelHasLeaflet;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use App\Models\Helpers\Media;

trait WithDisposableLeafletMedia
{
    /**
     * Drops artwork only once nothing points at it any more.
     *
     * A delivery note copies the artwork it ships with onto its own row, so an order placed months
     * ago still shows what actually went in the parcel. That copy is a reference, not a duplicate
     * file: deleting the customer's preference would take the order's record with it, and the
     * database refuses. Preferences alone are not enough to decide this.
     */
    protected function deleteLeafletMediaIfUnused(?int $mediaId): void
    {
        if (!$mediaId) {
            return;
        }

        if (ModelHasLeaflet::where('media_id', $mediaId)->exists()) {
            return;
        }

        if (DeliveryNoteLeaflet::where('media_id', $mediaId)->exists()) {
            return;
        }

        Media::find($mediaId)?->delete();
    }
}
