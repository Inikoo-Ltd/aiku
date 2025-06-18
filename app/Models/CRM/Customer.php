<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:21:10 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\CRM;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCustomerInvoices;
use App\Enums\CRM\Customer\CustomerRejectReasonEnum;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\MitSavedCard;
use App\Models\Accounting\Payment;
use App\Models\Accounting\TopUp;
use App\Models\Accounting\TopUpPaymentApiPoint;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Comms\SubscriptionEvent;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\AmazonUser;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\Goods\Stock;
use App\Models\Helpers\Address;
use App\Models\Helpers\Media;
use App\Models\Helpers\TaxNumber;
use App\Models\Helpers\UniversalSearch;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasEmail;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\CRM\Customer
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property string $slug
 * @property string|null $reference customer public id
 * @property string|null $name
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property string|null $contact_website
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property int|null $delivery_address_id
 * @property CustomerStatusEnum $status
 * @property CustomerStateEnum $state
 * @property string $balance
 * @property CustomerTradeStateEnum $trade_state number of invoices
 * @property bool $is_fulfilment
 * @property bool $is_dropshipping
 * @property \Illuminate\Support\Carbon|null $last_submitted_order_at
 * @property \Illuminate\Support\Carbon|null $last_dispatched_delivery_at
 * @property \Illuminate\Support\Carbon|null $last_invoiced_at
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property string|null $internal_notes
 * @property string|null $warehouse_internal_notes
 * @property string|null $warehouse_public_notes
 * @property int|null $prospects_sender_email_id
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property array<array-key, mixed> $migration_data
 * @property string|null $registered_at
 * @property CustomerRejectReasonEnum|null $rejected_reason
 * @property string|null $rejected_notes
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property bool $is_vip VIP customer
 * @property int|null $as_organisation_id Indicate customer is an organisation in this group
 * @property int|null $as_employee_id Indicate customer is an employee
 * @property string|null $approved_at
 * @property numeric $amount_in_basket
 * @property int|null $current_order_in_basket_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property int $number_exclusive_products
 * @property-read Address|null $address
 * @property-read Collection<int, Address> $addresses
 * @property-read Collection<int, \App\Models\CRM\Appointment> $appointments
 * @property-read MediaCollection<int, Media> $attachments
 * @property-read Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read Collection<int, \App\Models\CRM\BackInStockReminder> $backInStockReminder
 * @property-read Collection<int, CustomerClient> $clients
 * @property-read \App\Models\CRM\CustomerComms|null $comms
 * @property-read Collection<int, CreditTransaction> $creditTransactions
 * @property-read Collection<int, \App\Models\CRM\CustomerNote> $customerNotes
 * @property-read Collection<int, CustomerSalesChannel> $customerSalesChannels
 * @property-read Collection<int, Platform> $customerSalesChannelsXXX
 * @property-read Address|null $deliveryAddress
 * @property-read Collection<int, DeliveryNote> $deliveryNotes
 * @property-read EbayUser|null $ebayUser
 * @property-read Collection<int, Product> $exclusiveProducts
 * @property-read Collection<int, \App\Models\CRM\Favourite> $favourites
 * @property-read FulfilmentCustomer|null $fulfilmentCustomer
 * @property-read Group $group
 * @property-read Media|null $image
 * @property-read MediaCollection<int, Media> $images
 * @property-read Collection<int, Invoice> $invoices
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, MitSavedCard> $mitSavedCard
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read Order|null $orderInBasket
 * @property-read Collection<int, Order> $orders
 * @property-read Organisation $organisation
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, \App\Models\CRM\PollReply> $pollReplies
 * @property-read Collection<int, Portfolio> $portfolios
 * @property-read Collection<int, Asset> $products
 * @property-read Collection<int, \App\Models\CRM\Prospect> $prospects
 * @property-read Media|null $seoImage
 * @property-read Shop|null $shop
 * @property-read ShopifyUser|null $shopifyUser
 * @property-read \App\Models\CRM\CustomerStats|null $stats
 * @property-read Collection<int, Stock> $stocks
 * @property-read Collection<int, StoredItem> $storedItems
 * @property-read Collection<int, SubscriptionEvent> $subscriptionEvents
 * @property-read TaxNumber|null $taxNumber
 * @property-read TiktokUser|null $tiktokUser
 * @property-read Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read Collection<int, TopUpPaymentApiPoint> $topUpPaymentApiPoint
 * @property-read Collection<int, TopUp> $topUps
 * @property-read Collection<int, Transaction> $transactions
 * @property-read UniversalSearch|null $universalSearch
 * @property-read Collection<int, \App\Models\CRM\WebUser> $webUsers
 * @property-read WooCommerceUser|null $wooCommerceUser
 * @method static \Database\Factories\CRM\CustomerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Customer newModelQuery()
 * @method static Builder<static>|Customer newQuery()
 * @method static Builder<static>|Customer onlyTrashed()
 * @method static Builder<static>|Customer query()
 * @method static Builder<static>|Customer withTrashed()
 * @method static Builder<static>|Customer withoutTrashed()
 * @mixin \Eloquent
 */
class Customer extends Model implements HasMedia, Auditable
{
    use SoftDeletes;
    use HasAddress;
    use HasAddresses;
    use HasSlug;
    use HasUniversalSearch;
    use HasImage;
    use HasFactory;
    use HasHistory;
    use InShop;
    use HasAttachments;
    use HasEmail;
    use HasApiTokens;
    use Notifiable;

    protected $casts = [
        'data'                        => 'array',
        'settings'                    => 'array',
        'location'                    => 'array',
        'migration_data'              => 'array',
        'state'                       => CustomerStateEnum::class,
        'status'                      => CustomerStatusEnum::class,
        'trade_state'                 => CustomerTradeStateEnum::class,
        'rejected_reason'             => CustomerRejectReasonEnum::class,
        'last_submitted_order_at'     => 'datetime',
        'last_dispatched_delivery_at' => 'datetime',
        'last_invoiced_at'            => 'datetime',
        'fetched_at'                  => 'datetime',
        'rejected_at'                 => 'datetime',
        'last_fetched_at'             => 'datetime',
        'amount_in_basket'            => 'decimal:2'
    ];


    protected $attributes = [
        'data'           => '{}',
        'settings'       => '{}',
        'location'       => '{}',
        'migration_data' => '{}'
    ];

    protected $guarded = [];

    public function generateTags(): array
    {
        $tags = ['crm'];
        if ($this->is_fulfilment) {
            $tags[] = 'fulfilment';
        }

        return $tags;
    }

    protected array $auditInclude = [
        'reference',
        'contact_name',
        'company_name',
        'email',
        'phone',
        'contact_website',
        'identity_document_type',
        'identity_document_number',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function () {
                return $this->reference.'-'.$this->shop->slug;
            })
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(128)
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(
            function (Customer $customer) {
                $name = $customer->company_name == '' ? $customer->contact_name : $customer->company_name;
                $name = trim($name);
                if ($name == '') {
                    $emailData = explode('@', $customer->email);
                    $name      = $emailData[0] ?? $customer->email;
                }
                $customer->name = $name;
            }
        );

        static::updated(function (Customer $customer) {
            if ($customer->wasChanged('trade_state')) {
                ShopHydrateCustomerInvoices::dispatch($customer->shop);
            }
            if ($customer->wasChanged(['contact_name', 'company_name', 'email'])) {
                $name = $customer->company_name == '' ? $customer->contact_name : $customer->company_name;
                $name = trim($name);
                if ($name == '') {
                    $emailData = explode('@', $customer->email);
                    $name      = $emailData[0] ?? $customer->email;
                }

                $customer->updateQuietly(
                    [
                        'name' => $name
                    ]
                );
            }
        });
    }

    public function clients(): HasMany
    {
        return $this->hasMany(CustomerClient::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(CustomerStats::class);
    }

    public function comms(): HasOne
    {
        return $this->hasOne(CustomerComms::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function webUsers(): HasMany
    {
        return $this->hasMany(WebUser::class);
    }


    public function products(): MorphMany
    {
        return $this->morphMany(Asset::class, 'model', 'model_type', 'model_id', 'id');
    }

    public function stocks(): MorphMany
    {
        return $this->morphMany(Stock::class, 'owner', 'owner_type', 'owner_id', 'id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function storedItems(): HasMany
    {
        return $this->hasMany(StoredItem::class, 'fulfilment_customer_id');
    }

    public function taxNumber(): MorphOne
    {
        return $this->morphOne(TaxNumber::class, 'owner');
    }

    public function fulfilmentCustomer(): HasOne
    {
        return $this->hasOne(FulfilmentCustomer::class);
    }


    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function hasUsers(): bool
    {
        return (bool)$this->webUsers->count();
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'delivery_address_id');
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function customerSalesChannelsXXX(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'customer_sales_channels')
            ->withPivot('id', 'platform_id', 'group_id', 'organisation_id', 'shop_id', 'reference')->withTimestamps();
    }

    public function customerSalesChannels(): HasMany
    {
        return $this->hasMany(CustomerSalesChannel::class);
    }



    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function topUps(): HasMany
    {
        return $this->hasMany(TopUp::class);
    }

    public function shopifyUser(): HasOne
    {
        return $this->hasOne(ShopifyUser::class);
    }

    public function wooCommerceUser(): HasOne
    {
        return $this->hasOne(WooCommerceUser::class);
    }

    public function ebayUser(): HasOne
    {
        return $this->hasOne(EbayUser::class);
    }

    public function tiktokUser(): HasOne
    {
        return $this->hasOne(TiktokUser::class);
    }

    public function amazonUsers(): HasMany
    {
        return $this->hasMany(AmazonUser::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function customerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(Favourite::class);
    }

    public function backInStockReminder(): HasMany
    {
        return $this->hasMany(BackInStockReminder::class);
    }

    public function pollReplies(): HasMany
    {
        return $this->hasMany(PollReply::class);
    }

    public function subscriptionEvents(): MorphMany
    {
        return $this->morphMany(SubscriptionEvent::class, 'model');
    }

    public function orderInBasket(): HasOne
    {
        return $this->hasOne(Order::class, 'id', 'current_order_in_basket_id');
    }

    public function topUpPaymentApiPoint(): HasMany
    {
        return $this->hasMany(TopUpPaymentApiPoint::class);
    }

    public function mitSavedCard(): HasMany
    {
        return $this->hasMany(MitSavedCard::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'customer_id');
    }

    public function exclusiveProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'exclusive_for_customer_id');
    }
}
