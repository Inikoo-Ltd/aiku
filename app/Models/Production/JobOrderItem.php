<?php

namespace App\Models\Production;

use App\Enums\Production\JobOrderItem\JobOrderItemStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $job_order_id
 * @property int $artefact_id
 * @property string|null $slug
 * @property string|null $reference
 * @property JobOrderItemStatusEnum $status
 * @property JobOrderItemStateEnum $state
 * @property string|null $notes
 * @property int $quantity
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property-read \App\Models\Production\Artefact $artefact
 * @property-read Group $group
 * @property-read \App\Models\Production\JobOrder $jobOrder
 * @property-read Organisation $organisation
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrderItem withoutTrashed()
 * @mixin \Eloquent
 */

class JobOrderItem extends Model
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;

    protected $guarded = [];
    protected $casts   = [
        'data'                     => 'array',
        'state'                    => JobOrderItemStateEnum::class,
        'status'                   => JobOrderItemStatusEnum::class,
        'set_as_not_received_at'   => 'datetime',
        'received_at'              => 'datetime',
        'booking_in_at'            => 'datetime',
        'booked_in_at'             => 'datetime',
        'storing_at'               => 'datetime',
        'requested_for_return_at'  => 'datetime',
        'picking_at'               => 'datetime',
        'picked_at'                => 'datetime',
        'set_as_incident_at'       => 'datetime',
        'dispatched_at'            => 'datetime',

    ];

    protected $attributes = [
        'data'  => '{}',
        'notes' => '',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->doNotGenerateSlugsOnUpdate()
            ->doNotGenerateSlugsOnCreate()
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function artefact(): BelongsTo
    {
        return $this->belongsTo(Artefact::class);

    }
}
