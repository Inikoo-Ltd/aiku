<?php

namespace App\Models\Traits;

use App\Models\Notifications\NotificationType;
use App\Models\Notifications\UserNotificationSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasNotificationSettings
{
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

        // Try to find user specific setting
        $query = $this->notificationSettings()
            ->where('notification_type_id', $type->id);

        if ($scope) {
            $query->where('scope_type', get_class($scope))
                  ->where('scope_id', $scope->getKey());
        } else {
            $query->whereNull('scope_type')
                  ->whereNull('scope_id');
        }

        $setting = $query->first();

        if ($setting) {
            return $setting;
        }

        // Return default from type
        return (object) [
            'is_enabled' => true,
            'channels' => $type->default_channels ?? ['database'],
            'filters' => []
        ];
    }

    public function notificationSettings(): HasMany
    {
        return $this->hasMany(UserNotificationSetting::class);
    }
}
