<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:26:20 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Payment;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Helpers\TaxCategory;
use App\Models\Helpers\UniversalSearch;
use App\Models\ShopifyUserHasFulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InCustomer;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Ordering\Order
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $shop_id
 * @property int $customer_id
 * @property int|null $sales_channel_id
 * @property int|null $customer_client_id
 * @property string|null $reference
 * @property string|null $customer_reference Customers own order reference
 * @property string|null $nest
 * @property OrderStateEnum $state
 * @property OrderStatusEnum $status
 * @property OrderHandingTypeEnum $handing_type
 * @property bool $customer_locked
 * @property bool $billing_locked
 * @property bool $delivery_locked
 * @property int|null $estimated_weight grams
 * @property int|null $weight actual weight, grams
 * @property array<array-key, mixed> $payment_data
 * @property int|null $billing_address_id
 * @property int|null $delivery_address_id
 * @property int|null $collection_address_id
 * @property int|null $billing_country_id
 * @property int|null $delivery_country_id
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $in_warehouse_at
 * @property \Illuminate\Support\Carbon|null $handling_at
 * @property \Illuminate\Support\Carbon|null $packed_at
 * @property \Illuminate\Support\Carbon|null $finalised_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $settled_at dispatched_at|cancelled_at
 * @property bool $is_invoiced
 * @property bool|null $is_handling_on_hold
 * @property bool|null $can_dispatch
 * @property string|null $customer_notes
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property int $currency_id
 * @property numeric|null $grp_exchange
 * @property numeric|null $org_exchange
 * @property string $gross_amount Total asserts amount (excluding charges and shipping) before discounts
 * @property string $goods_amount
 * @property string $services_amount
 * @property string $charges_amount
 * @property string|null $shipping_amount
 * @property string|null $insurance_amount
 * @property string $net_amount
 * @property string|null $grp_net_amount
 * @property string|null $org_net_amount
 * @property int $tax_category_id
 * @property string $tax_amount
 * @property string $total_amount
 * @property numeric $payment_amount
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $source_id
 * @property bool $is_vip Indicate if order is for a VIP customer
 * @property int|null $as_organisation_id Indicate if order is for an organisation in this group
 * @property int|null $as_employee_id Indicate if order is for an employee
 * @property int|null $platform_id
 * @property-read Collection<int, Address> $addresses
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Address|null $billingAddress
 * @property-read Address|null $collectionAddress
 * @property-read Currency $currency
 * @property-read \App\Models\CRM\Customer $customer
 * @property-read CustomerClient|null $customerClient
 * @property-read Address|null $deliveryAddress
 * @property-read Collection<int, DeliveryNote> $deliveryNotes
 * @property-read Collection<int, DispatchedEmail> $dispatchedEmails
 * @property-read Collection<int, Address> $fixedAddresses
 * @property-read Group $group
 * @property-read Collection<int, Invoice> $invoices
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read Organisation $organisation
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Platform> $platforms
 * @property-read \App\Models\Ordering\SalesChannel|null $salesChannel
 * @property-read Shop $shop
 * @property-read ShopifyUserHasFulfilment|null $shopifyOrder
 * @property-read \App\Models\Ordering\OrderStats|null $stats
 * @property-read TaxCategory $taxCategory
 * @property-read Collection<int, \App\Models\Ordering\Transaction> $transactions
 * @property-read UniversalSearch|null $universalSearch
 * @method static \Database\Factories\Ordering\OrderFactory factory($count = null, $state = [])
 * @method static Builder<static>|Order newModelQuery()
 * @method static Builder<static>|Order newQuery()
 * @method static Builder<static>|Order onlyTrashed()
 * @method static Builder<static>|Order query()
 * @method static Builder<static>|Order withTrashed()
 * @method static Builder<static>|Order withoutTrashed()
 * @mixin Eloquent
 */
class Order extends Model implements HasMedia, Auditable
{
    use HasSlug;
    use SoftDeletes;
    use HasUniversalSearch;
    use HasFactory;
    use InCustomer;
    use HasAddresses;
    use HasAttachments;
    use HasHistory;


    protected $casts = [
        'data'         => 'array',
        'payment_data' => 'array',


        'date'            => 'datetime',
        'submitted_at'    => 'datetime',
        'in_warehouse_at' => 'datetime',
        'handling_at'     => 'datetime',
        'packed_at'       => 'datetime',
        'finalised_at'    => 'datetime',
        'dispatched_at'   => 'datetime',
        'cancelled_at'    => 'datetime',
        'settled_at'      => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'grp_exchange'    => 'decimal:4',
        'org_exchange'    => 'decimal:4',
        'payment_amount'  => 'decimal:2',


        'state'        => OrderStateEnum::class,
        'status'       => OrderStatusEnum::class,
        'handing_type' => OrderHandingTypeEnum::class,

    ];

    protected $attributes = [
        'data'         => '{}',
        'payment_data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['ordering'];
    }

    protected array $auditInclude = [
        'reference',
        'handing_type',
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
            ->saveSlugsTo('slug');
    }

    public function customerClient(): BelongsTo
    {
        return $this->belongsTo(CustomerClient::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }


    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryNote::class, 'delivery_note_order')->withTimestamps();
    }

    public function payments(): MorphToMany
    {
        return $this->morphToMany(Payment::class, 'model', 'model_has_payments')->withTimestamps()->withPivot(['amount', 'share']);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrderStats::class);
    }

    public function fixedAddresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'model', 'model_has_fixed_addresses')->withTimestamps();
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function collectionAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function shopifyOrder(): MorphOne
    {
        return $this->morphOne(ShopifyUserHasFulfilment::class, 'model');
    }

    public function addresses(): MorphToMany
    {
        return $this->morphToMany(Address::class, 'model', 'model_has_addresses')->withTimestamps();
    }

    public function platforms(): MorphToMany
    {
        return $this->morphToMany(Platform::class, 'model', 'model_has_platforms')->withTimestamps();
    }

    public function platform(): Platform|null
    {
        /** @var Platform $platform */
        $platform = $this->platforms()->first();

        return $platform;
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function dispatchedEmails(): MorphToMany
    {
        return $this->morphToMany(DispatchedEmail::class, 'model', 'model_has_dispatched_emails')->withTimestamps();
    }

    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class);
    }


}
