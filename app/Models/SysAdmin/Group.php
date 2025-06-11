<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 17:25:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Accounting\CreditTransaction;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Accounting\TopUp;
use App\Models\Analytics\AikuSection;
use App\Models\Analytics\UserRequest;
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
use App\Models\Comms\Email;
use App\Models\Comms\EmailAddress;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\EmailTemplate;
use App\Models\Comms\Mailshot;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\CRM\WebUser;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Picking;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RecurringBill;
use App\Models\Fulfilment\Space;
use App\Models\Goods\Ingredient;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Query;
use App\Models\Helpers\Upload;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\JobPosition;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseArea;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\Ordering\SalesChannel;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Production\Artefact;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasImage;
use App\Models\Web\Banner;
use App\Models\Web\ExternalLink;
use App\Models\Web\Redirect;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as LaravelCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 *
 *
 * @property int $id
 * @property string $ulid
 * @property string $slug
 * @property string|null $subdomain
 * @property string $code
 * @property string $name
 * @property int $country_id
 * @property int $language_id
 * @property int $timezone_id
 * @property int $currency_id customer accounting currency
 * @property int|null $image_id
 * @property array<array-key, mixed> $limits
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int $number_organisations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\SysAdmin\GroupAccountingStats|null $accountingStats
 * @property-read LaravelCollection<int, Adjustment> $adjustments
 * @property-read LaravelCollection<int, Agent> $agents
 * @property-read LaravelCollection<int, AikuSection> $aikuScopedSections
 * @property-read LaravelCollection<int, AikuSection> $aikuSections
 * @property-read LaravelCollection<int, Artefact> $artefacts
 * @property-read LaravelCollection<int, Asset> $assets
 * @property-read LaravelCollection<int, \App\Models\Helpers\Audit> $audits
 * @property-read LaravelCollection<int, Banner> $banners
 * @property-read LaravelCollection<int, Barcode> $barcodes
 * @property-read \App\Models\SysAdmin\GroupCatalogueStats|null $catalogueStats
 * @property-read LaravelCollection<int, Charge> $charges
 * @property-read LaravelCollection<int, CustomerClient> $clients
 * @property-read LaravelCollection<int, ClockingMachine> $clockingMachines
 * @property-read LaravelCollection<int, Collection> $collections
 * @property-read \App\Models\SysAdmin\GroupCommsStats|null $commsStats
 * @property-read LaravelCollection<int, CreditTransaction> $creditTransactions
 * @property-read \App\Models\SysAdmin\GroupCRMStats|null $crmStats
 * @property-read Currency $currency
 * @property-read LaravelCollection<int, Customer> $customers
 * @property-read LaravelCollection<int, DeliveryNote> $deliveryNotes
 * @property-read \App\Models\SysAdmin\GroupDiscountsStats|null $discountsStats
 * @property-read LaravelCollection<int, DispatchedEmail> $dispatchedEmails
 * @property-read \App\Models\SysAdmin\GroupDropshippingStat|null $dropshippingStats
 * @property-read LaravelCollection<int, EmailAddress> $emailAddresses
 * @property-read LaravelCollection<int, EmailBulkRun> $emailBulkRuns
 * @property-read LaravelCollection<int, EmailTemplate> $emailTemplates
 * @property-read LaravelCollection<int, Email> $emails
 * @property-read LaravelCollection<int, Employee> $employees
 * @property-read LaravelCollection<int, ExternalLink> $externalLinks
 * @property-read LaravelCollection<int, FulfilmentCustomer> $fulfilmentCustomers
 * @property-read \App\Models\SysAdmin\GroupFulfilmentStats|null $fulfilmentStats
 * @property-read LaravelCollection<int, Fulfilment> $fulfilments
 * @property-read \App\Models\SysAdmin\GroupGoodsStats|null $goodsStats
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\Guest> $guests
 * @property-read \App\Models\SysAdmin\GroupHumanResourcesStats|null $humanResourcesStats
 * @property-read \App\Models\Helpers\Media|null $image
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $images
 * @property-read LaravelCollection<int, Ingredient> $ingredients
 * @property-read \App\Models\SysAdmin\GroupInventoryStats|null $inventoryStats
 * @property-read LaravelCollection<int, InvoiceCategory> $invoiceCategories
 * @property-read LaravelCollection<int, InvoiceTransaction> $invoiceTransactions
 * @property-read LaravelCollection<int, Invoice> $invoices
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\JobPositionCategory> $jobPositionCategories
 * @property-read LaravelCollection<int, JobPosition> $jobPositions
 * @property-read LaravelCollection<int, Location> $locations
 * @property-read LaravelCollection<int, Mailshot> $mailshots
 * @property-read \App\Models\SysAdmin\GroupMailshotsIntervals|null $mailshotsIntervals
 * @property-read \App\Models\SysAdmin\GroupManufactureStats|null $manufactureStats
 * @property-read LaravelCollection<int, ManufactureTask> $manufactureTasks
 * @property-read LaravelCollection<int, MasterAsset> $masterAssets
 * @property-read LaravelCollection<int, MasterProductCategory> $masterProductCategories
 * @property-read LaravelCollection<int, MasterShop> $masterShops
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Helpers\Media> $media
 * @property-read LaravelCollection<int, OfferCampaign> $offerCampaigns
 * @property-read LaravelCollection<int, Offer> $offers
 * @property-read \App\Models\SysAdmin\GroupOrderHandlingStats|null $orderHandlingStats
 * @property-read \App\Models\SysAdmin\GroupOrderingIntervals|null $orderingIntervals
 * @property-read \App\Models\SysAdmin\GroupOrderingStats|null $orderingStats
 * @property-read LaravelCollection<int, Order> $orders
 * @property-read LaravelCollection<int, OrgPaymentServiceProvider> $orgPaymentServiceProviders
 * @property-read LaravelCollection<int, OrgPostRoom> $orgPostRooms
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\Organisation> $organisations
 * @property-read \App\Models\SysAdmin\GroupOutboxColdEmailsIntervals|null $outboxColdEmailsIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxCustomerNotificationIntervals|null $outboxCustomerNotificationIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxMarketingIntervals|null $outboxMarketingIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxMarketingNotificationIntervals|null $outboxMarketingNotificationIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxNewsletterIntervals|null $outboxNewsletterIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxPushIntervals|null $outboxPushIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxTestIntervals|null $outboxTestIntervals
 * @property-read \App\Models\SysAdmin\GroupOutboxUserNotificationIntervals|null $outboxUserNotificationIntervals
 * @property-read LaravelCollection<int, Outbox> $outboxes
 * @property-read LaravelCollection<int, Packing> $packings
 * @property-read LaravelCollection<int, PaymentAccount> $paymentAccounts
 * @property-read LaravelCollection<int, PaymentServiceProvider> $paymentServiceProviders
 * @property-read LaravelCollection<int, Payment> $payments
 * @property-read LaravelCollection<int, Picking> $pickings
 * @property-read LaravelCollection<int, Platform> $platforms
 * @property-read LaravelCollection<int, Portfolio> $portfolios
 * @property-read LaravelCollection<int, PostRoom> $postRooms
 * @property-read \App\Models\SysAdmin\GroupProcurementStats|null $procurementStats
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
 * @property-read LaravelCollection<int, SalesChannel> $salesChannels
 * @property-read \App\Models\SysAdmin\GroupSalesIntervals|null $salesIntervals
 * @property-read \App\Models\Helpers\Media|null $seoImage
 * @property-read LaravelCollection<int, Service> $services
 * @property-read LaravelCollection<int, ShippingZoneSchema> $shippingZoneSchemas
 * @property-read LaravelCollection<int, ShippingZone> $shippingZones
 * @property-read LaravelCollection<int, Shop> $shops
 * @property-read LaravelCollection<int, Space> $spaces
 * @property-read \App\Models\SysAdmin\GroupStats|null $stats
 * @property-read LaravelCollection<int, StockFamily> $stockFamilies
 * @property-read LaravelCollection<int, Stock> $stocks
 * @property-read LaravelCollection<int, Subscription> $subscriptions
 * @property-read LaravelCollection<int, SupplierProduct> $supplierProducts
 * @property-read LaravelCollection<int, Supplier> $suppliers
 * @property-read \App\Models\SysAdmin\GroupSupplyChainStats|null $supplyChainStats
 * @property-read \App\Models\SysAdmin\GroupSysadminIntervals|null $sysadminIntervals
 * @property-read \App\Models\SysAdmin\GroupSysAdminStats|null $sysadminStats
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\Task> $tasks
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\GroupTimeSeries> $timeSeries
 * @property-read LaravelCollection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read LaravelCollection<int, TopUp> $topUps
 * @property-read LaravelCollection<int, TradeUnit> $tradeUnits
 * @property-read LaravelCollection<int, Upload> $uploads
 * @property-read LaravelCollection<int, UserRequest> $userRequests
 * @property-read LaravelCollection<int, \App\Models\SysAdmin\User> $users
 * @property-read LaravelCollection<int, WarehouseArea> $warehouseAreas
 * @property-read LaravelCollection<int, Warehouse> $warehouses
 * @property-read LaravelCollection<int, WebBlockType> $webBlockTypes
 * @property-read \App\Models\SysAdmin\GroupWebStats|null $webStats
 * @property-read LaravelCollection<int, WebUser> $webUsers
 * @property-read LaravelCollection<int, Webpage> $webpages
 * @property-read LaravelCollection<int, Website> $websites
 * @method static \Database\Factories\SysAdmin\GroupFactory factory($count = null, $state = [])
 * @method static Builder<static>|Group newModelQuery()
 * @method static Builder<static>|Group newQuery()
 * @method static Builder<static>|Group onlyTrashed()
 * @method static Builder<static>|Group query()
 * @method static Builder<static>|Group withTrashed()
 * @method static Builder<static>|Group withoutTrashed()
 * @mixin Eloquent
 */
class Group extends Authenticatable implements Auditable, HasMedia
{
    use SoftDeletes;
    use HasSlug;
    use HasFactory;
    use InteractsWithMedia;
    use HasImage;
    use HasApiTokens;
    use HasHistory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'limits'   => 'array',
            'data'     => 'array',
            'settings' => 'array',
        ];
    }

    protected $attributes = [
        'limits'   => '{}',
        'data'     => '{}',
        'settings' => '{}',
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

    protected array $auditInclude = [
        'code',
        'name',
        'country_id',
        'currency_id',
        'language_id',
        'timezone_id'
    ];

    public function tradeUnits(): HasMany
    {
        return $this->hasMany(TradeUnit::class);
    }

    public function stockFamilies(): HasMany
    {
        return $this->hasMany(StockFamily::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(GroupStats::class);
    }

    public function humanResourcesStats(): HasOne
    {
        return $this->hasOne(GroupHumanResourcesStats::class);
    }

    public function crmStats(): HasOne
    {
        return $this->hasOne(GroupCRMStats::class);
    }

    public function accountingStats(): HasOne
    {
        return $this->hasOne(GroupAccountingStats::class);
    }

    public function discountsStats(): HasOne
    {
        return $this->hasOne(GroupDiscountsStats::class);
    }

    public function orderingStats(): HasOne
    {
        return $this->hasOne(GroupOrderingStats::class);
    }

    public function salesIntervals(): HasOne
    {
        return $this->hasOne(GroupSalesIntervals::class);
    }

    public function orderHandlingStats(): HasOne
    {
        return $this->hasOne(GroupOrderHandlingStats::class);
    }

    public function mailshotsIntervals(): HasOne
    {
        return $this->hasOne(GroupMailshotsIntervals::class);
    }

    public function sysadminStats(): HasOne
    {
        return $this->hasOne(GroupSysAdminStats::class);
    }

    public function inventoryStats(): HasOne
    {
        return $this->hasOne(GroupInventoryStats::class);
    }

    public function goodsStats(): HasOne
    {
        return $this->hasOne(GroupGoodsStats::class);
    }

    public function supplyChainStats(): HasOne
    {
        return $this->hasOne(GroupSupplyChainStats::class);
    }

    public function procurementStats(): HasOne
    {
        return $this->hasOne(GroupProcurementStats::class);
    }

    public function webStats(): HasOne
    {
        return $this->hasOne(GroupWebStats::class);
    }

    public function commsStats(): HasOne
    {
        return $this->hasOne(GroupCommsStats::class);
    }

    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class);
    }

    public function postRooms(): HasMany
    {
        return $this->hasMany(PostRoom::class);
    }

    public function orgPostRooms(): HasMany
    {
        return $this->hasMany(OrgPostRoom::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function webUsers(): HasMany
    {
        return $this->hasMany(WebUser::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function jobPositions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }

    public function jobPositionCategories(): HasMany
    {
        return $this->hasMany(JobPositionCategory::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function fulfilmentStats(): HasOne
    {
        return $this->hasOne(GroupFulfilmentStats::class);
    }

    public function fulfilments(): HasMany
    {
        return $this->hasMany(Fulfilment::class);
    }

    public function manufactureStats(): HasOne
    {
        return $this->hasOne(GroupManufactureStats::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceTransactions(): HasMany
    {
        return $this->hasMany(InvoiceTransaction::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paymentServiceProviders(): HasMany
    {
        return $this->hasMany(PaymentServiceProvider::class);
    }

    public function orgPaymentServiceProviders(): HasMany
    {
        return $this->hasMany(OrgPaymentServiceProvider::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function catalogueStats(): HasOne
    {
        return $this->hasOne(GroupCatalogueStats::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
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

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
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

    public function artefacts(): HasMany
    {
        return $this->hasMany(Artefact::class);
    }

    public function clockingMachines(): HasMany
    {
        return $this->hasMany(ClockingMachine::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(Webpage::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
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

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function dropshippingStats(): HasOne
    {
        return $this->hasOne(GroupDropshippingStat::class);
    }

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(CustomerClient::class);
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function mailshots(): HasMany
    {
        return $this->hasMany(Mailshot::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function emailBulkRuns(): HasMany
    {
        return $this->hasMany(EmailBulkRun::class);
    }

    public function dispatchedEmails(): HasMany
    {
        return $this->hasMany(DispatchedEmail::class);
    }

    public function webBlockTypes(): HasMany
    {
        return $this->hasMany(WebBlockType::class);
    }

    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
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

    public function masterShops(): HasMany
    {
        return $this->hasMany(MasterShop::class);
    }

    public function masterAssets(): HasMany
    {
        return $this->hasMany(MasterAsset::class);
    }

    public function masterProductCategories(): HasMany
    {
        return $this->hasMany(MasterProductCategory::class);
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(Redirect::class);
    }

    public function externalLinks(): HasMany
    {
        return $this->hasMany(ExternalLink::class);
    }

    public function emailAddresses(): HasMany
    {
        return $this->hasMany(EmailAddress::class);
    }
    public function purges(): HasMany
    {
        return $this->hasMany(Purge::class);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class);
    }

    public function emailTemplates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class);
    }


    public function invoiceCategories(): HasMany
    {
        return $this->hasMany(InvoiceCategory::class);
    }

    public function userRequests(): HasMany
    {
        return $this->hasMany(UserRequest::class);
    }

    public function aikuSections(): HasMany
    {
        return $this->hasMany(AikuSection::class);
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

    public function orderingIntervals(): HasOne
    {
        return $this->hasOne(GroupOrderingIntervals::class);
    }

    public function sysadminIntervals(): HasOne
    {
        return $this->hasOne(GroupSysadminIntervals::class);
    }

    public function timeSeries(): HasMany
    {
        return $this->hasMany(GroupTimeSeries::class);
    }

    public function outboxNewsletterIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxNewsletterIntervals::class);
    }


    public function outboxMarketingIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxMarketingIntervals::class);
    }

    public function outboxMarketingNotificationIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxMarketingNotificationIntervals::class);
    }

    public function outboxCustomerNotificationIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxCustomerNotificationIntervals::class);
    }

    public function outboxColdEmailsIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxColdEmailsIntervals::class);
    }

    public function outboxUserNotificationIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxUserNotificationIntervals::class);
    }

    public function outboxPushIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxPushIntervals::class);
    }

    public function fulfilmentCustomers(): HasMany
    {
        return $this->hasMany(FulfilmentCustomer::class);
    }

    public function outboxTestIntervals(): HasOne
    {
        return $this->hasOne(GroupOutboxTestIntervals::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }

}
