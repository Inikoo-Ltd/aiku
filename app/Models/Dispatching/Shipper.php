<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 22:27:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Dispatching;

use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Dispatching\Shipper
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $code
 * @property string|null $api_shipper
 * @property bool $status
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $tracking_url
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Organisation $organisation
 * @property-read Collection<int, \App\Models\Dispatching\Shipment> $shipments
 * @property-read \App\Models\Dispatching\ShipperStats|null $stats
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static Builder<static>|Shipper newModelQuery()
 * @method static Builder<static>|Shipper newQuery()
 * @method static Builder<static>|Shipper onlyTrashed()
 * @method static Builder<static>|Shipper query()
 * @method static Builder<static>|Shipper withTrashed()
 * @method static Builder<static>|Shipper withoutTrashed()
 * @mixin Eloquent
 */
class Shipper extends Model implements Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasHistory;
    use HasFactory;

    protected $casts = [
        'data'            => 'array',
        'settings'        => 'array',
        'status'          => 'boolean',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'        => '{}',
        'settings'    => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return [
            'dispatching'
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected array $auditInclude = [
        'code',
        'api_shipper',
        'status',
        'name',
        'contact_name',
        'company_name',
        'email',
        'phone',
        'website',
        'tracking_url',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ShipperStats::class);
    }

}
