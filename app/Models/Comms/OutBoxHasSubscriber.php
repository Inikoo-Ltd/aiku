<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Mar 2025 11:04:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Models\SysAdmin\User;
use App\Models\Traits\InOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $outbox_id
 * @property int|null $user_id null if external email is set
 * @property string|null $external_email null if user_id is set
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\Outbox $outbox
 * @property-read User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutBoxHasSubscriber newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutBoxHasSubscriber newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OutBoxHasSubscriber query()
 * @mixin \Eloquent
 */
class OutBoxHasSubscriber extends Model
{
    use InOrganisation;

    protected $table = 'outbox_has_subscribers';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

}
