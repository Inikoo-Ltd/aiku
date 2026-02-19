<?php

namespace App\Models\Inventory;

use App\Models\Dispatching\DeliveryNote;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property bool $status
 * @property int $warehouse_id
 * @property string $slug
 * @property string $code
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int $number_delivery_notes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNote> $deliveryNotes
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickedBay newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickedBay newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickedBay onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickedBay query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickedBay withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PickedBay withoutTrashed()
 * @mixin \Eloquent
 */
class PickedBay extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use HasHistory;
    use InWarehouse;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'number_delivery_notes' => 'integer',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function generateTags(): array
    {
        return [
            'dispatching'
        ];
    }

    protected array $auditInclude = [
        'code','status'
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryNote::class, 'delivery_note_has_trolleys')
            ->withTimestamps();
    }



}
