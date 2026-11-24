<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UI\GetMasterAssetsPartnerPricing;
use App\Actions\Masters\MasterAsset\UpdateMasterAssetPartnerPrices;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Actions\Procurement\OrgPartner\StoreOrgPartner;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetPartnerPrice;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();
    actingAs($this->adminGuest->getUser());

    $this->aromaMasterShop = partnerPricesMasterShop($this->group, GetMasterAssetsPartnerPricing::AROMA_MASTER_SHOP_SLUG);
    $this->shop->updateQuietly(['master_shop_id' => $this->aromaMasterShop->id]);

    $this->buyer      = partnerPricesBuyerOrganisation($this->organisation);
    $this->orgPartner = OrgPartner::where('organisation_id', $this->buyer->id)->where('partner_id', $this->organisation->id)->first()
        ?? StoreOrgPartner::make()->action($this->buyer, $this->organisation);
    $this->orgPartner->update(['status' => true]);

    $this->code        = 'PPT-'.strtoupper(substr(uniqid(), -6));
    $this->masterAsset = partnerPricesMasterAsset(partnerPricesFamily($this->aromaMasterShop), $this->code, $this->shop->currency->code, 20, 8);
});

function partnerPricesMasterShop($group, string $slug): MasterShop
{
    $masterShop = MasterShop::where('slug', $slug)->first();
    if ($masterShop) {
        return $masterShop;
    }

    $masterShop = StoreMasterShop::make()->action($group, [
        'type' => ShopTypeEnum::B2B,
        'code' => $slug,
        'name' => 'Partner prices '.$slug,
    ]);
    $masterShop->updateQuietly(['slug' => $slug]);

    return $masterShop->refresh();
}

function partnerPricesFamily(MasterShop $masterShop): MasterProductCategory
{
    $department = StoreMasterDepartment::make()->action($masterShop, [
        'code' => 'PPD-'.uniqid(),
        'name' => 'Partner prices department',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    return StoreMasterFamily::make()->action($department, [
        'code' => 'PPF-'.uniqid(),
        'name' => 'Partner prices family',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);
}

function partnerPricesMasterAsset(MasterProductCategory $masterFamily, string $code, string $currencyCode, float $price, float $cost): MasterAsset
{
    $masterAsset = StoreMasterAsset::make()->action($masterFamily, [
        'code'    => $code,
        'name'    => $code,
        'is_main' => true,
        'type'    => MasterAssetTypeEnum::PRODUCT,
        'price'   => $price,
        'stocks'  => [],
    ]);

    $masterAsset->updateQuietly([
        'units'          => 16,
        'effective_cost' => $cost,
        'master_prices'  => [$currencyCode => ['value' => (string) $price, 'independent' => false]],
        'master_rrps'    => [$currencyCode => ['value' => '47.20', 'independent' => false]],
    ]);

    return $masterAsset->refresh();
}

/** A partner organisation selling in EUR, so its prices are converted from the seller's currency. */
function partnerPricesBuyerOrganisation(Organisation $seller): Organisation
{
    $euro  = Currency::where('code', 'EUR')->firstOrFail();
    $buyer = Organisation::where('code', 'ppbuy')->first()
        ?? StoreOrganisation::make()->action($seller->group, array_merge(Organisation::factory()->definition(), [
            'code' => 'ppbuy',
            'type' => OrganisationTypeEnum::SHOP,
        ]));
    $buyer->updateQuietly(['currency_id' => $euro->id]);

    return $buyer->refresh();
}

/** The AW-class master the buyer's own B2B shop sells under the same code. */
function partnerPricesSibling(Organisation $buyer, string $code): MasterAsset
{
    $siblingMasterShop = StoreMasterShop::make()->action($buyer->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'PPS'.substr(uniqid(), -6),
        'name' => 'Partner prices sibling class',
    ]);
    $sibling = partnerPricesMasterAsset(partnerPricesFamily($siblingMasterShop), $code, 'EUR', 21.06, 8);

    $buyerShop = Shop::where('organisation_id', $buyer->id)->where('type', ShopTypeEnum::B2B)->first()
        ?? StoreShop::make()->action($buyer, array_merge(Shop::factory()->definition(), ['type' => ShopTypeEnum::B2B]));
    $buyerShop->updateQuietly(['master_shop_id' => $siblingMasterShop->id]);

    [, $product] = createProduct($buyerShop);
    $product->updateQuietly([
        'code'              => $code,
        'master_product_id' => $sibling->id,
        'state'             => ProductStateEnum::ACTIVE,
    ]);

    return $sibling;
}

test('a partner price is stored in the partner currency and cleared back to the default', function () {
    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => 12.5],
    ]);

    $partnerPrice = MasterAssetPartnerPrice::where('master_asset_id', $this->masterAsset->id)->where('org_partner_id', $this->orgPartner->id)->first();
    expect($partnerPrice)->not->toBeNull()
        ->and((float) $partnerPrice->price)->toBe(12.5)
        ->and($partnerPrice->currency->code)->toBe('EUR');

    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => 13.25],
    ]);
    expect((float) $partnerPrice->refresh()->price)->toBe(13.25);

    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => null],
    ]);
    expect(MasterAssetPartnerPrice::where('master_asset_id', $this->masterAsset->id)->exists())->toBeFalse();
});

test('a partner price above the product price is accepted', function () {
    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => 99],
    ]);

    expect((float) MasterAssetPartnerPrice::where('master_asset_id', $this->masterAsset->id)->value('price'))->toBe(99.0);
});

test('a partner price is refused outside the Aromatics master shop', function () {
    $otherMasterShop  = partnerPricesMasterShop($this->group, 'ppother'.substr(uniqid(), -4));
    $otherMasterAsset = partnerPricesMasterAsset(partnerPricesFamily($otherMasterShop), 'PPO-'.uniqid(), 'GBP', 20, 8);

    UpdateMasterAssetPartnerPrices::make()->action($otherMasterAsset, [
        'partner_prices' => [$this->orgPartner->id => 12.5],
    ]);
})->throws(ValidationException::class);

test('a partner price is refused for an organisation that is not a partner of the seller', function () {
    $notPartner = OrgPartner::where('partner_id', '!=', $this->organisation->id)->first()
        ?? StoreOrgPartner::make()->action($this->organisation, $this->buyer);

    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$notPartner->id => 12.5],
    ]);
})->throws(ValidationException::class);

test('partner pricing gives the list price, the hub discount, the live rate and the same-code AW-class sibling', function () {
    $sibling = partnerPricesSibling($this->buyer, $this->code);

    $pricing = GetMasterAssetsPartnerPricing::run($this->aromaMasterShop, collect([$this->masterAsset]));
    $partner = collect($pricing['meta']['partners'])->firstWhere('org_partner_id', $this->orgPartner->id);
    $row     = $pricing['rows'][$this->masterAsset->id];

    expect($pricing['meta']['partner_discount'])->toBe(0.45)
        ->and($pricing['meta']['seller_code'])->toBe($this->organisation->code)
        ->and($partner['currency_code'])->toBe('EUR')
        ->and($partner['from_base_rate'])->toBeGreaterThan(0)
        ->and($row['list_price'])->toBe(20.0)
        ->and($row['prices'][$this->orgPartner->id])->toBeNull()
        ->and($row['sibling']['id'])->toBe($sibling->id)
        ->and($row['sibling']['update_route']['name'])->toBe('grp.models.master_asset.prices.update')
        ->and($row['sibling']['master_prices']['EUR']['value'])->toBe('21.06');
});

test('partner pricing has no sibling when no partner sells the code and nothing outside Aromatics', function () {
    $pricing = GetMasterAssetsPartnerPricing::run($this->aromaMasterShop, collect([$this->masterAsset]));
    expect($pricing['rows'][$this->masterAsset->id]['sibling'])->toBeNull();

    $otherMasterShop = partnerPricesMasterShop($this->group, 'ppother'.substr(uniqid(), -4));
    expect(GetMasterAssetsPartnerPricing::run($otherMasterShop, collect([$this->masterAsset])))->toBeNull();
});

test('the edit master product page shows partner prices under Pricing for Aromatics', function () {
    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => 12.5],
    ]);

    $response = get(route('grp.masters.master_shops.show.master_families.master_products.edit', [
        'masterShop'    => $this->aromaMasterShop->slug,
        'masterFamily'  => $this->masterAsset->masterFamily->slug,
        'masterProduct' => $this->masterAsset->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('EditModel')
            ->where('formData.blueprint.3.fields.partner_prices.type', 'partner_prices')
            ->where('formData.blueprint.3.fields.partner_prices.updateRoute.name', 'grp.models.master_asset.partner_prices.update')
            ->where('formData.blueprint.3.fields.partner_prices.row.prices.'.$this->orgPartner->id, 12.5)
            ->has('formData.blueprint.3.fields.master_prices')
            ->etc()
    );
});

test('the pricing tab of an Aromatics family carries partner pricing on each row', function () {
    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => 12.5],
    ]);

    $response = get(route('grp.masters.master_shops.show.master_families.master_products.index', [
        $this->aromaMasterShop->slug,
        $this->masterAsset->masterFamily->slug,
        'tab' => 'pricing',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('partnerPricingMeta.seller_code', $this->organisation->code)
            ->where('pricing.data.0.partner_pricing.list_price', 20)
            ->where('pricing.data.0.partner_pricing.prices.'.$this->orgPartner->id, 12.5)
            ->etc()
    );
});
