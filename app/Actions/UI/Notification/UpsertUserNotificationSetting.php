<?php

namespace App\Actions\UI\Notification;

use App\Models\Notifications\NotificationType;
use App\Models\Notifications\UserNotificationSetting;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpsertUserNotificationSetting
{
    use AsAction;

    public function rules(): array
    {
        return [
            'notification_type_id' => ['required', 'integer', 'exists:notification_types,id'],
            'is_enabled'           => ['sometimes', 'boolean'],
            'channels'             => ['sometimes', 'array'],
            'channels.*'           => ['string'],
            'filters'              => ['sometimes', 'array'],
            'scope_type'           => ['nullable', 'string'],
            'scope_id'             => ['nullable', 'integer'],
        ];
    }

    public function handle(User $user, array $data): UserNotificationSetting
    {
        $type = NotificationType::query()->findOrFail($data['notification_type_id']);

        $availableChannels = (array) $type->available_channels;
        $defaultChannels   = (array) $type->default_channels;
        $requestedChannels = array_values(array_unique((array) ($data['channels'] ?? $defaultChannels)));
        $validatedChannels = array_values(array_intersect($requestedChannels, $availableChannels));

        $keys = [
            'user_id'             => $user->id,
            'notification_type_id' => $type->id,
            'scope_type'          => $data['scope_type'] ?? null,
            'scope_id'            => $data['scope_id'] ?? null,
        ];

        $values = [
            'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : true,
            'channels'   => $validatedChannels,
            'filters'    => $data['filters'] ?? null,
        ];

        return UserNotificationSetting::query()->updateOrCreate($keys, $values);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $setting = $this->handle($request->user(), $data);

        return response()->json($setting);
    }
}
