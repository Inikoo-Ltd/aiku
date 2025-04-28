<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:29:30 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Models\Catalogue;

use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\OrgPaymentServiceProviderShop;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Accounting\TopUp;
use App\Models\Analytics\AikuSection;
use App\Models\Billables\Charge;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\Comms\SenderEmail;
use App\Models\CRM\Appointment;
use App\Models\CRM\Contacter;
use App\Models\CRM\Customer;
use App\Models\CRM\Poll;
use App\Models\CRM\Prospect;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Discounts\OfferComponent;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\InvoiceTransactionHasFeedback;
use App\Models\Helpers\Language;
use App\Models\Helpers\Query;
use App\Models\Helpers\SerialReference;
use App\Models\Helpers\TaxNumber;
use App\Models\Helpers\Timezone;
use App\Models\Helpers\UniversalSearch;
use App\Models\Helpers\Upload;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\Role;
use App\Models\SysAdmin\Task;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Traits\HasUniversalSearch;
use App\Models\Traits\InOrganisation;
use App\Models\Web\Redirect;
use App\Models\Web\Website;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\Catalogue\Shop
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $master_shop_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $identity_document_type
 * @property string|null $identity_document_number
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property int|null $collection_address_id
 * @property ShopStateEnum $state
 * @property ShopTypeEnum $type
 * @property string|null $open_at
 * @property string|null $closed_at
 * @property int $country_id
 * @property int $language_id
 * @property int $currency_id
 * @property int $timezone_id
 * @property int|null $image_id
 * @property int|null $shipping_zone_schema_id
 * @property int|null $discount_shipping_zone_schema_id
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int|null $sender_email_id
 * @property int|null $prospects_sender_email_id
 * @property \Illuminate\Support\Carbon|null $fetched_at
 * @property \Illuminate\Support\Carbon|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 * @property string|null $source_id
 * @property string|null $invoice_footer
 * @property-read \App\Models\Catalogue\ShopAccountingStats|null $accountingStats
 * @property-read Address|null $address
 * @property-read LaravelCollection<int, Address> $addresses
 * @property-read LaravelCollection<int, Adjustment> $adjustments
 * @property-read LaravelCollection<int, AikuSection> $aikuScopedSections
 * @property-read LaravelCollection<int, Appointment> $appointments
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Asset> $assets
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, Charge> $charges
 * @property-read LaravelCollection<int, CustomerClient> $clients
 * @property-read Address|null $collectionAddress
 * @property-read LaravelCollection<int, \App\Models\Catalogue\CollectionCategory> $collectionCategories
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Collection> $collections
 * @property-read \App\Models\Catalogue\ShopCommsStats|null $commsStats
 * @property-read Country $country
 * @property-read LaravelCollection<int, CreditTransaction> $creditTransactions
 * @property-read \App\Models\Catalogue\ShopCRMStats|null $crmStats
 * @property-read Currency $currency
 * @property-read ShippingZoneSchema|null $currentShippingZoneSchema
 * @property-read LaravelCollection<int, Customer> $customers
 * @property-read LaravelCollection<int, DeliveryNote> $deliveryNotes
 * @property-read ShippingZoneSchema|null $discountShippingZoneSchema
 * @property-read \App\Models\Catalogue\ShopDiscountsStats|null $discountsStats
 * @property-read \App\Models\Catalogue\ShopDropshippingStat|null $dropshippingStats
 * @property-read LaravelCollection<int, InvoiceTransactionHasFeedback> $feedbackBridges
 * @property-read Fulfilment|null $fulfilment
 * @property-read Group $group
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read LaravelCollection<int, Invoice> $invoices
 * @property-read Language $language
 * @property-read LaravelCollection<int, Mailshot> $mailshots
 * @property-read \App\Models\Catalogue\ShopMailshotsIntervals|null $mailshotsIntervals
 * @property-read MasterShop|null $masterShop
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read LaravelCollection<int, OfferCampaign> $offerCampaigns
 * @property-read LaravelCollection<int, OfferComponent> $offerComponents
 * @property-read LaravelCollection<int, Offer> $offers
 * @property-read \App\Models\Catalogue\ShopOrderHandlingStats|null $orderHandlingStats
 * @property-read \App\Models\Catalogue\ShopOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\Catalogue\ShopOrderingStats|null $orderingStats
 * @property-read LaravelCollection<int, Order> $orders
 * @property-read OrgPaymentServiceProviderShop|null $pivot
 * @property-read LaravelCollection<int, OrgPaymentServiceProvider> $orgPaymentServiceProviders
 * @property-read Organisation $organisation
 * @property-read \App\Models\Catalogue\ShopOutboxColdEmailsIntervals|null $outboxColdEmailsIntervals
 * @property-read \App\Models\Catalogue\ShopOutboxCustomerNotificationIntervals|null $outboxCustomerNotificationIntervals
 * @property-read \App\Models\Catalogue\ShopOutboxMarketingIntervals|null $outboxMarketingIntervals
 * @property-read \App\Models\Catalogue\ShopOutboxMarketingNotificationIntervals|null $outboxMarketingNotificationIntervals
 * @property-read \App\Models\Catalogue\ShopOutboxNewsletterIntervals|null $outboxNewsletterIntervals
 * @property-read \App\Models\Catalogue\ShopOutboxPushIntervals|null $outboxPushIntervals
 * @property-read LaravelCollection<int, Outbox> $outboxes
 * @property-read LaravelCollection<int, Packing> $packings
 * @property-read LaravelCollection<int, PaymentAccountShop> $paymentAccountShops
 * @property-read LaravelCollection<int, Payment> $payments
 * @property-read LaravelCollection<int, Picking> $pickings
 * @property-read LaravelCollection<int, Poll> $polls
 * @property-read LaravelCollection<int, Portfolio> $portfolios
 * @property-read LaravelCollection<int, \App\Models\Catalogue\ProductCategory> $productCategories
 * @property-read LaravelCollection<int, \App\Models\Catalogue\Product> $products
 * @property-read LaravelCollection<int, Prospect> $prospects
 * @property-read LaravelCollection<int, Purge> $purges
 * @property-read LaravelCollection<int, Query> $queries
 * @property-read LaravelCollection<int, Redirect> $redirects
 * @property-read LaravelCollection<int, Rental> $rentals
 * @property-read LaravelCollection<int, Role> $roles
 * @property-read \App\Models\Catalogue\ShopSalesIntervals|null $salesIntervals
 * @property-read SenderEmail|null $senderEmail
 * @property-read LaravelCollection<int, SerialReference> $serialReferences
 * @property-read LaravelCollection<int, Service> $services
 * @property-read LaravelCollection<int, ShippingZoneSchema> $shippingZoneSchemas
 * @property-read LaravelCollection<int, ShippingZone> $shippingZones
 * @property-read \App\Models\Catalogue\ShopStats|null $stats
 * @property-read LaravelCollection<int, Task> $tasks
 * @property-read TaxNumber|null $taxNumber
 * @property-read LaravelCollection<int, \App\Models\Catalogue\ShopTimeSeries> $timeSeries
 * @property-read Timezone $timezone
 * @property-read LaravelCollection<int, TopUp> $topUps
 * @property-read LaravelCollection<int, Transaction> $transactions
 * @property-read UniversalSearch|null $universalSearch
 * @property-read LaravelCollection<int, Upload> $uploads
 * @property-read Website|null $website
 * @method static \Database\Factories\Catalogue\ShopFactory factory($count = null, $state = [])
 * @method static Builder<static>|Shop newModelQuery()
 * @method static Builder<static>|Shop newQuery()
 * @method static Builder<static>|Shop onlyTrashed()
 * @method static Builder<static>|Shop query()
 * @method static Builder<static>|Shop withTrashed()
 * @method static Builder<static>|Shop withoutTrashed()
 * @mixin Eloquent
 */
class Shop extends Model implements HasMedia, Auditable
{
    use HasAddress;
    use HasAddresses;
    use SoftDeletes;
    use HasSlug;
    use HasUniversalSearch;
    use HasFactory;
    use InOrganisation;
    use HasHistory;
    use HasImage;

    protected $casts = [
        'data'            => 'array',
        'settings'        => 'array',
        'location'        => 'array',
        'type'            => ShopTypeEnum::class,
        'state'           => ShopStateEnum::class,
        'fetched_at'      => 'datetime',
        'last_fetched_at' => 'datetime',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'location' => '{}',
    ];

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'catalogue'
        ];
    }

    protected array $auditInclude = [
        'code',
        'name',
        'email',
        'phone',
        'state',
        'country_id',
        'currency_id',
        'language_id',
        'timezone_id',
        'company_name',
        'contact_name',
        'identity_document_number'
    ];


    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(664);
    }

    public function crmStats(): HasOne
    {
        return $this->hasOne(ShopCRMStats::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(ShopOrderingStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(ShopSalesIntervals::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(ShopOrderingIntervals::class);
    }

    public function orderHandlingStats(): HasOne
    {
        return $this->hasOne(ShopOrderHandlingStats::class);
    }

    public function mailshotsIntervals(): HasOne
    {
        return $this->hasOne(ShopMailshotsIntervals::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(ShopStats::class);
    }

    public function accountingStats(): HasOne
    {
        return $this->hasOne(ShopAccountingStats::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function website(): HasOne
    {
        return $this->hasOne(Website::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function departments(): LaravelCollection
    {
        return $this->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->get();
    }

    public function subDepartments(): LaravelCollection
    {
        return $this->productCategories()->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)->get();
    }

    public function getFamilies(): LaravelCollection
    {
        return $this->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function masterShop(): BelongsTo
    {
        return $this->belongsTo(MasterShop::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    public function orgPaymentServiceProviders(): BelongsToMany
    {
        return $this->belongsToMany(OrgPaymentServiceProvider::class)->using(OrgPaymentServiceProviderShop::class)
            ->withTimestamps();
    }

    public function paymentAccountShops(): HasMany
    {
        return $this->hasMany(PaymentAccountShop::class);
    }

    public function getPaymentAccountTypeAccount(): ?PaymentAccount
    {
        $paymentAccountShop = $this->paymentAccountShops()->where('type', PaymentAccountTypeEnum::ACCOUNT)->first();
        return $paymentAccountShop ? $paymentAccountShop->paymentAccount : null;
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function offerCampaigns(): HasMany
    {
        return $this->hasMany(OfferCampaign::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function offerComponents(): HasMany
    {
        return $this->hasMany(OfferComponent::class);
    }

    public function taxNumber(): MorphOne
    {
        return $this->morphOne(TaxNumber::class, 'owner');
    }

    public function commsStats(): HasOne
    {
        return $this->hasOne(ShopCommsStats::class);
    }

    public function discountsStats(): HasOne
    {
        return $this->hasOne(ShopDiscountsStats::class);
    }

    public function shippingZoneSchemas(): HasMany
    {
        return $this->hasMany(ShippingZoneSchema::class);
    }

    public function currentShippingZoneSchema(): BelongsTo
    {
        return $this->belongsTo(ShippingZoneSchema::class, 'shipping_zone_schema_id');
    }

    public function discountShippingZoneSchema(): BelongsTo
    {
        return $this->belongsTo(ShippingZoneSchema::class, 'discount_shipping_zone_schema_id');
    }

    public function shippingZones(): HasMany
    {
        return $this->hasMany(ShippingZone::class);
    }

    public function serialReferences(): MorphMany
    {
        return $this->morphMany(SerialReference::class, 'container');
    }

    public function roles(): MorphMany
    {
        return $this->morphMany(Role::class, 'scope');
    }

    public function fulfilment(): HasOne
    {
        return $this->hasOne(Fulfilment::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function senderEmail(): BelongsTo
    {
        return $this->belongsTo(SenderEmail::class);
    }

    public function collectionCategories(): HasMany
    {
        return $this->hasMany(CollectionCategory::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function collectionAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'collection_address_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function tasks()
    {
        return $this->morphMany(Task::class, 'assigner');
    }

    public function dropshippingStats(): HasOne
    {
        return $this->hasOne(ShopDropshippingStat::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(CustomerClient::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    public function mailshots(): HasMany
    {
        return $this->hasMany(Mailshot::class);
    }

    public function topUps(): HasMany
    {
        return $this->hasMany(TopUp::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(Redirect::class);
    }

    public function purges(): HasMany
    {
        return $this->hasMany(Purge::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }

    public function feedbackBridges(): HasMany
    {
        return $this->hasMany(InvoiceTransactionHasFeedback::class);
    }

    public function aikuScopedSections(): MorphToMany
    {
        return $this->morphToMany(AikuSection::class, 'model', 'aiku_scoped_sections');
    }

    public function pickings(): HasMany
    {
        return $this->hasMany(Picking::class);
    }

    public function packings(): HasMany
    {
        return $this->hasMany(Packing::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(ShopTimeSeries::class);
    }

    public function outboxNewsletterIntervals(): HasOne
    {
        return $this->hasOne(ShopOutboxNewsletterIntervals::class);
    }


    public function outboxMarketingIntervals(): HasOne
    {
        return $this->hasOne(ShopOutboxMarketingIntervals::class);
    }

    public function outboxMarketingNotificationIntervals(): HasOne
    {
        return $this->hasOne(ShopOutboxMarketingNotificationIntervals::class);
    }

    public function outboxCustomerNotificationIntervals(): HasOne
    {
        return $this->hasOne(ShopOutboxCustomerNotificationIntervals::class);
    }

    public function outboxColdEmailsIntervals(): HasOne
    {
        return $this->hasOne(ShopOutboxColdEmailsIntervals::class);
    }

    public function outboxPushIntervals(): HasOne
    {
        return $this->hasOne(ShopOutboxPushIntervals::class);
    }

    public function contacters(): HasMany
    {
        return $this->hasMany(Contacter::class);
    }

}
