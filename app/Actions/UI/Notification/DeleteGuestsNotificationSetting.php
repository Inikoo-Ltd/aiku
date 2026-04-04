<?php

namespace App\Actions\UI\Notification;

use App\Models\Notifications\UserNotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteGuestsNotificationSetting
{
    use AsAction;

    public function handle(UserNotificationSetting $userNotificationSetting): void
    {
        $userNotificationSetting->delete();
    }

    public function asController(UserNotificationSetting $userNotificationSetting)
    {
        $this->handle($userNotificationSetting);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Guest notification setting successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
