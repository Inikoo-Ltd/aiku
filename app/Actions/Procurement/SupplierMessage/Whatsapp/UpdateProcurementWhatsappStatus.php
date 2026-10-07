<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage\Whatsapp;

use App\Models\Procurement\SupplierMessage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProcurementWhatsappStatus
{
    use AsAction;

    private const array ORDER = ['sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    /**
     * Meta can report a later state before an earlier one, so a status only ever moves forward.
     *
     * @param  array<string, mixed>  $value
     */
    public function handle(array $value): void
    {
        foreach (Arr::get($value, 'statuses', []) as $status) {
            $message = SupplierMessage::where('whatsapp_message_id', (string) Arr::get($status, 'id'))->first();
            $state   = (string) Arr::get($status, 'status');

            if (! $message || ! isset(self::ORDER[$state])) {
                continue;
            }

            if (self::ORDER[$state] > (self::ORDER[$message->delivery_state] ?? 0)) {
                $message->update(['delivery_state' => $state]);
            }
        }
    }
}
