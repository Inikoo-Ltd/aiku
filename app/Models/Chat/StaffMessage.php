<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Helpers\Media;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasImage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $staff_conversation_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 */
class StaffMessage extends Model implements HasMedia
{
    use SoftDeletes;
    use HasImage;
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StaffConversation::class, 'staff_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StaffMessage::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(StaffMessageReaction::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(StaffMessageTranslation::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
