<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Product\AskShopkeeperToReviewMasterText;
use App\Actions\Catalogue\Product\AskShopkeeperToUpdateProductUnit;
use App\Actions\Catalogue\Product\StoreProduct;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
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
