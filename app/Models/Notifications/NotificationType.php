<?php

namespace App\Models\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationType extends Model
{
    protected $guarded = [];

    protected $casts = [
        'available_channels' => 'array',
        'default_channels' => 'array',
    ];

    public function userSettings(): HasMany
    {
        return $this->hasMany(UserNotificationSetting::class);
    }
}
