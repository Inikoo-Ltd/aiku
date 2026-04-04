<?php

namespace App\Actions\UI\Notification;

use App\Models\Notifications\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Validation\Rule;
use App\Enums\Notification\NotificationChannelEnum;

class UpdateNotificationType
{
    use AsAction;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('notification_types', 'slug')->ignore(request()->route('notificationType'))],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'available_channels' => ['required', 'array'],
            'available_channels.*' => ['string', Rule::in(array_column(NotificationChannelEnum::cases(), 'value'))],
            'default_channels' => ['required', 'array'],
            'default_channels.*' => ['string', Rule::in(array_column(NotificationChannelEnum::cases(), 'value'))],
        ];
    }

    public function handle(NotificationType $notificationType, array $data): NotificationType
    {
        $notificationType->update($data);
        return $notificationType;
    }

    public function asController(NotificationType $notificationType, ActionRequest $request): RedirectResponse
    {
        $this->handle($notificationType, $request->validated());

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Notification type updated successfully.'),
        ]);

        return Redirect::back();
    }
}
