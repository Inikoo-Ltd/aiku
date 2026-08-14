<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Collection\AttachModelsToCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Collection\StoreCollectionWebpage;
use App\Actions\Catalogue\Product\SyncProductExclusiveCustomers;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\AttachFamiliesToSubDepartment;
use App\Actions\Catalogue\ProductCategory\DeleteProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\StoreSubDepartment;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Actions\Masters\MasterCollection\AttachModelsToMasterCollection;
use App\Actions\Masters\MasterCollection\StoreMasterCollection;
use App\Actions\Masters\MasterProductCategory\DeleteMasterProductCategory;
use App\Actions\Web\Redirect\StoreRedirect;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\CRM\Customer;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rebuilds the AW Aromatics catalogue to the agreed structure proposal: 5 departments, 41
 * sub-departments, the families re-parented under them, the supporting collections, and the
 * ranges that are only sold to named customers moved out of the public tree.
 *
 * Dry run unless --live. Idempotent: a second run reports nothing left to do.
 */
class AromaBuildStructure
{
    use AsAction;

    private bool $live = false;
    private Command $command;
    private Shop $shop;
    private array $counts = [];

    private const DEPARTMENTS = [
        'FO' => [
            'name' => 'Fragrance Oils',
            'url'  => 'fragrance-oils',
            'sub_departments' => [
                'bulk-fragrance-oils' => ['name' => 'Pure Bulk Fragrance Oils', 'families' => ['FOKG', 'FOBp', 'EXAWFO']],
                'fine-fragrance-perfume-oils' => ['name' => 'Fine Fragrance & Perfume Oils', 'families' => ['BFFPO']],
                'reed-diffuser-blends' => ['name' => 'Reed Diffuser Blends', 'families' => ['RDHF5', 'RDEO5']],
                'simmering-granules' => ['name' => 'Simmering Granules', 'families' => ['SGBK', 'BSGHC']],
                'room-mist-bases' => ['name' => 'Room & Mist Bases', 'families' => ['EOMB']],
            ],
        ],
        'ENO' => [
            'name' => 'Essential & Natural Oils',
            'url'  => 'essential-natural-oils',
            'sub_departments' => [
                'bulk-essential-oils' => ['name' => 'Pure Essential Oils', 'families' => ['EOKG', 'AEKG']],
                'bulk-organic-essential-oils' => ['name' => 'Organic Essential Oils', 'families' => ['OrgEOKG', 'OrgEOB']],
                'bulk-essential-oil-blends' => ['name' => 'Essential Oil Blends', 'families' => ['EBLKG', 'REBLBK']],
                'bulk-carrier-oils' => ['name' => 'Carrier & Base Oils', 'families' => ['BOKG', 'BOz', 'BOz5']],
                'bulk-organic-carrier-oils' => ['name' => 'Organic Carrier Oils', 'families' => ['OrgBOKG']],
                'bulk-massage-oils' => ['name' => 'Massage & Bath Oils', 'families' => ['MOB', 'MOB5']],
                'bulk-hydrolats' => ['name' => 'Hydrolats & Floral Waters', 'families' => ['FloralWKG', 'FWA5']],
            ],
        ],
        'RM' => [
            'name' => 'Raw Materials & Bulk Bases',
            'url'  => 'raw-materials',
            'sub_departments' => [
                'liquid-ingredients' => ['name' => 'Liquid Ingredients', 'families' => ['RAWL']],
                'dry-ingredients' => ['name' => 'Dry & Bath Bomb Ingredients', 'families' => ['RAWMDRY']],
                'clays-face-masks' => ['name' => 'Clays & Face Mask Powders', 'families' => ['RAWC', 'ARCLAY']],
                'fruit-flower-powders' => ['name' => 'Fruit & Flower Powders', 'families' => ['PWDR']],
                'botanicals-dried-florals' => ['name' => 'Botanicals & Dried Florals', 'families' => ['RAWPF']],
                'cosmetic-flavourings' => ['name' => 'Cosmetic Flavourings', 'families' => ['FLAV', 'FLKG']],
                'soap-bases-butters' => ['name' => 'Soap Bases & Butters', 'families' => ['RAWSB', 'RawB', 'Melt']],
                'bulk-lotions-washes' => ['name' => 'Bulk Lotions & Washes', 'families' => ['FHBLB', 'AHBLB', 'FHBWB', 'AHBWB']],
                'bulk-body-hair-face' => ['name' => 'Bulk Body, Hair & Face', 'families' => ['OBOB', 'OHSB', 'SerumKG', 'BeardoB']],
                'bulk-bath-salts' => ['name' => 'Bulk Bath Salts & Potions', 'families' => ['HBATHSB', 'ABPB']],
                'colours-pigments' => ['name' => 'Colours & Pigments', 'families' => ['MICA', 'WaterSC', 'LAKE', 'NEON', 'FUZIO', 'DYE']],
            ],
        ],
        'WL' => [
            'name' => 'White Label',
            'url'  => 'white-label',
            'sub_departments' => [
                'white-label-aromatherapy' => ['name' => 'White Label Aromatherapy Oils', 'families' => ['EOUL', 'PrEOUL', 'ULFO', 'ulfo-xmas', 'OrgeoUL', 'EblUL', 'ReblUL', 'FFPOUL', 'careoul', 'FOBX', 'EOBX']],
                'white-label-carrier-massage-oils' => ['name' => 'White Label Carrier & Massage Oils', 'families' => ['BOUL', 'OrgBOUL', 'MOLUL', 'MOPUL', 'HDLUL', 'MOUL']],
                'white-label-bath-bombs' => ['name' => 'White Label Bath Bombs', 'families' => ['JBB', 'ABB', 'bb-xmas', 'TPCB', 'HBB', 'SKB', 'Begg', 'HeartB', 'xbbul', 'GBBBUL', 'MFF', 'HSBB', 'EBB', 'SBB', 'GemBB', 'cbbul', 'JDBB', 'FBB', 'JABB', 'Donut']],
                'white-label-soaps' => ['name' => 'White Label Soaps', 'families' => ['HCS', 'SLHCS', 'WCSUL', 'TPSoap', 'AWGsoap', 'MSP', 'KSOAPUL', 'KSoap', 'soaps-xmas', 'Asoap', 'LSoap']],
                'white-label-face-body' => ['name' => 'White Label Face & Body', 'families' => ['FHBLUL', 'AHBLUL', 'FHBWUL', 'AHBWUL', 'FSBSUL', 'UBB', 'UBBFO', 'UBBEO', 'OHSUL', 'OBOUL', 'UWHSS', 'WSPUL', 'SERFUL', 'BeardOUL', 'FSBUL']],
                'white-label-bath-salts' => ['name' => 'White Label Bath Salts & Soaks', 'families' => ['HBathSUL', 'ABPCUL', 'EBBSUL', 'AWChill', 'BAS']],
                'white-label-shower-steamers' => ['name' => 'White Label Shower Steamers', 'families' => ['AROSS', 'ZSS']],
                'white-label-home-fragrance' => ['name' => 'White Label Home Fragrance', 'families' => ['AWRSUL', 'SRSUL', 'HFRSUL', 'XRPSUL', 'OudhRSUL', 'EOMUL', 'UASC', 'WLSoyC']],
            ],
        ],
        'PP' => [
            'name' => 'Packaging & Display',
            'url'  => 'packaging',
            'sub_departments' => [
                'glass-dropper-bottles' => ['name' => 'Glass Dropper & Boston Bottles', 'families' => ['GBot', 'GGDB', 'BLDB', 'CGDB', 'BGDB', 'AGBB', 'AGAB']],
                'glass-medicine-bottles' => ['name' => 'Glass Medicine & Spray Bottles', 'families' => ['GMB', 'CGMB', 'PerfB']],
                'reed-diffuser-packaging' => ['name' => 'Reed Diffuser Bottles & Reeds', 'families' => ['RDBot', 'Rreed', 'CarD']],
                'jars' => ['name' => 'Jars', 'families' => ['Gjar', 'CGJ', 'CandyJ', 'ACJAR']],
                'caps-closures' => ['name' => 'Caps, Pipettes & Closures', 'families' => ['GbotC', 'GbotD', 'AGBC', 'GMBC']],
                'plastic-aluminium' => ['name' => 'Plastic & Aluminium', 'families' => ['PBot', 'Mtin', 'ABot']],
                'bags-pouches' => ['name' => 'Bags & Pouches', 'families' => ['KWB', 'NatWP', 'CandyB', 'Grip', 'HCB', 'Kbag', 'JGP', 'JSack', 'Wbag', 'Sbag', 'Poly', 'JGBag']],
                'boxes' => ['name' => 'Boxes', 'families' => ['APBox', 'FPWB', 'boxes', 'DB']],
                'ribbons-labels-fillers' => ['name' => 'Ribbons, Labels & Fillers', 'families' => ['NTRib', 'PackR', 'AWKL', 'JuteTW', 'Reel', 'ShredP', 'ShredpKG']],
                'shop-fittings-display' => ['name' => 'Shop Fittings & Display', 'families' => ['ACShop', 'HMS', 'Prop', 'Promo_UK']],
            ],
        ],
    ];

    /** Collections the proposal adds, code doubles as the url slug. */
    private const COLLECTIONS = [
        'aquatic-fragrance-oils'      => 'Aquatic & Marine Fragrance Oils',
        'herbal-green-fragrance-oils' => 'Herbal & Green Fragrance Oils',
        'citrus-fragrance-oils'       => 'Citrus Fragrance Oils',
        'halloween'                   => 'Halloween',
        'soap-making'                 => 'Soap Making',
        'candle-making'               => 'Candle Making',
        'wax-melt-making'             => 'Wax Melt Making',
        'perfume-making'              => 'Perfume Making',
        'cosmetics-making'            => 'Cosmetics Making',
        'eazycolours'                 => 'EazyColours',
        'organic'                     => 'Organic',
        'made-in-sheffield'           => 'Made in Sheffield',
    ];

    /** Only the memberships the proposal states outright; the rest are a merchandising decision. */
    private const COLLECTION_FAMILIES = [
        'organic'        => ['OrgEOKG', 'OrgBOKG', 'OrgeoUL', 'OrgBOUL', 'OBOB', 'OBOUL'],
        'eazycolours'    => ['MICA', 'WaterSC', 'LAKE', 'NEON', 'FUZIO', 'DYE'],
        'CraftReed'      => ['RDBot', 'Rreed', 'RDHF5', 'RDEO5', 'CarD'],
        'perfume-making' => ['BFFPO', 'PerfB'],
    ];

    /** Empty shells the proposal drops: scent departments duplicating collections, plus Dyes and Colours. */
    private const RETIRE_EMPTY = [
        'AutumnFO', 'DesignFO', 'FloralFO', 'FreshFO', 'FruityFO',
        'GemFO', 'LoveFO', 'SpicyFO', 'SweetFO', 'XmasFO', 'DC',
    ];

    /** Departments whose ranges are sold to named customers only, so they leave the public tree. */
    private const RETIRE_EXCLUSIVE = ['AW', 'BP'];

    private const DEAD_FAMILY = 'EXEO';

    /**
     * Where a retired department's page should send its traffic. Its url disappears with it, so
     * without this the old address 404s and whatever ranking it had is thrown away. A department
     * with no entry here falls back to the storefront.
     */
    private const RETIRE_REDIRECTS = [
        'DC' => 'eazycolours',
    ];

    public function handle(Shop $shop, Command $command, bool $live, string $phase): void
    {
        $this->shop    = $shop;
        $this->command = $command;
        $this->live    = $live;

        if (in_array($phase, ['all', 'structure'])) {
            $this->buildStructure();
        }
        if (in_array($phase, ['all', 'collections'])) {
            $this->buildCollections();
        }
        if (in_array($phase, ['all', 'exclusive'])) {
            // Before the exclusive pass: a dead range must not be handed to a customer as if
            // they still bought it.
            $this->discontinueDeadFamily();
            $this->moveExclusiveRanges();
        }
        if (in_array($phase, ['all', 'cleanup'])) {
            $this->cleanUp();
        }

        $this->report();
    }

    private function tally(string $key, int $n = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $n;
    }

    private function find(string $type, string $code): ?ProductCategory
    {
        return ProductCategory::where('shop_id', $this->shop->id)->where('type', $type)
            ->whereNull('deleted_at')->whereRaw('lower(code) = lower(?)', [$code])->first();
    }

    private function buildStructure(): void
    {
        $this->command->info('— structure');

        foreach (self::DEPARTMENTS as $code => $spec) {
            $department = $this->find('department', $code);

            if (!$department) {
                $this->tally('departments created');
                $this->command->line("  create department $code {$spec['name']}");
            }

            if (!$department && $this->live) {
                $department = StoreProductCategory::make()->action($this->shop, [
                    'code' => $code,
                    'name' => $spec['name'],
                    'type' => ProductCategoryTypeEnum::DEPARTMENT,
                ]);
                $this->givePage($department);
                $this->setUrl($department, $spec['url']);
            }

            foreach ($spec['sub_departments'] as $subCode => $sub) {
                $this->buildSubDepartment($department, $subCode, $sub);
            }
        }
    }

    private function buildSubDepartment(?ProductCategory $department, string $code, array $sub): void
    {
        $subDepartment = $this->find('sub_department', $code);

        if (!$subDepartment) {
            $this->tally('sub departments created');
            $this->command->line("  create sub department $code");

            if ($this->live && $department) {
                $subDepartment = StoreSubDepartment::make()->action($department, [
                    'code' => $code,
                    'name' => $sub['name'],
                ]);
                $this->givePage($subDepartment);
            }
        }

        $toMove = [];
        foreach ($sub['families'] as $familyCode) {
            $family = $this->find('family', $familyCode);
            if (!$family) {
                $this->command->warn("  family $familyCode not found");
                $this->tally('families missing');
                continue;
            }
            if (!$subDepartment || $family->sub_department_id != $subDepartment->id) {
                $toMove[] = $family->id;
            }
        }

        if ($toMove) {
            $this->tally('families re-parented', count($toMove));
            $this->command->line('    move '.count($toMove)." families into $code");
            if ($this->live && $subDepartment) {
                AttachFamiliesToSubDepartment::make()->action($subDepartment, ['families_id' => $toMove]);
            }
        }
    }

    private function givePage(ProductCategory $category): void
    {
        if ($category->webpage) {
            return;
        }
        try {
            $webpage = StoreProductCategoryWebpage::make()->action($category);
            PublishWebpage::make()->action($webpage, ['comment' => 'Aroma structure build']);
            $this->tally('webpages published');
        } catch (\Throwable $e) {
            $this->command->warn("  no page for $category->code: ".$e->getMessage());
            $this->tally('webpages failed');
        }
    }

    private function setUrl(ProductCategory $department, string $url): void
    {
        if ($department->url === $url) {
            return;
        }
        if ($department->webpage) {
            UpdateWebpage::make()->action($department->webpage, ['url' => $url]);
        }
        UpdateProductCategory::make()->action($department, ['url' => $url]);
        $this->tally('urls corrected');
    }

    private function buildCollections(): void
    {
        $this->command->info('— collections');

        foreach (self::COLLECTIONS as $code => $name) {
            if ($this->collection($code)) {
                continue;
            }
            $this->tally('collections created');
            $this->command->line("  create collection $code");
            if (!$this->live) {
                continue;
            }
            $collection = StoreCollection::make()->action($this->shop, ['code' => $code, 'name' => $name]);
            try {
                $webpage = StoreCollectionWebpage::run($collection);
                PublishWebpage::make()->action($webpage, ['comment' => 'Aroma structure build']);
                $this->tally('webpages published');
            } catch (\Throwable $e) {
                $this->command->warn("  no page for collection $code: ".$e->getMessage());
                $this->tally('webpages failed');
            }
        }

        foreach (self::COLLECTION_FAMILIES as $code => $familyCodes) {
            $collection = $this->collection($code);
            if (!$collection) {
                continue;
            }
            $ids = [];
            foreach ($familyCodes as $familyCode) {
                $family = $this->find('family', $familyCode);
                if ($family && !DB::table('collection_has_models')->where('collection_id', $collection->id)
                    ->where('model_type', 'ProductCategory')->where('model_id', $family->id)->exists()) {
                    $ids[] = $family->id;
                }
            }
            if ($ids) {
                $this->tally('families added to collections', count($ids));
                $this->command->line('  add '.count($ids)." families to $code");
                if ($this->live) {
                    AttachModelsToCollection::make()->action($collection, ['families' => $ids]);
                }
            }
        }

        $this->mirrorCollectionsToMaster();
    }

    /**
     * Every shop collection needs its master, the same way families do. Without it the collection
     * is invisible on the master side, and maintenance:repair_orphan_collections treats a
     * collection with no master as rubbish and deletes it.
     */
    private function mirrorCollectionsToMaster(): void
    {
        $masterShop = $this->shop->masterShop;

        if (!$masterShop) {
            return;
        }

        $collections = Collection::where('shop_id', $this->shop->id)->whereNull('deleted_at')->get();

        foreach ($collections as $collection) {
            $masterCollection = MasterCollection::where('master_shop_id', $masterShop->id)
                ->whereNull('deleted_at')
                ->whereRaw('lower(code) = lower(?)', [$collection->code])->first();

            if (!$masterCollection) {
                $this->tally('master collections created');
                $this->command->line("  master collection {$collection->code}");

                if ($this->live) {
                    // createChildren would build a second shop collection beside the one we have.
                    $masterCollection = StoreMasterCollection::make()->action(
                        $masterShop,
                        ['code' => $collection->code, 'name' => $collection->name],
                        createChildren: false
                    );
                }
            }

            if (!$masterCollection || $collection->master_collection_id != $masterCollection->id) {
                $this->tally('collections linked to master');
                if ($this->live && $masterCollection) {
                    UpdateCollection::make()->action($collection, ['master_collection_id' => $masterCollection->id]);
                }
            }

            $this->mirrorCollectionMembers($collection, $masterCollection);
        }
    }

    private function mirrorCollectionMembers(Collection $collection, ?MasterCollection $masterCollection): void
    {
        $familyIds = DB::table('collection_has_models')
            ->where('collection_id', $collection->id)
            ->where('model_type', 'ProductCategory')
            ->pluck('model_id');

        $masterFamilyIds = ProductCategory::whereIn('id', $familyIds)
            ->whereNotNull('master_product_category_id')
            ->pluck('master_product_category_id')
            ->unique()
            ->reject(fn ($id) => $masterCollection && DB::table('master_collection_has_models')
                ->where('master_collection_id', $masterCollection->id)
                ->where('model_type', 'MasterProductCategory')
                ->where('model_id', $id)->exists())
            ->values()
            ->all();

        if (!$masterFamilyIds) {
            return;
        }

        $this->tally('families added to master collections', count($masterFamilyIds));

        if ($this->live && $masterCollection) {
            AttachModelsToMasterCollection::make()->action($masterCollection, ['families' => $masterFamilyIds]);
        }
    }

    private function collection(string $code): ?Collection
    {
        return Collection::where('shop_id', $this->shop->id)->whereNull('deleted_at')
            ->whereRaw('lower(code) = lower(?)', [$code])->first();
    }

    /**
     * The AW Group and Bespoke ranges are sold to named customers, not the public. Which customers
     * is read from who actually bought them, because aurora only records a single customer per
     * product and records none at all for the group companies.
     */
    private function moveExclusiveRanges(): void
    {
        $this->command->info('— exclusive ranges');

        $groupCustomers = Customer::where('shop_id', $this->shop->id)
            ->whereNotNull('as_organisation_id')->pluck('id')->all();

        if (!$groupCustomers) {
            $this->command->warn('  no group companies found as customers, skipping');

            return;
        }

        foreach (self::RETIRE_EXCLUSIVE as $code) {
            $department = $this->find('department', $code);
            if (!$department) {
                continue;
            }

            $products = Product::where('shop_id', $this->shop->id)->whereNull('deleted_at')
                ->where('department_id', $department->id)->where('is_main', true)
                ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)
                ->whereDoesntHave('exclusiveCustomers')->get();

            foreach ($products as $product) {
                $buyers = DB::table('invoice_transactions')
                    ->where('asset_id', $product->asset_id)
                    ->where('shop_id', $this->shop->id)
                    ->where('date', '>', now()->subMonths(24))
                    ->whereIn('customer_id', $groupCustomers)
                    ->distinct()->pluck('customer_id')->all();

                $this->tally($buyers ? 'exclusive from sales history' : 'exclusive defaulted to group');

                if ($this->live) {
                    SyncProductExclusiveCustomers::make()->action($product, [
                        'customer_ids' => $buyers ?: $groupCustomers,
                    ]);
                }
            }
        }

        // Nothing sold to named customers should still be public.
        $public = Product::where('shop_id', $this->shop->id)->whereNull('deleted_at')
            ->whereHas('exclusiveCustomers')
            ->where(fn ($q) => $q->where('is_for_sale', true)->orWhere('is_in_website', true))
            ->get();

        foreach ($public as $product) {
            $this->tally('exclusive taken off sale');
            $this->command->line("  off sale $product->code");
            if ($this->live) {
                UpdateProduct::make()->action($product, ['is_for_sale' => false]);
            }
        }
    }

    private function cleanUp(): void
    {
        $this->command->info('— clean up');

        $this->discontinueDeadFamily();

        foreach (self::RETIRE_EMPTY as $code) {
            $this->retireDepartment($code, requireEmpty: true);
        }

        foreach (self::RETIRE_EXCLUSIVE as $code) {
            $this->retireDepartment($code, requireEmpty: false);
        }
    }

    private function discontinueDeadFamily(): void
    {
        $family = $this->find('family', self::DEAD_FAMILY);
        if (!$family) {
            return;
        }

        $products = Product::where('family_id', $family->id)->whereNull('deleted_at')
            ->where('state', '!=', ProductStateEnum::DISCONTINUED->value)->get();

        if ($products->isEmpty() && $family->state == ProductCategoryStateEnum::DISCONTINUED) {
            return;
        }

        $this->tally('dead family products discontinued', $products->count());
        $this->command->line('  discontinue '.self::DEAD_FAMILY.' ('.$products->count().' products)');

        if (!$this->live) {
            return;
        }

        foreach ($products as $product) {
            UpdateProduct::make()->action($product, ['state' => ProductStateEnum::DISCONTINUED]);
        }
        UpdateProductCategory::make()->action($family, ['state' => ProductCategoryStateEnum::DISCONTINUED]);
    }

    /**
     * Deleting a department does not move what is inside it, so anything still pointing at it is
     * detached first, otherwise families and products are left referencing a deleted row.
     */
    private function retireDepartment(string $code, bool $requireEmpty): void
    {
        $department = $this->find('department', $code);
        if (!$department) {
            return;
        }

        $families = DB::table('product_categories')->where('department_id', $department->id)
            ->whereNull('deleted_at')->count();
        $products = DB::table('products')->where('department_id', $department->id)
            ->whereNull('deleted_at')->count();

        if ($requireEmpty && ($families || $products)) {
            $this->command->warn("  keep $code: not empty ($families families, $products products)");
            $this->tally('departments kept, not empty');

            return;
        }

        $public = DB::table('products')->where('department_id', $department->id)->whereNull('deleted_at')
            ->where(fn ($q) => $q->where('is_for_sale', true)->orWhere('is_in_website', true))->count();

        if ($public) {
            $this->command->warn("  keep $code: $public products still public");
            $this->tally('departments kept, still public');

            return;
        }

        $this->tally('departments retired');
        $this->command->line("  retire $code (detach $families families, $products products)");

        if ($department->webpage && $department->webpage->state == WebpageStateEnum::LIVE) {
            $this->redirectRetiredPage($department, $code);
        }

        if (!$this->live) {
            return;
        }

        DB::table('product_categories')->where('department_id', $department->id)->whereNull('deleted_at')
            ->update(['department_id' => null, 'sub_department_id' => null, 'parent_id' => null]);
        DB::table('products')->where('department_id', $department->id)->whereNull('deleted_at')
            ->update(['department_id' => null, 'sub_department_id' => null]);

        DeleteProductCategory::make()->handle($department);

        $master = MasterProductCategory::where('master_shop_id', $this->shop->master_shop_id)
            ->where('type', 'department')->whereNull('deleted_at')
            ->whereRaw('lower(code) = lower(?)', [$code])->first();

        if ($master) {
            DB::table('master_product_categories')->where('master_department_id', $master->id)->whereNull('deleted_at')
                ->update(['master_department_id' => null, 'master_sub_department_id' => null, 'master_parent_id' => null]);
            DB::table('master_assets')->where('master_department_id', $master->id)->whereNull('deleted_at')
                ->update(['master_department_id' => null, 'master_sub_department_id' => null]);
            DeleteMasterProductCategory::make()->handle($master);
            $this->tally('master departments retired');
        }
    }

    private function redirectRetiredPage(ProductCategory $department, string $code): void
    {
        $target = null;

        if ($collectionCode = self::RETIRE_REDIRECTS[$code] ?? null) {
            $target = $this->collection($collectionCode)?->webpage;
        }

        $target ??= Webpage::where('website_id', $department->webpage->website_id)
            ->where('type', 'storefront')->whereNull('deleted_at')->first();

        if (!$target || $target->state != WebpageStateEnum::LIVE) {
            $this->command->warn("  no redirect target for /{$department->webpage->url}");
            $this->tally('redirects with no target');

            return;
        }

        $this->tally('redirects created');
        $this->command->line("    301 /{$department->webpage->url} -> /{$target->url}");

        if (!$this->live) {
            return;
        }

        try {
            StoreRedirect::make()->action($department->webpage, [
                'type'          => RedirectTypeEnum::PERMANENT,
                'to_webpage_id' => $target->id,
            ]);
        } catch (\Throwable $e) {
            $this->command->warn('  redirect failed: '.$e->getMessage());
            $this->tally('redirects failed');
        }
    }

    private function report(): void
    {
        $this->command->newLine();
        $this->command->info($this->live ? '=== APPLIED ===' : '=== DRY RUN, nothing written ===');

        if (!$this->counts) {
            $this->command->line('nothing to do');

            return;
        }

        ksort($this->counts);
        foreach ($this->counts as $what => $n) {
            $this->command->line(str_pad($what, 36).$n);
        }

        if (!$this->live) {
            $this->command->newLine();
            $this->command->comment('run again with --live to apply');
        }
    }

    public function getCommandSignature(): string
    {
        return 'aroma:build_structure {--live : write the changes} {--phase=all : all|structure|collections|exclusive|cleanup}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', 'aroma')->firstOrFail();

        $phase = $command->option('phase');
        if (!in_array($phase, ['all', 'structure', 'collections', 'exclusive', 'cleanup'])) {
            $command->error("unknown phase $phase");

            return 1;
        }

        $this->handle($shop, $command, (bool) $command->option('live'), $phase);

        return 0;
    }
}
