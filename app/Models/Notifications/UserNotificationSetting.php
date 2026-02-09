<?php

namespace App\Models\Notifications;

use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserNotificationSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'channels' => 'array',
        'filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }

    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
