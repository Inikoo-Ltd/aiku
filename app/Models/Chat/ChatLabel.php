<?php

/*
 * Author: Yudhistira <artha@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A label customer service puts on email and website chat conversations to sort them, shared by
 * everybody in the organisation.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $name
 * @property string $colour
 * @property int|null $created_by
 */
class ChatLabel extends Model
{
    protected $guarded = [];

    public function chatSessions(): BelongsToMany
    {
        return $this->belongsToMany(ChatSession::class)->withPivot('created_at');
    }
}
