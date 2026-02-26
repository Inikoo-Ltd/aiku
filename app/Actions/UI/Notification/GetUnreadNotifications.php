<?php

namespace App\Actions\UI\Notification;

use App\Http\Resources\SysAdmin\NotificationResource;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;

class GetUnreadNotifications
{
    use AsAction;

    public function handle($user, int $perPage = 20)
    {
        $notifications = $user->unreadNotifications()
            ->latest()
            ->paginate($perPage);

        return [
            'count' => $user->unreadNotifications()->count(),
            'notifications' => NotificationResource::collection($notifications),
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json(
            $this->handle($request->user())
        );
    }
}
