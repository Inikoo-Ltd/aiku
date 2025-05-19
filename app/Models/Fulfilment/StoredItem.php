<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jul 2023 12:13:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUserHasProduct;
use App\Models\Dropshipping\TiktokUserHasProduct;
use App\Models\Inventory\Warehouse;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasRetinaSearch;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Fulfilment\Fulfilment\StoredItem
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $reference
 * @property StoredItemStateEnum $state
 * @property int $fulfilment_id
 * @property int $fulfilment_customer_id
 * @property string $notes
 * @property bool $return_requested
 * @property string|null $received_at
 * @property string|null $booked_in_at
 * @property string|null $settled_at
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $incident_report
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $source_id
 * @property string|null $name
 * @property string $total_quantity Total stock of the item in the warehouse
 * @property int|null $number_pallets
 * @property int $number_audits
 * @property string|null $last_audit_at
 * @property int|null $last_stored_item_audit_delta_id
 * @property int|null $last_stored_item_audit_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\PalletReturn> $palletReturns
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\PalletStoredItem> $palletStoredItems
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fulfilment\Pallet> $pallets
 * @property-read Portfolio|null $portfolio
 * @property-read \App\Models\Helpers\RetinaSearch|null $retinaSearch
 * @property-read ShopifyUserHasProduct|null $shopifyPortfolio
 * @property-read TiktokUserHasProduct|null $tiktokPortfolio
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Warehouse|null $warehouse
 * @method static Builder<static>|StoredItem newModelQuery()
 * @method static Builder<static>|StoredItem newQuery()
 * @method static Builder<static>|StoredItem query()
 * @mixin Eloquent
 */
class StoredItem extends Model implements Auditable
{
    use HasUniversalSearch;
    use HasRetinaSearch;
    use HasSlug;
    use HasHistory;
    use InOrganisation;

    protected $casts = [
        'data'            => 'array',
        'incident_report' => 'array',
        'state'           => StoredItemStateEnum::class,
    ];

    protected $attributes = [
        'data'            => '{}',
        'incident_report' => '{}',
        'notes'           => '',
    ];

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(Fulfilment::class);
    }

    public function fulfilmentCustomer(): BelongsTo
    {
        return $this->belongsTo(FulfilmentCustomer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function pallets(): BelongsToMany
    {
        return $this->belongsToMany(Pallet::class, 'pallet_stored_items')->withPivot('quantity', 'id');
    }

    public function palletStoredItems(): HasMany
    {
        return $this->hasMany(PalletStoredItem::class);
    }

    public function shopifyPortfolio(): MorphOne
    {
        return $this->morphOne(ShopifyUserHasProduct::class, 'product');
    }

    public function tiktokPortfolio(): MorphOne
    {
        return $this->morphOne(TiktokUserHasProduct::class, 'productable');
    }

    public function portfolio(): MorphOne
    {
        return $this->morphOne(Portfolio::class, 'item');
    }

    public function palletReturns(): BelongsToMany
    {
        return $this->belongsToMany(PalletReturn::class, 'pallet_return_items')->withPivot('quantity_ordered');
    }
}
