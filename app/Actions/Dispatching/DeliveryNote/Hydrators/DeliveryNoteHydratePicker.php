<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Mar 2026 11:58:53 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Hydrators;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class DeliveryNoteHydratePicker implements ShouldBeUnique
{
    use AsAction;
    use WithActionUpdate;

    public function getJobUniqueId(?int $deliveryNoteId): string
    {
        return (string)($deliveryNoteId ?? 'empty');
    }

    public function handle(?int $deliveryNoteId): void
    {
        if (!$deliveryNoteId) {
            return;
        }

        $deliveryNote = DeliveryNote::find($deliveryNoteId);
        if (!$deliveryNote) {
            return;
        }


        $picker     = [];
        $sortPicker = '';
        if ($deliveryNote->picker_user_id) {
            /** @var User $user */
            $user       = $deliveryNote->pickerUser;
            $picker     = [
                'id'   => $user->id,
                'name' => $user->contact_name ?? $user->username,
            ];
            $sortPicker = $user->contact_name ?? $user->username;
        }
        $deliveryNote->update([
            'sort_picker' => $sortPicker
        ]);

        $lock = Cache::lock('delivery_note_data_update_'.$deliveryNoteId, 10);

        try {
            $lock->block(5, function () use ($deliveryNote, $picker) {
                $data = $deliveryNote->data ?? [];

                if (empty($picker)) {
                    unset($data['picker']);
                } else {
                    $data['picker'] = $picker;
                }

                $deliveryNote->update(['data' => $data]);
            });
        } catch (LockTimeoutException) {
            return;
        }
    }


}
