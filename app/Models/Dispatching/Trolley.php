<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 13:57:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InWarehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Dispatching\Trolley
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $warehouse_id
 * @property string $name
 * @property int|null $current_delivery_note_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $picking_session_id
 * @property-read DeliveryNote $currentDeliveryNote
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read \App\Models\Inventory\Warehouse $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trolley newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trolley newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Trolley query()
 * @mixin \Eloquent
 */
class Trolley extends Model implements Auditable
{
    use HasFactory;
    use HasSlug;
    use HasUniversalSearch;
    use HasHistory;
    use InWarehouse;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function generateTags(): array
    {
        return [
            'dispatching'
        ];
    }

    protected array $auditInclude = [
        'name',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function currentDeliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class, 'current_delivery_note_id');
    }
}
