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
        DeliveryNote::where('id', $deliveryNoteId)->update([
            'sort_picker' => $sortPicker,
            'data->picker' => $picker ?: null,
        ]);
    }


}
