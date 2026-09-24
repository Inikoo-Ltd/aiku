<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Audits\DispatchSimpleAudit;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class SkipDeliveryNoteBoxPackingList extends OrgAction
{
    use WithActionUpdate;

    private DeliveryNote $deliveryNote;

    public function handle(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $deliveryNote = $this->update($deliveryNote, ['data' => ['box_packing_list_skipped_by' => $user->id]], ['data']);

        DispatchSimpleAudit::run(
            auditableModel: $deliveryNote,
            logKey: 'box_packing_list',
            oldValue: __('Packing list by box required'),
            newValue: __('Packing list by box skipped by :user', ['user' => $user->contact_name ?: $user->username]),
        );

        return $deliveryNote;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "supervisor-dispatching.{$this->deliveryNote->warehouse_id}",
            "org-admin.{$this->deliveryNote->organisation_id}",
        ]);
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, $request);

        return $this->handle($deliveryNote, $request->user());
    }

    public function action(DeliveryNote $deliveryNote, User $user): DeliveryNote
    {
        $this->asAction     = true;
        $this->deliveryNote = $deliveryNote;
        $this->initialisationFromShop($deliveryNote->shop, []);

        return $this->handle($deliveryNote, $user);
    }
}
