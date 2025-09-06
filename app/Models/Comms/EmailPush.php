<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Dec 2024 01:35:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\EmailPush\EmailPushExitStatusEnum;
use App\Enums\Comms\EmailPush\EmailPushStateEnum;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $number_pushed
 * @property int $number_pending_pushes
 * @property int $number_sent_pushes
 * @property EmailPushStateEnum $state
 * @property string|null $recipient_type
 * @property int|null $recipient_id
 * @property \Illuminate\Support\Carbon|null $next_push_at
 * @property string $next_push_identifier
 * @property EmailPushExitStatusEnum $exit_status
 * @property string|null $exit_breakpoint
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property array<array-key, mixed> $sources
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\DispatchedEmail> $dispatchedEmails
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\Outbox|null $outbox
 * @property-read Model|\Eloquent|null $recipient
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailPush newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailPush newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailPush query()
 * @mixin \Eloquent
 */
class EmailPush extends Model
{
    use InShop;

    protected $casts = [
        'data'            => 'array',
        'sources'         => 'array',
        'state'           => EmailPushStateEnum::class,
        'exit_status'     => EmailPushExitStatusEnum::class,
        'next_push_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',


    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function dispatchedEmails(): MorphMany
    {
        return $this->morphMany(DispatchedEmail::class, 'parent');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

}
