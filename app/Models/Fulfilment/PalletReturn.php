<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 13 Feb 2024 16:23:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Fulfilment;

use App\Enums\Fulfilment\PalletReturn\PalletReturnItemNoSetReasonStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dispatching\Shipment;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Inventory\Warehouse;
use App\Models\ShopifyUserHasFulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasRetinaSearch;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Fulfilment\PalletDelivery
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property string $ulid
 * @property int $fulfilment_customer_id
 * @property int $fulfilment_id
 * @property int|null $warehouse_id
 * @property string|null $customer_reference
 * @property string $reference
 * @property PalletReturnTypeEnum $type Pallet|StoredItem
 * @property PalletReturnStateEnum $state
 * @property \Illuminate\Support\Carbon|null $in_process_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $picking_at
 * @property \Illuminate\Support\Carbon|null $picked_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property string|null $consolidated_at
 * @property \Illuminate\Support\Carbon|null $cancel_at
 * @property string|null $date
 * @property array<array-key, mixed>|null $data
 * @property string|null $customer_notes
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property int|null $delivery_address_id
 * @property int|null $collection_address_id
 * @property int $currency_id
 * @property string|null $grp_exchange
 * @property string|null $org_exchange
 * @property string $gross_amount Total asserts amount (excluding charges and shipping) before discounts
 * @property string $goods_amount
 * @property string $services_amount
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property int $tax_category_id
 * @property string $tax_amount
 * @property string $total_amount
 * @property string $payment_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $deleted_note
 * @property bool $is_collection
 * @property int|null $invoice_id
 * @property int|null $recurring_bill_id
 * @property int|null $shopify_user_id
 * @property int|null $platform_id
 * @property string|null $collection_notes
 * @property string|null $estimated_delivery_date
 * @property int|null $deleted_by
 * @property array<array-key, mixed>|null $parcels
 * @property PalletReturnItemNoSetReasonStateEnum $not_setup_reason
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Currency $currency
 * @property-read Address|null $deliveryAddress
 * @property-read mixed $discount_amount
 * @property-read Collection<int, Address> $fixedAddresses
 * @property-read \App\Models\Fulfilment\Fulfilment $fulfilment
 * @property-read \App\Models\Fulfilment\FulfilmentCustomer $fulfilmentCustomer
 * @property-read Group $group
 * @property-read Collection<int, \App\Models\Fulfilment\PalletReturnItem> $items
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Collection<int, \App\Models\Fulfilment\Pallet> $pallets
 * @property-read Platform|null $platform
 * @property-read \App\Models\Fulfilment\RecurringBill|null $recurringBill
 * @property-read \App\Models\Helpers\RetinaSearch|null $retinaSearch
 * @property-read Collection<int, Shipment> $shipments
 * @property-read ShopifyUserHasFulfilment|null $shopifyFulfilment
 * @property-read \App\Models\Fulfilment\PalletReturnStats|null $stats
 * @property-read Collection<int, \App\Models\Fulfilment\StoredItem> $storedItems
 * @property-read TaxCategory $taxCategory
 * @property-read Collection<int, \App\Models\Fulfilment\FulfilmentTransaction> $transactions
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @property-read Warehouse|null $warehouse
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturn newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturn newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturn onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturn query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturn withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PalletReturn withoutTrashed()
 * @mixin \Eloquent
 */
class PalletReturn extends Model implements HasMedia
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasRetinaSearch;
    use HasAddress;
    use HasAddresses;
    use HasAttachments;

    protected $guarded = [];
    protected $casts = [
        'parcels'          => 'array',
        'state'            => PalletReturnStateEnum::class,
        'type'             => PalletReturnTypeEnum::class,
        'not_setup_reason' => PalletReturnItemNoSetReasonStateEnum::class,
        'in_process_at'    => 'datetime',
        'submitted_at'     => 'datetime',
        'confirmed_at'     => 'datetime',
        'picking_at'       => 'datetime',
        'picked_at'        => 'datetime',
        'dispatched_at'    => 'datetime',
        'cancel_at'        => 'datetime',
        'data'             => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
        'parcels' => '{}',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $this->gross_amount - $this->net_amount
        );
    }


    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function fulfilment(): BelongsTo
    {
        return $this->belongsTo(Fulfilment::class);
    }

    public function fulfilmentCustomer(): BelongsTo
    {
        return $this->belongsTo(FulfilmentCustomer::class);
    }

    public function pallets(): BelongsToMany
    {
        return $this->belongsToMany(Pallet::class, 'pallet_return_items')->withPivot('state', 'id');
    }

    public function storedItems(): BelongsToMany
    {
        return $this->belongsToMany(StoredItem::class, 'pallet_return_items')->withPivot('state', 'id', 'pallet_id', 'pallet_stored_item_id', 'quantity_ordered');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(PalletReturnStats::class);
    }

    public function recurringBill(): BelongsTo
    {
        return $this->belongsTo(RecurringBill::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(FulfilmentTransaction::class, 'parent');
    }

    public function services(): Collection
    {
        return $this->transactions()->where('type', 'service')->get();
    }

    public function products(): Collection
    {
        return $this->transactions()->where('type', 'product')->get();
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function shopifyFulfilment(): MorphOne
    {
        return $this->morphOne(ShopifyUserHasFulfilment::class, 'model');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PalletReturnItem::class);
    }

    public function shipments(): MorphToMany
    {
        return $this->morphToMany(Shipment::class, 'model', 'model_has_shipments');
    }

    public function fixedAddresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'model', 'model_has_fixed_addresses')->withTimestamps();
    }

}
