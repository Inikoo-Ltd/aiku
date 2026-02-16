<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Nov 2024 16:59:56 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Comms;

use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Models\Helpers\Deployment;
use App\Models\Helpers\Snapshot;
use App\Models\Traits\HasHistory;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int|null $shop_id
 * @property int $outbox_id
 * @property string|null $parent_type
 * @property int|null $parent_id
 * @property string|null $identifier
 * @property EmailBuilderEnum $builder
 * @property string $subject
 * @property int|null $unpublished_snapshot_id
 * @property int|null $live_snapshot_id
 * @property int|null $screenshot_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property string|null $source_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Deployment> $deployments
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read Snapshot|null $liveSnapshot
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Comms\Outbox $outbox
 * @property-read Model|\Eloquent|null $parent
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Snapshot> $snapshots
 * @property-read Snapshot|null $unpublishedSnapshot
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Email query()
 * @mixin \Eloquent
 */
class Email extends Model implements Auditable
{
    use HasHistory;
    use InShop;

    protected $casts = [
        'data'            => 'array',
        'builder'         => EmailBuilderEnum::class,
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
    ];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(Outbox::class);
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    public function snapshots(): MorphMany
    {
        return $this->morphMany(Snapshot::class, 'parent');
    }

    public function unpublishedSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'unpublished_snapshot_id');
    }

    public function liveSnapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'live_snapshot_id');
    }

    public function deployments(): MorphMany
    {
        return $this->morphMany(Deployment::class, 'model');
    }

}
