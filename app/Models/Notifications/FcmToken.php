<?php

namespace App\Models\Notifications;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * App\Models\Notifications\FcmToken
 *
 * @property int $id
 * @property int $personal_access_token_id
 * @property string $push_notifiable_type
 * @property int $push_notifiable_id
 * @property string $fcm_token
 * @property string|null $platform
 * @property string|null $app_version
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|\Eloquent $pushNotifiable
 * @property-read PersonalAccessToken $personalAccessToken
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FcmToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FcmToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FcmToken query()
 * @mixin Eloquent
 */
class FcmToken extends Model
{
    protected $guarded = [];

    public function pushNotifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }
}
