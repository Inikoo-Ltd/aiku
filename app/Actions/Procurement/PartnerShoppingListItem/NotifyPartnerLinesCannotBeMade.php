<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Permission;
use App\Models\SysAdmin\User;
use App\Notifications\ToProduceItemsCantBeDoneNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4906: the partner's production says it cannot make lines the buyer sent. Whoever added those lines is told
 * on the channels they chose (email unless they changed it); the rest of the buyer's procurement team sees it in the bell.
 */
class NotifyPartnerLinesCannotBeMade
{
    use AsAction;

    /**
     * @param  Collection<int, PartnerShoppingListItem>  $items
     */
    public function handle(OrgPartner $orgPartner, Collection $items, string $note, ?User $dismissedBy): void
    {
        $buyerId     = $orgPartner->organisation_id;
        $permissions = Permission::whereIn('name', ["procurement.$buyerId", "procurement.$buyerId.edit"])->pluck('name')->all();
        if ($permissions === []) {
            return;
        }

        $notification = new ToProduceItemsCantBeDoneNotification($orgPartner, $note);

        $adders = User::whereIn('id', $items->pluck('added_by_user_id')->filter()->unique())
            ->where('status', true)
            ->when($dismissedBy, fn ($query) => $query->where('id', '!=', $dismissedBy->id))
            ->get()
            ->filter(fn (User $user) => $user->authTo($permissions));

        foreach ($adders as $adder) {
            NotifyUserOnChosenChannels::run($adder, UserNotificationEnum::PARTNER_LINE_CANNOT_BE_MADE, clone $notification, $notification->title(), $notification->body(), $notification->url());
        }

        Notification::send(
            User::permission($permissions)
                ->where('status', true)
                ->whereNotIn('id', $adders->pluck('id')->when($dismissedBy, fn (Collection $ids) => $ids->push($dismissedBy->id))->all())
                ->get(),
            $notification
        );
    }
}
