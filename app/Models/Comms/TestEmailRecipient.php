<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 11:17:21 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $group_id
 * @property string|null $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestEmailRecipient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestEmailRecipient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestEmailRecipient query()
 * @mixin \Eloquent
 */
class TestEmailRecipient extends Model
{
    use InGroup;

    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dispatchedEmails(): BelongsToMany
    {
        return $this->belongsToMany(DispatchedEmail::class, 'test_email_recipient_has_dispatched_emails');
    }
}
