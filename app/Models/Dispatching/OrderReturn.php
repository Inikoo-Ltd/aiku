<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Return model for customer order returns in warehouse management
 */

namespace App\Models\Dispatching;

use App\Enums\Dispatching\Return\ReturnStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Helpers\Address;
use App\Models\Helpers\UniversalSearch;
use App\Models\HumanResources\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Models\Traits\HasAddresses;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Dispatching\OrderReturn
 *
 * @property ReturnStateEnum $state
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Customer|null $customer
 * @property-read CustomerClient|null $customerClient
 * @property-read CustomerSalesChannel|null $customerSalesChannel
 * @property-read Group|null $group
 * @property-read Employee|null $inspector
 * @property-read User|null $inspectorUser
 * @property-read Collection<int, Order> $orders
 * @property-read Organisation|null $organisation
 * @property-read Platform|null $platform
 * @property-read Employee|null $receiver
 * @property-read User|null $receiverUser
 * @property-read Address|null $returnAddress
 * @property-read Collection<int, \App\Models\Dispatching\ReturnItem> $returnItems
 * @property-read Shop|null $shop
 * @property-read \App\Models\Dispatching\ReturnStats|null $stats
 * @property-read UniversalSearch|null $universalSearch
 * @property-read Warehouse|null $warehouse
 * @method static Builder<static>|OrderReturn newModelQuery()
 * @method static Builder<static>|OrderReturn newQuery()
 * @method static Builder<static>|OrderReturn onlyTrashed()
 * @method static Builder<static>|OrderReturn query()
 * @method static Builder<static>|OrderReturn withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|OrderReturn withoutTrashed()
 * @mixin Eloquent
 */
class OrderReturn extends Model implements Auditable
{
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use InCustomer;
    use HasAddresses;
    use HasHistory;

    protected $table = 'returns';

    protected $casts = [
        'data'          => 'array',
        'state'         => ReturnStateEnum::class,
        'date'          => 'datetime',
        'received_at'   => 'datetime',
        'inspecting_at' => 'datetime',
        'processed_at'  => 'datetime',
        'cancelled_at'  => 'datetime',
        'fetched_at'    => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        return ['dispatching'];
    }

    protected array $auditInclude = [
        'reference',
        'state',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_has_returns', 'return_id', 'order_id')->withTimestamps();
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ReturnStats::class, 'return_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function returnAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'receiver_id');
    }

    public function receiverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'inspector_id');
    }

    public function inspectorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    public function customerClient(): BelongsTo
    {
        return $this->belongsTo(CustomerClient::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }
}
