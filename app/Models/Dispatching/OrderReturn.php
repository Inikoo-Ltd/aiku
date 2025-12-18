<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 12:00:00 Makassar Time.
 * Description: Return model for customer order returns
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\Return\ReturnStateEnum;
use App\Enums\Dispatching\Return\ReturnTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Dispatching\Return
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $warehouse_id
 * @property int $shop_id
 * @property int $customer_id
 * @property string $reference
 * @property string $state
 * @property string $type
 * @property string|null $reason
 * @property int|null $delivery_note_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property int|null $address_id
 * @property string $total_amount
 * @property string $refund_amount
 * @property int $number_items
 * @property string|null $weight
 * @property \Illuminate\Support\Carbon $date
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $customer_notes
 * @property string|null $internal_notes
 * @property string|null $public_notes
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Address|null $address
 * @property-read Customer $customer
 * @property-read DeliveryNote|null $deliveryNote
 * @property-read Collection<int, ReturnItem> $returnItems
 * @property-read Warehouse $warehouse
 */
class OrderReturn extends Model implements Auditable
{
    use HasHistory;
    use HasSlug;
    use HasUniversalSearch;
    use InShop;
    use SoftDeletes;

    protected $table = 'returns';

    protected $casts = [
        'data'            => 'array',
        'state'           => ReturnStateEnum::class,
        'type'            => ReturnTypeEnum::class,
        'date'            => 'datetime',
        'submitted_at'    => 'datetime',
        'confirmed_at'    => 'datetime',
        'received_at'     => 'datetime',
        'checked_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
        'total_amount'    => 'decimal:2',
        'refund_amount'   => 'decimal:2',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['dispatching'];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->reference.'-'.$this->shop->slug;
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(128);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'return_order');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
