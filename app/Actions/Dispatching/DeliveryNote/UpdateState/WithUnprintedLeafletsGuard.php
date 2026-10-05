<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Dispatching\DeliveryNote\UpdateState;

use App\Models\Dispatching\DeliveryNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

/**
 * The unprinted-insert guard as the warehouse meets it: a toast on the page they are already on,
 * rather than an error page that loses their place. handle() keeps its own hard guard for callers
 * that are not a browser, so this only changes how the refusal is delivered.
 */
trait WithUnprintedLeafletsGuard
{
    protected function unprintedLeafletsNotification(DeliveryNote $deliveryNote, string $description): ?RedirectResponse
    {
        if (!$deliveryNote->hasUnprintedLeaflets()) {
            return null;
        }

        return Redirect::back()->with('notification', [
            'status'      => 'error',
            'title'       => __('Inserts not printed'),
            'description' => $description,
        ]);
    }
}
