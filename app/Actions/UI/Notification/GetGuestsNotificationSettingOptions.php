<?php

namespace App\Actions\UI\Notification;

use App\Models\Catalogue\Shop;
use App\Models\Notifications\NotificationType;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetGuestsNotificationSettingOptions
{
    use AsAction;

    public function handle(User $authUser): array
    {
        $guests = User::query()
            ->where('group_id', $authUser->group_id)
            ->whereHas('guests', fn ($q) => $q->where('guests.status', true))
            ->orderBy('contact_name')
            ->get()
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->contact_name ?: $u->username])
            ->values();

        $groups = Group::query()->get()->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->values();

        $organisations = Organisation::query()
            ->where('group_id', $authUser->group_id)
            ->orderBy('name')
            ->get()
            ->map(fn ($o) => ['value' => $o->id, 'label' => $o->name])
            ->values();

        $shops = Shop::query()
            ->where('group_id', $authUser->group_id)
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])
            ->values();

        $notificationTypes = NotificationType::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => ['value' => $t->id, 'label' => $t->name, 'category' => $t->category])
            ->values();

        return [
            'users' => $guests,
            'groups' => $groups,
            'organisations' => $organisations,
            'shops' => $shops,
            'notification_types' => $notificationTypes,
        ];
    }

    public function asController(): JsonResponse
    {
        return response()->json($this->handle(request()->user()));
    }
}
