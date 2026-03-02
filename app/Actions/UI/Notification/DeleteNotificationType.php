<?php

namespace App\Actions\UI\Notification;

use App\Models\Notifications\NotificationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteNotificationType
{
    use AsAction;

    public function handle(NotificationType $notificationType): void
    {
        $notificationType->delete();
    }

    public function asController(NotificationType $notificationType): RedirectResponse
    {
        $this->handle($notificationType);

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Notification type deleted successfully.'),
        ]);

        return Redirect::back();
    }
}
