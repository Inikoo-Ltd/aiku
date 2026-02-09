<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;

abstract class BaseSmartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $typeSlug; // required in child class (e.g. 'order.created')
    protected ?Model $scope = null; // notification scope (Shop/Organisation)


    public function via($notifiable): array
    {

        if (!method_exists($notifiable, 'getNotificationSetting')) {
            return ['database'];
        }

        $settings = $notifiable->getNotificationSetting($this->typeSlug, $this->scope);

        if (!$settings->is_enabled) {
            return [];
        }

        if (!$this->passesFilters($settings->filters)) {
            return [];
        }
        return $settings->channels ?? ['database'];
    }

    /**
     *
     * @param array|null $userFilters Filter user (e.g. ['shop_id' => [1,2]])
     * @return bool
     */
    protected function passesFilters(?array $userFilters): bool
    {
        return true;
    }


    public function setScope(?Model $scope): self
    {
        $this->scope = $scope;
        return $this;
    }
}
