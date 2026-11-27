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
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A file shared in Slack, one row per Slack file id and side however many messages share it: media or the large files disk
 * hold the public copy; a private channel or DM file (is_private) is kept on disk/path under an opaque name and never in the
 * media library, so it has no URL of its own and is served only through ShowSlackMessageFile to the conversation's members.
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
 * @property string|null $disk
 * @property string|null $path
 * @property array|null $data
 * @property bool $is_private
 */
class SlackFile extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data'  => 'array',
            'state'      => SlackFileStateEnum::class,
            'is_private' => 'boolean',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class, 'slack_message_id');
    }

    /**
     * A link to a copy kept on the large files disk, good for a few minutes; give it only to someone allowed to see the channel.
     */
    public function temporaryUrl(int $minutes = 5, array $options = []): ?string
    {
        return !$this->is_private && $this->disk && $this->path ? Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes), $options) : null;
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Removes aiku's copy, wherever it is kept, and the row's pointers to it; a copied row goes back to pending, so the file is copied
     * again only if a message still lists it. Safe to call twice.
     */
    public function deleteStoredCopy(): void
    {
        $this->copy?->delete();
        if ($this->disk && $this->path) {
            Storage::disk($this->disk)->delete($this->path);
        }
        $this->update(['media_id' => null, 'disk' => null, 'path' => null] + ($this->state === SlackFileStateEnum::COPIED ? ['state' => SlackFileStateEnum::PENDING, 'reason' => null] : []));
    }
}
