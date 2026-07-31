<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 21 Oct 2021 12:37:51 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2021, Inikoo
 *  Version 4.0
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

/**
 * @property mixed $id
 * @property mixed $workplace_name
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $type
 * @property mixed $workplace_slug
 * @property mixed $kiosk_token
 * @property mixed $config
 */
class ClockingMachinesResource extends JsonResource
{
    /**
     * Which config key gates each kiosk-capable machine type's "enabled" state. Types without
     * an entry here (biometric, static-nfc, mobile-app, legacy) have no kiosk enable concept.
     */
    private const KIOSK_CONFIG_KEYS = [
        'qr-code'         => 'qr',
        'pin'             => 'pin',
        'barcode-scanner' => 'barcode',
    ];

    public function toArray($request): array|Arrayable|JsonSerializable
    {
        $configKey = self::KIOSK_CONFIG_KEYS[$this->type] ?? null;

        return [
            'workplace_name'         => $this->workplace_name,
            'workplace_slug'         => $this->workplace_slug,
            'slug'                   => $this->slug,
            'name'                   => $this->name,
            'type'                   => $this->type,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'kiosk_url'          => $this->kiosk_token
                ? route('grp.kiosk.show', ['kioskToken' => $this->kiosk_token])
                : null,
            // Missing from config entirely (never configured) is treated the same as off.
            'kiosk_enabled'      => $configKey === null
                ? null
                : (bool) data_get($this->config, "{$configKey}.enable", false),
            'delete_route'       => [
                'name'       => 'grp.models.clocking_machine..delete',
                'parameters' => ['clockingMachine' => $this->id],
            ],
        ];
    }
}
