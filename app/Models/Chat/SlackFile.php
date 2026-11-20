<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 16:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use App\Enums\CRM\Livechat\SlackFileStateEnum;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A file shared in a public Slack channel, one row per Slack file id however many messages share it; media is aiku's copy.
 *
 * @property int $id
 * @property int $group_id
 * @property string $slack_id
 * @property int|null $slack_message_id
 * @property string|null $name
 * @property string|null $mime_type
 * @property int|null $size
 * @property string|null $mode
 * @property SlackFileStateEnum $state
 * @property string|null $reason
 * @property int|null $media_id
 * @property array|null $data
 */
class SlackFile extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'  => 'array',
            'state' => SlackFileStateEnum::class,
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class, 'slack_message_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
}
