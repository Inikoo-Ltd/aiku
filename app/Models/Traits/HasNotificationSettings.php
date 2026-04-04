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

    public function shouldReceiveNotification(string $typeSlug, array $payload = [], ?Model $scope = null): bool
    {
        $setting = $this->getNotificationSetting($typeSlug, $scope);

        if (!$setting->is_enabled) {
            return false;
        }

        if (empty($setting->filters)) {
            return true;
        }

        foreach ($setting->filters as $key => $allowedValues) {
            // If the payload doesn't have the key, we skip checking this key (optional filter)
            // OR strict mode: return false if key missing. Let's assume optional for now.
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $actualValue = $payload[$key];

            // If allowedValues is an array, check if actualValue is in it
            if (is_array($allowedValues)) {
                if (!in_array($actualValue, $allowedValues)) {
                    return false;
                }
            }
            // If allowedValues is a single value, check for equality
            else {
                if ($actualValue != $allowedValues) {
                    return false;
                }
            }
        }

        return true;
    }

    public function notificationSettings(): HasMany
    {
        return $this->hasMany(UserNotificationSetting::class);
    }
}
