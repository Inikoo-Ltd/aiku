<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\AskShopkeeperToReviewMasterText;
use App\Actions\Catalogue\Product\FillShopProductTranslations;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateProductsWithNoDescription;
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
use App\Enums\Catalogue\Product\ProductStateEnum;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

test('a master translation never overwrites a language the shop website shows, which staff may have corrected', function () {
    $this->shop->updateQuietly([
        'language_id' => Language::where('code', 'sk')->first()->id,
        'settings'    => array_merge($this->shop->settings ?? [], ['preview_languages' => [Language::where('code', 'de')->first()->id]]),
    ]);

    $this->product->setTranslation('name_i8n', 'de', 'Lavendelseife von Hand')->save();

    $this->masterAsset->setTranslation('name_i8n', 'en', 'lavender soap')
        ->setTranslation('name_i8n', 'de', 'Lavendel Seife')
        ->setTranslation('name_i8n', 'es', 'jabón de lavanda')
        ->save();

    UpdateMasterAsset::make()->action($this->masterAsset, ['name' => 'lavender soap']);

    $product = $this->product->refresh();

    expect($product->getTranslation('name_i8n', 'de'))->toBe('Lavendelseife von Hand')
        ->and($product->getTranslation('name_i8n', 'es'))->toBe('jabón de lavanda');
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
        if ($test->aiDown ?? false) {
            return Http::response('down', 503);
        }
        $texts = collect(json_decode($request['messages'][1]['content'], true))
            ->map(fn (array $input, string $field) => isset($input['clean'])
                ? (is_array($test->cleanAnswer ?? null) ? ($test->cleanAnswer[$field] ?? $input['clean']) : ($test->cleanAnswer ?? str_replace(' en gros', '', $input['clean'])))
                : ($test->aiPrefix ?? 'DE ').$input['translate'])
            ->map(fn (string $text) => ($test->aiEchoesWrapper ?? false) ? ['translate' => $text] : $text);

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

test('fill translations cleans copied descriptions of reseller wording, leaves names and hand written text alone and never sends a cleaned one again', function () {
    fillTranslationsFixture($this);
    fillTranslations($this);
    $clean    = fn () => FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, true, false, null, 50, null, null, false, true);
    $requests = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"clean"'))->count();

    $result = $clean();

    $product = $this->product->refresh();
    expect($result['stats']['cleaned'])->toBe(1)
        ->and($product->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife</p>')
        ->and($product->getTranslation('name_i8n', 'de', false))->toBe('Lavendelseife 100g en gros')
        ->and($product->data['translation_sources']['de']['fields'])->toMatchArray(['name' => FillShopProductTranslations::COPIED, 'description' => FillShopProductTranslations::CLEANED])
        ->and(data_get($this->masterAsset->refresh()->description_i8n, 'de'))->toBeEmpty();

    $afterFirst = $requests();
    $clean();
    fillTranslations($this, force: true);
    expect($requests())->toBe($afterFirst)
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife</p>');
});

test('fill translations keeps the copy when the cleaned answer adds markup, a number or guts the text, and does not try that product again', function (string $answer, string $reason) {
    fillTranslationsFixture($this);
    fillTranslations($this);
    $this->cleanAnswer = $answer;
    $clean             = fn () => FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, true, false, null, 50, null, null, false, true);

    $result = $clean();

    $product = $this->product->refresh();
    expect($result['stats']['failed'])->toBe(1)
        ->and($product->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife en gros</p>')
        ->and($product->data['translation_sources']['de']['clean_failed'])->toContain($reason)
        ->and($product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::COPIED)
        ->and($clean()['stats']['failed'])->toBe(0);
})->with([
    'added tag'       => ['<p>Schöne Seife</p><img src="x">', 'HTML tags changed'],
    'added attribute' => ['<p onclick="x()">Schöne Seife</p>', 'HTML tags changed'],
    'unclosed tag'    => ['<p>Schöne Seife</p><img src=x ', 'HTML tags changed'],
    'added number'    => ['<p>Schöne Seife 500 g</p>', 'numbers added'],
    'rewritten'       => ['<p>Tolles feines mildes Stück hier</p>', 'rewritten'],
    'comment opened'  => ['<p>Schöne Seife</p><!--', 'HTML tags changed'],
]);

test('fill translations does not clean a copied description a person corrected', function () {
    fillTranslationsFixture($this);
    fillTranslations($this);
    $rejected = $this->product->replicate();
    $rejected->fill(['slug' => 'rj-'.Str::random(10), 'code' => 'RJ'.Str::random(8), 'master_product_id' => null, 'data' => ['translation_sources' => ['de' => ['failed' => 'name: empty', 'fields' => [], 'hashes' => []]]]]);
    $rejected->saveQuietly();
    $this->product->setTranslation('description_i8n', 'de', '<p>Von Hand korrigiert en gros</p>')->save();

    FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, true, false, null, 50, null, null, false, true);

    expect($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('<p>Von Hand korrigiert en gros</p>');
});

test('fill translations does not mark a copied description when the cleaning call itself fails, and a forced run keeps the clean marks', function () {
    fillTranslationsFixture($this);
    fillTranslations($this);
    $clean = fn () => FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, true, false, null, 50, null, null, false, true);

    $this->aiDown = true;
    expect($clean()['stats']['failed'])->toBe(1)
        ->and($this->product->refresh()->data['translation_sources']['de'])->not->toHaveKey('clean_failed');

    $this->aiDown      = false;
    $this->cleanAnswer = '<p>Tolles feines mildes Stück hier</p>';
    $clean();
    fillTranslations($this, force: true);

    expect($this->product->refresh()->data['translation_sources']['de']['clean_failed'])->toContain('rewritten')
        ->and($this->product->data['translation_sources']['de']['from_product_id'])->not->toBeNull();

    $long = '<p>Großhandel Seife für Ihr Geschäft.</p><p>'.str_repeat('Diese Seife duftet nach Lavendel und pflegt die Haut. ', 6).'</p>';
    $kept = '<p>'.str_repeat('Diese Seife duftet nach Lavendel und pflegt die Haut. ', 6).'</p>';
    $check = fn (string $answer) => (new FillShopProductTranslations())->invalidReason(['description' => ['clean' => $long]], ['description' => $answer]);
    expect($check($kept))->toBeNull()
        ->and($check('<p>Diese Seife duftet nach Lavendel und pflegt die Haut.</p>'))->toContain('too much removed');
});

function plainOtherShopProducts(object $test): void
{
    Product::where('shop_id', $test->shop->id)->whereKeyNot($test->product->id)->update(['description' => '<p>Plain</p>', 'description_title' => null, 'description_extra' => null, 'data' => '{}']);
}

function fillShopTextMode(object $test, string $mode, bool $apply = true, bool $retryFailed = false): array
{
    $language = Language::where('code', $mode === 'clean' ? 'en' : 'de')->first();

    return FillShopProductTranslations::run($test->shop, $language, $test->germanShop, $apply, false, null, 50, null, null, $retryFailed, false, $mode === 'clean', $mode === 'redo');
}

test('fill translations leaves customer bundles out of every mode, but fills a product exclusive to a customer', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    $own = collect([['is_bundle' => true, 'code' => 'B-1-'.Str::random(4)], ['is_bundle' => false, 'code' => 'EX'.Str::random(6)]])
        ->map(function (array $attributes) {
            $product = $this->product->replicate();
            $product->fill($attributes + ['slug' => 'own-'.Str::random(10), 'master_product_id' => null, 'exclusive_for_customer_id' => 1, 'name' => 'Customer own '.$attributes['code'], 'description' => '<p>Wholesale for your shop</p>', 'data' => []]);
            $product->saveQuietly();

            return $product;
        });
    [$bundle, $exclusive] = $own->all();
    $requestsFor          = fn (string $text) => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', $text))->count();

    fillTranslations($this);
    fillTranslations($this, force: true);
    fillShopTextMode($this, 'clean');
    fillShopTextMode($this, 'redo');

    expect($requestsFor($bundle->code))->toBe(0)
        ->and($bundle->refresh()->data)->toBeEmpty()
        ->and($requestsFor($exclusive->code))->toBeGreaterThan(0)
        ->and($exclusive->refresh()->getTranslation('name_i8n', 'de', false))->toBe('DE Customer own '.$exclusive->code);
});

test('fill translations pins that the product update copies a shop description into the master english translation, which the shop text cleanup must avoid', function () {
    UpdateProduct::make()->action($this->product->refresh(), ['description' => '<p>Written in the shop</p>'], strict: false);

    expect($this->masterAsset->refresh()->getTranslation('description_i8n', 'en', false))->toBe('<p>Written in the shop</p>');
});

test('fill translations cleans the shop own description on the product only, audited, keeps the german, the master and other shops, and never sends a cleaned field again', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    plainOtherShopProducts($this);
    $this->product->updateQuietly(['description' => '<p>Lovely soap. Your customers will love it.</p>', 'description_title' => 'Soap for your shop']);
    fillTranslations($this);
    $germanHash = $this->product->refresh()->data['translation_sources']['de']['hashes']['description'];
    $untouched  = $this->product->replicate();
    $untouched->fill(['slug' => 'un-'.Str::random(10), 'code' => 'UN'.Str::random(8), 'master_product_id' => null, 'description' => '<p>Plain soap</p>', 'data' => ['translation_sources' => ['de' => ['failed' => 'name: empty', 'fields' => [], 'hashes' => []]]]]);
    $untouched->saveQuietly();
    $otherShop    = StoreShop::make()->action($this->organisation, array_merge(Shop::factory()->definition(), ['language_id' => Language::where('code', 'en')->first()->id]));
    $otherEnglish = $this->product->replicate();
    $otherEnglish->fill(['shop_id' => $otherShop->id, 'slug' => 'oe-'.Str::random(10), 'description' => '<p>Lovely soap. Your customers will love it.</p>', 'data' => []]);
    $otherEnglish->saveQuietly();
    $this->cleanAnswer = ['description' => '<p>Lovely soap.</p>'];
    $requests          = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"clean"'))->count();

    $dry = fillShopTextMode($this, 'clean', apply: false);
    expect($dry['stats']['cleaned'])->toBe(1)
        ->and($this->product->refresh()->description)->toBe('<p>Lovely soap. Your customers will love it.</p>');

    $result = fillShopTextMode($this, 'clean');

    $product = $this->product->refresh();
    expect($result['stats']['cleaned'])->toBe(1)
        ->and($product->description)->toBe('<p>Lovely soap.</p>')
        ->and($product->getTranslation('description_i8n', 'en', false))->toBe('<p>Lovely soap.</p>')
        ->and($product->name)->toBe('Lavender soap 100g')
        ->and($product->description_title)->toBe('Soap for your shop')
        ->and($product->data['translation_sources']['en']['fields'])->toEqual(['description_title' => FillShopProductTranslations::SHOP_TEXT_CHECKED, 'description' => FillShopProductTranslations::SHOP_TEXT_CLEANED])
        ->and($product->data['translation_sources']['en']['hashes']['description'])->toBe(md5('<p>Lovely soap.</p>'))
        ->and($product->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Lovely soap. Your customers will love it.</p>')
        ->and($product->data['translation_sources']['de']['hashes']['description'])->toBe($germanHash)
        ->and($untouched->refresh()->description)->toBe('<p>Plain soap</p>')
        ->and($product->audits()->get()->contains(fn ($audit) => ($audit->new_values['description'] ?? null) === '<p>Lovely soap.</p>'))->toBeTrue()
        ->and($this->masterAsset->refresh()->getTranslation('description_i8n', 'en', false))->toBeEmpty()
        ->and($otherEnglish->refresh()->description)->toBe('<p>Lovely soap. Your customers will love it.</p>');

    $afterFirst = $requests();
    fillShopTextMode($this, 'clean');
    expect($requests())->toBe($afterFirst);
});

test('fill translations rejects a cleaned shop text that loses a number, marks only that field and still cleans the others', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    plainOtherShopProducts($this);
    $this->product->updateQuietly(['description' => '<p>Box of 12 soaps for your shop.</p>', 'description_extra' => '<p>Your customers will love it. Lasts long.</p>']);
    $this->cleanAnswer = ['description' => '<p>Box of soaps.</p>', 'description_extra' => '<p>Lasts long.</p>'];

    $first = fillShopTextMode($this, 'clean');

    $marks = $this->product->refresh()->data['translation_sources']['en'];
    expect($first['stats']['failed'])->toBe(1)
        ->and($marks['fields'])->toBe(['description' => FillShopProductTranslations::SHOP_TEXT_FAILED])
        ->and($marks['failures']['description'])->toContain('numbers lost (12)')
        ->and($this->product->description)->toBe('<p>Box of 12 soaps for your shop.</p>');

    $second = fillShopTextMode($this, 'clean');

    expect($second['stats']['cleaned'])->toBe(1)
        ->and($this->product->refresh()->description_extra)->toBe('<p>Lasts long.</p>')
        ->and($this->product->description)->toBe('<p>Box of 12 soaps for your shop.</p>')
        ->and(fillShopTextMode($this, 'clean')['stats'])->toMatchArray(['cleaned' => 0, 'failed' => 0, 'unchanged' => 0]);
});

test('fill translations redoes only the machine german of fields whose shop text was cleaned afterwards, never the name, once, and a rejected redo only with retry failed', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    plainOtherShopProducts($this);
    $this->product->updateQuietly(['description' => '<p>Lovely soap. Your customers will love it.</p>']);
    fillTranslations($this);
    $this->travel(1)->minutes();
    $this->cleanAnswer = ['description' => '<p>Lovely soap.</p>'];
    fillShopTextMode($this, 'clean');
    $this->travel(1)->minutes();
    $translations = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"translate"'))->count();

    $dry = fillShopTextMode($this, 'redo', apply: false);
    expect($dry['stats']['machine'])->toBe(1)
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Lovely soap. Your customers will love it.</p>');

    $this->aiPrefix = '<b>';
    expect(fillShopTextMode($this, 'redo')['stats']['failed'])->toBe(1)
        ->and($this->product->refresh()->data['translation_sources']['de']['redo']['description'])->toBe(FillShopProductTranslations::REDO_FAILED)
        ->and($this->product->data['translation_sources']['de'])->not->toHaveKey('failed');
    $afterFailure = $translations();
    fillShopTextMode($this, 'redo');
    expect($translations())->toBe($afterFailure);

    $this->aiPrefix = 'NEU ';
    fillShopTextMode($this, 'redo', retryFailed: true);

    $product = $this->product->refresh();
    expect($product->getTranslation('description_i8n', 'de', false))->toBe('NEU <p>Lovely soap.</p>')
        ->and($product->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soap 100g')
        ->and($product->data['translation_sources']['de']['hashes']['description'])->toBe(md5('NEU <p>Lovely soap.</p>'))
        ->and($product->data['translation_sources']['de']['redo']['description'])->toBe(FillShopProductTranslations::REDONE);

    $afterRedo = $translations();
    fillShopTextMode($this, 'redo');
    fillShopTextMode($this, 'redo', retryFailed: true);
    expect($translations())->toBe($afterRedo);
});

test('fill translations redo records a german a person corrected and comes back empty after', function () {
    fillTranslationsFixture($this, siblingUnits: 6);
    plainOtherShopProducts($this);
    $this->product->updateQuietly(['description' => '<p>Lovely soap. Your customers will love it.</p>']);
    fillTranslations($this);
    $this->product->refresh()->setTranslation('description_i8n', 'de', '<p>Von Hand</p>')->save();
    $this->travel(1)->minutes();
    $this->cleanAnswer = ['description' => '<p>Lovely soap.</p>'];
    fillShopTextMode($this, 'clean');
    $this->travel(1)->minutes();

    fillShopTextMode($this, 'redo');

    expect($this->product->refresh()->data['translation_sources']['de']['redo']['description'])->toBe(FillShopProductTranslations::REDO_KEPT)
        ->and($this->product->getTranslation('description_i8n', 'de', false))->toBe('<p>Von Hand</p>')
        ->and(fillShopTextMode($this, 'redo')['stats'])->toMatchArray(['machine' => 0, 'failed' => 0, 'nothing_to_translate' => 0]);
});

test('fill translations accepts a cleaned shop text that only drops our own branding, and shows what was removed', function () {
    $fill   = new FillShopProductTranslations();
    $source = '<p>These <strong>AWGifts Dropship Europe Satya incense sticks</strong> are hand rolled in India, 15 sticks per pack.</p>';
    $clean  = '<p>These <strong>Satya incense sticks</strong> are hand rolled in India, 15 sticks per pack.</p>';

    expect($fill->invalidReason(['description' => ['clean' => $source]], ['description' => $clean]))->toBeNull()
        ->and($fill->changedSpan($source, $clean))->toBe(['AWGifts Dropship Europe', ''])
        ->and(preg_match('/'.FillShopProductTranslations::RESELLER_WORDING.'/iu', 'It represents strength'))->toBe(0)
        ->and(preg_match('/'.FillShopProductTranslations::RESELLER_WORDING.'/iu', 'AWGifts presents Satya'))->toBe(1);
});

test('fill translations lets the imperial equivalent beside a metric size drop, never the metric size or a lone imperial one', function () {
    $check = fn (string $field, string $source, string $output) => (new FillShopProductTranslations())->invalidReason([$field => ['translate' => $source]], [$field => $output]);

    expect($check('description', '<p><strong>Dimensions:</strong> 34 x 18 x 5 cm (13.4 x 7.1 x 2 inch)&nbsp;</p><p>Strap: 51 - 96 cm (20.1 x 37.8 inch)</p>', '<p><strong>Maße:</strong> 34 x 18 x 5 cm</p><p>Riemen: 51 - 96 cm</p>'))->toBeNull()
        ->and($check('description', '<li>Teapot Capacity: 200ml (6.8 fluid ounces)</li>', '<li>Fassungsvermögen: 200 ml</li>'))->toBeNull()
        ->and($check('description_extra', '<p>Size: 62 x 200 cm (approx. 24 x 79 in)</p>', '<p>Größe: 62 x 200 cm</p>'))->toBeNull()
        ->and($check('name', 'Crystal Gemstone Necklace Cord 45cm/18inch - Green', 'Kristall-Edelstein-Halskette Kordel 45 cm - Grün'))->toBeNull()
        ->and($check('name', 'Crystal Gemstone Necklace Cord 45cm/18inch - Green', 'Kristall-Edelstein-Halskette Kordel 18 Zoll - Grün'))->toContain('numbers lost (45)')
        ->and($check('name', 'Carved Mango Wood Ashcatcher 10"', 'Geschnitzter Aschefänger aus Mangoholz 25 cm'))->toContain('numbers lost (10)')
        ->and($check('name', 'Lrg Natural 6oz Cotton Bag 38x42cm', 'Große Baumwolltasche 170 g 38x42 cm'))->toContain('numbers lost (6)')
        ->and($check('description', '<p>Heavy cotton bag (8 oz)</p>', '<p>Schwere Baumwolltasche (227 g)</p>'))->toContain('numbers lost (8)')
        ->and($check('description', '<p>Weight 250g (6 inch tall)</p>', '<p>Gewicht 250 g</p>'))->toContain('numbers lost (6)')
        ->and($check('description', '<p>Weight 250g (6 inch)</p>', '<p>Gewicht 250 g</p>'))->toContain('numbers lost (6)')
        ->and($check('description', '<p>Net weight 100g (3.5 oz)</p>', '<p>Nettogewicht 100 g</p>'))->toBeNull()
        ->and($check('description', '<p>Lotion 250ml (8.45 oz)</p>', '<p>Lotion 250 ml</p>'))->toContain('numbers lost (8, 45)');
});

function borrowFixture(object $test, array $target, array $source, float $sourceUnits = 1): Product
{
    fillTranslationsFixture($test, siblingUnits: 6);
    plainOtherShopProducts($test);
    $test->product->updateQuietly($target);
    $test->englishShop = StoreShop::make()->action($test->organisation, array_merge(Shop::factory()->definition(), ['language_id' => Language::where('code', 'en')->first()->id]));
    $twin              = $test->product->replicate();
    $twin->fill(['shop_id' => $test->englishShop->id, 'slug' => 'en-'.Str::random(10), 'master_product_id' => null, 'units' => $sourceUnits, 'data' => []] + $source);
    $twin->saveQuietly();
    $twin->tradeUnits()->sync([$test->bottle => ['quantity' => $sourceUnits]]);

    return $twin;
}

function borrow(object $test, bool $apply = true, bool $retryFailed = false): array
{
    return FillShopProductTranslations::run($test->shop, Language::where('code', 'en')->first(), null, $apply, false, null, 50, null, null, $retryFailed, false, false, false, $test->englishShop);
}

test('fill translations fills an empty description from the same pack in another shop, leaves text, names, the master and the source alone, and a dry run writes nothing', function () {
    Queue::fake();
    $twin = borrowFixture(
        $this,
        ['description' => '<p>&nbsp;</p>', 'description_title' => 'Own title', 'description_extra' => null, 'data' => ['translation_sources' => ['de' => ['failed' => 'name: empty', 'fields' => [], 'hashes' => []]]]],
        ['name' => 'Other name', 'description' => '<p>Calming soap</p>', 'description_title' => 'Other title', 'description_extra' => '<p>Made in Wales</p>']
    );

    borrow($this, apply: false);
    expect($this->product->refresh()->description)->toBe('<p>&nbsp;</p>');

    borrow($this);

    $product = $this->product->refresh();
    expect($product->description)->toBe('<p>Calming soap</p>')
        ->and($product->getTranslation('description_i8n', 'en', false))->toBe('<p>Calming soap</p>')
        ->and($product->description_extra)->toBe('<p>Made in Wales</p>')
        ->and($product->description_title)->toBe('Own title')
        ->and($product->name)->toBe('Lavender soap 100g')
        ->and($product->data['translation_sources']['en']['fields'])->toEqual(['description' => FillShopProductTranslations::BORROWED, 'description_extra' => FillShopProductTranslations::BORROWED])
        ->and($product->data['translation_sources']['en']['borrowed_from']['description'])->toBe($twin->id)
        ->and($product->data['translation_sources']['en']['hashes']['description'])->toBe(md5('<p>Calming soap</p>'))
        ->and($product->data['translation_sources']['de']['failed'])->toBe('name: empty')
        ->and($product->audits()->get()->contains(fn ($audit) => ($audit->new_values['description'] ?? null) === '<p>Calming soap</p>'))->toBeTrue()
        ->and($this->masterAsset->refresh()->getTranslation('description_i8n', 'en', false))->toBeEmpty()
        ->and($twin->refresh()->description)->toBe('<p>Calming soap</p>')
        ->and($twin->data)->toBeEmpty();
    ShopHydrateProductsWithNoDescription::assertPushed(1);
});

test('fill translations never borrows from a different pack', function () {
    borrowFixture($this, ['description' => null], ['description' => '<p>Box of six soaps</p>'], sourceUnits: 6);

    borrow($this);

    expect($this->product->refresh()->description)->toBeNull()
        ->and(data_get($this->product->data, 'translation_sources.en'))->toBeNull();
});

test('fill translations cleans a borrowed text with reseller wording first, and the shop text cleanup leaves it alone', function () {
    borrowFixture($this, ['description' => null], ['description' => '<p>Calming soap. Your customers will love it.</p>']);
    $this->cleanAnswer = ['description' => '<p>Calming soap.</p>'];
    $cleanRequests     = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"clean"'))->count();

    borrow($this);

    expect($this->product->refresh()->description)->toBe('<p>Calming soap.</p>')
        ->and($this->product->data['translation_sources']['en']['fields']['description'])->toBe(FillShopProductTranslations::BORROWED_CLEANED);

    $afterBorrow = $cleanRequests();
    fillShopTextMode($this, 'clean');
    borrow($this);
    expect($cleanRequests())->toBe($afterBorrow);
});

test('fill translations fills nothing when the borrowed text cleaning is rejected, and tries it again only with retry failed', function () {
    borrowFixture($this, ['description' => null, 'description_extra' => null], ['description' => '<p>Calming soap. Your customers will love it.</p>', 'description_extra' => '<p>Made with olive oil</p>']);
    $this->cleanAnswer = ['description' => '<p>Calming soap 500 g.</p>'];
    $reported          = [];
    $borrowReporting   = function () use (&$reported) {
        FillShopProductTranslations::run($this->shop, Language::where('code', 'en')->first(), null, true, false, null, 50, null, function (Product $product, array $fields, ?array $texts, array $sources, ?string $failure = null) use (&$reported) {
            $reported[] = [$texts, $failure];
        }, false, false, false, false, $this->englishShop);
    };
    $cleanRequests     = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"clean"'))->count();

    $borrowReporting();

    expect($this->product->refresh()->description)->toBeNull()
        ->and($this->product->description_extra)->toBe('<p>Made with olive oil</p>')
        ->and($reported[0][0])->toBe(['description_extra' => '<p>Made with olive oil</p>'])
        ->and($reported[0][1])->toContain('numbers added')
        ->and($this->product->data['translation_sources']['en']['fields']['description'])->toBe(FillShopProductTranslations::BORROW_FAILED)
        ->and($this->product->data['translation_sources']['en']['failures']['description'])->toContain('numbers added');

    $afterFailure = $cleanRequests();
    borrow($this);
    expect($cleanRequests())->toBe($afterFailure);

    $this->cleanAnswer = ['description' => '<p>Calming soap.</p>'];
    borrow($this, retryFailed: true);

    expect($this->product->refresh()->description)->toBe('<p>Calming soap.</p>')
        ->and($this->product->data['translation_sources']['en']['fields']['description'])->toBe(FillShopProductTranslations::BORROWED_CLEANED)
        ->and(data_get($this->product->data, 'translation_sources.en.failures.description'))->toBeNull();
});

test('fill translations translates a borrowed description into a language already filled with redo cleaned, never the name', function () {
    borrowFixture($this, ['description' => null], ['description' => '<p>Calming soap</p>']);
    fillTranslations($this);
    $this->travel(1)->minutes();
    borrow($this);
    $this->travel(1)->minutes();
    $this->aiPrefix = 'NEU ';

    fillShopTextMode($this, 'redo');

    $product = $this->product->refresh();
    expect($product->getTranslation('description_i8n', 'de', false))->toBe('NEU <p>Calming soap</p>')
        ->and($product->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soap 100g')
        ->and($product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::MACHINE)
        ->and(fillShopTextMode($this, 'redo')['stats'])->toMatchArray(['machine' => 0, 'failed' => 0]);
});

test('fill translations borrows only from an active product, and cleans delivery terms and prices out of a borrowed text but copies a plain mention of the UK', function () {
    borrowFixture($this, ['description' => null, 'description_extra' => null], [
        'description'       => '<p>Solid wood cabinet, 40 cm tall, handmade in the UK.</p><p>Standard delivery of 2 business days (UK Mainland). For orders outside of UK mainland, there will be an additional fee.</p>',
        'description_extra' => '<p>Sizes 3.5-6.5 UK (36-40 EU), handmade in the UK.</p>',
    ]);
    $this->cleanAnswer = ['description' => '<p>Solid wood cabinet, 40 cm tall, handmade in the UK.</p>'];
    $cleanRequests     = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"clean"'));

    borrow($this);

    $product = $this->product->refresh();
    expect($product->description)->toBe('<p>Solid wood cabinet, 40 cm tall, handmade in the UK.</p>')
        ->and($product->data['translation_sources']['en']['fields']['description'])->toBe(FillShopProductTranslations::BORROWED_CLEANED)
        ->and($product->description_extra)->toBe('<p>Sizes 3.5-6.5 UK (36-40 EU), handmade in the UK.</p>')
        ->and($product->data['translation_sources']['en']['fields']['description_extra'])->toBe(FillShopProductTranslations::BORROWED)
        ->and($cleanRequests()->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'], 'Sizes'))->count())->toBe(0)
        ->and($cleanRequests()->first()[0]['messages'][0]['content'])->toContain('delivery or shipping times');

});

test('fill translations never borrows from a discontinued product, nor lets a borrowed clean lose a number outside the delivery terms', function () {
    $twin = borrowFixture($this, ['description' => null], ['description' => '<p>Calming soap</p>']);
    $twin->updateQuietly(['state' => ProductStateEnum::DISCONTINUED]);

    borrow($this);
    expect($this->product->refresh()->description)->toBeNull();

    $twin->updateQuietly(['state' => ProductStateEnum::ACTIVE, 'description' => '<p>Cabinet 40 cm tall, solid mango wood, hand carved.</p><p>Standard delivery of 2 business days.</p>']);
    $this->cleanAnswer = ['description' => '<p>Cabinet, solid mango wood, hand carved.</p>'];
    borrow($this);

    expect($this->product->refresh()->description)->toBeNull()
        ->and($this->product->data['translation_sources']['en']['failures']['description'])->toContain('numbers lost (40)');
});

test('fill translations redo cleaned leaves a borrowed text of a product never filled in that language to the normal fill, which then fills the name too', function () {
    borrowFixture($this, ['description' => null, 'name_i8n' => ['en' => 'Lavender soap 100g'], 'data' => []], ['description' => '<p>Calming soap</p>']);
    borrow($this);
    $this->travel(1)->minutes();

    fillShopTextMode($this, 'redo');
    expect(data_get($this->product->refresh()->data, 'translation_sources.de'))->toBeNull();

    FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), null, true);

    $product = $this->product->refresh();
    expect($product->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soap 100g')
        ->and($product->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Calming soap</p>');
});

function uncleanableCopyFixture(object $test, string $shopDescription = '<p>Lovely soap</p>'): Product
{
    $sibling = fillTranslationsFixture($test);
    plainOtherShopProducts($test);
    fillTranslations($test);
    $test->cleanAnswer = ['description' => '<p>Tolles feines mildes Stück hier</p>'];
    FillShopProductTranslations::run($test->shop, Language::where('code', 'de')->first(), $test->germanShop, true, false, null, 50, null, null, false, true);
    $test->cleanAnswer = null;
    $test->product->refresh()->updateQuietly(['description' => $shopDescription]);

    return $sibling;
}

function replaceUncleanable(object $test, bool $apply = true, bool $retryFailed = false): array
{
    return FillShopProductTranslations::run($test->shop, Language::where('code', 'de')->first(), $test->germanShop, $apply, false, null, 50, null, null, $retryFailed, false, false, false, null, true);
}

test('fill translations replaces a copy the clean rejected with a translation of the shop text, and a dry run writes nothing', function () {
    $sibling = uncleanableCopyFixture($this);
    $empty   = $this->product->replicate();
    $empty->fill(['slug' => 'em-'.Str::random(10), 'code' => 'EM'.Str::random(8), 'master_product_id' => null, 'data' => ['translation_sources' => ['de' => ['failed' => 'name: empty', 'fields' => [], 'hashes' => []]]]]);
    $empty->saveQuietly();
    expect($this->product->data['translation_sources']['de']['clean_failed'])->toContain('description: rewritten');

    $dry = replaceUncleanable($this, apply: false);
    expect($dry['stats']['replaced'])->toBe(1)
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife en gros</p>');

    replaceUncleanable($this);

    $german = $this->product->refresh()->data['translation_sources']['de'];
    expect($this->product->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Lovely soap</p>')
        ->and($this->product->getTranslation('name_i8n', 'de', false))->toBe('Lavendelseife 100g en gros')
        ->and($german['fields'])->toMatchArray(['name' => FillShopProductTranslations::COPIED, 'description' => FillShopProductTranslations::MACHINE])
        ->and($german['hashes']['description'])->toBe(md5('DE <p>Lovely soap</p>'))
        ->and($german['replaced']['description'])->toBe(FillShopProductTranslations::REPLACED)
        ->and($german['replaced_copy_from']['description'])->toBe($sibling->id)
        ->and($german)->not->toHaveKey('clean_failed')
        ->and($empty->refresh()->data['translation_sources']['de']['fields'])->toBe([])
        ->and($this->masterAsset->refresh()->getTranslation('description_i8n', 'de', false))->toBeEmpty()
        ->and(replaceUncleanable($this)['stats']['replaced'])->toBe(0);
});

test('fill translations does not replace a rejected copy a person corrected, nor translate a shop text that still has reseller wording', function () {
    uncleanableCopyFixture($this);
    $this->product->refresh()->setTranslation('description_i8n', 'de', '<p>Von Hand korrigiert</p>')->save();

    expect(replaceUncleanable($this)['stats']['kept_person_text'])->toBe(1)
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('<p>Von Hand korrigiert</p>');

    $this->product->setTranslation('description_i8n', 'de', '<p>Schöne Seife en gros</p>')->save();
    $this->product->updateQuietly(['description' => '<p>Lovely soap, wholesale for your shop</p>']);
    $translations = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', 'wholesale for your shop'))->count();

    expect(replaceUncleanable($this)['stats']['skipped_shop_text_wording'])->toBe(1)
        ->and($translations())->toBe(0)
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife en gros</p>');
});

test('fill translations keeps the copy when the replacing translation is rejected, and tries it again only with retry failed', function () {
    uncleanableCopyFixture($this);
    $this->aiPrefix = '<b>';
    $translations   = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', '"translate":"<p>Lovely soap<\/p>"'))->count();

    expect(replaceUncleanable($this)['stats']['failed'])->toBe(1)
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('<p>Schöne Seife en gros</p>')
        ->and($this->product->data['translation_sources']['de']['replaced']['description'])->toBe(FillShopProductTranslations::REPLACE_FAILED)
        ->and($this->product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::COPIED);

    $afterFailure = $translations();
    replaceUncleanable($this);
    expect($translations())->toBe($afterFailure);

    $this->aiPrefix = 'DE ';
    replaceUncleanable($this, retryFailed: true);

    expect($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Lovely soap</p>')
        ->and($this->product->data['translation_sources']['de']['replaced']['description'])->toBe(FillShopProductTranslations::REPLACED)
        ->and(data_get($this->product->data, 'translation_sources.de.replace_failures.description'))->toBeNull();
});

test('fill translations keeps a replaced copy through a forced fill and never redoes it after the shop text is cleaned', function () {
    uncleanableCopyFixture($this);
    replaceUncleanable($this);
    $this->aiPrefix = 'NEU ';

    fillTranslations($this, force: true);

    expect($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Lovely soap</p>')
        ->and($this->product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::MACHINE);

    $data = $this->product->data;
    data_set($data, 'translation_sources.en', ['fields' => ['description' => FillShopProductTranslations::SHOP_TEXT_CLEANED], 'cleaned_at' => now()->addDay()->toIso8601String()]);
    data_set($data, 'translation_sources.de.at', now()->subDay()->toIso8601String());
    $this->product->updateQuietly(['data' => $data]);

    expect(fillShopTextMode($this, 'redo')['stats'])->toMatchArray(['machine' => 0, 'failed' => 0])
        ->and($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Lovely soap</p>');
});

test('fill translations leaves a blanked german field alone in every mode, and redo cleaned translates it once the shop has its own text', function () {
    fillTranslationsFixture($this);
    plainOtherShopProducts($this);
    $this->product->updateQuietly(['description' => null]);
    fillTranslations($this);
    $product = $this->product->refresh();
    expect($product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::COPIED);

    $product->forgetTranslation('description_i8n', 'de');
    $data = $product->data;
    data_set($data, 'translation_sources.de.fields.description', FillShopProductTranslations::BLANKED);
    data_set($data, 'translation_sources.de.blanked.description', ['at' => now()->toIso8601String(), 'was' => '<p>Schöne Seife en gros</p>', 'why' => 'wholesale pitch']);
    Arr::forget($data, 'translation_sources.de.hashes.description');
    $product->data = $data;
    $product->save();

    fillTranslations($this, force: true);
    FillShopProductTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, true, false, null, 50, null, null, false, true);
    replaceUncleanable($this);
    fillShopTextMode($this, 'redo');

    expect($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBeEmpty()
        ->and($this->product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::BLANKED);

    $this->product->updateQuietly(['description' => '<p>Lovely soap</p>']);
    $this->aiPrefix = 'NEU ';
    fillTranslations($this, force: true);
    expect($this->product->refresh()->getTranslation('description_i8n', 'de', false))->toBeEmpty();

    fillShopTextMode($this, 'redo');

    $product = $this->product->refresh();
    expect($product->getTranslation('description_i8n', 'de', false))->toBe('NEU <p>Lovely soap</p>')
        ->and($product->data['translation_sources']['de']['fields']['description'])->toBe(FillShopProductTranslations::MACHINE)
        ->and($product->data['translation_sources']['de']['redo']['description'])->toBe(FillShopProductTranslations::REDONE)
        ->and($product->data['translation_sources']['de']['blanked']['description']['why'])->toBe('wholesale pitch')
        ->and(fillShopTextMode($this, 'redo')['stats']['machine'])->toBe(0);
});

/**
 * @return array{0: \App\Models\Catalogue\ProductCategory, 1: \App\Models\Catalogue\Collection, 2: \App\Models\Catalogue\ProductCategory}
 */
function fillCategoryTranslationsFixture(object $test): array
{
    fillTranslationsFixture($test);
    $family = $test->product->family;
    DB::table('product_categories')->where('id', $family->id)->update(['state' => 'active', 'name' => 'Lavender soaps', 'description' => '<p>Soaps your customers love</p>', 'name_i8n' => json_encode(['en' => 'Lavender soaps']), 'description_i8n' => '{}', 'description_title_i8n' => '{}', 'description_extra_i8n' => '{}', 'data' => '{}']);
    $family->refresh();
    $germanFamily = $family->replicate();
    $germanFamily->fill(['shop_id' => $test->germanShop->id, 'slug' => 'de-'.Str::random(10), 'master_product_category_id' => null, 'name' => 'Lavendelseifen', 'description' => '<p>Großhandel Seifen</p>', 'data' => []]);
    $germanFamily->saveQuietly();
    $collection = \App\Actions\Catalogue\Collection\StoreCollection::make()->action($test->shop, ['code' => 'gifts-'.uniqid(), 'name' => 'Christmas gifts']);
    $collection->updateQuietly(['state' => 'active', 'description' => '<p>Gifts for 12 people</p>']);

    return [$family, $collection->refresh(), $germanFamily];
}

function fillCategoryTranslations(object $test, bool $apply = true, bool $force = false, bool $retryFailed = false): array
{
    return \App\Actions\Catalogue\ProductCategory\FillShopCategoryTranslations::run($test->shop, Language::where('code', 'de')->first(), $test->germanShop, $apply, $force, null, 50, null, null, $retryFailed);
}

test('fill category translations machine translates families and collections from the shop own text, on the shop record only, and never copies the wholesale text of the sibling shop', function () {
    [$family, $collection] = fillCategoryTranslationsFixture($this);

    $dryRun  = fillCategoryTranslations($this, apply: false);
    $limited = \App\Actions\Catalogue\ProductCategory\FillShopCategoryTranslations::run($this->shop, Language::where('code', 'de')->first(), $this->germanShop, false, false, 1);
    expect($family->refresh()->data)->not->toHaveKey('translation_sources')
        ->and($dryRun['stats']['machine'])->toBeGreaterThan(0)
        ->and(array_sum($limited['stats']))->toBe(1);

    fillCategoryTranslations($this);

    $family->refresh();
    $collection->refresh();
    Http::assertNotSent(fn (Request $request) => str_contains($request->body(), 'Großhandel'));
    Http::assertSent(fn (Request $request) => str_contains($request['messages'][0]['content'], 'calls the same family "Lavendelseifen"'));
    expect($family->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soaps')
        ->and($family->getTranslation('description_i8n', 'de', false))->toBe('DE <p>Soaps your customers love</p>')
        ->and($family->getTranslation('name_i8n', 'en', false))->toBe('Lavender soaps')
        ->and($family->name)->toBe('Lavender soaps')
        ->and($family->data['translation_sources']['de']['fields']['name'])->toBe(FillShopProductTranslations::MACHINE)
        ->and($collection->name_i8n['de'])->toBe('DE Christmas gifts')
        ->and($collection->description_i8n['de'])->toBe('DE <p>Gifts for 12 people</p>')
        ->and(data_get($this->masterFamily->refresh()->name_i8n, 'de'))->toBeEmpty();
});

test('fill category translations resumes past done ones, redoes only its own text with force and never overwrites german a person wrote', function () {
    [$family, $collection] = fillCategoryTranslationsFixture($this);
    fillCategoryTranslations($this);
    $requests = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]['messages'][1]['content'] ?? '', 'Lavender soaps'))->count();
    $before   = $requests();

    fillCategoryTranslations($this);
    expect($requests())->toBe($before);

    $collection->updateQuietly(['name_i8n' => ['de' => 'Weihnachtsgeschenke']]);
    $this->aiPrefix = 'NEU ';
    fillCategoryTranslations($this, force: true);

    expect($family->refresh()->getTranslation('name_i8n', 'de', false))->toBe('NEU Lavender soaps')
        ->and($collection->refresh()->name_i8n['de'])->toBe('Weihnachtsgeschenke')
        ->and($collection->description_i8n['de'])->toBe('NEU <p>Gifts for 12 people</p>');
});

test('fill category translations marks a rejected translation and tries it again only with retry failed', function () {
    [$family] = fillCategoryTranslationsFixture($this);
    $this->aiPrefix = '<b>';

    fillCategoryTranslations($this);
    expect($family->refresh()->getTranslation('name_i8n', 'de', false))->toBe('')
        ->and($family->data['translation_sources']['de']['failed'])->toContain('HTML tags changed');

    $this->aiPrefix = 'DE ';
    fillCategoryTranslations($this);
    expect($family->refresh()->getTranslation('name_i8n', 'de', false))->toBe('');

    fillCategoryTranslations($this, retryFailed: true);
    expect($family->refresh()->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soaps')
        ->and($family->data['translation_sources']['de'])->not->toHaveKey('failed');
});

test('fill category translations takes the text when the ai answers inside the translate wrapper it was sent', function () {
    [$family] = fillCategoryTranslationsFixture($this);
    $this->aiEchoesWrapper = true;

    fillCategoryTranslations($this);

    expect($family->refresh()->getTranslation('name_i8n', 'de', false))->toBe('DE Lavender soaps')
        ->and($family->data['translation_sources']['de'])->not->toHaveKey('failed');
});

test('language readiness counts the live categories and collections with a name and a description per language', function () {
    [$family, $collection] = fillCategoryTranslationsFixture($this);
    $this->product->updateQuietly(['name_i8n' => ['en' => 'Lavender soap 100g', 'de' => 'Lavendelseife']]);
    $row = fn () => collect(\App\Actions\Catalogue\Shop\UI\GetShopLanguageReadiness::run($this->shop->fresh()))->firstWhere('code', 'de');

    $before = $row();
    $family->updateQuietly(['name_i8n' => ['de' => 'Lavendelseifen'], 'description_i8n' => ['de' => ' ']]);
    $collection->updateQuietly(['state' => 'inactive', 'name_i8n' => ['de' => 'Geschenke']]);
    $after = $row();

    expect($after['category_names'])->toBe($before['category_names'] + 1)
        ->and($after['category_descriptions'])->toBe($before['category_descriptions'])
        ->and($after['categories'])->toBe($before['categories'] - 1)
        ->and($after['names'])->toBe($before['names']);
});
