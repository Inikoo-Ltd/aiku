<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 21 Jul 2026 09:10:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClockingMachineQRCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'label'                  => $this->label,
            'hash'                   => $this->hash,
            'qr_value'               => route('grp.clocking_scan', ['hash' => $this->hash]),
            'active'                 => (bool) $this->active,
            'active_badge'           => $this->active ? [
                'label' => __('Active'),
                'class' => 'bg-green-50 text-green-700 ring-green-600/20',
            ] : [
                'label' => __('Inactive'),
                'class' => 'bg-red-50 text-red-700 ring-red-600/20',
            ],
            'deactivated_at'         => $this->deactivated_at,
            'number_clockings'       => (int) $this->number_clockings,
            'number_different_staff' => (int) $this->number_different_staff,
            'last_used_at'           => $this->last_used_at,
            'created_at'             => $this->created_at,
            'edit_route'             => [
                'name'       => 'grp.org.hr.clocking_machines.show.qr_codes.edit',
                'parameters' => [
                    'organisation'          => $this->clockingMachine->organisation->slug,
                    'clockingMachine'       => $this->clockingMachine->slug,
                    'clockingMachineQRCode' => $this->id,
                ],
            ],
            'toggle_active_route'    => [
                'name'       => 'grp.models.clocking_machine_qr_code.toggle_active',
                'parameters' => ['clockingMachineQRCode' => $this->id],
                'method'     => 'patch',
            ],
        ];
    }
}
