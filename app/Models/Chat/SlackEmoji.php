<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A custom emoji of the Slack workspace; an alias has no image of its own, and one removed in Slack is kept for old messages.
 *
 * @property int $id
 * @property string $name
 * @property string|null $alias_for
 * @property string|null $url
 * @property int|null $media_id
 * @property \Illuminate\Support\Carbon|null $removed_in_slack_at
 */
class SlackEmoji extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'slack_emojis';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'removed_in_slack_at' => 'datetime',
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
