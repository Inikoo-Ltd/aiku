<?php

namespace App\Actions\UI\Notification;

use App\Models\Notifications\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Validation\Rule;
use App\Enums\Notification\NotificationChannelEnum;

class StoreNotificationType
{
    use AsAction;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:notification_types,slug'],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'available_channels' => ['required', 'array'],
            'available_channels.*' => ['string', Rule::in(array_column(NotificationChannelEnum::cases(), 'value'))],
            'default_channels' => ['required', 'array'],
            'default_channels.*' => ['string', Rule::in(array_column(NotificationChannelEnum::cases(), 'value'))],
        ];
    }

    public function handle(array $data): NotificationType
    {
        return NotificationType::create($data);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->handle($request->validated());

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Notification type created successfully.'),
        ]);

        return Redirect::back();
    }
}
