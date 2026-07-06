<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Helpers\Translations\DetectLanguage;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Reviews\ApproveReview;
use App\Actions\Reviews\AutoPublishReviews;
use App\Actions\Reviews\DeleteReview;
use App\Actions\Reviews\DetectReviewLanguage;
use App\Actions\Reviews\DetectReviewReplyLanguage;
use App\Actions\Reviews\GetReviewCustomers;
use App\Actions\Reviews\GetReviews;
use App\Actions\Reviews\RejectReview;
use App\Actions\Reviews\ReviewReaction\DeleteReviewReaction;
use App\Actions\Reviews\ReviewReaction\StoreReviewReaction;
use App\Actions\Reviews\ReviewReaction\UpdateReviewReaction;
use App\Actions\Reviews\ReviewReply\DeleteReviewReply;
use App\Actions\Reviews\ReviewReply\StoreReviewReply;
use App\Actions\Reviews\ReviewReply\UpdateReviewReply;
use App\Actions\Reviews\GetReviewableReviews;
use App\Actions\Reviews\Import\ReviewIOImport;
use App\Actions\Reviews\Import\TrustPilotImport;
use App\Actions\Reviews\StoreReview;
use App\Actions\Reviews\TranslateReply;
use App\Actions\Reviews\TranslateReview;
use App\Actions\Reviews\UI\IndexReviewsInIris;
use App\Actions\Reviews\UpdateReview;
use App\InertiaTable\InertiaTable;
use Illuminate\Routing\Route as IlluminateRoute;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Helpers\Language;
use App\Models\Ordering\Order;
use App\Models\Reviews\GroupReviewStat;
use App\Models\Reviews\MasterAssetReviewStat;
use App\Models\Reviews\MasterProductCategoryReviewStat;
use App\Models\Reviews\OrderReviewStat;
use App\Models\Reviews\ProductCategoryReviewStat;
use App\Models\Reviews\ProductReviewStat;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewRatingLabel;
use App\Models\Reviews\ReviewReaction;
use App\Models\Reviews\ShopReviewStat;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = $this->organisation->group;
    $this->user         = createAdminGuest($this->group)->getUser();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);
        $shop = StoreShop::make()->action($this->organisation, $storeData);
    }
    $this->shop = UpdateShop::make()->action($shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = createCustomer($this->shop);

    list($this->orgStocks, $this->product) = createProduct($this->shop);

    $this->family = $this->shop->productCategories()
        ->where('type', ProductCategoryTypeEnum::FAMILY)->first();

    $this->order = createOrder($this->customer, $this->product);

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);

    Queue::fake();
    actingAs($this->user);
});

function storeProductReview(array $overrides = []): Review
{
    return StoreReview::make()->action(
        test()->product,
        array_merge([
            'customer_id' => test()->customer->id,
            'rating'      => 5,
            'message'     => 'Great product, works perfectly.',
        ], $overrides)
    );
}

test('store product review is published by default', function () {
    $review = storeProductReview();

    expect($review)->toBeInstanceOf(Review::class)
        ->and($review->scope)->toBe(ReviewScopeEnum::PRODUCT)
        ->and($review->product_id)->toBe($this->product->id)
        ->and($review->state)->toBe(ReviewStateEnum::PUBLISHED)
        ->and($review->review_status)->toBe(ReviewStatusEnum::APPROVED)
        ->and($review->approved)->toBeTrue()
        ->and((float) $review->rating_main)->toBe(5.0)
        ->and($review->published_at)->not->toBeNull();

    return $review;
});

test('store family review', function () {
    $review = StoreReview::make()->action($this->family, [
        'customer_id' => $this->customer->id,
        'rating'      => 4,
        'message'     => 'Nice family of products.',
    ]);

    expect($review->scope)->toBe(ReviewScopeEnum::FAMILY)
        ->and($review->product_category_id)->toBe($this->family->id);
});

test('store order review', function () {
    $review = StoreReview::make()->action($this->order, [
        'customer_id' => $this->customer->id,
        'order_id'    => $this->order->id,
        'rating'      => 3,
        'message'     => 'Order arrived on time.',
    ]);

    expect($review->scope)->toBe(ReviewScopeEnum::ORDER)
        ->and($review->order_id)->toBe($this->order->id);
});

test('store shop review', function () {
    $review = StoreReview::make()->action($this->shop, [
        'customer_id' => $this->customer->id,
        'rating'      => 5,
        'message'     => 'Great shop.',
    ]);

    expect($review->scope)->toBe(ReviewScopeEnum::SHOP)
        ->and($review->shop_id)->toBe($this->shop->id);
});

test('store private review when not public', function () {
    $review = storeProductReview(['is_public' => false]);

    expect($review->state)->toBe(ReviewStateEnum::PRIVATE)
        ->and($review->review_status)->toBe(ReviewStatusEnum::NA)
        ->and($review->is_public)->toBeFalse();
});

function setShopReviewSettings(array $reviews): void
{
    $settings = test()->shop->settings ?? [];
    data_set($settings, 'reviews.data.approval_required', data_get($reviews, 'approval_required', false));
    data_set($settings, 'reviews.public_rating_threshold', data_get($reviews, 'public_rating_threshold', 0));
    data_set($settings, 'reviews.auto_publishing.mode', data_get($reviews, 'mode', 'immediately'));
    data_set($settings, 'reviews.auto_publishing.delay_hours', data_get($reviews, 'delay_hours', 24));
    test()->shop->update(['settings' => $settings]);
}

test('store review waiting approval when approval required', function () {
    setShopReviewSettings(['approval_required' => true]);

    $review = storeProductReview();

    expect($review->state)->toBe(ReviewStateEnum::WAITING_APPROVAL)
        ->and($review->review_status)->toBe(ReviewStatusEnum::PENDING);
});

test('store review rejected when auto publishing never', function () {
    setShopReviewSettings(['mode' => 'never']);

    $review = storeProductReview();

    expect($review->state)->toBe(ReviewStateEnum::REJECTED)
        ->and($review->review_status)->toBe(ReviewStatusEnum::REJECTED);
});

test('store review waiting approval when auto publishing scheduled with delay', function () {
    setShopReviewSettings(['mode' => 'scheduled', 'delay_hours' => 12]);

    $review = storeProductReview();

    expect($review->state)->toBe(ReviewStateEnum::WAITING_APPROVAL)
        ->and($review->auto_approve_at)->not->toBeNull();
});

test('store review becomes public when rating above threshold', function () {
    setShopReviewSettings(['public_rating_threshold' => 3, 'mode' => 'immediately']);

    $review = storeProductReview(['is_public' => false, 'rating' => 5]);

    expect($review->is_public)->toBeTrue()
        ->and($review->state)->toBe(ReviewStateEnum::PUBLISHED);
});

test('update review recalculates rating from dimensions', function () {
    $review = storeProductReview();

    $updated = UpdateReview::make()->handle($review, [
        'message'  => 'Updated message',
        'rating_a' => 4,
        'rating_b' => 2,
    ]);

    expect((float) $updated->rating_main)->toBe(3.0)
        ->and($updated->message)->toBe('Updated message');
});

test('approve review', function () {
    $review = storeProductReview();
    $review->update(['state' => ReviewStateEnum::WAITING_APPROVAL, 'review_status' => ReviewStatusEnum::PENDING, 'published_at' => null]);

    $approved = ApproveReview::make()->handle($review->refresh(), $this->user->id);

    expect($approved->state)->toBe(ReviewStateEnum::PUBLISHED)
        ->and($approved->review_status)->toBe(ReviewStatusEnum::APPROVED)
        ->and($approved->approved)->toBeTrue()
        ->and($approved->approved_by)->toBe($this->user->id)
        ->and($approved->published_at)->not->toBeNull();
});

test('reject review', function () {
    $review = storeProductReview();

    $rejected = RejectReview::make()->handle($review);

    expect($rejected->state)->toBe(ReviewStateEnum::REJECTED)
        ->and($rejected->review_status)->toBe(ReviewStatusEnum::REJECTED)
        ->and($rejected->approved)->toBeFalse()
        ->and($rejected->published_at)->toBeNull();
});

test('delete review soft deletes', function () {
    $review = storeProductReview();
    $id     = $review->id;

    $result = DeleteReview::make()->handle($review);

    expect($result)->toBeTrue()
        ->and(Review::find($id))->toBeNull()
        ->and(Review::withTrashed()->find($id))->not->toBeNull();
});

test('review reply store update delete', function () {
    $review = storeProductReview();

    $review = StoreReviewReply::make()->handle($review, ['body' => 'Thanks for the review', 'reply_by' => $this->user->id]);
    expect($review->replied)->toBeTrue()
        ->and($review->reply_message)->toBe('Thanks for the review');

    $review = UpdateReviewReply::make()->handle($review, ['body' => 'Edited reply']);
    expect($review->reply_message)->toBe('Edited reply');

    $review = DeleteReviewReply::make()->handle($review);
    expect($review->replied)->toBeFalse()
        ->and($review->reply_message)->toBeNull();
});

test('review reaction store update delete hydrates counts', function () {
    $review = storeProductReview();

    $reaction = StoreReviewReaction::make()->action($review, [
        'customer_id' => $this->customer->id,
        'target'      => 'review',
        'type'        => 'like',
    ]);
    expect($reaction)->toBeInstanceOf(ReviewReaction::class)
        ->and($review->refresh()->likes)->toBe(1);

    UpdateReviewReaction::make()->action($reaction, ['type' => 'dislike']);
    expect($review->refresh()->likes)->toBe(0)
        ->and($review->dislikes)->toBe(1);

    DeleteReviewReaction::make()->action($reaction);
    expect($review->refresh()->dislikes)->toBe(0);
});

test('get reviews returns paginator with stats', function () {
    storeProductReview();
    storeProductReview(['rating' => 4]);

    $paginator = GetReviews::run([
        'scope'         => ReviewScopeEnum::PRODUCT->value,
        'reviewable_id' => $this->product->id,
        'state'         => ReviewStateEnum::PUBLISHED->value,
        'sort'          => '-rating',
        'per_page'      => 10,
    ]);

    expect($paginator->total())->toBeGreaterThanOrEqual(2);
});

test('get reviews without scope filters by state', function () {
    storeProductReview();

    $paginator = GetReviews::run(['state' => ReviewStateEnum::PUBLISHED->value]);

    expect($paginator->total())->toBeGreaterThanOrEqual(1);
});

test('get reviews with order scope does not throw', function () {
    StoreReview::make()->action($this->order, [
        'customer_id' => $this->customer->id,
        'order_id'    => $this->order->id,
        'rating'      => 5,
        'message'     => 'order scope',
    ]);

    $action    = GetReviews::make();
    $paginator = $action->handle([
        'scope'         => ReviewScopeEnum::ORDER->value,
        'reviewable_id' => $this->order->id,
        'state'         => ReviewStateEnum::PUBLISHED->value,
    ]);

    expect($paginator->total())->toBeGreaterThanOrEqual(1)
        ->and($action->jsonResponse($paginator))->not->toBeNull();
});

test('get review customers', function () {
    storeProductReview();

    $data = GetReviewCustomers::run($this->product);

    expect($data)->toBeArray();
});

test('hydrate review stats command', function () {
    storeProductReview();

    $this->artisan('hydrate:review-stats', ['scopes' => ['shop', 'bogus']])->assertExitCode(0);
    $this->artisan('hydrate:review-stats', ['--group' => true])->assertExitCode(0);
});

test('auto publish reviews', function () {
    $review = storeProductReview();
    $review->update([
        'state'           => ReviewStateEnum::WAITING_APPROVAL,
        'review_status'   => ReviewStatusEnum::PENDING,
        'auto_approve_at' => now()->subHour(),
        'published_at'    => null,
    ]);

    AutoPublishReviews::run();

    $review->refresh();
    expect($review->state)->toBe(ReviewStateEnum::PUBLISHED)
        ->and($review->review_status)->toBe(ReviewStatusEnum::APPROVED)
        ->and($review->auto_approved)->toBeTrue();
});

test('detect review language', function () {
    $language = Language::first();
    DetectLanguage::mock()->shouldReceive('handle')->andReturn($language);

    $review = storeProductReview();
    $review->update(['language_id' => null]);

    $result = DetectReviewLanguage::run($review->refresh(), true);

    expect($result->language_id)->toBe($language->id);
});

test('detect review reply language', function () {
    $language = Language::first();
    DetectLanguage::mock()->shouldReceive('handle')->andReturn($language);

    $review = storeProductReview();
    $review->update(['reply_message' => 'Thank you', 'replied' => true, 'reply_language_id' => null]);

    $result = DetectReviewReplyLanguage::run($review->refresh(), true);

    expect($result->reply_language_id)->toBe($language->id);
});

test('translate review stores translations keyed by language under message', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('translated');

    $this->shop->update(['is_aiku' => true]);
    $aikuLanguageId = $this->shop->language_id;

    $review = storeProductReview();
    $result = TranslateReview::run($review);

    $messageTranslations = $result->translations['message'];

    expect($messageTranslations)->toBeArray()
        ->and($messageTranslations)->toHaveKey($aikuLanguageId)
        ->and($messageTranslations[$aikuLanguageId])->toBe('translated')
        ->and($messageTranslations)->not->toHaveKey('message');
});

test('translate reply stores translations keyed by language under reply_message', function () {
    Translate::mock()->shouldReceive('handle')->andReturn('translated');

    $this->shop->update(['is_aiku' => true]);
    $aikuLanguageId = $this->shop->language_id;

    $review = storeProductReview();
    $review->update(['replied' => true, 'reply_message' => 'Thanks', 'reply_language_id' => $this->shop->language_id]);

    $result = TranslateReply::run($review->refresh());

    $replyTranslations = $result->translations['reply_message'];

    expect($replyTranslations)->toBeArray()
        ->and($replyTranslations)->toHaveKey($aikuLanguageId)
        ->and($replyTranslations[$aikuLanguageId])->toBe('translated')
        ->and($replyTranslations)->not->toHaveKey('reply_message');
});

test('translate job unique id is per review and override flag', function () {
    $reviewA = storeProductReview();
    $reviewB = storeProductReview();

    $action = TranslateReview::make();

    expect($action->getJobUniqueId($reviewA, false))->toBe($reviewA->id.'-n')
        ->and($action->getJobUniqueId($reviewA, true))->toBe($reviewA->id.'-o')
        ->and($action->getJobUniqueId($reviewA, false))->not->toBe($action->getJobUniqueId($reviewB, false));

    $replyAction = TranslateReply::make();
    expect($replyAction->getJobUniqueId($reviewA, false))->toBe($reviewA->id.'-n')
        ->and($replyAction->getJobUniqueId($reviewA, true))->toBe($reviewA->id.'-o');
});

test('translate reply returns early when not replied', function () {
    $review = storeProductReview();

    $result = TranslateReply::run($review);

    expect($result->replied)->toBeFalse();
});

test('review model relations and average rating', function () {
    $review = StoreReview::make()->action($this->order, [
        'customer_id' => $this->customer->id,
        'order_id'    => $this->order->id,
        'rating'      => 4,
        'message'     => 'ok',
    ]);

    expect($review->order)->toBeInstanceOf(Order::class)
        ->and($review->shop)->toBeInstanceOf(Shop::class)
        ->and($review->customer)->toBeInstanceOf(Customer::class)
        ->and($review->reactions)->toHaveCount(0);

    $productReview = storeProductReview();
    expect($productReview->product)->toBeInstanceOf(Product::class);

    $familyReview = StoreReview::make()->action($this->family, ['customer_id' => $this->customer->id, 'rating' => 5, 'message' => 'f']);
    expect($familyReview->productCategory)->toBeInstanceOf(ProductCategory::class);

    $model = new Review();
    $model->rating_a = 4;
    $model->rating_b = 5;
    $model->calculateAverageRating();
    expect((float) $model->rating_main)->toBe(4.5);

    $model2 = new Review();
    $model2->rating_main = 3;
    $model2->calculateAverageRating();
    expect((float) $model2->rating_main)->toBe(3.0);
});

test('review reaction relations', function () {
    $review   = storeProductReview();
    $reaction = StoreReviewReaction::make()->action($review, [
        'customer_id' => $this->customer->id,
        'target'      => 'review',
        'type'        => 'like',
    ]);

    expect($reaction->review)->toBeInstanceOf(Review::class)
        ->and($reaction->customer)->toBeInstanceOf(Customer::class);
});

test('review rating label options', function () {
    expect(ReviewRatingLabel::dimensionOptions())->toBeArray()
        ->and(ReviewRatingLabel::reviewContextOptions())->toBeArray();
});

test('review stat model relations', function () {
    expect((new ShopReviewStat())->shop())->toBeInstanceOf(BelongsTo::class)
        ->and((new GroupReviewStat())->group())->toBeInstanceOf(BelongsTo::class)
        ->and((new OrderReviewStat())->order())->toBeInstanceOf(BelongsTo::class)
        ->and((new ProductReviewStat())->product())->toBeInstanceOf(BelongsTo::class)
        ->and((new ProductCategoryReviewStat())->productCategory())->toBeInstanceOf(BelongsTo::class)
        ->and((new MasterAssetReviewStat())->masterAsset())->toBeInstanceOf(BelongsTo::class)
        ->and((new MasterProductCategoryReviewStat())->masterProductCategory())->toBeInstanceOf(BelongsTo::class);
});

test('UI reviews dashboard', function () {
    $this->withoutExceptionHandling();
    get(route('grp.org.shops.show.reviews.dashboard', [$this->organisation->slug, $this->shop->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Catalogue/ShopReviewsDashboard')
            ->has('title'));
});

test('UI reviews overall', function () {
    $this->withoutExceptionHandling();
    storeProductReview();
    get(route('grp.org.shops.show.reviews.overall', [$this->organisation->slug, $this->shop->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('title'));
});

test('UI reviews products', function () {
    $this->withoutExceptionHandling();
    storeProductReview();
    get(route('grp.org.shops.show.reviews.products', [$this->organisation->slug, $this->shop->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('title'));
});

test('UI reviews families', function () {
    $this->withoutExceptionHandling();
    StoreReview::make()->action($this->family, ['customer_id' => $this->customer->id, 'rating' => 4, 'message' => 'f']);
    get(route('grp.org.shops.show.reviews.families', [$this->organisation->slug, $this->shop->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('title'));
});

test('UI reviews backlog', function () {
    $this->withoutExceptionHandling();
    storeProductReview();
    get(route('grp.org.shops.show.reviews.backlog', [$this->organisation->slug, $this->shop->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('title'));
});

test('approve review via controller', function () {
    $review = storeProductReview();
    $review->update(['state' => ReviewStateEnum::WAITING_APPROVAL, 'review_status' => ReviewStatusEnum::PENDING, 'published_at' => null]);

    patch(route('grp.models.review.approve', [$review->id]))->assertRedirect();

    expect($review->refresh()->state)->toBe(ReviewStateEnum::PUBLISHED);
});

test('reject review via controller', function () {
    $review = storeProductReview();

    patch(route('grp.models.review.reject', [$review->id]))->assertRedirect();

    expect($review->refresh()->state)->toBe(ReviewStateEnum::REJECTED);
});

test('update review via controller', function () {
    $this->withoutExceptionHandling();
    $review = storeProductReview();

    patch(route('grp.models.review.update', [$review->id]), [
        'message' => 'Controller updated',
        'rating'  => 4,
    ], ['Accept' => 'application/json'])->assertOk();

    expect($review->refresh()->message)->toBe('Controller updated');
});

test('update review via controller coerces empty and null sentinels', function () {
    $this->withoutExceptionHandling();
    $review = storeProductReview();

    patch(route('grp.models.review.update', [$review->id]), [
        'message'     => 'Sentinel update',
        'rating'      => 4,
        'rating_a'    => '',
        'customer_id' => 'null',
    ], ['Accept' => 'application/json'])->assertOk();

    $review->refresh();
    expect($review->message)->toBe('Sentinel update')
        ->and($review->customer_id)->toBeNull();
});

test('delete review via controller', function () {
    $review = storeProductReview();

    delete(route('grp.models.review.delete', [$review->id]))->assertRedirect();

    expect(Review::find($review->id))->toBeNull();
});

test('review reply endpoints via controller', function () {
    $review = storeProductReview();

    post(route('grp.models.review.reply.store'), ['reviewable_id' => $review->id, 'body' => 'Thanks a lot'])
        ->assertStatus(201);
    expect($review->refresh()->replied)->toBeTrue();

    patch(route('grp.models.review.reply.update', [$review->id]), ['body' => 'Edited via controller'])
        ->assertOk();
    expect($review->refresh()->reply_message)->toBe('Edited via controller');

    delete(route('grp.models.review.reply.delete', [$review->id]))->assertRedirect();
    expect($review->refresh()->replied)->toBeFalse();
});

test('review customers endpoints via controller', function () {
    storeProductReview();

    get(route('grp.models.review.customers.product', [$this->product->id]))->assertOk();
    get(route('grp.models.review.customers.shop', [$this->shop->id]))->assertOk();
    get(route('grp.models.review.customers', [$this->family->id]))->assertOk();
});

test('get reviews json response', function () {
    storeProductReview();

    $action    = GetReviews::make();
    $paginator = $action->handle([
        'scope'         => ReviewScopeEnum::PRODUCT->value,
        'reviewable_id' => $this->product->id,
        'state'         => ReviewStateEnum::PUBLISHED->value,
    ]);

    expect($action->jsonResponse($paginator))->not->toBeNull();
});

test('get reviewable reviews handle and json response', function () {
    storeProductReview();
    StoreReview::make()->action($this->shop, ['customer_id' => $this->customer->id, 'rating' => 5, 'message' => 'shop']);
    StoreReview::make()->action($this->family, ['customer_id' => $this->customer->id, 'rating' => 4, 'message' => 'family']);

    $action    = GetReviewableReviews::make();
    $paginator = $action->handle($this->product, ['sort' => 'rating']);
    expect($paginator->total())->toBeGreaterThanOrEqual(1)
        ->and($action->jsonResponse($paginator))->not->toBeNull();

    expect(GetReviewableReviews::make()->handle($this->shop, ['sort' => '-rating'])->total())->toBeGreaterThanOrEqual(1)
        ->and(GetReviewableReviews::make()->handle($this->family, ['sort' => 'created_at'])->total())->toBeGreaterThanOrEqual(1);
});

test('detect and translate commands', function () {
    storeProductReview();

    $languageId = Language::first()->id;
    Review::query()->whereNull('language_id')->update(['language_id' => $languageId]);
    Review::query()->whereNull('reply_language_id')->whereNotNull('reply_message')->update(['reply_language_id' => $languageId]);

    $this->artisan('reviews:detect-languages')->assertExitCode(0);
    $this->artisan('reviews:detect-reply-languages')->assertExitCode(0);
    $this->artisan('reviews:translate')->assertExitCode(0);
    $this->artisan('reviews:translate-reply-languages')->assertExitCode(0);
});

test('trustpilot import', function () {
    $code = $this->shop->language->code;

    $rows = collect([
        collect(array_fill(0, 18, 'header')),
        ['tp-1', '2026-01-01 10:00:00', 'consumer-1', '', $this->customer->email, 'Title', 'Solid product', 5, '', '', '', '', $code, '', '', '', '', ''],
        ['tp-2', '2026-01-02 10:00:00', 'consumer-2', '', $this->customer->email, 'Title2', 'With reply and order', 4, '', '123456', 'Thanks for your review', $this->user->username, $code, '', '', '', '', '2026-01-03 10:00:00'],
    ]);

    (new TrustPilotImport($this->shop))->collection($rows);

    expect(Review::where('external_id', 'tp-1')->exists())->toBeTrue()
        ->and(Review::where('external_id', 'tp-2')->first()->replied)->toBeTrue();
});

test('review io import product and shop csv', function () {
    $productCode = $this->product->code;
    $email       = $this->customer->email;

    $productRows = collect([
        collect(['order_id', 'email', 'comments', 'rating', 'reply', 'date_created', 'product_sku', 'widget_fingerprint', 'published_images', 'published_videos']),
        collect(['123456', $email, 'Great', 5, '', '2026-01-01', $productCode, 'rio-fp-1', '', '']),
    ]);
    (new ReviewIOImport($this->shop))->collection($productRows);

    $shopRows = collect([
        collect(['nps', 'order_id', 'email', 'comments', 'rating', 'reply', 'date_created', 'published_images', 'published_videos', 'widget_fingerprint']),
        collect([10, '', $email, 'Nice', 4, 'Our reply', '2026-02-02', '', '', 'rio-fp-2']),
    ]);
    (new ReviewIOImport($this->shop))->collection($shopRows);

    expect(Review::where('external_id', 'rio-fp-1')->exists())->toBeTrue()
        ->and(Review::where('external_id', 'rio-fp-2')->first()->replied)->toBeTrue();
});

test('csv import command guards', function () {
    $this->artisan('import:trustpilot_csv', ['filename' => 'nope.csv', 'shop' => ''])->assertExitCode(1);
    $this->artisan('import:trustpilot_csv', ['filename' => 'does-not-exist.csv', 'shop' => $this->shop->slug])->assertExitCode(1);
    $this->artisan('import:review_io_csv', ['filename' => 'nope.csv', 'shop' => ''])->assertExitCode(1);
    $this->artisan('import:review_io_csv', ['filename' => 'does-not-exist.csv', 'shop' => $this->shop->slug])->assertExitCode(1);
});

test('index reviews in iris scope handlers', function () {
    $route = new IlluminateRoute('GET', 'iris/reviews', ['as' => 'iris.reviews']);
    request()->setRouteResolver(fn () => $route);

    storeProductReview();
    StoreReview::make()->action($this->shop, ['customer_id' => $this->customer->id, 'rating' => 5, 'message' => 'shop level']);
    StoreReview::make()->action($this->family, ['customer_id' => $this->customer->id, 'rating' => 4, 'message' => 'family level']);
    StoreReview::make()->action($this->order, ['customer_id' => $this->customer->id, 'order_id' => $this->order->id, 'rating' => 5, 'message' => 'order level']);

    $indexer = IndexReviewsInIris::make();

    expect($indexer->handleAllScopeReviews(shop: $this->shop, prefix: 'all')->total())->toBeGreaterThanOrEqual(1)
        ->and($indexer->handleProductScopeReviews(shop: $this->shop, prefix: 'product')->total())->toBeGreaterThanOrEqual(0)
        ->and($indexer->handleFamilyScopeReviews(shop: $this->shop, prefix: 'family')->total())->toBeGreaterThanOrEqual(0)
        ->and($indexer->handleCompanyScopeReviews(shop: $this->shop, prefix: 'company')->total())->toBeGreaterThanOrEqual(0)
        ->and($indexer->handleAllScopeReviews(shop: $this->shop)->total())->toBeGreaterThanOrEqual(1);

    $indexer->handleSpecificProductReviews($this->product);
    $indexer->handleSpecificFamilyReviews($this->family);
    $indexer->handleProductsInFamilyReviews($this->family);
    $indexer->handle($this->product);
    $indexer->handle($this->family);

    expect($indexer->avgReview($this->shop))->not->toBeNull()
        ->and($indexer->avgReview($this->product))->not->toBeNull()
        ->and($indexer->avgReview($this->family))->not->toBeNull()
        ->and($indexer->includesOtherShops($this->shop))->toBeFalse();

    $closure = $indexer->tableStructure('all', $this->shop, [ReviewScopeEnum::SHOP, ReviewScopeEnum::PRODUCT]);
    $closure(new InertiaTable(request()));
});
