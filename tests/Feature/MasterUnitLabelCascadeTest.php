<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\AskShopkeeperToReviewMasterText;
use App\Actions\Catalogue\Product\FillShopProductTranslations;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Models\Catalogue\Shop;
use App\Actions\Catalogue\Product\AskShopkeeperToUpdateProductUnit;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Helpers\Translations\ChatGPT5Driver;
use App\Actions\Helpers\Translations\GetCatalogueTranslationBrief;
use App\Actions\Helpers\Translations\MineTranslationTerms;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Helpers\Translations\TranslateFromMaster;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Language;
use App\Models\Helpers\TranslationReview;
use App\Models\Helpers\TranslationTerm;
use App\Models\Tasks\StaffTask;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

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

test('a master text change asks the shopkeeper to review it, piles onto the open task and ticks off what was rewritten', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id, 'state' => ShopStateEnum::OPEN]);
    UpdateShop::make()->action($this->shop, ['shopkeeper_in_charge_id' => $this->user->id]);
    $reviewTasks = fn () => StaffTask::where('data->kind', AskShopkeeperToReviewMasterText::TASK_KIND)->where('data->shop_id', $this->shop->id);
    $reviewTasks()->delete();

    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);

    $task = $reviewTasks()->sole();
    expect($task->assignee_id)->toBe($this->user->id)
        ->and($task->model_type)->toBe('Product')
        ->and($task->data['subtasks'])->toBe([['title' => $this->product->code.' · Name', 'status' => 'todo']]);

    UpdateMasterAsset::make()->action($this->masterAsset, ['description' => 'Smells of lavender']);

    expect($reviewTasks()->sole()->data['subtasks'])->toBe([['title' => $this->product->code.' · Name, Description', 'status' => 'todo']]);

    patch(route('grp.models.product.update', $this->product->id), ['name' => 'levanduľové mydlo'])->assertRedirect();
    expect($reviewTasks()->sole()->data['subtasks'][0]['status'])->toBe('todo');

    patch(route('grp.models.product.update', $this->product->id), ['description' => 'Vonia levanduľou'])->assertRedirect();
    expect($reviewTasks()->sole()->data['subtasks'][0]['status'])->toBe('done');
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

test('a webmaster rating and then rewriting a machine translation is kept as one review', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    $this->product->updateQuietly(['name' => 'strojový preklad', 'is_name_reviewed' => false]);

    post(route('grp.models.product.translation_review.store', $this->product->id), ['field' => 'name', 'rating' => 2])
        ->assertSuccessful();
    patch(route('grp.models.product.update', $this->product->id), ['name' => 'levanduľové mydlo'])
        ->assertRedirect();

    $review = TranslationReview::where('model_type', 'Product')->where('model_id', $this->product->id)->sole();

    expect($review->field)->toBe('name')
        ->and($review->source_text)->toBe('unit label asset')
        ->and($review->machine_text)->toBe('strojový preklad')
        ->and($review->corrected_text)->toBe('levanduľové mydlo')
        ->and($review->rating)->toBe(2)
        ->and($review->language_id)->toBe($this->shop->language_id);
});

test('editing text a webmaster already reviewed is not taken for a machine correction', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    $this->product->updateQuietly(['name' => 'ľudský preklad', 'is_name_reviewed' => true]);

    patch(route('grp.models.product.update', $this->product->id), ['name' => 'iný ľudský preklad'])
        ->assertRedirect();

    expect(TranslationReview::where('model_type', 'Product')->where('model_id', $this->product->id)->exists())->toBeFalse();
});

test('the catalogue brief keeps brand names, follows the family and learns from corrections', function () {
    config(['auto-translations.keep_in_english' => ['Ancient Witch']]);
    Cache::forget('translation-brief:kept-names');
    $slovak = Language::where('code', 'sk')->first();
    $this->shop->updateQuietly(['language_id' => $slovak->id]);
    $this->product->updateQuietly(['name' => 'Sviečka Ancient Witch', 'is_name_reviewed' => true]);
    TranslationReview::create([
        'group_id'          => $this->shop->group_id,
        'organisation_id'   => $this->shop->organisation_id,
        'shop_id'           => $this->shop->id,
        'language_id'       => $slovak->id,
        'model_type'        => 'Product',
        'model_id'          => $this->product->id,
        'field'             => 'name',
        'source_text'       => 'Incense cones',
        'machine_text'      => 'Kadidlové šišky',
        'machine_text_hash' => md5('Kadidlové šišky'),
        'corrected_text'    => 'Vonné kužele',
    ]);

    $brief = GetCatalogueTranslationBrief::run($slovak, $this->product->family);

    expect($brief)->toContain('Ancient Witch')
        ->and($brief)->toContain('not automatically a brand')
        ->and($brief)->toContain('- unit label asset => Sviečka Ancient Witch')
        ->and($brief)->toContain('corrected: Vonné kužele');
});

test('the brief reaches the translator and is gone after the call', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"0":"Sviečka Ancient Witch"}']]]])]);
    $english = Language::where('code', 'en')->first();
    $slovak  = Language::where('code', 'sk')->first();

    $translated = Translate::make()->translateWith('Ancient Witch candle', $english, $slovak, 'sonnet', 'Never translate Ancient Witch.');

    expect($translated)->toBe('Sviečka Ancient Witch')
        ->and(app()->bound(ChatGPT5Driver::BRIEF))->toBeFalse();
    Http::assertSent(fn (Request $request) => str_starts_with($request['messages'][0]['content'], 'Never translate Ancient Witch.'));
});

test('a mined term is kept only when the webmasters really use it', function () {
    $slovak = Language::where('code', 'sk')->first();
    TranslationTerm::where('language_id', $slovak->id)->delete();
    foreach (['Incense Cones - Rose' => 'Vonné kužele - ruža', 'Incense Cones - Sage' => 'Vonných kužeľov - šalvia', 'Incense Cones - Lotus' => 'Vonné kužele - lotos'] as $english => $slovakName) {
        TranslationReview::create([
            'group_id'          => $this->shop->group_id,
            'organisation_id'   => $this->shop->organisation_id,
            'shop_id'           => $this->shop->id,
            'language_id'       => $slovak->id,
            'model_type'        => 'Product',
            'model_id'          => $this->product->id,
            'field'             => 'name',
            'source_text'       => $english,
            'machine_text'      => 'stroj '.$english,
            'machine_text_hash' => md5('stroj '.$english),
            'corrected_text'    => $slovakName,
        ]);
    }
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => '{"incense cones": "kadidlové šišky"}']]]])
        ->push(['choices' => [['message' => ['content' => '{"incense cones": "vonné kužele"}']]]])]);

    MineTranslationTerms::run($slovak, ['incense cones'], 6);
    expect(TranslationTerm::where('language_id', $slovak->id)->exists())->toBeFalse();

    MineTranslationTerms::run($slovak, ['incense cones'], 6);
    $term = TranslationTerm::where('language_id', $slovak->id)->sole();
    expect($term->target_term)->toBe('vonné kužele')
        ->and($term->support)->toBeGreaterThanOrEqual(3);

    $terms = GetCatalogueTranslationBrief::make()->termsFor($slovak, '<p>Backflow <b>incense cones</b> with holder</p>');
    expect($terms)->toContain('- incense cones => vonné kužele')
        ->and(GetCatalogueTranslationBrief::make()->termsFor($slovak, 'Lavender soap'))->toBe('');
});

test('translate all from master redoes only the product texts nobody reviewed', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    $this->masterAsset->updateQuietly(['description' => 'Lavender soap description']);
    $this->product->updateQuietly(['name' => 'starý strojový názov', 'is_name_reviewed' => false, 'description' => 'ľudský popis', 'is_description_reviewed' => true]);
    Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (string $text) => 'SK '.$text);

    post(route('grp.models.product.translate_from_master', $this->product->id))->assertRedirect();

    expect($this->product->refresh()->name)->toBe('SK unit label asset')
        ->and($this->product->is_name_reviewed)->toBeFalse()
        ->and($this->product->description)->toBe('ľudský popis');
});

test('a person saving a family text marks it reviewed and translate all leaves it alone', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'sk')->first()->id]);
    $family = $this->product->family;
    $this->masterFamily->updateQuietly(['description' => 'Soaps of every scent', 'description_title' => 'Our soaps']);
    $family->updateQuietly([
        'master_product_category_id'    => $this->masterFamily->id,
        'is_description_reviewed'       => false,
        'is_description_title_reviewed' => false,
        'is_name_reviewed'              => false,
    ]);

    patch(route('grp.models.product_category.update', $family->id), ['description' => 'Mydlá každej vône'])->assertRedirect();
    expect($family->refresh()->is_description_reviewed)->toBeTrue();

    $family->updateQuietly(['is_description_reviewed' => false]);
    DB::table('audits')->where('auditable_type', 'ProductCategory')->where('auditable_id', $family->id)->delete();
    DB::table('audits')->insert([
        'group_id'       => $family->group_id,
        'user_type'      => 'User',
        'user_id'        => $this->adminGuest->getUser()->id,
        'auditable_type' => 'ProductCategory',
        'auditable_id'   => $family->id,
        'event'          => 'updated',
        'tags'           => '[]',
        'old_values'     => '{}',
        'new_values'     => json_encode(['description' => 'Mydlá každej vône']),
        'url'            => 'https://app.aiku.test/models/product_category/'.$family->id.'/update',
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
    Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (string $text) => 'SK '.$text);

    $translated = TranslateFromMaster::run($family->refresh());

    expect($translated)->toBe(['name', 'description_title'])
        ->and($family->refresh()->description)->toBe('Mydlá každej vône')
        ->and($family->description_title)->toBe('SK Our soaps')
        ->and($family->is_description_title_reviewed)->toBeFalse();
});

test('the layout tells the page editor which language each shop writes in, for the font pickers', function () {
    $this->shop->updateQuietly(['language_id' => Language::where('code', 'pl')->first()->id]);

    $shops = App\Actions\SysAdmin\User\UI\GetUserOrganisationLayout::make()->getShops($this->adminGuest->getUser(), $this->shop->organisation);

    expect(collect($shops)->firstWhere('id', $this->shop->id)['language'])->toBe('pl');
});

test('a text the stronger model confirms stays as it is, like a size code, is translated once and then served from the cache', function () {
    $english = Language::where('code', 'en')->first();
    $slovak  = Language::where('code', 'sk')->first();
    $size    = 'M/L-'.Str::random(6);

    $translate = Translate::partialMock();
    $translate->shouldReceive('translateWith')->twice()->andReturn($size);
    $translate->shouldReceive('isBelowQuality')->once()->andReturn(true);

    expect(Translate::run($size, $english, $slovak, 'catalogue', brief: 'brief'))->toBe($size)
        ->and(Translate::run($size, $english, $slovak, 'catalogue', brief: 'brief'))->toBe($size);
});

test('the master meta description and share image reach the webpages of products in shops that follow the master', function () {
    createWebsite($this->shop);
    $webpage = $this->product->webpage ?? StoreProductWebpage::make()->action($this->product);

    $this->shop->updateQuietly([
        'language_id' => Language::where('code', 'fr')->first()->id,
        'settings'    => array_merge($this->shop->settings ?? [], ['catalog' => ['product_follow_master' => false]]),
    ]);

    Translate::mock()->shouldReceive('handle')->never();

    UpdateMasterAsset::make()->action($this->masterAsset, ['meta_description' => 'A lamp that glows like embers', 'seo_image_alt' => 'A glowing lamp']);

    expect($webpage->fresh()->description)->not->toBe('A lamp that glows like embers')
        ->and(data_get($webpage->fresh()->seo_data, 'image_alt'))->toBeNull();

    $this->shop->updateQuietly([
        'settings' => array_merge($this->shop->settings ?? [], ['catalog' => ['product_follow_master' => true]]),
    ]);

    Translate::mock()->shouldReceive('handle')->andReturnUsing(fn (?string $text) => 'fr: '.$text);

    UpdateMasterAsset::make()->action($this->masterAsset, [
        'meta_description' => 'A lamp that glows like hot embers',
        'seo_image'        => UploadedFile::fake()->image('lamp.png', 1200, 630),
    ]);

    $webpage->refresh();

    expect($webpage->description)->toBe('fr: A lamp that glows like hot embers')
        ->and($webpage->seo_image_id)->toBe($this->masterAsset->refresh()->seo_image_id)
        ->and($webpage->seo_image_url)->toBeNull();

    UpdateMasterAsset::make()->action($this->masterAsset, ['seo_image_url' => 'https://cdn.example.com/lamp.png']);

    expect($webpage->fresh()->seo_image_url)->toBe('https://cdn.example.com/lamp.png');

    UpdateMasterAsset::make()->action($this->masterAsset, ['seo_image_alt' => 'A lamp glowing on a desk']);

    expect(data_get($webpage->fresh()->seo_data, 'image_alt'))->toBe('fr: A lamp glowing on a desk');
});

test('the master product edit information page edits the meta description and share image', function () {
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    get(route('grp.masters.master_shops.show.master_products.edit_information', [$this->masterShop->slug, $this->masterAsset->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Masters/EditMasterProductInformation')
            ->where('seo.meta_description', '')
            ->where('seo.seo_image', null)
            ->where('seo.seo_image_url', null)
            ->where('seo.seo_image_alt', null)
            ->etc());

    post(route('grp.models.master_asset.information.update', $this->masterAsset->id), [
        '_method'          => 'patch',
        'meta_description' => 'A lamp that glows like embers',
        'seo_image_url'    => 'cdn.example.com/lamp.png',
        'seo_image_alt'    => 'A glowing lamp',
    ])->assertSessionHasNoErrors();

    $this->masterAsset->refresh();

    expect($this->masterAsset->meta_description)->toBe('A lamp that glows like embers')
        ->and($this->masterAsset->seo_image_url)->toBe('https://cdn.example.com/lamp.png')
        ->and($this->masterAsset->seo_image_alt)->toBe('A glowing lamp');

    post(route('grp.models.master_asset.information.update', $this->masterAsset->id), [
        '_method'   => 'patch',
        'seo_image' => UploadedFile::fake()->image('lamp.png', 1200, 630),
    ])->assertSessionHasNoErrors();

    $this->masterAsset->refresh();

    expect($this->masterAsset->seo_image_id)->not->toBeNull()
        ->and($this->masterAsset->seo_image_url)->toBeNull();
});

test('the master edit information page shows the trade unit data but never writes it, the trade unit is shared with other masters', function () {
    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);

    $loaf = TradeUnit::find($this->bottle);
    $loaf->update(['is_divisible' => true, 'marketing_weight' => 1200, 'gross_weight' => 1200]);

    $slice = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'        => 'UL-SLICE',
        'name'        => 'soap slice',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 3,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $loaf->id, 'quantity' => 0.1],
        ],
    ]);

    get(route('grp.masters.master_shops.show.master_products.edit_information', [$this->masterShop->slug, $slice->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Masters/EditMasterProductInformation')
            ->where('information.marketing_weight', 120)
            ->where('information.gross_weight', 120)
            ->where('information.trade_unit.code', $loaf->code)
            ->etc());

    post(route('grp.models.master_asset.information.update', $slice->id), [
        '_method'          => 'patch',
        'name'             => 'soap slice renamed',
        'marketing_weight' => 115,
        'gross_weight'     => 130,
    ])->assertSessionHasNoErrors();

    expect($slice->refresh()->name)->toBe('soap slice renamed')
        ->and($slice->marketing_weight)->toBe(120)
        ->and($loaf->refresh()->marketing_weight)->toBe(1200)
        ->and($loaf->gross_weight)->toBe(1200);
});

test('changing the units of a single trade unit master recalculates its net weight', function () {
    $loaf = TradeUnit::find($this->bottle);
    $loaf->update(['is_divisible' => true, 'marketing_weight' => 1200]);

    $slice = StoreMasterAsset::make()->action($this->masterFamily, [
        'code'        => 'UL-SLICE2',
        'name'        => 'soap slice',
        'is_main'     => true,
        'type'        => MasterAssetTypeEnum::PRODUCT,
        'price'       => 3,
        'stocks'      => [],
        'trade_units' => [
            ['id' => $loaf->id, 'quantity' => 0.1],
        ],
    ]);

    expect($slice->refresh()->marketing_weight)->toBe(120);

    UpdateMasterAsset::make()->action($slice, ['has_independent_units' => true, 'units' => 2]);

    expect($slice->refresh()->marketing_weight)->toBe(1200);
});

function fillTranslationsFixture(object $test, float $siblingUnits = 1): Product
{
    Config::set('services.openrouter.api_key', 'test-key');
    Http::fake(['openrouter.ai/*' => function (Request $request) use ($test) {
        $texts = collect(json_decode($request['messages'][1]['content'], true))
            ->map(fn (array $input) => ($test->aiPrefix ?? 'DE ').$input['translate']);

        return Http::response(['choices' => [['message' => ['content' => json_encode($texts)]]], 'usage' => ['cost' => 0.001]]);
    }]);

    $test->product->updateQuietly(['state' => 'active', 'units' => 1, 'name' => 'Lavender soap 100g', 'description' => '<p>Lovely soap</p>', 'name_i8n' => ['en' => 'Lavender soap 100g'], 'data' => []]);
    $test->product->tradeUnits()->sync([$test->bottle => ['quantity' => 1]]);

    $test->germanShop ??= StoreShop::make()->action($test->organisation, array_merge(Shop::factory()->definition(), ['language_id' => Language::where('code', 'de')->first()->id]));
    $sibling = $test->product->replicate();
    $sibling->fill(['shop_id' => $test->germanShop->id, 'slug' => 'de-'.Str::random(10), 'units' => $siblingUnits, 'name' => 'Lavendelseife 100g en gros', 'description' => '<p>Schöne Seife en gros</p>', 'master_product_id' => null, 'data' => []]);
    $sibling->saveQuietly();
    $sibling->tradeUnits()->sync([$test->bottle => ['quantity' => $siblingUnits]]);

    return $sibling;
}

function fillTranslations(object $test, bool $apply = true, bool $force = false): array
{
    return FillShopProductTranslations::run($test->shop, Language::where('code', 'de')->first(), $test->germanShop, $apply, $force);
}

test('fill translations copies the same pack text of the sibling shop as it is, without the AI, onto the product and never the master', function () {
    $sibling = fillTranslationsFixture($this);

    fillTranslations($this);

    $product = $this->product->refresh();
    Http::assertNotSent(fn (Request $request) => str_contains($request->body(), 'Lavendelseife') || str_contains($request->body(), 'Seife'));
    expect($product->getTranslation('name_i8n', 'de', false))->toBe('Lavendelseife 100g en gros')
        ->and($product->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife en gros</p>')
        ->and($product->data['translation_sources']['de']['model'])->toBeNull()
        ->and($product->getTranslation('name_i8n', 'en', false))->toBe('Lavender soap 100g')
        ->and($product->name)->toBe('Lavender soap 100g')
        ->and($product->data['translation_sources']['de']['fields']['name'])->toBe(FillShopProductTranslations::COPIED)
        ->and($product->data['translation_sources']['de']['from_product_id'])->toBe($sibling->id)
        ->and(data_get($this->masterAsset->refresh()->name_i8n, 'de'))->toBeEmpty();
});

test('fill translations machine translates a product whose sibling is a wholesale pack', function () {
    fillTranslationsFixture($this, siblingUnits: 6);

    fillTranslations($this);

    $product = $this->product->refresh();
    expect($product->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soap 100g')
        ->and($product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::MACHINE)
        ->and($product->data['translation_sources']['de']['from_product_id'])->toBeNull();
});

test('fill translations in dry run writes nothing', function () {
    fillTranslationsFixture($this);

    $result = fillTranslations($this, apply: false);

    expect($this->product->refresh()->getTranslation('name_i8n', 'de', false))->toBe('')
        ->and($this->product->data)->not->toHaveKey('translation_sources')
        ->and($result['stats']['copied'])->toBeGreaterThan(0)
        ->and($result['cost'])->toBe(0.0);
});

test('fill translations resumes past done products and never overwrites german a person wrote', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    fillTranslations($this);
    $handWritten = $this->product->replicate();
    $handWritten->fill(['slug' => 'hw-'.Str::random(10), 'code' => 'HW'.Str::random(8), 'master_product_id' => null, 'name' => 'Handmade soap', 'name_i8n' => ['de' => 'Von Hand geschrieben'], 'data' => []]);
    $handWritten->saveQuietly();
    $this->aiPrefix = 'NEU ';
    $requestsFor = fn (string $text) => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', $text))->count();

    $before = $requestsFor('Lavender soap 100g');

    fillTranslations($this);
    expect($requestsFor('Lavender soap 100g'))->toBe($before);

    fillTranslations($this, force: true);
    expect($requestsFor('Lavender soap 100g'))->toBeGreaterThan($before)
        ->and($requestsFor('Handmade soap'))->toBe(0)
        ->and($this->product->refresh()->getTranslation('name_i8n', 'de', false))->toBe('NEU Lavender soap 100g')
        ->and($handWritten->refresh()->getTranslation('name_i8n', 'de', false))->toBe('Von Hand geschrieben');

    $this->product->setTranslation('name_i8n', 'de', 'Korrigiert')->save();
    $this->aiPrefix = 'NOCHMAL ';
    fillTranslations($this, force: true);

    expect($this->product->refresh()->getTranslation('name_i8n', 'de', false))->toBe('Korrigiert')
        ->and($this->product->getTranslation('description_i8n', 'de', false))->toBe('NOCHMAL <p>Lovely soap</p>');
});

test('fill translations marks a rejected product, leaves it alone on later runs and tries it again only with retry failed', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    $this->aiPrefix = '<b>';
    $requests       = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', 'Lavender soap 100g'))->count();

    $result     = fillTranslations($this);
    $afterFirst = $requests();

    expect($result['stats']['failed'])->toBe(1)
        ->and($this->product->refresh()->getTranslation('name_i8n', 'de', false))->toBe('')
        ->and($this->product->data['translation_sources']['de']['failed'])->toContain('HTML tags changed');

    fillTranslations($this);
    expect($requests())->toBe($afterFirst);

    $this->aiPrefix = 'DE ';
    FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, true, false, null, 50, null, null, true);

    expect($this->product->refresh()->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soap 100g')
        ->and($this->product->data['translation_sources']['de'])->not->toHaveKey('failed')
        ->and((new FillShopProductTranslations())->invalidReason(['name' => ['translate' => '1,000 beads']], ['name' => '1.000 Perlen']))->toBeNull()
        ->and((new FillShopProductTranslations())->invalidReason(['name' => ['translate' => '1,000 beads']], ['name' => 'Perlen']))->toContain('numbers lost');
});

test('fill translations lets inline emphasis move, merge or drop but keeps block structure, nesting, links and images', function () {
    $check  = fn (string $source, string $output) => (new FillShopProductTranslations())->invalidReason(['description' => ['translate' => $source]], ['description' => $output]);
    $source = '<p><strong>AWGifts - Dropshipping</strong> presents<strong>&nbsp;Stamford Premium Incense</strong> with a higher fragrance load.</p><p>- <strong>hand rolled top quality</strong> incense</p>';

    expect($check($source, '<p><strong>AWGifts - Dropshipping präsentiert Stamford Premium Räucherstäbchen</strong> mit mehr Duft.</p><p>- handgerollte Räucherstäbchen bester Qualität</p>'))->toBeNull()
        ->and($check($source, '<p><strong>AWGifts</strong> präsentiert <strong>Stamford</strong> mit <em>mehr</em> Duft.</p><p>- <strong>handgerollt</strong></p>'))->toContain('HTML tags changed')
        ->and($check($source, '<p><strong>AWGifts</strong> präsentiert <strong>Stamford</strong> <strong>Premium</strong> mit mehr Duft.</p><p>- <strong>handgerollt</strong></p>'))->toContain('HTML tags changed')
        ->and($check($source, '<p><strong>AWGifts präsentiert Stamford mit mehr Duft.</p><p>- handgerollt</strong></p>'))->toContain('HTML tags changed')
        ->and($check($source, '<p><strong>AWGifts</strong> präsentiert Stamford mit mehr Duft.</p>- handgerollt'))->toContain('HTML tags changed')
        ->and($check('<p>See <a href="https://www.aw-dropship.eu/ish">holders</a></p>', '<p>Siehe <a href="https://www.aw-dropship.eu/ish">Halter</a></p>'))->toBeNull()
        ->and($check('<p>See <a href="https://www.aw-dropship.eu/ish">holders</a></p>', '<p>Siehe <a href="https://www.aw-dropship.eu/de/ish">Halter</a></p>'))->toContain('HTML tags changed')
        ->and($check('<p>Fine</p><img src="a.jpg" alt="x">', '<p>Gut</p>'))->toContain('HTML tags changed');
});

test('fill translations reads a comma list of sizes as a list, and still rejects a size that is dropped', function () {
    $check = fn (string $source, string $output) => (new FillShopProductTranslations())->invalidReason(['name' => ['translate' => $source]], ['name' => $output]);

    expect($check('Tamper Evident Cap & Dropper for 10,50,100ml', 'Originalitätsverschluss mit Pipette für 10, 50, 100 ml'))->toBeNull()
        ->and($check('Tamper Evident Cap & Dropper for 10,50,100ml', 'Originalitätsverschluss mit Pipette für 10, 50 ml'))->toContain('numbers lost')
        ->and($check('Pack of 1,000 beads', 'Packung mit 1000 Perlen'))->toBeNull()
        ->and($check('Lrg Natural 6oz Cotton Bag 38x42cm', 'Große Baumwolltasche 170 g 38x42 cm'))->toContain('numbers lost (6)');
});

test('fill translations rejects unfinished tags, added attributes, emphasis wrapping blocks and self closing emphasis', function () {
    $check = fn (string $source, string $output) => (new FillShopProductTranslations())->invalidReason(['description' => ['translate' => $source]], ['description' => $output]);

    expect($check('<p>Fine</p>', '<p>Gut</p><img src=x onerror=alert(1) '))->toContain('HTML tags changed')
        ->and($check('<p>Fine <b>soap</b></p>', '<p>Gut</p><b'))->toContain('HTML tags changed')
        ->and($check('<p>Fine soap</p>', '<p onclick="alert(1)">Gute Seife</p>'))->toContain('HTML tags changed')
        ->and($check('<p>Fine <strong>soap</strong></p>', '<p>Gute <strong onmouseover="alert(1)">Seife</strong></p>'))->toContain('HTML tags changed')
        ->and($check('<p>Fine <span style="color:red">soap</span></p>', '<p>Gute <span style="color:red">Seife</span></p>'))->toBeNull()
        ->and($check('<p>Fine <span style="color:red">soap</span></p>', '<p>Gute <span style="color:blue">Seife</span></p>'))->toContain('HTML tags changed')
        ->and($check('<p>A <strong>fine</strong> soap</p><p>Lasts</p>', '<strong><p>Eine feine Seife</p><p>Hält</p></strong>'))->toContain('HTML tags changed')
        ->and($check('<p>A <strong>fine</strong> soap</p>', '<p>Eine <strong/>feine Seife</p>'))->toContain('HTML tags changed')
        ->and($check('<p>A <strong>fine</strong> soap<br />now</p>', '<p>Eine feine <strong>Seife</strong><br />jetzt</p>'))->toBeNull();
});
