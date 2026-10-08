<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\OrgAction;
use App\Events\BroadcastProductionQueuesChanged;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Notifications\PartnerLinePokedNotification;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\ActionRequest;

class PokePartnerShoppingListItem extends OrgAction
{
    /**
     * The buyer is desperate for a line the hub has not delivered yet: the hub production team
     * gets a notification. One poke per line an hour, so it stays a signal and not noise.
     */
    public function handle(PartnerShoppingListItem $partnerShoppingListItem, User $user): int
    {
        abort_unless($partnerShoppingListItem->canBePoked(), 422, __('This line is no longer with the partner'));

        $production = Organisation::find($partnerShoppingListItem->partner_organisation_id ?? $partnerShoppingListItem->orgPartner->partner_id)?->productions()->first();
        abort_unless($production, 422, __('The partner has no production to notify'));

        $poked = PartnerShoppingListItem::whereKey($partnerShoppingListItem->id)
            ->where(fn ($query) => $query->whereNull('poked_at')->orWhere('poked_at', '<', now()->subHour()))
            ->update(['poked_at' => now(), 'poked_by_user_id' => $user->id]);
        abort_unless($poked, 429, __('Already poked in the last hour'));
        rescue(fn () => BroadcastProductionQueuesChanged::dispatch($production->organisation_id));

        $note = __(':user (:buyer) is waiting for :code ×:quantity, requested :date. Please start it as soon as you can.', [
            'user'     => $user->contact_name ?: $user->username,
            'buyer'    => $partnerShoppingListItem->organisation->code,
            'code'     => $partnerShoppingListItem->orgStock->code,
            'quantity' => trimDecimalZeros($partnerShoppingListItem->quantity),
            'date'     => $partnerShoppingListItem->created_at->format('d M'),
        ]);

        $recipients = User::permission(Permission::whereIn('name', [
            "productions_operations.$production->id.orchestrate",
            "productions_operations.$production->id.prepare",
        ])->pluck('name')->all())
            ->where('status', true)
            ->where('id', '!=', $user->id)
            ->get();

        Notification::send($recipients, new PartnerLinePokedNotification($production, $partnerShoppingListItem->organisation->name, $note));

        return $recipients->count();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, PartnerShoppingListItem $partnerShoppingListItem, ActionRequest $request): int
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $partnerShoppingListItem->org_partner_id === $orgPartner->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($partnerShoppingListItem, $request->user());
    }
}
