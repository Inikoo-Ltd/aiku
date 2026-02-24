<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:11:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Comms\EmailTrackingEvent
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $dispatched_email_id
 * @property EmailTrackingEventTypeEnum $type
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property string|null $ip
 * @property string|null $device
 * @property-read \App\Models\Comms\DispatchedEmail $dispatchedEmail
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @method static Builder<static>|EmailTrackingEvent newModelQuery()
 * @method static Builder<static>|EmailTrackingEvent newQuery()
 * @method static Builder<static>|EmailTrackingEvent query()
 * @mixin Eloquent
 */
class EmailTrackingEvent extends Model
{
    use inOrganisation;

    protected $casts = [
        'data'  => 'array',
        'type'  => EmailTrackingEventTypeEnum::class
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function dispatchedEmail(): BelongsTo
    {
        return $this->belongsTo(DispatchedEmail::class);
    }
}
