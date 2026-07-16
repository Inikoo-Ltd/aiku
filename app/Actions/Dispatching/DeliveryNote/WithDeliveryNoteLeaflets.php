<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 16 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Enums\Dispatching\DeliveryNoteLeaflet\DeliveryNoteLeafletStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteLeaflet;

trait WithDeliveryNoteLeaflets
{
    /** @return array<int, array{id: int, name: string, type: string, copies: int, state: string, state_label: string, has_media: bool}> */
    protected function getLeaflets(DeliveryNote $deliveryNote): array
    {
        return $deliveryNote->leaflets
            ->map(fn (DeliveryNoteLeaflet $leaflet) => [
                'id'          => $leaflet->id,
                'name'        => $leaflet->name,
                'type'        => $leaflet->type->value,
                'copies'      => $leaflet->copies,
                'state'       => $leaflet->state->value,
                'state_label' => $leaflet->state->labels()[$leaflet->state->value],
                'has_media'   => $leaflet->media_id !== null,
            ])->values()->all();
    }

    /** @return array{total: int, printed: int, all_printed: bool, label: string} */
    protected function getPrintStatus(DeliveryNote $deliveryNote): array
    {
        $leaflets = $deliveryNote->leaflets;
        $total    = $leaflets->count();
        $printed  = $leaflets->whereIn('state', [
            DeliveryNoteLeafletStateEnum::PRINTED,
            DeliveryNoteLeafletStateEnum::INCLUDED,
        ])->count();

        return [
            'total'       => $total,
            'printed'     => $printed,
            'all_printed' => $total > 0 && $printed === $total,
            'label'       => $total === 0
                ? __('No inserts')
                : ($printed === $total ? __('Printed') : __('Not printed')),
        ];
    }
}
