<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One part of the brief the AI customer service routine works from: the core rules, or one section
 * of the CS knowledge appendix. Kept here, not in the repository, because the repository is public.
 * Rows are never edited: setting a section adds a version, the newest one per key is the live text,
 * and the older ones are its history.
 *
 * @property int $id
 * @property int $group_id
 * @property string $key
 * @property string $title
 * @property string $body
 * @property int|null $set_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ChatAiBriefSection extends Model
{
    protected $guarded = [];

    /**
     * @param  Builder<ChatAiBriefSection>  $query
     */
    public function scopeLive(Builder $query, int $groupId): void
    {
        $query->where('group_id', $groupId)
            ->whereIn('id', fn ($latest) => $latest->from('chat_ai_brief_sections')->where('group_id', $groupId)->selectRaw('max(id)')->groupBy('key'));
    }
}
