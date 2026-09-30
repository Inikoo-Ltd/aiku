<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\AskShopkeeperToUpdateProductUnit;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Language;
use App\Models\Tasks\StaffTask;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patch;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    actingAs($this->adminGuest->getUser());

    $this->masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'UL'.substr(uniqid(), -6),
        'name' => 'Unit Label Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'ULD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'ULF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->shop->updateQuietly([
        'master_shop_id' => $this->masterShop->id,
        'language_id'   => Language::where('code', 'en')->first()->id,
    ]);

    $this->bottle = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;
    $this->plug   = StoreTradeUnit::make()->action(group(), TradeUnit::factory()->definition())->id;

    $this->masterAsset = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'        => 'UL-AST',
        'name'        => 'unit label asset',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 10,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $this->bottle, 'quantity' => 6],
            ['id' => $this->plug, 'quantity' => 6],
        ],
    ]);

    [, $seed] = createProduct($this->shop);
    $this->product = StoreProduct::make()->action(
        $seed->family,
        array_merge(Product::factory()->definition(), [
            'code'  => 'ULP'.substr(uniqid(), -8),
            'price' => 10,
            'unit'  => 'piece',
        ])
    );
    $this->product->updateQuietly(['master_product_id' => $this->masterAsset->id]);
});

test('the unit label typed on a master reaches its products', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);

    expect($this->masterAsset->refresh()->unit)->toBe('bottle')
        ->and($this->product->refresh()->unit)->toBe('bottle');
});

test('a unit label saved with the composition survives it', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, [
        'unit'        => 'bottle',
        'trade_units' => [
            ['id' => $this->bottle, 'quantity' => 6],
            ['id' => $this->plug, 'quantity' => 6],
        ],
    ]);

    expect($this->masterAsset->refresh()->unit)->toBe('bottle')
        ->and((float) $this->masterAsset->units)->toBe(6.0);
});

test('a composition saved on its own still names the unit', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, [
        'trade_units' => [
            ['id' => $this->bottle, 'quantity' => 4],
            ['id' => $this->plug, 'quantity' => 4],
        ],
    ]);

    expect($this->masterAsset->refresh()->unit)->toBe('bundle')
        ->and((float) $this->masterAsset->units)->toBe(4.0);
});

test('the unit label is translated for shops in another language that follow the master', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('bouteille');

    $french = Language::where('code', 'fr')->first();
    $this->shop->updateQuietly([
        'language_id' => $french->id,
        'settings'    => array_merge($this->shop->settings ?? [], [
            'catalog' => ['product_follow_master' => true],
        ]),
    ]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);

    expect($this->product->refresh()->unit)->toBe('bouteille');
});

test('a shop that does not follow the master keeps its own unit label and its shopkeeper is asked to change it', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('bouteille');
    $shopkeeper = $this->user;
    $this->shop->updateQuietly([
        'language_id' => Language::where('code', 'fr')->first()->id,
        'settings'    => array_merge($this->shop->settings ?? [], [
            'catalog' => ['product_follow_master' => false],
        ]),
    ]);
    UpdateShop::make()->action($this->shop, ['shopkeeper_in_charge_id' => $shopkeeper->id]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);

    $task = StaffTask::where('data->kind', AskShopkeeperToUpdateProductUnit::TASK_KIND)->where('data->shop_id', $this->shop->id)->sole();

    expect($this->product->refresh()->unit)->toBe('piece')
        ->and($task->assignee_id)->toBe($shopkeeper->id)
        ->and($task->description)->toContain($this->product->code)
        ->and($task->description)->toContain('«bouteille»');
});

test('unit changes pile onto the shop open unit task, and a new task starts once it is done', function () {
    Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (string $text) => $text.'-fr');
    $this->shop->updateQuietly([
        'language_id' => Language::where('code', 'fr')->first()->id,
        'settings'    => array_merge($this->shop->settings ?? [], ['catalog' => ['product_follow_master' => false]]),
    ]);
    $unitTasks = fn () => StaffTask::where('data->kind', AskShopkeeperToUpdateProductUnit::TASK_KIND)->where('data->shop_id', $this->shop->id);
    $unitTasks()->delete();

    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'bottle']);
    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'jar']);

    $task = $unitTasks()->sole();
    expect($task->department)->toBe('products')
        ->and($task->description)->toContain('«bottle-fr»')
        ->and($task->description)->toContain('«jar-fr»');

    $task->update(['status' => StaffTaskStatusEnum::DONE]);
    UpdateMasterAsset::make()->action($this->masterAsset, ['unit' => 'box']);

    expect($unitTasks()->count())->toBe(2);
});

test('a master name change is copied to a shop that speaks the master language', function () {
    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);

    expect($this->product->refresh()->name)->toBe('lavender soap')
        ->and($this->product->is_name_reviewed)->toBeTrue();
});

test('a master name change never overwrites a shop that wrote its own translation', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    $ownName = $this->product->name;

    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);

    expect($this->product->refresh()->name)->toBe($ownName)
        ->and($this->product->is_name_reviewed)->toBeFalse();
});

test('the review flag is raised whatever the shop follow master setting says', function () {
    $this->shop->updateQuietly([
        'language_id' => Language::where('code', 'sk')->first()->id,
        'settings'    => array_merge($this->shop->settings ?? [], [
            'catalog' => ['product_follow_master' => true],
        ]),
    ]);
    $ownName = $this->product->name;

    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);

    expect($this->product->refresh()->name)->toBe($ownName)
        ->and($this->product->is_name_reviewed)->toBeFalse();
});

test('writing the shop text clears the review flag', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);
    expect($this->product->refresh()->is_name_reviewed)->toBeFalse();

    patch(route('grp.models.product.update', $this->product->id), ['name' => 'levanduľové mydlo'])
        ->assertRedirect();

    expect($this->product->refresh()->name)->toBe('levanduľové mydlo')
        ->and($this->product->is_name_reviewed)->toBeTrue();
});

test('a machine rewriting the text does not mark it reviewed', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);
    expect($this->product->refresh()->is_name_reviewed)->toBeFalse();

    UpdateProduct::make()->action($this->product, ['name' => 'strojový preklad']);

    expect($this->product->refresh()->name)->toBe('strojový preklad')
        ->and($this->product->is_name_reviewed)->toBeFalse();
});

test('a master translation reaches the shop map in every language but the shop own', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);

    $this->product->setTranslation('name_i8n', 'sk', 'levanduľové mydlo')->save();

    $this->masterAsset->setTranslation('name_i8n', 'en', 'lavender soap')
        ->setTranslation('name_i8n', 'sk', 'strojové mydlo')
        ->setTranslation('name_i8n', 'es', 'jabón de lavanda')
        ->save();

    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);

    $product = $this->product->refresh();

    expect($product->getTranslation('name_i8n', 'es'))->toBe('jabón de lavanda')
        ->and($product->getTranslation('name_i8n', 'sk'))->toBe('levanduľové mydlo')
        ->and($product->name)->not->toBe('lavender soap')
        ->and($product->is_name_reviewed)->toBeFalse();
});

test('part of a trade unit is only accepted when the trade unit is divisible', function () {
    $partOfABottle = ['trade_units' => [['id' => $this->bottle, 'quantity' => 0.01]]];

    expect(fn () => UpdateMasterAsset::make()->action($this->masterAsset, $partOfABottle))
        ->toThrow(ValidationException::class);

    TradeUnit::find($this->bottle)->update(['is_divisible' => true]);
    UpdateMasterAsset::make()->action($this->masterAsset, $partOfABottle);

    expect((float) $this->masterAsset->refresh()->tradeUnits->first()->pivot->quantity)->toBe(0.01);
});
