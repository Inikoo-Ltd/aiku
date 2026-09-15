<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Dispatching\DeliveryNoteLeaflet;

use App\Models\Dispatching\DeliveryNoteLeaflet;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies the customer's current artwork onto an insert that reached checkout without any.
 *
 * Deliberately a copy rather than a live read: a preference can change long after the order
 * was placed, and this row is the record of what that order ships with. Taking it is a
 * decision someone makes and is stamped on the row, so the change can be traced.
 */
class PullDeliveryNoteLeafletMediaFromPreference
{
    use AsAction;

    public function handle(DeliveryNoteLeaflet $deliveryNoteLeaflet, ?User $user = null): DeliveryNoteLeaflet
    {
        if ($deliveryNoteLeaflet->media_id !== null) {
            throw ValidationException::withMessages([
                'messages' => __('This insert already has its own file.'),
            ]);
        }

        $preference = $deliveryNoteLeaflet->preferenceMediaCandidate();

        if (!$preference) {
            throw ValidationException::withMessages([
                'messages' => __('The customer has not uploaded a file for this insert yet.'),
            ]);
        }

        $deliveryNoteLeaflet->update([
            'media_id'             => $preference->media_id,
            'model_has_leaflet_id' => $deliveryNoteLeaflet->model_has_leaflet_id ?? $preference->id,
            'data'                 => array_merge($deliveryNoteLeaflet->data ?? [], [
                'media_pulled_from_preference_at'      => now()->toISOString(),
                'media_pulled_from_preference_by_user' => $user?->id,
                'media_pulled_from_model_has_leaflet'  => $preference->id,
            ]),
        ]);

        return $deliveryNoteLeaflet->refresh();
    }

    public function asController(DeliveryNoteLeaflet $deliveryNoteLeaflet, ActionRequest $request): RedirectResponse
    {
        $this->handle($deliveryNoteLeaflet, $request->user());

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('File taken'),
            'description' => __(':insert now uses the file the customer uploaded.', [
                'insert' => $deliveryNoteLeaflet->name,
            ]),
        ]);
    }

}
