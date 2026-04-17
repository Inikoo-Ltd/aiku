<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Mar 2026 12:01:25 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $group_id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\DispatchedEmail> $dispatchedEmails
 * @property-read \App\Models\SysAdmin\Group|null $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalSubscriberEmailRecipient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalSubscriberEmailRecipient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalSubscriberEmailRecipient query()
 * @mixin \Eloquent
 */
class ExternalSubscriberEmailRecipient extends Model
{
    use InGroup;

    protected $guarded = [];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dispatchedEmails(): BelongsToMany
    {
        return $this->belongsToMany(DispatchedEmail::class, 'external_subscriber_email_recipient_has_dispatched_emails');
    }
}
