<?php

namespace App\Actions\UI\Notification;

use App\Models\Catalogue\Shop;
use App\Models\Notifications\UserNotificationSetting;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreUsersNotificationSettings
{
    use AsAction;

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'notification_type_id' => ['required', 'integer', 'exists:notification_types,id'],
            'scope_kind' => ['nullable', 'in:group,organisation,shop'],
            'scopes' => ['array'],
            'scopes.*' => ['integer'],
            'filters' => ['array'],
            'is_enabled' => ['boolean'],
        ];
    }

    public function handle(User $authUser, array $data): int
    {
        $user = User::where('group_id', $authUser->group_id)->findOrFail($data['user_id']);

        $scopeKind = $data['scope_kind'] ?? null;
        $scopes = Arr::get($data, 'scopes', []);
        $filters = Arr::get($data, 'filters', []);

        if (!$scopeKind || empty($scopes)) {
            UserNotificationSetting::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type_id' => $data['notification_type_id'],
                    'scope_type' => null,
                    'scope_id' => null,
                ],
                [
                    'is_enabled' => true,
                    'channels' => ['database'],
                    'filters' => $filters,
                ]
            );
            return 1;
        }

        $scopeTypeMap = [
            'group' => Group::class,
            'organisation' => Organisation::class,
            'shop' => Shop::class,
        ];
        $scopeType = $scopeTypeMap[$scopeKind];

        $count = 0;
        foreach ($scopes as $scopeId) {
            UserNotificationSetting::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type_id' => $data['notification_type_id'],
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                ],
                [
                    'is_enabled' => Arr::get($data, 'is_enabled', true),
                    'channels' => ['database'],
                    'filters' => $filters,
                ]
            );
            $count++;
        }

        return $count;
    }

    public function asController(ActionRequest $request)
    {
        $payload = $request->validated();
        $this->handle($request->user(), $payload);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Notification settings successfully saved.'),
        ]);

        return Redirect::back();
    }
}
