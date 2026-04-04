<?php

namespace App\Actions\UI\Profile;

use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUserNotificationPreferences
{
    use AsAction;

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:user_notification_settings,id'],
            'is_enabled' => ['required', 'boolean'],
            'filters' => ['nullable', 'array'],
        ];
    }

    public function handle(User $user, array $data): void
    {
        // Ensure the setting belongs to the user
        $setting = $user->notificationSettings()->where('id', $data['id'])->firstOrFail();

        $setting->update([
            'is_enabled' => $data['is_enabled'],
            'filters' => $data['filters'] ?? [],
        ]);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->handle($request->user(), $request->validated());

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Notification preference updated successfully.'),
        ]);

        return Redirect::back();
    }
}
