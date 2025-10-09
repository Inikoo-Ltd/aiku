<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 12:47:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $outbox_id
 * @property string $model_type Customer|Prospect
 * @property int $model_id
 * @property string $type subscribe|unsubscribe
 * @property string|null $origin_type EmailBulkRun|Mailshot|Website|Customer (Customer is used when a user unsubscribes from aiku UI)
 * @property string|null $origin_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $source_id
 * @property string|null $source_alt_id
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Model|\Eloquent $model
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Comms\Outbox $outbox
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEvent query()
 * @mixin \Eloquent
 */
class SubscriptionEvent extends Model
{
    use InShop;

    public const null UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'data'               => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }



}
