<?php

namespace App\Models\Production;

use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string|null $slug
 * @property string|null $reference
 * @property int $production_id
 * @property JobOrderStateEnum $state
 * @property \Illuminate\Support\Carbon|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $not_received_at
 * @property \Illuminate\Support\Carbon|null $booking_in_at
 * @property \Illuminate\Support\Carbon|null $booked_in_at
 * @property string|null $date
 * @property string|null $customer_notes
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property-read Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Production\JobOrderItem> $jobOrderItems
 * @property-read Organisation $organisation
 * @property-read \App\Models\Production\Production $production
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrder onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrder withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobOrder withoutTrashed()
 * @mixin \Eloquent
 */

class JobOrder extends Model
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;

    protected $guarded = [];

    protected $casts   = [
        'state'               => JobOrderStateEnum::class,
        'in_process_at'       => 'datetime',
        'submitted_at'        => 'datetime',
        'confirmed_at'        => 'datetime',
        'received_at'         => 'datetime',
        'not_received_at'     => 'datetime',
        'booked_in_at'        => 'datetime',
        'booking_in_at'       => 'datetime',
        'dispatched_at'       => 'datetime',
        'data'                => 'array'
    ];

    protected $attributes = [
        'data'  => '{}',
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

    public function jobOrderItems(): HasMany
    {
        return $this->hasMany(JobOrderItem::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    // pls review
}
