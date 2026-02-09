<?php

namespace App\Models\Traits;

use App\Models\Notifications\NotificationType;
use App\Models\Notifications\UserNotificationSetting;
use Illuminate\Database\Eloquent\Model;

trait HasNotificationSettings
{
    /**
     * Get the effective notification setting for a specific type and optional scope.
     *
     * @param string $typeSlug (e.g. 'order.created')
     * @param Model|null $scope (Optional: Shop or Organisation model)
     * @return object { is_enabled, channels, filters }
     */
    public function getNotificationSetting(string $typeSlug, ?Model $scope = null)
    {

        $type = NotificationType::where('slug', $typeSlug)->first();

        if (!$type) {
            return (object) [
                'is_enabled' => false,
                'channels' => [],
                'filters' => []
            ];
        }

        if ($scope) {
            $setting = $this->notificationSettings()
                ->where('notification_type_id', $type->id)
                ->where('scope_type', get_class($scope))
                ->where('scope_id', $scope->getKey())
                ->first();

            if ($setting) {
                return $setting;
            }
        }

        $globalSetting = $this->notificationSettings()
            ->where('notification_type_id', $type->id)
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->first();

        if ($globalSetting) {
            return $globalSetting;
        }

        return (object) [
            'is_enabled' => true,
            'channels' => $type->default_channels,
            'filters' => []
        ];
    }

    public function notificationSettings()
    {
        return $this->hasMany(UserNotificationSetting::class);
    }
}
