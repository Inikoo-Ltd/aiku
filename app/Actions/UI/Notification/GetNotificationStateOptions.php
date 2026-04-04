<?php

namespace App\Actions\UI\Notification;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Notifications\NotificationType;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetNotificationStateOptions
{
    use AsAction;

    public function handle(int $notificationTypeId): array
    {
        $type = NotificationType::findOrFail($notificationTypeId);
        if ($type->category === 'Ordering') {
            $labels = OrderStateEnum::labels();
            return [
                'states' => collect($labels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()
            ];
        }

        return ['states' => []];
    }

    public function asController(): JsonResponse
    {
        $notificationTypeId = (int) request('notification_type_id');
        return response()->json($this->handle($notificationTypeId));
    }
}
