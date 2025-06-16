<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 17:25:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Accounting\TopUp;
use App\Models\Analytics\AikuSection;
use App\Models\Billables\Charge;
use App\Models\Billables\Rental;
use App\Models\Billables\Service;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\Subscription;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Mailshot;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Dispatching\Shipper;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\Space;
use App\Models\Helpers\Address;
use App\Models\Helpers\Country;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Feedback;
use App\Models\Helpers\Language;
use App\Models\Helpers\Media;
use App\Models\Helpers\Query;
use App\Models\Helpers\SerialReference;
use App\Models\Helpers\Timezone;
use App\Models\Helpers\Upload;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\HumanResources\Workplace;
use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\Ordering\Adjustment;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\Traits\HasAddress;
use App\Models\Traits\HasAddresses;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * App\Models\SysAdmin\Organisation
 *
 * @property int $id
 * @property int $group_id
 * @property string $ulid
 * @property OrganisationTypeEnum $type
 * @property string $slug
 * @property string $code
 * @property string $name company name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $status
 * @property int|null $address_id
 * @property array<array-key, mixed> $location
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed> $source
 * @property int $country_id
 * @property int $language_id
 * @property int $timezone_id
 * @property int $currency_id customer accounting currency
 * @property int|null $image_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property string|null $deleted_at
 * @property string|null $source_id
 * @property string|null $colour
 * @property-read \App\Models\SysAdmin\OrganisationAccountingStats|null $accountingStats
 * @property-read Address|null $address
 * @property-read LaravelCollection<int, Address> $addresses
 * @property-read LaravelCollection<int, Adjustment> $adjustments
 * @property-read Agent|null $agent
 * @property-read LaravelCollection<int, AikuSection> $aikuScopedSections
 * @property-read LaravelCollection<int, Artefact> $artefacts
 * @property-read LaravelCollection<int, Asset> $assets
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\SysAdmin\OrganisationCatalogueStats|null $catalogueStats
 * @property-read LaravelCollection<int, Charge> $charges
 * @property-read LaravelCollection<int, CustomerClient> $clients
 * @property-read LaravelCollection<int, ClockingMachine> $clockingMachines
 * @property-read LaravelCollection<int, Collection> $collections
 * @property-read \App\Models\SysAdmin\OrganisationCommsStats|null $commsStats
 * @property-read Country $country
 * @property-read LaravelCollection<int, CreditTransaction> $creditTransactions
 * @property-read \App\Models\SysAdmin\OrganisationCRMStats|null $crmStats
 * @property-read Currency $currency
 * @property-read LaravelCollection<int, Customer> $customers
 * @property-read LaravelCollection<int, DeliveryNote> $deliveryNotes
 * @property-read \App\Models\SysAdmin\OrganisationDiscountsStats|null $discountsStats
 * @property-read LaravelCollection<int, DispatchedEmail> $dispatchedEmails
 * @property-read \App\Models\SysAdmin\OrganisationDropshippingStat|null $dropshippingStats
 * @property-read LaravelCollection<int, Employee> $employees
 * @property-read LaravelCollection<int, Favourite> $favourites
 * @property-read LaravelCollection<int, Feedback> $feedbacks
 * @property-read LaravelCollection<int, FulfilmentCustomer> $fulfilmentCustomers
 * @property-read \App\Models\SysAdmin\OrganisationFulfilmentStats|null $fulfilmentStats
 * @property-read LaravelCollection<int, Fulfilment> $fulfilments
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\OrganisationHumanResourcesStats|null $humanResourcesStats
 * @property-read Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $images
 * @property-read \App\Models\SysAdmin\OrganisationInventoryStats|null $inventoryStats
 * @property-read LaravelCollection<int, InvoiceCategory> $invoiceCategories
 * @property-read LaravelCollection<int, Invoice> $invoices
 * @property-read LaravelCollection<int, JobPosition> $jobPositions
 * @property-read Language $language
 * @property-read LaravelCollection<int, Location> $locations
 * @property-read Media|null $logo
 * @property-read LaravelCollection<int, Mailshot> $mailshots
 * @property-read \App\Models\SysAdmin\OrganisationMailshotsIntervals|null $mailshotsIntervals
 * @property-read \App\Models\SysAdmin\OrganisationManufactureStats|null $manufactureStats
 * @property-read LaravelCollection<int, ManufactureTask> $manufactureTasks
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read LaravelCollection<int, OfferCampaign> $offerCampaigns
 * @property-read LaravelCollection<int, Offer> $offers
 * @property-read \App\Models\SysAdmin\OrganisationOrderHandlingStats|null $orderHandlingStats
 * @property-read \App\Models\SysAdmin\OrganisationOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOrderingStats|null $orderingStats
 * @property-read LaravelCollection<int, Order> $orders
 * @property-read LaravelCollection<int, OrgAgent> $orgAgents
 * @property-read LaravelCollection<int, OrgPartner> $orgPartners
 * @property-read LaravelCollection<int, OrgPaymentServiceProvider> $orgPaymentServiceProviders
 * @property-read LaravelCollection<int, OrgPostRoom> $orgPostRooms
 * @property-read LaravelCollection<int, OrgStockFamily> $orgStockFamilies
 * @property-read LaravelCollection<int, OrgStock> $orgStocks
 * @property-read LaravelCollection<int, OrgSupplierProduct> $orgSupplierProducts
 * @property-read LaravelCollection<int, OrgSupplier> $orgSuppliers
 * @property-read \App\Models\SysAdmin\OrganisationOutboxColdEmailsIntervals|null $outboxColdEmailsIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxCustomerNotificationIntervals|null $outboxCustomerNotificationIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxMarketingIntervals|null $outboxMarketingIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxMarketingNotificationIntervals|null $outboxMarketingNotificationIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxNewsletterIntervals|null $outboxNewsletterIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxPushIntervals|null $outboxPushIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxTestIntervals|null $outboxTestIntervals
 * @property-read \App\Models\SysAdmin\OrganisationOutboxUserNotificationIntervals|null $outboxUserNotificationIntervals
 * @property-read LaravelCollection<int, Outbox> $outboxes
 * @property-read LaravelCollection<int, Packing> $packings
 * @property-read LaravelCollection<int, PaymentAccount> $paymentAccounts
 * @property-read LaravelCollection<int, PaymentServiceProvider> $paymentServiceProviders
 * @property-read LaravelCollection<int, Payment> $payments
 * @property-read LaravelCollection<int, Picking> $pickings
 * @property-read LaravelCollection<int, Portfolio> $portfolios
 * @property-read \App\Models\SysAdmin\OrganisationProcurementStats|null $procurementStats
 * @property-read LaravelCollection<int, ProductCategory> $productCategories
 * @property-read LaravelCollection<int, Production> $productions
 * @property-read LaravelCollection<int, Product> $products
 * @property-read LaravelCollection<int, Prospect> $prospects
 * @property-read LaravelCollection<int, PurchaseOrder> $purchaseOrders
 * @property-read LaravelCollection<int, Purge> $purges
 * @property-read LaravelCollection<int, Query> $queries
 * @property-read LaravelCollection<int, RawMaterial> $rawMaterials
 * @property-read LaravelCollection<int, RecurringBill> $recurringBills
 * @property-read LaravelCollection<int, Redirect> $redirects
 * @property-read LaravelCollection<int, Rental> $rentals
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\Role> $roles
 * @property-read \App\Models\SysAdmin\OrganisationSalesIntervals|null $salesIntervals
 * @property-read Media|null $seoImage
 * @property-read LaravelCollection<int, SerialReference> $serialReferences
 * @property-read LaravelCollection<int, Service> $services
 * @property-read LaravelCollection<int, Shipper> $shippers
 * @property-read LaravelCollection<int, ShippingZoneSchema> $shippingZoneSchemas
 * @property-read LaravelCollection<int, ShippingZone> $shippingZones
 * @property-read LaravelCollection<int, Shop> $shops
 * @property-read LaravelCollection<int, Space> $spaces
 * @property-read \App\Models\SysAdmin\OrganisationStats|null $stats
 * @property-read LaravelCollection<int, Subscription> $subscriptions
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\Task> $tasks
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\OrganisationTimeSeries> $timeSeries
 * @property-read Timezone $timezone
 * @property-read LaravelCollection<int, TopUp> $topUps
 * @property-read LaravelCollection<int, Upload> $uploads
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\UserHasAuthorisedModels> $userAuthorisedModels
 * @property-read LaravelCollection<int, WarehouseArea> $warehouseAreas
 * @property-read LaravelCollection<int, Warehouse> $warehouses
 * @property-read \App\Models\SysAdmin\OrganisationWebStats|null $webStats
 * @property-read LaravelCollection<int, WebUser> $webUsers
 * @property-read LaravelCollection<int, Webpage> $webpages
 * @property-read LaravelCollection<int, Website> $websites
 * @property-read LaravelCollection<int, Workplace> $workplaces
 * @method static \Database\Factories\SysAdmin\OrganisationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organisation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organisation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organisation query()
 * @mixin \Eloquent
 */
class Organisation extends Model implements HasMedia, Auditable
{
    use HasFactory;
    use HasSlug;
    use InteractsWithMedia;
    use HasImage;
    use HasAddress;
    use HasAddresses;
    use HasHistory;

    protected $casts = [
        'data'     => 'array',
        'settings' => 'array',
        'source'   => 'array',
        'location' => 'array',
        'type'     => OrganisationTypeEnum::class
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
        'source'   => '{}',
        'location' => '{}'
    ];

    protected $guarded = [];

    protected array $auditInclude = [
        'code',
        'name',
        'email',
        'phone',
        'status',
        'country_id',
        'currency_id',
        'language_id',
        'timezone_id',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('code')
            ->doNotGenerateSlugsOnUpdate()
            ->saveSlugsTo('slug');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function generateTags(): array
    {
        return [
            'sysadmin',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }

    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(OrganisationStats::class);
    }

    public function humanResourcesStats(): HasOne
    {
        return $this->hasOne(OrganisationHumanResourcesStats::class);
    }

    public function procurementStats(): HasOne
    {
        return $this->hasOne(OrganisationProcurementStats::class);
    }

    public function inventoryStats(): HasOne
    {
        return $this->hasOne(OrganisationInventoryStats::class);
    }

    public function fulfilmentStats(): HasOne
    {
        return $this->hasOne(OrganisationFulfilmentStats::class);
    }

    public function catalogueStats(): HasOne
    {
        return $this->hasOne(OrganisationCatalogueStats::class);
    }

    public function commsStats(): HasOne
    {
        return $this->hasOne(OrganisationCommsStats::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(OrganisationOrderingStats::class);
    }

    public function manufactureStats(): HasOne
    {
        return $this->hasOne(OrganisationManufactureStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(OrganisationSalesIntervals::class);
    }

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOrderingIntervals::class);
    }

    public function orderHandlingStats(): HasOne
    {
        return $this->hasOne(OrganisationOrderHandlingStats::class);
    }

    public function mailshotsIntervals(): HasOne
    {
        return $this->hasOne(OrganisationMailshotsIntervals::class);
    }

    public function crmStats(): HasOne
    {
        return $this->hasOne(OrganisationCRMStats::class);
    }

    public function webStats(): HasOne
    {
        return $this->hasOne(OrganisationWebStats::class);
    }

    public function accountingStats(): HasOne
    {
        return $this->hasOne(OrganisationAccountingStats::class);
    }

    public function discountsStats(): HasOne
    {
        return $this->hasOne(OrganisationDiscountsStats::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function timezone(): BelongsTo
    {
        return $this->belongsTo(Timezone::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function getAccountsServiceProvider(): OrgPaymentServiceProvider
    {
        return OrgPaymentServiceProvider::where('organisation_id', $this->id)->where('type', PaymentServiceProviderTypeEnum::ACCOUNT)->first();
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function logo(): HasOne
    {
        return $this->hasOne(Media::class, 'id', 'logo_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class);
    }

    public function manufactureTasks(): HasMany
    {
        return $this->hasMany(ManufactureTask::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function warehouseAreas(): HasMany
    {
        return $this->hasMany(WarehouseArea::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function shippers(): HasMany
    {
        return $this->hasMany(Shipper::class);
    }

    public function clockingMachines(): HasMany
    {
        return $this->hasMany(ClockingMachine::class);
    }

    public function orgPaymentServiceProviders(): HasMany
    {
        return $this->hasMany(OrgPaymentServiceProvider::class);
    }

    public function paymentServiceProviders(): HasMany
    {
        return $this->hasMany(PaymentServiceProvider::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
    }

    public function roles(): MorphMany
    {
        return $this->morphMany(Role::class, 'scope');
    }

    public function userAuthorisedModels(): HasMany
    {
        return $this->hasMany(UserHasAuthorisedModels::class, 'org_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function fulfilmentCustomers(): HasMany
    {
        return $this->hasMany(FulfilmentCustomer::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(Webpage::class);
    }


    public function orgPartners(): HasMany
    {
        return $this->hasMany(OrgPartner::class);
    }

    public function orgAgents(): HasMany
    {
        return $this->hasMany(OrgAgent::class);
    }

    public function orgSuppliers(): HasMany
    {
        return $this->hasMany(OrgSupplier::class);
    }

    public function orgSupplierProducts(): HasMany
    {
        return $this->hasMany(OrgSupplierProduct::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function orgStocks(): HasMany
    {
        return $this->hasMany(OrgStock::class);
    }

    public function orgStockFamilies(): HasMany
    {
        return $this->hasMany(OrgStockFamily::class);
    }

    public function fulfilments(): HasMany
    {
        return $this->hasMany(Fulfilment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceCategories(): HasMany
    {
        return $this->hasMany(InvoiceCategory::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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

    public function getFamilies(): ?LaravelCollection
    {
        return $this->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->get();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function recurringBills(): HasMany
    {
        return $this->hasMany(RecurringBill::class);
    }

    public function artefacts(): HasMany
    {
        return $this->hasMany(Artefact::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'assigner');
    }

    public function dropshippingStats(): HasOne
    {
        return $this->hasOne(OrganisationDropshippingStat::class);
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(CustomerClient::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function topUps(): HasMany
    {
        return $this->hasMany(TopUp::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function shippingZoneSchemas(): HasMany
    {
        return $this->hasMany(ShippingZoneSchema::class);
    }

    public function shippingZones(): HasMany
    {
        return $this->hasMany(ShippingZone::class);
    }

    public function offerCampaigns(): HasMany
    {
        return $this->hasMany(OfferCampaign::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
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

    public function serialReferences(): MorphMany
    {
        return $this->morphMany(SerialReference::class, 'container');
    }

    public function feedbacks(): MorphMany
    {
        return $this->morphMany(Feedback::class, 'origin');
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }

    public function aikuScopedSections(): MorphToMany
    {
        return $this->morphToMany(AikuSection::class, 'model', 'aiku_scoped_sections');
    }

    public function orgPostRooms(): HasMany
    {
        return $this->hasMany(OrgPostRoom::class);
    }

    public function webUsers(): HasMany
    {
        return $this->hasMany(WebUser::class);
    }

    public function pickings(): HasMany
    {
        return $this->hasMany(Picking::class);
    }

    public function packings(): HasMany
    {
        return $this->hasMany(Packing::class);
    }

    public function mailshots(): HasMany
    {
        return $this->hasMany(Mailshot::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(OrganisationTimeSeries::class);
    }

    public function outboxNewsletterIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxNewsletterIntervals::class);
    }


    public function outboxMarketingIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxMarketingIntervals::class);
    }

    public function outboxMarketingNotificationIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxMarketingNotificationIntervals::class);
    }

    public function outboxCustomerNotificationIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxCustomerNotificationIntervals::class);
    }

    public function outboxColdEmailsIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxColdEmailsIntervals::class);
    }

    public function outboxUserNotificationIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxUserNotificationIntervals::class);
    }

    public function outboxPushIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxPushIntervals::class);
    }

    public function outboxTestIntervals(): HasOne
    {
        return $this->hasOne(OrganisationOutboxTestIntervals::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function favourites(): HasMany
    {
        return $this->hasMany(Favourite::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

    public function dispatchedEmails(): HasMany
    {
        return $this->hasMany(DispatchedEmail::class);
    }


}
