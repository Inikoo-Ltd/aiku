<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UI\GetMasterAssetsPartnerPricing;
use App\Actions\Masters\MasterAsset\UI\EditMasterProduct;
use App\Actions\Masters\MasterAsset\UI\GetMasterProductShowcase;
use App\Actions\Masters\MasterAsset\UI\GetMasterAssetMaterialCost;
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
            ->where('formData.blueprint.3.fields', fn ($fields) => array_key_first(collect($fields)->all()) === 'effective_cost')
            ->where('formData.blueprint.3.fields.effective_cost.type', 'input')
            ->where('formData.blueprint.3.fields.effective_cost.readonly', true)
            ->etc()
    );

    $otherMasterShop = partnerPricesMasterShop($this->group, 'ppother'.substr(uniqid(), -4));
    $otherAsset      = partnerPricesMasterAsset(partnerPricesFamily($otherMasterShop), 'PPO-'.strtoupper(substr(uniqid(), -6)), $this->shop->currency->code, 20, 8);

    expect(collect(EditMasterProduct::make()->getBlueprint($otherAsset))->pluck('fields')->collapse()->has('effective_cost'))->toBeFalse();
});

test('the Aromatics master product showcase links its partner prices to the Pricing section of the edit page', function () {
    $response = get(route('grp.masters.master_shops.show.master_products.show', [
        'masterShop'    => $this->aromaMasterShop->slug,
        'masterProduct' => $this->masterAsset->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('showcase.editPricingRoute.name', 'grp.masters.master_shops.show.master_products.edit')
            ->where('showcase.editPricingRoute.parameters.section', 3)
            ->where('showcase.editPricingRoute.parameters.masterProduct', $this->masterAsset->slug)
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

test('the Aromatics master product showcase gives each partner its price and the margin over the effective cost', function () {
    $this->masterAsset->updateQuietly(['effective_cost' => 5]);

    $partnerPricing = GetMasterProductShowcase::run($this->masterAsset->refresh())['partnerPricing'];
    $partner        = collect($partnerPricing['partners'])->firstWhere('code', $this->buyer->code);
    $fromBaseRate   = collect(GetMasterAssetsPartnerPricing::run($this->aromaMasterShop, collect([$this->masterAsset]))['meta']['partners'])
        ->firstWhere('org_partner_id', $this->orgPartner->id)['from_base_rate'];

    expect($partnerPricing['partner_discount'])->toBe(0.45)
        ->and($partner['currency_code'])->toBe('EUR')
        ->and($partner['price'])->toBe(round(20 * 0.55 * $fromBaseRate, 2))
        ->and($partner['is_custom'])->toBeFalse()
        ->and($partner['margin'])->toBe((int) round((11 - 5) / 11 * 100));

    UpdateMasterAssetPartnerPrices::make()->action($this->masterAsset, [
        'partner_prices' => [$this->orgPartner->id => 12.5],
    ]);

    $customPartner = collect(GetMasterProductShowcase::run($this->masterAsset->refresh())['partnerPricing']['partners'])->firstWhere('code', $this->buyer->code);

    expect($customPartner['price'])->toBe(12.5)
        ->and($customPartner['is_custom'])->toBeTrue();

    $otherMasterShop = partnerPricesMasterShop($this->group, 'ppother'.substr(uniqid(), -4));
    $otherAsset      = partnerPricesMasterAsset(partnerPricesFamily($otherMasterShop), 'PPO-'.strtoupper(substr(uniqid(), -6)), $this->shop->currency->code, 20, 8);

    expect(GetMasterProductShowcase::run($otherAsset)['partnerPricing'])->toBeNull();
});

test('the Aromatics master product showcase splits the effective cost by the recipe materials of the seller\'s artefact', function () {
    require_once __DIR__.'/../Support/ProductionHelpers.php';

    $production = \App\Models\Production\Production::where('organisation_id', $this->organisation->id)->first()
        ?? \App\Actions\Production\Production\StoreProduction::make()->action($this->organisation, ['code' => 'PPTP'.substr(uniqid(), -4), 'name' => 'Partner prices production']);

    $tradeUnit = \App\Actions\Goods\TradeUnit\StoreTradeUnit::make()->action($this->group, \App\Models\Goods\TradeUnit::factory()->definition());
    $this->masterAsset->tradeUnits()->attach($tradeUnit->id, ['quantity' => 2]);
    $this->masterAsset->updateQuietly(['effective_cost' => 5]);

    $artefact = \App\Actions\Production\Artefact\StoreArtefact::make()->action($production, [
        'code'  => 'PPTA-'.strtoupper(substr(uniqid(), -6)),
        'name'  => 'Partner prices artefact',
        'state' => \App\Enums\Production\Artefact\ArtefactStateEnum::ACTIVE->value,
    ]);
    $artefact->updateQuietly(['trade_unit_id' => $tradeUnit->id]);

    $rawMaterial = fn (string $code, float $unitCost) => \App\Actions\Production\RawMaterial\StoreRawMaterial::make()->action($production, [
        'type'        => \App\Enums\Production\RawMaterial\RawMaterialTypeEnum::CONSUMABLE->value,
        'state'       => \App\Enums\Production\RawMaterial\RawMaterialStateEnum::ORPHAN->value,
        'code'        => $code.'-'.strtoupper(substr(uniqid(), -5)),
        'description' => $code.' material',
        'unit'        => \App\Enums\Production\RawMaterial\RawMaterialUnitEnum::KILOGRAM->value,
        'unit_cost'   => $unitCost,
    ]);
    $oil    = $rawMaterial('OIL', 3);
    $bottle = $rawMaterial('BOT', 0.5);

    $recipeStep = \App\Models\Production\ArtefactManufactureTask::create([
        'artefact_id'         => $artefact->id,
        'manufacture_task_id' => \Tests\Feature\stepTestManufactureTask($production)->id,
        'position'            => 1,
        'units_per_artefact'  => 1,
    ]);
    foreach ([[$oil, 0.25], [$bottle, 1]] as [$material, $quantityPerUnit]) {
        \App\Models\Production\RecipeStepRawMaterial::create([
            'group_id'                     => $this->group->id,
            'organisation_id'              => $this->organisation->id,
            'artefact_manufacture_task_id' => $recipeStep->id,
            'raw_material_id'              => $material->id,
            'quantity_per_unit'            => $quantityPerUnit,
        ]);
    }

    $materialCost = GetMasterProductShowcase::run($this->masterAsset->refresh())['materialCost'];

    expect($materialCost['source'])->toBe('recipe')
        ->and($materialCost['organisation_code'])->toBe($this->organisation->code)
        ->and($materialCost['materials_total'])->toBe(2.5)
        ->and($materialCost['effective_cost'])->toBe(5.0)
        ->and($materialCost['rest'])->toBe(2.5)
        ->and($materialCost['overhead'])->toBe(round(GetMasterAssetsPartnerPricing::ESTIMATED_OVERHEAD_PER_UNIT * ((float) $this->masterAsset->units ?: 1), 4))
        ->and(collect($materialCost['materials'])->pluck('code')->all())->toBe([$oil->code, $bottle->code])
        ->and($materialCost['materials'][0]['quantity'])->toBe(0.5)
        ->and($materialCost['materials'][0]['cost'])->toBe(1.5)
        ->and($materialCost['materials'][0]['share'])->toBe(30.0)
        ->and($materialCost['materials'][1]['share'])->toBe(20.0)
        ->and($materialCost['materials'][0]['next_delivery'])->toBeNull();

    $artefactLink = GetMasterAssetMaterialCost::artefactIconLink($this->masterAsset);
    expect($artefactLink['route']['name'])->toBe('grp.org.productions.show.crafts.artefacts.show')
        ->and($artefactLink['route']['parameters'])->toBe([
            'organisation' => $this->organisation->slug,
            'production'   => $production->slug,
            'artefact'     => $artefact->slug,
        ]);

    $otherMasterShop = partnerPricesMasterShop($this->group, 'ppother'.substr(uniqid(), -4));
    $otherAsset      = partnerPricesMasterAsset(partnerPricesFamily($otherMasterShop), 'PPO-'.strtoupper(substr(uniqid(), -6)), $this->shop->currency->code, 20, 8);
    $otherAsset->tradeUnits()->attach($tradeUnit->id, ['quantity' => 1]);

    expect(GetMasterProductShowcase::run($otherAsset)['materialCost'])->toBeNull()
        ->and(GetMasterAssetMaterialCost::artefactIconLink($otherAsset))->toBeNull();
});

test('without a recipe or stock the Aromatics material bar still shows the whole effective cost, and nothing without a cost', function () {
    $this->masterAsset->updateQuietly(['effective_cost' => null]);

    expect(GetMasterAssetMaterialCost::run($this->masterAsset->refresh()))->toBeNull()
        ->and(GetMasterAssetMaterialCost::artefactIconLink($this->masterAsset))->toBeNull();

    $this->masterAsset->updateQuietly(['effective_cost' => 4]);
    $materialCost = GetMasterAssetMaterialCost::run($this->masterAsset->refresh());

    expect($materialCost['source'])->toBe('none')
        ->and($materialCost['materials'])->toBe([])
        ->and($materialCost['materials_total'])->toBe(0.0)
        ->and($materialCost['rest'])->toBe(4.0);
});

test('the Aroma shop product carries the master\'s Aromatics cards with its own price, and other shops do not', function () {
    $this->masterAsset->updateQuietly(['effective_cost' => 5]);
    [, $product] = createProduct($this->shop);
    $product->updateQuietly(['master_product_id' => $this->masterAsset->id, 'price' => 20, 'rrp' => 40, 'units' => 2]);

    $showcaseOf = fn () => get(route('grp.org.shops.show.catalogue.products.all_products.show', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
        'product'      => $product->slug,
        'tab'          => 'showcase',
    ]))->assertOk()->inertiaProps()['showcase'];

    $aromaPricing = $showcaseOf()['aromaPricing'];

    expect($aromaPricing['product'])->toEqual(['currency_code' => $product->currency->code, 'price' => 20.0, 'rrp' => 40.0, 'units' => 2.0])
        ->and($aromaPricing['partnerPricing']['partners'])->not->toBeEmpty()
        ->and($aromaPricing['pricingCosts'])->not->toBeNull()
        ->and($aromaPricing['competitorsRoute']['parameters']['tab'])->toBe('competitors')
        ->and($aromaPricing['editPricingRoute']['parameters']['section'])->toBe(3)
        ->and(array_keys($aromaPricing))->toContain('materialCost', 'competitors', 'majorCurrencies');

    $otherMasterShop = partnerPricesMasterShop($this->group, 'ppother'.substr(uniqid(), -4));
    $otherAsset      = partnerPricesMasterAsset(partnerPricesFamily($otherMasterShop), 'PPO-'.strtoupper(substr(uniqid(), -6)), $this->shop->currency->code, 20, 8);
    $product->updateQuietly(['master_product_id' => $otherAsset->id]);

    expect($showcaseOf()['aromaPricing'])->toBeNull();
});

test('the Aromatics master product showcase gives what the partners sell the same code at, with their margins', function () {
    $sibling = partnerPricesSibling($this->buyer, $this->code);

    $siblingPricing = GetMasterProductShowcase::run($this->masterAsset->refresh())['partnerPricing']['sibling'];
    $siblingRow     = GetMasterAssetsPartnerPricing::run($this->aromaMasterShop, collect([$this->masterAsset]))['rows'][$this->masterAsset->id]['sibling'];

    expect($siblingPricing['code'])->toBe($sibling->code)
        ->and(collect($siblingPricing['currencies'])->pluck('currency_code')->sort()->values()->all())
        ->toBe(collect($siblingRow['currencies'])->keys()->sort()->values()->all())
        ->and($siblingPricing['currencies'])->not->toBeEmpty();

    foreach ($siblingPricing['currencies'] as $currency) {
        $listed = data_get($siblingRow, 'master_prices.'.$currency['currency_code'].'.value');

        expect(array_keys($currency))->toBe(['currency_code', 'is_major', 'price', 'price_margin', 'rrp', 'rrp_margin'])
            ->and($currency['price'])->toBe($listed !== null ? (float) $listed : null)
            ->and($currency['is_major'])->toBe((bool) data_get($siblingRow, 'currencies.'.$currency['currency_code'].'.is_major'));
    }
});
