<?php

namespace App\Models\Notifications;

use Eloquent;
use hisorange\BrowserDetect\Parser as Browser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Notifications\FcmToken
 *
 * @property int $id
 * @property string $token_id
 * @property string $push_notifiable_type
 * @property int $push_notifiable_id
 * @property string $fcm_token
 * @property string|null $platform
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $fcmable
 * @property-read string $token
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FcmToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FcmToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FcmToken query()
 *
 * @mixin Eloquent
 */
class FcmToken extends Model
{
    protected $guarded = [];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $parsedUserAgent = (new Browser)->parse(request()->server('HTTP_USER_AGENT'));

            $model->platform = $parsedUserAgent->userAgent();
        });
    }

    public function getTokenAttribute(): string
    {
        return $this->fcm_token;
    }

    public function fcmable(): MorphTo
    {
        return $this->morphTo();
    }
}
