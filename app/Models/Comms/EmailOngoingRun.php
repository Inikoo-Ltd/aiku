<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 08:14:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunStatusEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunCodeEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $outbox_id
 * @property int|null $email_id
 * @property EmailOngoingRunCodeEnum $code
 * @property EmailOngoingRunTypeEnum $type
 * @property EmailOngoingRunStatusEnum $status
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\DispatchedEmail> $dispatchedEmails
 * @property-read \App\Models\Comms\Email|null $email
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comms\EmailBulkRun> $emailBulkRuns
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Comms\EmailOngoingRunIntervals|null $intervals
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\Outbox|null $outbox
 * @property-read Shop|null $shop
 * @property-read \App\Models\Comms\EmailOngoingRunStats|null $stats
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailOngoingRun newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailOngoingRun newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailOngoingRun query()
 * @mixin \Eloquent
 */
class EmailOngoingRun extends Model
{
    use InShop;

    protected $casts = [
        'data'            => 'array',
        'code'            => EmailOngoingRunCodeEnum::class,
        'type'            => EmailOngoingRunTypeEnum::class,
        'status'          => EmailOngoingRunStatusEnum::class,
        'date'            => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'comms'
        ];
    }

    protected array $auditInclude = [
        'subject',
        'schedule_at',
    ];


    public function email(): MorphOne
    {
        return $this->morphOne(Email::class, 'parent');
    }


    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(EmailOngoingRunStats::class);
    }

    public function intervals(): HasOne
    {
        return $this->hasOne(EmailOngoingRunIntervals::class);
    }

    public function dispatchedEmails(): MorphMany
    {
        return $this->morphMany(DispatchedEmail::class, 'parent');
    }

    public function emailBulkRuns(): HasMany
    {
        return $this->hasMany(EmailBulkRun::class);
    }

    public function sender(): string
    {
        if (app()->environment('production')) {
            /** @var Shop $parent */
            $parent = $this->shop;
            $sender = $parent->senderEmail?->email_address ?? $parent->email;
        } else {
            $sender = config('app.email_address_in_non_production_env');
        }

        return $sender;
    }
}
