<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-17h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\Goods\ProductDevelopment\GetProductDevelopmentStageGate;
use App\Actions\Goods\ProductDevelopment\SendProductDevelopmentReminders;
use App\Actions\Goods\ProductDevelopment\Hydrators\ProductDevelopmentHydrateChecklist;
use App\Actions\Goods\ProductDevelopment\MoveProductDevelopmentToNextStage;
use App\Actions\Goods\ProductDevelopment\LinkProductDevelopmentSupplierProduct;
use App\Actions\Goods\ProductDevelopment\StoreProductDevelopment;
use App\Actions\Goods\ProductDevelopment\StoreProductDevelopmentComment;
use App\Actions\Goods\ProductDevelopment\Design\ReviewProductDevelopmentDesignVersion;
use App\Actions\Goods\ProductDevelopment\Design\StoreProductDevelopmentDesignVersion;
use App\Actions\Goods\ProductDevelopment\Design\SubmitProductDevelopmentDesignVersion;
use App\Actions\Goods\ProductDevelopment\Design\UpdateProductDevelopmentDesignVersion;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentDesignRoundStatusEnum;
use App\Actions\Goods\ProductDevelopment\UpdateProductDevelopmentChecklistItem;
use App\Actions\Goods\ProductDevelopment\SyncProductDevelopmentMembers;
use App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFiles;
use App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentActivity;
use App\Actions\Goods\ProductDevelopment\UI\ShowProductDevelopmentsBoard;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentChecklistItemStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentTargetFieldEnum;
use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Actions\UI\Grp\Layout\GetGroupNavigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Illuminate\Validation\ValidationException;

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
    $this->user       = $this->adminGuest->getUser();
    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

function testAgent(object $test): \App\Models\SupplyChain\Agent
{
    return \App\Models\SupplyChain\Agent::where('group_id', $test->group->id)->orderBy('id')->first()
        ?? \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($test->group, \App\Models\SupplyChain\Agent::factory()->definition());
}

function newAgentUser(object $test, ?\App\Models\SupplyChain\Agent $agent = null): \App\Models\SysAdmin\User
{
    $agent ??= testAgent($test);
    $user    = newGuestUser($test);
    \Illuminate\Support\Facades\DB::table('user_has_authorised_models')->insert([
        'user_id'    => $user->id,
        'org_id'     => $agent->organisation_id,
        'model_type' => 'Organisation',
        'model_id'   => $agent->organisation_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function storeDevelopmentWithNewSkus(object $test, int $numberSkus = 4): ProductDevelopment
{
    $suffix = Str::upper(Str::random(5));

    return StoreProductDevelopment::make()->action($test->group, $test->user, [
        'name'     => 'Lavender candles '.$suffix,
        'type'     => 'new',
        'agent_id' => testAgent($test)->id,
        'members' => [
            ['user_id' => $test->user->id, 'role' => ProductDevelopmentMemberRoleEnum::BUYER->value],
            ['user_id' => $test->user->id, 'role' => ProductDevelopmentMemberRoleEnum::APPROVER->value],
        ],
        'items'   => collect(range(1, $numberSkus))->map(fn (int $index) => [
            'code' => 'PD-'.$suffix.'-'.$index,
            'name' => 'Lavender candle '.$index,
        ])->all(),
    ]);
}

test('a buyer creates a development with 4 new SKUs and members', function () {
    $developmentsBefore = ProductDevelopment::count();

    $productDevelopment = storeDevelopmentWithNewSkus($this);

    expect(ProductDevelopment::count())->toBe($developmentsBefore + 1)
        ->and($productDevelopment->code)->toBe('DEV-'.str_pad((string) $productDevelopment->number, 4, '0', STR_PAD_LEFT))
        ->and($productDevelopment->owner_id)->toBe($this->user->id)
        ->and($productDevelopment->current_position)->toBe(1)
        ->and($productDevelopment->items)->toHaveCount(4)
        ->and($productDevelopment->members)->toHaveCount(2)
        ->and($productDevelopment->stages)->toHaveCount(count(ProductDevelopmentStageEnum::cases()));

    $productDevelopment->items->each(function ($item) {
        expect($item->tradeUnit->status)->toBe(TradeUnitStatusEnum::IN_PROCESS);
    });

    $brief = $productDevelopment->stages->firstWhere('stage', ProductDevelopmentStageEnum::BRIEF);
    expect($brief->state)->toBe(ProductDevelopmentStageStateEnum::IN_PROGRESS)
        ->and($brief->started_at)->not->toBeNull()
        ->and($productDevelopment->stages->where('position', 2))->toHaveCount(3);

    $stats = $productDevelopment->stats->fresh();
    expect($stats->number_items)->toBe(4)
        ->and($stats->number_checklist_items)->toBeGreaterThan(0)
        ->and($stats->number_required_missing)->toBe(
            $productDevelopment->checklistItems()->where('required', true)->where('state', ProductDevelopmentChecklistItemStateEnum::MISSING->value)->count()
        );

    return $productDevelopment;
});

test('stage move is blocked with a reason while required items are missing', function (ProductDevelopment $productDevelopment) {
    $gate = GetProductDevelopmentStageGate::run($productDevelopment);

    expect($gate['can_move'])->toBeFalse()
        ->and($gate['reason'])->toContain(__('Buyer'))
        ->and($gate['missing'])->toHaveKey(ProductDevelopmentMemberRoleEnum::BUYER->value);

    expect(fn () => MoveProductDevelopmentToNextStage::make()->action($productDevelopment, $this->user))
        ->toThrow(ValidationException::class);

    expect($productDevelopment->fresh()->current_position)->toBe(1);
})->depends('a buyer creates a development with 4 new SKUs and members');

test('stage moves once the gated items are complete or N/A', function (ProductDevelopment $productDevelopment) {
    $buyerItems = $productDevelopment->checklistItems()->where('role', ProductDevelopmentMemberRoleEnum::BUYER->value)->get();
    $buyerItems->first()->update(['state' => ProductDevelopmentChecklistItemStateEnum::COMPLETE]);
    $buyerItems->skip(1)->each(fn ($item) => $item->update(['state' => ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE, 'na_reason' => 'Not needed for this family']));
    ProductDevelopmentHydrateChecklist::run($productDevelopment);

    $productDevelopment = MoveProductDevelopmentToNextStage::make()->action($productDevelopment->fresh(), $this->user);

    expect($productDevelopment->current_position)->toBe(2);

    $stages = $productDevelopment->stages()->get();
    expect($stages->firstWhere('stage', ProductDevelopmentStageEnum::BRIEF)->state)->toBe(ProductDevelopmentStageStateEnum::COMPLETED)
        ->and($stages->where('position', 2)->every(fn ($stage) => $stage->state === ProductDevelopmentStageStateEnum::IN_PROGRESS))->toBeTrue();

    $sentences = collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('sentence');
    expect($sentences)->toContain(__('Moved from :from to :to', ['from' => ProductDevelopmentStageEnum::positionLabel(1), 'to' => ProductDevelopmentStageEnum::positionLabel(2)]))
        ->and($sentences)->toContain(__('Created :code', ['code' => $productDevelopment->code]))
        ->and($sentences)->toContain(trans_choice('Added :count SKU|Added :count SKUs', 4, ['count' => 4]))
        ->and(collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('changes')->flatten(1)->pluck('label'))->not->toContain('Current Position');

    $gate = GetProductDevelopmentStageGate::run($productDevelopment);
    expect($gate['can_move'])->toBeFalse()
        ->and($gate['missing'])->toHaveKeys([
            ProductDevelopmentMemberRoleEnum::AGENT->value,
            ProductDevelopmentMemberRoleEnum::PURCHASING->value,
            ProductDevelopmentMemberRoleEnum::COMPLIANCE->value,
        ]);
})->depends('a buyer creates a development with 4 new SKUs and members');

test('filling a trade unit field completes its checklist item from the live value', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $tradeUnit          = $productDevelopment->items->first()->tradeUnit;
    $tradeUnit->update(['tariff_code' => '34060000']);

    ProductDevelopmentHydrateChecklist::run($productDevelopment);

    $tariffItem = $productDevelopment->checklistItems()->where('key', 'trade_unit.tariff_code')->first();
    expect($tariffItem->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE);
});

test('a new SKU code that already exists is rejected', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $existingCode       = $productDevelopment->items->first()->tradeUnit->code;

    expect(fn () => StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'  => 'Duplicate code',
        'type'  => 'redesign',
        'items' => [['code' => $existingCode, 'name' => 'Clash']],
    ]))->toThrow(ValidationException::class);
});

test('the list page shows developments with stage and missing count', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 2);

    get(route('grp.product_developments.index', ['filter' => ['global' => $productDevelopment->code]]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/ProductDevelopments')
            ->has('pageHead')
            ->has('viewSwitch', 3)
            ->has('filterBar.counts.status')
            ->where('filterBar.statuses', fn ($statuses) => collect($statuses)->pluck('color', 'key')->all() === collect(ProductDevelopmentStatusEnum::cases())->mapWithKeys(fn ($status) => [$status->value => $status->color()])->all())
            ->where('filterBar.flags', fn ($flags) => collect($flags)->pluck('color', 'key')->all() === ['missing' => 'amber', 'overdue' => 'red'])
            ->has('filterBar.options.stage', ProductDevelopmentStageEnum::lastPosition())
            ->has('data.data', 1)
            ->where('data.data.0.code', $productDevelopment->code)
            ->where('data.data.0.stage_position', 1)
            ->where('data.data.0.number_required_missing', $productDevelopment->stats->fresh()->number_required_missing));
});

test('the board shows the same development in its stage column with the same missing count', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 2);
    $missing            = $productDevelopment->stats->fresh()->number_required_missing;

    get(route('grp.product_developments.board'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/ProductDevelopmentsBoard')
            ->has('columns', 7)
            ->where('columns.0.position', 1)
            ->where('columns.0.cards', fn ($cards) => collect($cards)->contains(fn ($card) => $card['code'] === $productDevelopment->code && $card['number_required_missing'] === $missing))
            ->where('columns.0.cards', function ($cards) use ($productDevelopment, $missing) {
                $card = collect($cards)->firstWhere('code', $productDevelopment->code);

                return array_sum($card['missing_by_role']) === $missing
                    && $card['number_done'] === $card['number_required'] - $missing
                    && $card['owner_user']['id'] === $this->user->id
                    && $card['type_color'] === 'teal'
                    && $card['current_stage_due_at'] !== null;
            })
            ->has('roles.agent.icon'));
});

test('the development page shows the header, stage bar and a blocked move with its reason', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 2);

    get(route('grp.product_developments.show', ['productDevelopment' => $productDevelopment->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/ProductDevelopment')
            ->has('breadcrumbs', 3)
            ->has('header')
            ->has('overview.strip', count(ProductDevelopmentStageEnum::cases()) + 1)
            ->has('overview.roles', 5)
            ->has('overview.products', 2)
            ->where('overview.waiting_on', fn ($rows) => count($rows) > 0 && collect($rows)->every(fn ($row) => in_array($row['tab'], ['brief', 'products', 'compliance'], true)
                && \App\Models\Goods\ProductDevelopmentChecklistItem::whereKey((int) str_replace('pd-check-', '', $row['target']))->where('product_development_id', $productDevelopment->id)->exists()))
            ->where('header.type.color', 'teal')
            ->where('recentActivity', fn ($entries) => count($entries) > 0 && count($entries) <= 5)
            ->where('stageMove.can_move', false)
            ->where('stageMove.reason', fn ($reason) => str_contains($reason, __('Buyer')))
            ->where('tabs.current', 'brief')
            ->has('brief.members', 2)
            ->has('tabs.navigation', 7)
            ->where('tabs.navigation.products.number', 2));
});

test('the create page and the store endpoint create a development', function () {
    get(route('grp.product_developments.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/CreateProductDevelopment')
            ->has('options.roles', count(ProductDevelopmentMemberRoleEnum::cases())));

    $suffix = Str::upper(Str::random(5));
    $before = ProductDevelopment::count();

    post(route('grp.models.product_development.store'), [
        'name'    => 'Rose soaps '.$suffix,
        'type'    => 'new',
        'members' => [['user_id' => $this->user->id, 'role' => 'buyer']],
        'items'   => [['code' => 'RS-'.$suffix, 'name' => 'Rose soap']],
    ])->assertRedirect();

    expect(ProductDevelopment::count())->toBe($before + 1);
});

test('the group menu shows Product development just above Trade Units', function () {
    $navigation = GetGroupNavigation::run($this->user);
    $keys       = array_keys($navigation);

    expect($keys)->toContain('product-developments')
        ->and(array_search('product-developments', $keys))->toBe(array_search('trade-units', $keys) - 1);
});

test('the filter bar counts match the list when a quick filter is applied', function () {
    storeDevelopmentWithNewSkus($this, 1);

    $response = get(route('grp.product_developments.index'));
    $counts   = $response->viewData('page')['props']['filterBar']['counts'];

    get(route('grp.product_developments.index', ['filter' => ['missing' => 'yes'], 'perPage' => 500]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.meta.total', $counts['missing'])
            ->where('data.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['number_required_missing'] > 0)));

    get(route('grp.product_developments.board', ['filter' => ['status' => 'active']]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns', fn ($columns) => collect($columns)->sum(fn ($column) => count($column['cards'])) === min($counts['status']['active'], ShowProductDevelopmentsBoard::MAX_CARDS)));
});

test('the owner changes the target dates and the change shows in the activity feed', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);

    patch(route('grp.models.product_development.update', ['productDevelopment' => $productDevelopment->id]), [
        'target_landing_date' => '2026-11-02',
        'target_online_date'  => '2026-11-20',
    ])->assertRedirect();

    $productDevelopment->refresh();
    expect($productDevelopment->target_online_date->toDateString())->toBe('2026-11-20');

    $changes = collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('changes')->flatten(1);
    expect($changes->firstWhere('label', __('Target online'))['new'])->toBe('20 Nov 2026');

    patch(route('grp.models.product_development.update', ['productDevelopment' => $productDevelopment->id]), [
        'target_landing_date' => '2026-12-01',
        'target_online_date'  => '2026-11-20',
    ])->assertSessionHasErrors('target_online_date');
});

test('the owner adds and removes members and edits markets from the brief, and it shows in the activity feed', function () {
    $productDevelopment = StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'  => 'No members yet '.Str::upper(Str::random(5)),
        'type'  => 'new',
        'items' => [['code' => 'NM-'.Str::upper(Str::random(5)), 'name' => 'No members SKU']],
    ]);
    expect($productDevelopment->members()->count())->toBe(0);

    get(route('grp.product_developments.show', ['productDevelopment' => $productDevelopment->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('editor.options.users')
            ->where('editor.values.members', []));

    patch(route('grp.models.product_development.members.sync', ['productDevelopment' => $productDevelopment->id]), [
        'members' => [['user_id' => $this->user->id, 'role' => ProductDevelopmentMemberRoleEnum::BUYER->value]],
    ])->assertRedirect();
    expect($productDevelopment->members()->count())->toBe(1);

    patch(route('grp.models.product_development.update', ['productDevelopment' => $productDevelopment->id]), [
        'markets' => ['uk', 'eu'],
    ])->assertRedirect();
    expect($productDevelopment->fresh()->markets)->toBe(['uk', 'eu']);

    patch(route('grp.models.product_development.members.sync', ['productDevelopment' => $productDevelopment->id]), [
        'members' => [],
    ])->assertRedirect();
    expect($productDevelopment->members()->count())->toBe(0);

    $sentences = collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('sentence');
    expect($sentences)->toContain(__('Added members'))
        ->and($sentences)->toContain(__('Removed members'));
});

test('a new SDS replaces the old one on the trade unit and keeps it as an earlier version', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $item               = $productDevelopment->items->first();
    $tradeUnit          = $item->tradeUnit;

    UploadProductDevelopmentFiles::make()->action($productDevelopment, $this->user, [
        'files'                       => [UploadedFile::fake()->createWithContent('sds-v1.pdf', 'SDS version one '.Str::random(20))],
        'scope'                       => 'sds',
        'product_development_item_id' => $item->id,
    ]);
    $firstMediaId = $tradeUnit->attachments()->wherePivot('scope', 'sds')->value('media.id');
    expect($firstMediaId)->not->toBeNull();

    UploadProductDevelopmentFiles::make()->action($productDevelopment, $this->user, [
        'files'                       => [UploadedFile::fake()->createWithContent('sds-v2.pdf', 'SDS version two '.Str::random(20))],
        'scope'                       => 'sds',
        'product_development_item_id' => $item->id,
    ]);

    $onTradeUnit = $tradeUnit->attachments()->wherePivot('scope', 'sds')->pluck('media.id');
    expect($onTradeUnit)->toHaveCount(1)
        ->and($onTradeUnit->first())->not->toBe($firstMediaId);

    $files = $productDevelopment->files()->where('scope', 'sds')->orderBy('version')->get();
    expect($files)->toHaveCount(2)
        ->and($files[0]->version)->toBe(1)
        ->and($files[0]->superseded_at)->not->toBeNull()
        ->and($files[0]->media_id)->toBe($firstMediaId)
        ->and($files[1]->version)->toBe(2)
        ->and($files[1]->superseded_at)->toBeNull();

    $sentences = collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('sentence');
    expect($sentences->contains(fn ($sentence) => str_contains($sentence, 'SDS (v2)') && str_contains($sentence, $tradeUnit->code)))->toBeTrue();
});

test('an agent uploads six factory photos for one SKU and none of them replaces another', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $item               = $productDevelopment->items->first();

    UploadProductDevelopmentFiles::make()->action($productDevelopment, $this->user, [
        'files'                       => collect(range(1, 6))->map(fn (int $index) => UploadedFile::fake()->createWithContent("photo-$index.jpg", "photo $index ".Str::random(20)))->all(),
        'scope'                       => 'factory_photo',
        'product_development_item_id' => $item->id,
    ]);

    $photos = $productDevelopment->files()->where('scope', 'factory_photo')->get();
    expect($photos)->toHaveCount(6)
        ->and($photos->whereNotNull('superseded_at'))->toHaveCount(0)
        ->and($photos->pluck('media_id')->unique())->toHaveCount(6);

    expect(collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('sentence')->first())->toContain('6');

    get(route('grp.product_developments.show', ['productDevelopment' => $productDevelopment->slug, 'tab' => 'files']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tabs.navigation.files.number', 6)
            ->has('files.groups', 1)
            ->has('files.groups.0.current', 6)
            ->where('files.groups.0.sku', $item->tradeUnit->code)
            ->has('files.upload.scopes'));
});

test('a per-SKU file needs its SKU', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);

    expect(fn () => UploadProductDevelopmentFiles::make()->action($productDevelopment, $this->user, [
        'files' => [UploadedFile::fake()->createWithContent('sds.pdf', 'no sku '.Str::random(20))],
        'scope' => 'sds',
    ]))->toThrow(ValidationException::class);
});

function newGuestUser(object $test): \App\Models\SysAdmin\User
{
    return \App\Actions\SysAdmin\Guest\StoreGuest::make()->action($test->group, [...\App\Models\SysAdmin\Guest::factory()->definition(), 'positions' => []])->getUser();
}

function developmentTasks(ProductDevelopment $productDevelopment): \Illuminate\Support\Collection
{
    return \App\Models\Tasks\StaffTask::where('model_type', 'ProductDevelopment')->where('model_id', $productDevelopment->id)->get();
}

test('creating a development opens the agent and compliance tasks once, for the right people', function () {
    $agent      = newAgentUser($this);
    $compliance = newGuestUser($this);

    $productDevelopment = StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'     => 'Tasks check '.Str::upper(Str::random(5)),
        'agent_id' => testAgent($this)->id,
        'type'    => 'new',
        'members' => [
            ['user_id' => $agent->id, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value],
            ['user_id' => $compliance->id, 'role' => ProductDevelopmentMemberRoleEnum::COMPLIANCE->value],
        ],
        'items'   => [['code' => 'TK-'.Str::upper(Str::random(5)), 'name' => 'Tasks SKU']],
    ]);

    $tasks = developmentTasks($productDevelopment)->keyBy(fn ($task) => $task->data['product_development_event']);
    expect($tasks->keys()->sort()->values()->all())->toBe([CreateProductDevelopmentTask::SPECS, CreateProductDevelopmentTask::COMPLIANCE_SCOPE])
        ->and($tasks[CreateProductDevelopmentTask::SPECS]->assignee_id)->toBe($agent->id)
        ->and($tasks[CreateProductDevelopmentTask::SPECS]->due_at)->not->toBeNull()
        ->and($tasks[CreateProductDevelopmentTask::COMPLIANCE_SCOPE]->assignee_id)->toBe($compliance->id);

    expect(CreateProductDevelopmentTask::run($productDevelopment, CreateProductDevelopmentTask::SPECS))->toBeNull()
        ->and(developmentTasks($productDevelopment))->toHaveCount(2);
});

test('entering artwork to supplier asks purchasing to send it, and once done asks the agent to confirm', function () {
    $purchasing = newGuestUser($this);
    $agent      = newAgentUser($this);
    $productDevelopment = StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'     => 'Artwork check '.Str::upper(Str::random(5)),
        'agent_id' => testAgent($this)->id,
        'type'    => 'new',
        'members' => [
            ['user_id' => $purchasing->id, 'role' => ProductDevelopmentMemberRoleEnum::PURCHASING->value],
            ['user_id' => $agent->id, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value],
        ],
        'items'   => [['code' => 'AW-'.Str::upper(Str::random(5)), 'name' => 'Artwork SKU']],
    ]);
    $productDevelopment->checklistItems()->update(['state' => ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE->value, 'na_reason' => 'test']);
    $productDevelopment->update([
        'current_position' => ProductDevelopmentStageEnum::FINAL_APPROVAL->position(),
        'data'             => ['sign_off' => ['buyer' => ['user_id' => $this->user->id, 'name' => 'Buyer', 'at' => now()->toIso8601String()], 'compliance' => ['user_id' => $this->user->id, 'name' => 'Compliance', 'at' => now()->toIso8601String()]], 'artwork_locked_at' => now()->toIso8601String()],
    ]);
    ProductDevelopmentHydrateChecklist::run($productDevelopment);

    MoveProductDevelopmentToNextStage::make()->action($productDevelopment->fresh(), $this->user);

    $sendTask = developmentTasks($productDevelopment)->first(fn ($task) => $task->data['product_development_event'] === CreateProductDevelopmentTask::SEND_ARTWORK);
    expect($sendTask)->not->toBeNull()
        ->and($sendTask->assignee_id)->toBe($purchasing->id);

    \App\Actions\Tasks\UpdateStaffTask::run($sendTask, $purchasing, ['status' => 'done']);

    $confirmTask = developmentTasks($productDevelopment)->first(fn ($task) => $task->data['product_development_event'] === CreateProductDevelopmentTask::CONFIRM_ARTWORK);
    expect($confirmTask)->not->toBeNull()
        ->and($confirmTask->assignee_id)->toBe($agent->id);
});

test('an SDS upload notifies compliance but not the uploader', function () {
    $compliance         = newGuestUser($this);
    $productDevelopment = StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'    => 'Notify check '.Str::upper(Str::random(5)),
        'type'    => 'new',
        'members' => [['user_id' => $compliance->id, 'role' => ProductDevelopmentMemberRoleEnum::COMPLIANCE->value]],
        'items'   => [['code' => 'NT-'.Str::upper(Str::random(5)), 'name' => 'Notify SKU']],
    ]);
    $complianceBefore = $compliance->notifications()->count();
    $uploaderBefore   = $this->user->notifications()->count();

    UploadProductDevelopmentFiles::make()->action($productDevelopment, $this->user, [
        'files'                       => [UploadedFile::fake()->createWithContent('sds.pdf', 'notify sds '.Str::random(20))],
        'scope'                       => 'sds',
        'product_development_item_id' => $productDevelopment->items->first()->id,
    ]);

    expect($compliance->notifications()->count())->toBe($complianceBefore + 1)
        ->and($this->user->notifications()->count())->toBe($uploaderBefore);
});

test('three days before a stage is due its role is reminded, and the owner gets a digest', function () {
    $agent              = newAgentUser($this);
    $productDevelopment = StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'     => 'Reminder check '.Str::upper(Str::random(5)),
        'agent_id' => testAgent($this)->id,
        'type'    => 'new',
        'members' => [['user_id' => $agent->id, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value]],
        'items'   => [['code' => 'RM-'.Str::upper(Str::random(5)), 'name' => 'Reminder SKU']],
    ]);
    $today = \Illuminate\Support\Carbon::today();
    $productDevelopment->stages()->where('stage', ProductDevelopmentStageEnum::PRODUCT_SPECS->value)->update(['state' => ProductDevelopmentStageStateEnum::IN_PROGRESS->value, 'due_at' => $today->copy()->addDays(3)->toDateString()]);

    $agentBefore = $agent->notifications()->count();
    $ownerBefore = $this->user->notifications()->count();

    SendProductDevelopmentReminders::run($today);

    expect($agent->notifications()->count())->toBe($agentBefore + 1)
        ->and($this->user->notifications()->count())->toBeGreaterThan($ownerBefore);
});

test('the owner changes a stage due date and it shows in the activity feed', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $stage              = $productDevelopment->stages()->where('stage', ProductDevelopmentStageEnum::CONCEPT->value)->first();

    patch(route('grp.models.product_development.stage.due_date', ['productDevelopmentStage' => $stage->id]), ['due_at' => '2026-12-24'])->assertRedirect();

    expect($stage->fresh()->due_at->toDateString())->toBe('2026-12-24')
        ->and(collect(GetProductDevelopmentActivity::run($productDevelopment))->pluck('sentence'))->toContain(__('Changed the due date of :stage', ['stage' => __('Concept')]));
});

test('a role task waits for someone holding the role, then later holders join as collaborators', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $specsTask          = fn () => developmentTasks($productDevelopment)->first(fn ($task) => $task->data['product_development_event'] === CreateProductDevelopmentTask::SPECS);

    expect($specsTask())->toBeNull();

    $firstAgent  = newAgentUser($this);
    $secondAgent = newAgentUser($this);
    $members     = $productDevelopment->members()->get(['user_id', 'role'])->map(fn ($member) => ['user_id' => $member->user_id, 'role' => $member->role->value])->all();

    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [...$members, ['user_id' => $firstAgent->id, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value]]]);
    expect($specsTask()?->assignee_id)->toBe($firstAgent->id);

    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [
        ...$members,
        ['user_id' => $firstAgent->id, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value],
        ['user_id' => $secondAgent->id, 'role' => ProductDevelopmentMemberRoleEnum::AGENT->value],
    ]]);

    $task = $specsTask();
    expect($task->assignee_id)->toBe($firstAgent->id)
        ->and($task->collaborators()->pluck('users.id')->all())->toContain($secondAgent->id)
        ->and(developmentTasks($productDevelopment)->filter(fn ($row) => $row->data['product_development_event'] === CreateProductDevelopmentTask::SPECS))->toHaveCount(1);
});

function developmentWithSupplier(object $test, array $members = []): ProductDevelopment
{
    $agent    = \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($test->group, \App\Models\SupplyChain\Agent::factory()->definition());
    $supplier = \App\Actions\SupplyChain\Supplier\StoreSupplier::make()->action($agent, \App\Models\SupplyChain\Supplier::factory()->definition());

    return StoreProductDevelopment::make()->action($test->group, $test->user, [
        'name'        => 'Checklist check '.Str::upper(Str::random(5)),
        'type'        => 'new',
        'supplier_id' => $supplier->id,
        'members'     => $members,
        'items'       => [['code' => 'CL-'.Str::upper(Str::random(5)), 'name' => 'Checklist SKU']],
    ]);
}

test('saving a spec item writes the real trade unit field and records who saved it', function () {
    $agent              = newAgentUser($this);
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    \App\Actions\Goods\ProductDevelopment\SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [['user_id' => $agent->id, 'role' => 'agent']]]);
    $item         = $productDevelopment->items->first();
    $netWeight    = $productDevelopment->checklistItems()->where('product_development_item_id', $item->id)->where('key', 'trade_unit.net_weight')->first();
    $missingBefore = $productDevelopment->stats->fresh()->number_required_missing;

    actingAs($agent);
    patch(route('grp.models.product_development.checklist_item.update', ['productDevelopmentChecklistItem' => $netWeight->id]), ['operation' => 'save', 'value' => 250])->assertRedirect();

    expect($item->tradeUnit->fresh()->net_weight)->toBe(250)
        ->and($netWeight->fresh()->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE)
        ->and($netWeight->fresh()->completed_by_id)->toBe($agent->id)
        ->and($productDevelopment->stats->fresh()->number_required_missing)->toBe($missingBefore - 1);

    $compliance = $productDevelopment->checklistItems()->where('role', 'compliance')->first();
    patch(route('grp.models.product_development.checklist_item.update', ['productDevelopmentChecklistItem' => $compliance->id]), ['operation' => 'save', 'value' => '3406'])->assertForbidden();
});

test('the buyer answers and marks N/A with a reason, then the brief can move on', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    [$first, $second]   = $productDevelopment->checklistItems()->where('role', 'buyer')->orderBy('id')->get()->all();

    UpdateProductDevelopmentChecklistItem::make()->action($first, $this->user, ['operation' => 'save', 'value' => 'Women 25-45, £12-15']);
    expect(fn () => UpdateProductDevelopmentChecklistItem::make()->action($second, $this->user, ['operation' => 'not_applicable']))->toThrow(ValidationException::class);
    UpdateProductDevelopmentChecklistItem::make()->action($second, $this->user, ['operation' => 'not_applicable', 'na_reason' => 'Same mood board as the old range']);

    expect($first->fresh()->value)->toBe(['text' => 'Women 25-45, £12-15'])
        ->and($second->fresh()->state)->toBe(ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE)
        ->and(GetProductDevelopmentStageGate::run($productDevelopment->fresh())['can_move'])->toBeTrue();
});

test('supplier product fields wait for a linked supplier product, and charges go to the supplier notes', function () {
    $productDevelopment = developmentWithSupplier($this);
    $item               = $productDevelopment->items->first();
    $unitsPerCarton     = $productDevelopment->checklistItems()->where('product_development_item_id', $item->id)->where('key', 'supplier_product.units_per_carton')->first();

    expect(fn () => UpdateProductDevelopmentChecklistItem::make()->action($unitsPerCarton, $this->user, ['operation' => 'save', 'value' => 24]))->toThrow(ValidationException::class);

    LinkProductDevelopmentSupplierProduct::make()->action($item, $this->user, ['code' => 'SP-'.Str::upper(Str::random(6)), 'cost' => 1.25, 'units_per_pack' => 1, 'units_per_carton' => 12]);
    $item = $item->fresh();
    expect($item->supplier_product_id)->not->toBeNull()
        ->and($item->supplierProduct->tradeUnits()->where('trade_units.id', $item->trade_unit_id)->exists())->toBeTrue();

    UpdateProductDevelopmentChecklistItem::make()->action($unitsPerCarton->fresh(), $this->user, ['operation' => 'save', 'value' => 24]);
    expect($unitsPerCarton->fresh()->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE)
        ->and($item->supplierProduct->fresh()->units_per_carton)->toBe(24);

    $charge      = $productDevelopment->checklistItems()->where('key', 'charge_label_print')->first();
    $notesBefore = \App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $productDevelopment->supplier_id)->count();
    UpdateProductDevelopmentChecklistItem::make()->action($charge, $this->user, ['operation' => 'save', 'value' => 'USD 0.05 per label, min 5,000']);

    $note = \App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $productDevelopment->supplier_id)->latest('id')->first();
    expect(\App\Models\Procurement\ProcurementNote::where('subject_type', 'Supplier')->where('subject_id', $productDevelopment->supplier_id)->count())->toBe($notesBefore + 1)
        ->and($note->body)->toContain('USD 0.05 per label');
});

test('a supplier fixes the agent of a development', function () {
    $productDevelopment = developmentWithSupplier($this);
    $otherAgent         = \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($this->group, \App\Models\SupplyChain\Agent::factory()->definition());

    expect($productDevelopment->agent_id)->toBe($productDevelopment->supplier->agent_id)
        ->and(fn () => StoreProductDevelopment::make()->action($this->group, $this->user, [
            'name'        => 'Mismatched agent',
            'type'        => 'new',
            'supplier_id' => $productDevelopment->supplier_id,
            'agent_id'    => $otherAgent->id,
            'items'       => [['code' => 'MA-'.Str::upper(Str::random(5)), 'name' => 'Mismatch SKU']],
        ]))->toThrow(ValidationException::class);
});

test('the concept runs from v1 through pinned comments and changes to an approved, locked v2 that opens the artwork task', function () {
    $designer           = newGuestUser($this);
    $buyer              = newGuestUser($this);
    $agent              = newAgentUser($this);
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [
        ['user_id' => $designer->id, 'role' => 'designer'],
        ['user_id' => $buyer->id, 'role' => 'buyer'],
        ['user_id' => $agent->id, 'role' => 'agent'],
    ]]);

    expect($productDevelopment->designSections()->pluck('name')->all())->toBe(['Box', 'Label'])
        ->and(fn () => StoreProductDevelopmentDesignVersion::make()->action($productDevelopment, $designer, ['round' => 'packaging']))->toThrow(ValidationException::class);

    $v1 = StoreProductDevelopmentDesignVersion::make()->action($productDevelopment, $designer, ['round' => 'concept']);
    expect($v1->version)->toBe(1)
        ->and(fn () => SubmitProductDevelopmentDesignVersion::make()->action($v1, $designer))->toThrow(ValidationException::class);

    \App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentDesignArtwork::make()->action($v1, $designer, ['file' => UploadedFile::fake()->create('concept.pdf', 120, 'application/pdf')]);
    UpdateProductDevelopmentDesignVersion::make()->action($v1->fresh(), $designer, ['notes' => 'First take']);
    SubmitProductDevelopmentDesignVersion::make()->action($v1->fresh(), $designer);

    $pin = StoreProductDevelopmentComment::make()->action($productDevelopment, $buyer, ['body' => 'Make the scent name larger', 'design_round_id' => $v1->id, 'pin' => ['x' => 0.42, 'y' => 0.5]]);
    expect($pin->model_type)->toBe('ProductDevelopmentDesignRound')
        ->and($pin->data['pin']['number'])->toBe(1);

    ReviewProductDevelopmentDesignVersion::make()->action($v1->fresh(), $buyer, ['operation' => 'request_changes']);
    expect($v1->fresh()->status)->toBe(ProductDevelopmentDesignRoundStatusEnum::CHANGES_REQUESTED);

    $v2 = StoreProductDevelopmentDesignVersion::make()->action($productDevelopment, $designer, ['round' => 'concept']);
    expect($v2->version)->toBe(2);

    \App\Actions\Goods\ProductDevelopment\UpdateProductDevelopmentCommentStatus::make()->action($pin, $designer, ['status' => 'resolved']);
    expect($pin->fresh()->data['resolved_in_version'])->toBe(2);

    \App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentDesignArtwork::make()->action($v2, $designer, ['file' => UploadedFile::fake()->create('concept-2.pdf', 120, 'application/pdf')]);
    SubmitProductDevelopmentDesignVersion::make()->action($v2->fresh(), $designer);
    expect(fn () => ReviewProductDevelopmentDesignVersion::make()->action($v2->fresh(), $buyer, ['operation' => 'approve']))->toThrow(ValidationException::class)
        ->and(fn () => ReviewProductDevelopmentDesignVersion::make()->action($v2->fresh(), $buyer, ['operation' => 'check', 'check' => 'print_limits']))->toThrow(ValidationException::class);

    ReviewProductDevelopmentDesignVersion::make()->action($v2->fresh(), $agent, ['operation' => 'check', 'check' => 'print_limits']);
    ReviewProductDevelopmentDesignVersion::make()->action($v2->fresh(), $buyer, ['operation' => 'check', 'check' => 'matches_brief']);
    ReviewProductDevelopmentDesignVersion::make()->action($v2->fresh(), $buyer, ['operation' => 'check', 'check' => 'compliance_space']);
    ReviewProductDevelopmentDesignVersion::make()->action($v2->fresh(), $buyer, ['operation' => 'approve']);

    $v2 = $v2->fresh();
    expect($v2->status)->toBe(ProductDevelopmentDesignRoundStatusEnum::APPROVED)
        ->and($v2->locked_at)->not->toBeNull()
        ->and($v2->approved_by_id)->toBe($buyer->id)
        ->and(fn () => UpdateProductDevelopmentDesignVersion::make()->action($v2, $designer, ['notes' => 'too late']))->toThrow(ValidationException::class);

    $artworkTask = developmentTasks($productDevelopment)->first(fn ($task) => ($task->data['product_development_event'] ?? null) === CreateProductDevelopmentTask::DESIGN_ARTWORK);
    expect($artworkTask)->not->toBeNull()
        ->and($artworkTask->assignee_id)->toBe($designer->id);

    actingAs($buyer);
    get(route('grp.product_developments.design', ['productDevelopment' => $productDevelopment->slug, 'round' => 'concept']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/ProductDevelopmentDesign')
            ->where('version.version', 2)
            ->where('version.status.value', 'approved')
            ->has('versions', 2)
            ->has('comments.threads', 0)
            ->where('previous.version', 1));

    get(route('grp.product_developments.design', ['productDevelopment' => $productDevelopment->slug, 'round' => 'concept', 'version' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('version.version', 1)->has('comments.threads', 1));

    actingAs(newGuestUser($this));
    get(route('grp.product_developments.design', ['productDevelopment' => $productDevelopment->slug]))->assertForbidden();
});

test('the specs task follows the agent checklist: in progress, done, and reopened when an item goes missing again', function () {
    $agent              = newAgentUser($this);
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [['user_id' => $agent->id, 'role' => 'agent']]]);

    $specsTask = fn () => developmentTasks($productDevelopment)->first(fn ($task) => ($task->data['product_development_event'] ?? null) === CreateProductDevelopmentTask::SPECS);
    expect($specsTask()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::TODO);

    $agentItems = $productDevelopment->checklistItems()->where('role', 'agent')->where('required', true)->orderBy('id')->get();
    UpdateProductDevelopmentChecklistItem::make()->action($agentItems->first(), $agent, ['operation' => 'not_applicable', 'na_reason' => 'Test']);
    expect($specsTask()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::IN_PROGRESS);

    $agentItems->skip(1)->each(fn ($checklistItem) => UpdateProductDevelopmentChecklistItem::make()->action($checklistItem->fresh(), $agent, ['operation' => 'not_applicable', 'na_reason' => 'Test']));
    expect($specsTask()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::DONE)
        ->and($specsTask()->data['closed_by_development'])->toBeTrue();

    UpdateProductDevelopmentChecklistItem::make()->action($agentItems->first()->fresh(), $agent, ['operation' => 'reopen']);
    expect($specsTask()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::IN_PROGRESS);
});

test('the agent role only goes to someone working for the development\'s agent', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $outsider           = newGuestUser($this);

    expect(fn () => SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [['user_id' => $outsider->id, 'role' => 'agent']]]))->toThrow(ValidationException::class);

    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [['user_id' => $outsider->id, 'role' => 'designer']]]);
    expect($productDevelopment->members()->where('user_id', $outsider->id)->value('role')->value)->toBe('designer');

    $agentStaff = newAgentUser($this);
    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => [['user_id' => $agentStaff->id, 'role' => 'agent']]]);
    expect($productDevelopment->members()->where('user_id', $agentStaff->id)->exists())->toBeTrue();
});

test('a new SKU code is checked before saving, with a readable message', function () {
    $taken = storeDevelopmentWithNewSkus($this, 1)->items->first()->tradeUnit;

    get(route('grp.product_developments.json.sku_code', ['code' => strtolower($taken->code)]))
        ->assertOk()
        ->assertJson(['valid' => false])
        ->assertJsonPath('message', __('Already used by trade unit :code · :name', ['code' => $taken->code, 'name' => $taken->name]));

    get(route('grp.product_developments.json.sku_code', ['code' => 'FREE-'.Str::upper(Str::random(8))]))->assertOk()->assertJson(['valid' => true]);

    post(route('grp.models.product_development.store'), [
        'name'  => 'Duplicate code',
        'type'  => 'new',
        'items' => [['code' => $taken->code, 'name' => 'Clash']],
    ])->assertSessionHasErrors(['items.0.code' => __('This code is already used by another trade unit')]);
});

function approveDesign(ProductDevelopment $productDevelopment, string $round, array $people): \App\Models\Goods\ProductDevelopmentDesignRound
{
    $version = StoreProductDevelopmentDesignVersion::make()->action($productDevelopment, $people['designer'], ['round' => $round]);
    \App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentDesignArtwork::make()->action($version, $people['designer'], ['file' => UploadedFile::fake()->create('artwork.pdf', 120, 'application/pdf')]);
    SubmitProductDevelopmentDesignVersion::make()->action($version->fresh(), $people['designer']);
    ReviewProductDevelopmentDesignVersion::make()->action($version->fresh(), $people['agent'], ['operation' => 'check', 'check' => 'print_limits']);
    ReviewProductDevelopmentDesignVersion::make()->action($version->fresh(), $people['buyer'], ['operation' => 'check', 'check' => 'matches_brief']);
    ReviewProductDevelopmentDesignVersion::make()->action($version->fresh(), $people['buyer'], ['operation' => 'check', 'check' => 'compliance_space']);
    ReviewProductDevelopmentDesignVersion::make()->action($version->fresh(), $people['buyer'], ['operation' => 'approve']);

    return $version->fresh();
}

function completeChecklist(ProductDevelopment $productDevelopment): void
{
    $productDevelopment->checklistItems()->update(['state' => ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE->value, 'na_reason' => 'Not needed for this test']);
    ProductDevelopmentHydrateChecklist::run($productDevelopment);
}

function uploadArtwork(\App\Models\Goods\ProductDevelopmentDesignSection $section, \App\Models\SysAdmin\User $user, int $itemId, ?string $note = null): \App\Models\Goods\ProductDevelopmentArtwork
{
    return \App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentArtwork::make()->action($section, $user, [
        'product_development_item_id' => $itemId,
        'file'                        => UploadedFile::fake()->create('artwork-'.$itemId.'.pdf', 80, 'application/pdf'),
        'update_note'                 => $note,
    ]);
}

function developmentWithDesignTeam(object $test, int $numberSkus): array
{
    $people             = ['designer' => newGuestUser($test), 'buyer' => newGuestUser($test), 'agent' => newAgentUser($test), 'compliance' => newGuestUser($test)];
    $productDevelopment = storeDevelopmentWithNewSkus($test, $numberSkus);
    SyncProductDevelopmentMembers::make()->action($productDevelopment, ['members' => collect($people)->map(fn ($user, $role) => ['user_id' => $user->id, 'role' => $role])->values()->all()]);

    return [$productDevelopment->fresh(), $people];
}

test('artwork is uploaded per SKU in each section, sent back with a required comment, and the stage passes once every SKU in every section is approved', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 2);
    [$first, $second]              = $productDevelopment->items()->orderBy('id')->get()->all();
    $box                           = $productDevelopment->designSections()->where('name', 'Box')->firstOrFail();

    $artwork = uploadArtwork($box, $people['designer'], $first->id);
    expect($artwork->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::IN_REVIEW)
        ->and($artwork->version)->toBe(1)
        ->and(fn () => \App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentArtwork::make()->action($box, $people['designer'], ['product_development_item_id' => $first->id, 'file' => UploadedFile::fake()->image('artwork.jpg')]))->toThrow(ValidationException::class);

    $review = \App\Actions\Goods\ProductDevelopment\Design\ReviewProductDevelopmentArtwork::make();
    expect(fn () => $review->action($artwork->fresh(), $people['buyer'], ['operation' => 'request_changes']))->toThrow(ValidationException::class);
    $review->action($artwork->fresh(), $people['buyer'], ['operation' => 'request_changes', 'comment' => 'Please adjust the logo size']);
    expect($artwork->fresh()->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED)
        ->and($artwork->fresh()->reviewed_by_id)->toBe($people['buyer']->id);

    $artwork = uploadArtwork($box, $people['designer'], $first->id, 'Logo smaller');
    expect($artwork->version)->toBe(2)
        ->and($artwork->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::IN_REVIEW)
        ->and($artwork->update_note)->toBe('Logo smaller')
        ->and($artwork->review_comment)->toBeNull()
        ->and($box->artworks()->count())->toBe(1);

    $review->action($artwork->fresh(), $people['buyer'], ['operation' => 'approve']);
    expect($artwork->fresh()->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::APPROVED);

    $label        = $productDevelopment->designSections()->where('name', 'Label')->firstOrFail();
    $labelArtwork = uploadArtwork($label, $people['designer'], $first->id);
    expect($label->needs_compliance_text)->toBeTrue()
        ->and(fn () => $review->action($labelArtwork->fresh(), $people['buyer'], ['operation' => 'approve']))->toThrow(ValidationException::class);

    approveDesign($productDevelopment, 'concept', $people);
    $productDevelopment->updateQuietly(['current_position' => ProductDevelopmentStageEnum::ARTWORK->position()]);
    expect(GetProductDevelopmentStageGate::run($productDevelopment->fresh())['can_move'])->toBeFalse();

    $productDevelopment->checklistItems()->where('role', ProductDevelopmentMemberRoleEnum::COMPLIANCE->value)->update(['state' => ProductDevelopmentChecklistItemStateEnum::NOT_APPLICABLE->value, 'na_reason' => 'Not needed for this test']);
    $review->action($labelArtwork->fresh(), $people['buyer'], ['operation' => 'approve']);
    expect($labelArtwork->fresh()->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::APPROVED);

    foreach ($productDevelopment->designSections as $section) {
        foreach ([$first, $second] as $item) {
            $existing = $section->artworks()->where('product_development_item_id', $item->id)->first();
            if ($existing?->status === \App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::APPROVED) {
                continue;
            }
            $review->action(uploadArtwork($section, $people['designer'], $item->id), $people['buyer'], ['operation' => 'approve']);
        }
    }

    $productDevelopment = $productDevelopment->fresh();
    completeChecklist($productDevelopment);
    $artworkTask        = fn () => developmentTasks($productDevelopment)->first(fn ($task) => ($task->data['product_development_event'] ?? null) === CreateProductDevelopmentTask::DESIGN_ARTWORK);
    expect(GetProductDevelopmentStageGate::run($productDevelopment)['can_move'])->toBeTrue()
        ->and($artworkTask()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::DONE);

    $replaced = uploadArtwork($box, $people['designer'], $second->id, 'Corrected barcode');
    expect($replaced->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::IN_REVIEW)
        ->and(GetProductDevelopmentStageGate::run($productDevelopment->fresh())['can_move'])->toBeFalse()
        ->and($artworkTask()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::IN_PROGRESS);

    $tag = \App\Actions\Goods\ProductDevelopment\Design\StoreProductDevelopmentDesignSection::make()->action($productDevelopment, ['name' => 'Tag', 'packaging_supplier' => 'Creative Tags Ltd']);
    expect(fn () => \App\Actions\Goods\ProductDevelopment\Design\StoreProductDevelopmentDesignSection::make()->action($productDevelopment, ['name' => 'tag']))->toThrow(ValidationException::class)
        ->and(fn () => \App\Actions\Goods\ProductDevelopment\Design\DeleteProductDevelopmentDesignSection::make()->action($box->fresh(), $people['designer']))->toThrow(ValidationException::class);
    \App\Actions\Goods\ProductDevelopment\Design\UpdateProductDevelopmentDesignSection::make()->action($tag, ['name' => 'Hang tag', 'description' => 'Hanging tag design.']);
    expect($tag->fresh()->name)->toBe('Hang tag');

    actingAs($people['buyer']);
    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'design']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('design.sections', 3)
            ->where('design.sections.0.name', 'Box')
            ->where('design.sections.0.progress', ['done' => 1, 'total' => 2])
            ->where('design.sections.0.files', 2)
            ->where('design.sections.0.rows.1.status.value', 'in_review')
            ->where('design.sections.0.rows.1.artwork.version', 2)
            ->where('design.sections.0.rows.1.artwork.update_note', 'Corrected barcode')
            ->where('design.sections.0.rows.1.artwork.can_review', true)
            ->where('design.sections.2.packaging_supplier', 'Creative Tags Ltd')
            ->where('design.sections.2.rows.0.status.value', 'not_submitted')
            ->where('design.sections.2.can_delete', false));

    $zip = get(route('grp.product_developments.design_section.zip', ['productDevelopment' => $productDevelopment->slug, 'section' => $box->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/zip');
    $zipPath = tempnam(sys_get_temp_dir(), 'pd-zip-test');
    file_put_contents($zipPath, $zip->getContent());
    $archive = new ZipArchive();
    $archive->open($zipPath);
    $names = collect(range(0, $archive->numFiles - 1))->map(fn (int $index) => $archive->getNameIndex($index))->sort()->values()->all();
    $archive->close();
    unlink($zipPath);
    expect($names)->toBe([$first->tradeUnit->code.'_Box_V2.pdf', $second->tradeUnit->code.'_Box_V2.pdf']);

    \App\Actions\Goods\ProductDevelopment\Design\DeleteProductDevelopmentDesignSection::make()->action($tag->fresh(), $people['designer']);
    expect($productDevelopment->designSections()->count())->toBe(2);

    actingAs(newGuestUser($this));
    get(route('grp.product_developments.design_section.zip', ['productDevelopment' => $productDevelopment->slug, 'section' => $box->id]))->assertForbidden();
});

test('final approval needs the buyer and compliance to sign off, which locks the artwork', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $productDevelopment->updateQuietly(['current_position' => ProductDevelopmentStageEnum::FINAL_APPROVAL->position()]);
    CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment->fresh(), $this->user);
    $productDevelopment = $productDevelopment->fresh();

    $signOffTask = fn (string $key) => developmentTasks($productDevelopment)->first(fn ($task) => ($task->data['product_development_event'] ?? null) === $key);
    expect($signOffTask(CreateProductDevelopmentTask::SIGN_OFF_BUYER)->assignee_id)->toBe($people['buyer']->id)
        ->and($signOffTask(CreateProductDevelopmentTask::SIGN_OFF_COMPLIANCE)->assignee_id)->toBe($people['compliance']->id)
        ->and(GetProductDevelopmentStageGate::run($productDevelopment)['can_move'])->toBeFalse();

    expect(fn () => \App\Actions\Goods\ProductDevelopment\SignOffProductDevelopment::make()->action($productDevelopment, $people['designer'], ['role' => 'buyer', 'operation' => 'sign']))->toThrow(ValidationException::class);

    \App\Actions\Goods\ProductDevelopment\SignOffProductDevelopment::make()->action($productDevelopment, $people['buyer'], ['role' => 'buyer', 'operation' => 'sign']);
    expect(GetProductDevelopmentStageGate::run($productDevelopment->fresh())['reason'])->toContain('Compliance')
        ->and($signOffTask(CreateProductDevelopmentTask::SIGN_OFF_BUYER)->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::DONE);

    \App\Actions\Goods\ProductDevelopment\SignOffProductDevelopment::make()->action($productDevelopment->fresh(), $people['compliance'], ['role' => 'compliance', 'operation' => 'sign']);
    $productDevelopment = $productDevelopment->fresh();
    expect($productDevelopment->data['artwork_locked_at'])->not->toBeNull()
        ->and((string) GetProductDevelopmentStageGate::run($productDevelopment)['reason'])->not->toContain('sign-off')
        ->and(fn () => StoreProductDevelopmentDesignVersion::make()->action($productDevelopment, $people['designer'], ['round' => 'concept']))->toThrow(ValidationException::class)
        ->and(fn () => uploadArtwork($productDevelopment->designSections()->firstOrFail(), $people['designer'], $productDevelopment->items->first()->id))->toThrow(ValidationException::class);

    \App\Actions\Goods\ProductDevelopment\SignOffProductDevelopment::make()->action($productDevelopment, $people['compliance'], ['role' => 'compliance', 'operation' => 'withdraw']);
    expect($productDevelopment->fresh()->data['artwork_locked_at'])->toBeNull()
        ->and($signOffTask(CreateProductDevelopmentTask::SIGN_OFF_COMPLIANCE)->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::IN_PROGRESS);
});

test('compliance confirms pictograms and label marks, and links ingredients, straight on the trade unit', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items->first();
    $checklistItem                 = fn (string $key) => $productDevelopment->checklistItems()->where('product_development_item_id', $item->id)->where('key', $key)->first();

    expect($checklistItem('trade_unit.pictograms')->state)->toBe(ProductDevelopmentChecklistItemStateEnum::MISSING);

    UpdateProductDevelopmentChecklistItem::make()->action($checklistItem('trade_unit.pictograms'), $people['compliance'], ['operation' => 'save', 'value' => ['pictogram_flammable' => true, 'pictogram_toxic' => false]]);
    expect($item->tradeUnit->fresh()->pictogram_flammable)->toBeTrue()
        ->and($checklistItem('trade_unit.pictograms')->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE);

    UpdateProductDevelopmentChecklistItem::make()->action($checklistItem('trade_unit.label_info'), $people['compliance'], ['operation' => 'save', 'value' => ['markets' => ['uk'], 'ce_marking' => true, 'best_before' => null, 'label_info_approved' => true]]);
    $labelInfo = $item->tradeUnit->fresh()->label_info;
    expect($labelInfo['markets'])->toBe(['uk'])
        ->and($labelInfo['ce_marking'])->toBeTrue()
        ->and($labelInfo['label_info_approved'] ?? false)->toBeFalse()
        ->and($checklistItem('trade_unit.label_info')->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE);

    $ingredient = \App\Actions\Goods\Ingredient\StoreIngredient::make()->action($this->group, \App\Models\Goods\Ingredient::factory()->definition());
    UpdateProductDevelopmentChecklistItem::make()->action($checklistItem('trade_unit.ingredients'), $people['compliance'], ['operation' => 'save', 'value' => [$ingredient->slug]]);
    expect($item->tradeUnit->ingredients()->pluck('slug')->all())->toBe([$ingredient->slug])
        ->and($checklistItem('trade_unit.ingredients')->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE);

    ProductDevelopmentHydrateChecklist::run($productDevelopment->fresh());
    expect($checklistItem('trade_unit.pictograms')->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE);
});

test('send reminders tells each role what is waiting on it, and only the owner or purchasing can send them', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $productDevelopment->update(['current_position' => 2]);

    $reminded = \App\Actions\Goods\ProductDevelopment\RemindProductDevelopmentMembers::make()->action($productDevelopment, $this->user);
    $agentRows = \App\Actions\Goods\ProductDevelopment\RemindProductDevelopmentMembers::remindable($productDevelopment->fresh())[ProductDevelopmentMemberRoleEnum::AGENT->value];
    expect(\App\Actions\Goods\ProductDevelopment\RemindProductDevelopmentMembers::openAt($agentRows[0]))
        ->toContain('tab=products')
        ->toContain('focus=pd-check-'.$agentRows[0]['items'][0]);
    expect($reminded)->toContain('Agent')
        ->and($productDevelopment->fresh()->data['reminders_sent']['roles'])->toContain('Agent')
        ->and(collect(GetProductDevelopmentActivity::run($productDevelopment->fresh()))->pluck('sentence')->first())->toContain('Sent reminders to');

    actingAs($people['agent']);
    post(route('grp.models.product_development.remind', ['productDevelopment' => $productDevelopment->id]))->assertForbidden();
});

test('waiting on me lists what the person has to do across developments, and the dashboard widget only shows for members', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $waitingOn                     = new \App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn();

    $agentRows = collect($waitingOn->forUser($people['agent']))->where('development.code', $productDevelopment->code);
    expect($agentRows)->not->toBeEmpty()
        ->and($agentRows->pluck('kind')->unique()->all())->toBe(['checklist'])
        ->and(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::dashboardWidget($people['agent']))->toBeInstanceOf(\Inertia\DeferProp::class)
        ->and(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::dashboardWidget(newGuestUser($this)))->toBeNull();

    actingAs($people['agent']);
    get(route('grp.product_developments.waiting_on_me'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/ProductDevelopmentsWaitingOnMe')
            ->where('rows', fn ($rows) => collect($rows)->contains(fn ($row) => $row['development']['code'] === $productDevelopment->code
                && $row['kind'] === 'checklist'
                && count($row['fields']) > 0
                && $row['fields'][0]['can_edit'] === true)));
});

test('an artwork built in Aiku shows the SKU pack text from compliance and its three barcodes, saves its layout and is sent for review, and once in review a PDF can replace it and the other way round', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items->first();
    $label                         = $productDevelopment->designSections()->where('name', 'Label')->firstOrFail();
    approveDesign($productDevelopment, 'concept', $people);
    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($item->tradeUnit, ['gpsr_warnings' => 'Keep away from flames', 'pictogram_flammable' => true]);

    $artwork = \App\Actions\Goods\ProductDevelopment\Design\StartProductDevelopmentArtworkInAiku::make()->action($label, $people['designer'], ['product_development_item_id' => $item->id]);
    expect($artwork->source)->toBe('aiku')
        ->and($artwork->layout['kind'])->toBe('sheet')
        ->and($artwork->layout['width'])->toBeNull()
        ->and(fn () => \App\Actions\Goods\ProductDevelopment\Design\SubmitProductDevelopmentArtwork::make()->action($artwork, $people['designer'], []))->toThrow(ValidationException::class)
        ->and(fn () => uploadArtwork($label, $people['designer'], $item->id))->toThrow(ValidationException::class);

    $box = $productDevelopment->designSections()->where('name', 'Box')->firstOrFail();
    \App\Actions\Goods\ProductDevelopment\Design\ReviewProductDevelopmentArtwork::make()->action(uploadArtwork($box, $people['designer'], $item->id)->fresh(), $people['buyer'], ['operation' => 'request_changes', 'comment' => 'Redo the barcode']);
    expect(fn () => \App\Actions\Goods\ProductDevelopment\Design\StartProductDevelopmentArtworkInAiku::make()->action($box, $people['designer'], ['product_development_item_id' => $item->id]))->toThrow(ValidationException::class);

    actingAs($people['designer']);
    get(route('grp.product_developments.artwork', ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/ProductDevelopmentArtwork')
            ->where('artwork.can_edit', true)
            ->where('labelContent.sku.code', $item->tradeUnit->code)
            ->has('labelContent.barcodes', 3)
            ->has('labelContent.pictograms', 1)
            ->where('labelContent.values.text.warnings', 'Keep away from flames')
            ->where('labelContent.values.pictograms.pictogram_flammable', true)
            ->has('dataSources.barcodes', 3));

    $layout = [...$artwork->layout, 'width' => 100, 'height' => 40];
    $layout['faces']['sheet']['elements'] = [
        ['id' => 'w', 'type' => 'data', 'source' => 'warnings', 'x' => 2, 'y' => 2, 'w' => 60, 'h' => 10],
        ['id' => 'p', 'type' => 'pictogram', 'source' => 'pictogram_flammable', 'x' => 70, 'y' => 2, 'w' => 10, 'h' => 10],
        ['id' => 'b', 'type' => 'barcode', 'source' => 'unit', 'x' => 60, 'y' => 20, 'w' => 37, 'h' => 18, 'group_id' => 'g1', 'locked' => true],
        ['id' => 's', 'type' => 'shape', 'shape' => 'rounded', 'x' => 1, 'y' => 1, 'w' => 20, 'h' => 10, 'fill' => 'linear:45:#ff0000:#0000ff', 'stroke' => '#111827', 'stroke_px' => 2, 'radius' => 3],
    ];
    $layout['faces']['sheet']['background']['color'] = 'radial:#ffffff:#cccccc';
    $layout['faces']['sheet']['groups']                = ['g1' => 'Barcode block', 'gone' => 'No members'];
    patch(route('grp.models.product_development.artwork.layout', ['productDevelopmentArtwork' => $artwork->id]), ['layout' => $layout])->assertOk();

    $saved = collect($artwork->fresh()->layout['faces']['sheet']['elements']);
    expect($artwork->fresh()->layout['faces']['sheet']['groups'])->toBe(['g1' => 'Barcode block'])
        ->and($saved->pluck('source')->filter()->values()->all())->toBe(['warnings', 'pictogram_flammable', 'unit'])
        ->and($saved->firstWhere('id', 's')['fill'])->toBe('linear:45:#ff0000:#0000ff')
        ->and($artwork->fresh()->layout['faces']['sheet']['background']['color'])->toBe('radial:#ffffff:#cccccc')
        ->and($saved->firstWhere('id', 'b')['locked'])->toBeTrue();

    \App\Actions\Goods\ProductDevelopment\Design\SubmitProductDevelopmentArtwork::make()->action($artwork->fresh(), $people['designer'], ['update_note' => 'First layout']);
    expect($artwork->fresh()->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::IN_REVIEW)
        ->and($artwork->fresh()->version)->toBe(1)
        ->and(fn () => \App\Actions\Goods\ProductDevelopment\Design\UpdateProductDevelopmentArtworkLayout::make()->action($artwork->fresh(), ['layout' => $layout]))->toThrow(ValidationException::class);

    $reopened = \App\Actions\Goods\ProductDevelopment\Design\StartProductDevelopmentArtworkInAiku::make()->action($label, $people['designer'], ['product_development_item_id' => $item->id]);
    expect($reopened->id)->toBe($artwork->id)
        ->and($reopened->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED)
        ->and($reopened->layout['width'])->toEqual(100);

    \App\Actions\Goods\ProductDevelopment\Design\SubmitProductDevelopmentArtwork::make()->action($reopened->fresh(), $people['designer'], []);
    $asPdf = uploadArtwork($label, $people['designer'], $item->id, 'Redone in Illustrator');
    expect($asPdf->id)->toBe($artwork->id)
        ->and($asPdf->source)->toBe('upload')
        ->and($asPdf->version)->toBe(3);

    uploadArtwork($box, $people['designer'], $item->id, 'New barcode');
    $inAiku = \App\Actions\Goods\ProductDevelopment\Design\StartProductDevelopmentArtworkInAiku::make()->action($box, $people['designer'], ['product_development_item_id' => $item->id]);
    expect($inAiku->source)->toBe('aiku')
        ->and($inAiku->status)->toBe(\App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED)
        ->and($inAiku->layout['kind'])->toBe('box');
});

test('the agent completes factory photos angle by angle, and a new photo for an angle keeps the old one as an earlier version', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items->first();
    $photos                        = $productDevelopment->checklistItems()->where('product_development_item_id', $item->id)->where('key', 'factory_photos')->first();
    $upload                        = \App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto::make();

    expect($photos)->not->toBeNull()
        ->and(fn () => UpdateProductDevelopmentChecklistItem::make()->action($photos, $people['agent'], ['operation' => 'save', 'value' => 'done']))->toThrow(ValidationException::class);

    foreach (array_slice(\App\Actions\Goods\ProductDevelopment\UploadProductDevelopmentFactoryPhoto::ANGLES, 0, 5) as $angle) {
        $upload->action($photos->fresh(), $people['agent'], ['angle' => $angle, 'photo' => UploadedFile::fake()->image($angle.'.jpg')]);
    }
    expect($photos->fresh()->state)->toBe(ProductDevelopmentChecklistItemStateEnum::MISSING);

    $upload->action($photos->fresh(), $people['agent'], ['angle' => 'top', 'photo' => UploadedFile::fake()->image('top.jpg')]);
    expect($photos->fresh()->state)->toBe(ProductDevelopmentChecklistItemStateEnum::COMPLETE)
        ->and($photos->fresh()->completed_by_id)->toBe($people['agent']->id);

    $firstFront = $photos->fresh()->value['angles']['front'];
    $upload->action($photos->fresh(), $people['agent'], ['angle' => 'front', 'photo' => UploadedFile::fake()->image('front-2.jpg')]);
    expect($photos->fresh()->value['angles']['front'])->not->toBe($firstFront)
        ->and($productDevelopment->files()->find($firstFront)->superseded_at)->not->toBeNull()
        ->and($productDevelopment->files()->where('checklist_item_id', $photos->id)->whereNull('superseded_at')->count())->toBe(6);

    $editor = \App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentChecklistEditor::run($productDevelopment->fresh(), $this->user, true);
    $front  = collect(collect(collect($editor['skus'])->firstWhere('id', $item->id)['rows'])->firstWhere('id', $photos->id)['value']['angles'])->firstWhere('key', 'front');
    expect($front['name'])->toBe('front.jpg')
        ->and($front['mime'])->toBe('image/jpeg')
        ->and($front['url'])->not->toBeNull();
});

test('concept is uploaded and marked up with pins and freehand lines, and a development without design sections passes the artwork stage as skipped', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);

    $concept = StoreProductDevelopmentDesignVersion::make()->action($productDevelopment, $people['designer'], ['round' => 'concept']);
    expect($concept->data['source'])->toBe('upload')
        ->and(fn () => SubmitProductDevelopmentDesignVersion::make()->action($concept, $people['designer']))->toThrow(ValidationException::class);

    \App\Actions\Goods\ProductDevelopment\Design\UploadProductDevelopmentDesignArtwork::make()->action($concept, $people['designer'], ['file' => UploadedFile::fake()->create('concept.pdf', 200, 'application/pdf')]);
    expect($concept->fresh()->data['upload']['kind'])->toBe('pdf');
    SubmitProductDevelopmentDesignVersion::make()->action($concept->fresh(), $people['designer']);

    $pin    = StoreProductDevelopmentComment::make()->action($productDevelopment, $people['buyer'], ['body' => 'Logo bigger', 'design_round_id' => $concept->id, 'pin' => ['x' => 0.3, 'y' => 0.2, 'page' => 2]]);
    $stroke = StoreProductDevelopmentComment::make()->action($productDevelopment, $people['buyer'], ['body' => 'This whole area', 'design_round_id' => $concept->id, 'stroke' => ['page' => 1, 'points' => [[0.1, 0.1], [0.2, 0.15], [0.3, 0.1]]]]);
    expect($pin->data['pin']['page'])->toBe(2)
        ->and($stroke->data['stroke']['number'])->toBe(2)
        ->and($stroke->data['stroke']['points'])->toHaveCount(3);

    foreach (['print_limits' => 'agent', 'matches_brief' => 'buyer', 'compliance_space' => 'buyer'] as $check => $person) {
        ReviewProductDevelopmentDesignVersion::make()->action($concept->fresh(), $people[$person], ['operation' => 'check', 'check' => $check]);
    }
    ReviewProductDevelopmentDesignVersion::make()->action($concept->fresh(), $people['buyer'], ['operation' => 'approve']);

    foreach ($productDevelopment->designSections as $section) {
        \App\Actions\Goods\ProductDevelopment\Design\DeleteProductDevelopmentDesignSection::make()->action($section, $people['designer']);
    }
    $productDevelopment = $productDevelopment->fresh();
    $productDevelopment->updateQuietly(['current_position' => ProductDevelopmentStageEnum::ARTWORK->position()]);
    $productDevelopment->stages()->where('stage', 'artwork')->update(['state' => ProductDevelopmentStageStateEnum::IN_PROGRESS->value]);
    completeChecklist($productDevelopment);

    expect(GetProductDevelopmentStageGate::run($productDevelopment->fresh())['can_move'])->toBeTrue();
    MoveProductDevelopmentToNextStage::make()->action($productDevelopment->fresh(), $this->user);
    expect($productDevelopment->stages()->where('stage', 'artwork')->first()->state)->toBe(ProductDevelopmentStageStateEnum::SKIPPED)
        ->and($productDevelopment->fresh()->current_position)->toBe(ProductDevelopmentStageEnum::FINAL_APPROVAL->position());
});

function purchaseOrderFor(object $test, \App\Models\SupplyChain\Supplier $supplier, ?\App\Models\SupplyChain\HistoricSupplierProduct $orderedProduct = null): \App\Models\Procurement\PurchaseOrder
{
    $organisation = createOrganisation();
    $orgSupplier  = \App\Models\Procurement\OrgSupplier::where('organisation_id', $organisation->id)->where('supplier_id', $supplier->id)->first()
        ?? \App\Actions\Procurement\OrgSupplier\StoreOrgSupplier::make()->action($organisation, $supplier);

    $purchaseOrder = \App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder::make()->action(
        $orgSupplier,
        [...\App\Models\Procurement\PurchaseOrder::factory()->definition(), 'reference' => 'PD-PO-'.Str::upper(Str::random(6))],
        strict: false
    );

    if ($orderedProduct) {
        $stock    = \App\Actions\Goods\Stock\StoreStock::make()->action($test->group, [...\App\Models\Goods\Stock::factory()->definition(), 'state' => \App\Enums\Goods\Stock\StockStateEnum::ACTIVE]);
        $orgStock = \App\Actions\Inventory\OrgStock\StoreOrgStock::make()->action($organisation, $stock);
        \App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction::make()->action($purchaseOrder, $orderedProduct, $orgStock, \App\Models\Procurement\PurchaseOrderTransaction::factory()->definition());
    }

    return $purchaseOrder->fresh();
}

test('a trade unit and its family show their developments in a Development tab, only to people who may see them', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $tradeUnit          = $productDevelopment->items->first()->tradeUnit;
    $family             = \App\Actions\Goods\TradeUnitFamily\StoreTradeUnitFamily::make()->action($this->group, ['code' => 'TUF-'.Str::upper(Str::random(5)), 'name' => 'Candles family']);
    $tradeUnit->update(['trade_unit_family_id' => $family->id]);

    get(route('grp.trade_units.units.show', [$tradeUnit->slug, 'tab' => 'product_developments']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/TradeUnit')
            ->where('tabs.navigation.product_developments.number', 1)
            ->where('product_developments.rows.0.code', $productDevelopment->code)
            ->where('product_developments.rows.0.route.parameters.productDevelopment', $productDevelopment->slug));

    get(route('grp.trade_units.families.show', [$family->slug, 'tab' => 'product_developments']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Goods/TradeUnitFamily')
            ->where('product_developments.rows', fn ($rows) => collect($rows)->pluck('code')->contains($productDevelopment->code)));

    $outsider = newGuestUser($this);
    expect(\App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments::make()->forTradeUnit($tradeUnit, $outsider))->toBe([])
        ->and(\App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments::make()->forTradeUnitFamily($family, $outsider))->toBe([]);
});

test('the supplier and agent pages list only the open developments the viewer may see', function () {
    $open      = developmentWithSupplier($this);
    $supplier  = $open->supplier;
    $cancelled = StoreProductDevelopment::make()->action($this->group, $this->user, [
        'name'        => 'Cancelled '.Str::upper(Str::random(5)),
        'type'        => 'new',
        'supplier_id' => $supplier->id,
        'items'       => [['code' => 'CX-'.Str::upper(Str::random(5)), 'name' => 'Cancelled SKU']],
    ]);
    $cancelled->update(['status' => ProductDevelopmentStatusEnum::CANCELLED]);

    get(route('grp.supply-chain.suppliers.show', [$supplier->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/Supplier')
            ->where('productDevelopments.rows', fn ($rows) => collect($rows)->pluck('code')->all() === [$open->code]));

    get(route('grp.supply-chain.agents.show', [$supplier->agent->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SupplyChain/Agent')
            ->where('productDevelopments.rows', fn ($rows) => collect($rows)->pluck('code')->all() === [$open->code]));

    $outsider = newGuestUser($this);
    expect(\App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments::make()->forSupplier($supplier, $outsider))->toBe([])
        ->and(\App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments::make()->forAgent($supplier->agent, $outsider))->toBe([]);
});

test('a purchase order ordering a development\'s SKU links on its own, and others are linked and unlinked by hand', function () {
    $productDevelopment = developmentWithSupplier($this);
    $item               = $productDevelopment->items->first();
    LinkProductDevelopmentSupplierProduct::make()->action($item, $this->user, ['code' => 'SP-'.Str::upper(Str::random(6)), 'cost' => 1.25, 'units_per_pack' => 1, 'units_per_carton' => 12]);
    $supplierProduct = $item->fresh()->supplierProduct;

    $ordering = purchaseOrderFor($this, $productDevelopment->supplier, $supplierProduct->historicSupplierProduct);
    $other    = purchaseOrderFor($this, $productDevelopment->supplier);
    $links    = fn () => collect(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentPurchaseOrders::rows($productDevelopment->fresh()))->pluck('linked', 'reference')->all();

    expect($links())->toBe([$ordering->reference => 'auto']);

    \App\Actions\Goods\ProductDevelopment\LinkProductDevelopmentPurchaseOrder::make()->action($productDevelopment, $this->user, ['purchase_order_ids' => [$other->id], 'operation' => 'link']);
    expect($links())->toEqualCanonicalizing([$ordering->reference => 'auto', $other->reference => 'manual'])
        ->and(collect(GetProductDevelopmentActivity::make()->handle($productDevelopment->fresh()))->pluck('sentence')->implode(' '))->toContain($other->reference);

    get(route('grp.product_developments.show', [$productDevelopment->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('header.number_purchase_orders', 2)
            ->where('header.purchase_orders.link.route.name', 'grp.models.product_development.purchase_order'));

    get(route('grp.org.procurement.purchase_orders.show', [$other->organisation->slug, $other->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Procurement/PurchaseOrder')
            ->where('productDevelopments.0.code', $productDevelopment->code)
            ->where('productDevelopments.0.linked', 'manual'));
    expect(collect(\App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments::make()->forPurchaseOrder($ordering, $this->user))->pluck('linked')->all())->toBe(['auto'])
        ->and(\App\Actions\Goods\ProductDevelopment\UI\GetRelatedProductDevelopments::make()->forPurchaseOrder($ordering, newGuestUser($this)))->toBe([]);

    patch(route('grp.models.product_development.purchase_order', [$productDevelopment->id]), ['purchase_order_ids' => [$other->id], 'operation' => 'unlink'])->assertRedirect();
    expect($links())->toBe([$ordering->reference => 'auto']);

    $otherSupplier = \App\Actions\SupplyChain\Supplier\StoreSupplier::make()->action($productDevelopment->agent, \App\Models\SupplyChain\Supplier::factory()->definition());
    $foreign       = purchaseOrderFor($this, $otherSupplier);
    expect(fn () => \App\Actions\Goods\ProductDevelopment\LinkProductDevelopmentPurchaseOrder::make()->action($productDevelopment, $this->user, ['purchase_order_ids' => [$foreign->id], 'operation' => 'link']))
        ->toThrow(ValidationException::class);
});

test('a purchase order is raised from the development for an organisation buying from its supplier, and opens linked to it', function () {
    $productDevelopment = developmentWithSupplier($this);
    $open               = purchaseOrderFor($this, $productDevelopment->supplier);
    LinkProductDevelopmentSupplierProduct::make()->action($productDevelopment->items->first(), $this->user, ['code' => 'SP-'.Str::upper(Str::random(6)), 'cost' => 1.25, 'units_per_pack' => 1, 'units_per_carton' => 12]);
    $orgSupplier = \App\Models\Procurement\OrgSupplier::where('supplier_id', $productDevelopment->supplier_id)->where('organisation_id', $open->organisation_id)->first();
    $store       = fn () => post(route('grp.models.product_development.purchase_order.store', [$productDevelopment->id]), ['org_supplier_ids' => [$orgSupplier->id]]);

    get(route('grp.product_developments.show', [$productDevelopment->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('header.purchase_orders.link.create.options', fn ($options) => collect($options)->pluck('value')->contains($orgSupplier->id)));

    $store()->assertSessionHasErrors(['org_supplier_ids' => $orgSupplier->organisation->name.': '.__('There is already an open purchase order (:reference). Add the products to it, or submit or delete it before creating a new one.', ['reference' => $open->reference])]);

    $open->update(['state' => \App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum::SUBMITTED]);
    $before = \App\Models\Procurement\PurchaseOrder::where('supplier_id', $productDevelopment->supplier_id)->count();
    $store()->assertSessionHasNoErrors()->assertRedirect();

    $raised = \App\Models\Procurement\PurchaseOrder::where('supplier_id', $productDevelopment->supplier_id)->latest('id')->first();
    expect(\App\Models\Procurement\PurchaseOrder::where('supplier_id', $productDevelopment->supplier_id)->count())->toBe($before + 1)
        ->and(collect(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentPurchaseOrders::rows($productDevelopment->fresh()))->firstWhere('reference', $raised->reference)['linked'] ?? null)->toBe('manual')
        ->and(collect(GetProductDevelopmentActivity::make()->handle($productDevelopment->fresh()))->pluck('sentence')->implode(' '))->toContain('Raised purchase order '.$raised->reference);

    $withoutSupplier = storeDevelopmentWithNewSkus($this, 1);
    post(route('grp.models.product_development.purchase_order.store', [$withoutSupplier->id]), ['org_supplier_ids' => [$orgSupplier->id]])->assertSessionHasErrors('org_supplier_ids');
});

test('the rail shows a developments badge with what waits on the person, and nothing for someone not taking part', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $badge                         = \App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::railBadge($people['agent']);

    expect($badge['waiting'])->toBeGreaterThan(0)
        ->and(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::railBadge(newGuestUser($this)))->toBeNull()
        ->and(\App\Actions\UI\Grp\GetFirstLoadProps::run($people['agent'])['development_badges'])->toBe($badge);

    actingAs($people['agent']);
    foreach (['grp.product_developments.index', 'grp.product_developments.waiting_on_me'] as $page) {
        get(route($page))->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('viewSwitch.2.count', $badge['waiting']));
    }
    $list = get(route('grp.product_developments.json.waiting_badge'))->assertOk()->json();
    expect($list['total'])->toBe($badge['waiting'])
        ->and(collect($list['rows'])->pluck('development.code'))->toContain($productDevelopment->code);

    patch(route('grp.models.profile.update'), ['rail_hidden_badges' => ['developments']]);
    expect($people['agent']->fresh()->settings['rail_hidden_badges'] ?? [])->toBe(['developments']);
});

test('a sent reminder makes the reminded person\'s rail badge pulse until they open it', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $agent                         = $people['agent'];
    $productDevelopment->update(['current_position' => 2]);
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastProductDevelopmentBadgeUpdate::class]);

    expect(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::railBadge($agent->fresh())['reminded'])->toBeFalse();

    \App\Actions\Goods\ProductDevelopment\RemindProductDevelopmentMembers::make()->action($productDevelopment, $this->user);

    expect(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::railBadge($agent->fresh())['reminded'])->toBeTrue()
        ->and(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::railBadge($this->user->fresh())['reminded'] ?? false)->toBeFalse();
    \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\BroadcastProductDevelopmentBadgeUpdate::class, fn ($event) => $event->userId === $agent->id && $event->developmentBadges['reminded'] === true);

    actingAs($agent);
    expect(get(route('grp.product_developments.json.waiting_badge'))->assertOk()->json('reminded'))->toBeFalse()
        ->and(\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::railBadge($agent->fresh())['reminded'])->toBeFalse();
});

test('send reminders only chases checklist items of the current stage, while design work is open from day one', function () {
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    $remindable         = \App\Actions\Goods\ProductDevelopment\RemindProductDevelopmentMembers::remindable($productDevelopment->fresh());
    $byRole             = (new \App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn())->byRole($productDevelopment->fresh());

    expect($productDevelopment->fresh()->current_position)->toBe(1)
        ->and(array_keys($byRole))->toContain(ProductDevelopmentMemberRoleEnum::AGENT->value)
        ->and(array_keys($remindable))->not->toContain(ProductDevelopmentMemberRoleEnum::AGENT->value)
        ->and(array_keys($remindable))->not->toContain(ProductDevelopmentMemberRoleEnum::PURCHASING->value)
        ->and(array_keys($remindable))->not->toContain(ProductDevelopmentMemberRoleEnum::COMPLIANCE->value)
        ->and(collect($remindable[ProductDevelopmentMemberRoleEnum::DESIGNER->value] ?? [])->pluck('kind')->unique()->all())->toBe([\App\Actions\Goods\ProductDevelopment\ProductDevelopmentWaitingOn::KIND_DESIGN]);
});

test('development notifications go to Slack and browser push by default, and a saved choice still wins', function () {
    $user = newGuestUser($this);

    foreach (\App\Enums\SysAdmin\User\UserNotificationEnum::PRODUCT_DEVELOPMENT_EVENTS as $event) {
        expect($event->channelsFor($user))->toBe(['slack', 'browser']);
    }

    $user->update(['settings' => [...($user->settings ?? []), 'notifications' => ['product_development_task' => ['email']]]]);
    expect(\App\Enums\SysAdmin\User\UserNotificationEnum::PRODUCT_DEVELOPMENT_TASK->channelsFor($user->fresh()))->toBe(['email']);

    Config::set('services.webpush.public_key', 'test-public-key');
    $productDevelopment = storeDevelopmentWithNewSkus($this, 1);
    get(route('grp.product_developments.show', [$productDevelopment->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('push.public_key', 'test-public-key'));

    $settings = $this->user->settings ?? [];
    $this->user->update(['settings' => [...$settings, 'notifications' => ['product_development_task' => ['slack']]]]);
    get(route('grp.product_developments.show', [$productDevelopment->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('push', null));
    $this->user->update(['settings' => $settings]);
});

test('picking a member warns what their permissions will stop them doing, and nothing for someone with full access', function () {
    $outsider = newGuestUser($this);
    $check    = fn (\App\Models\SysAdmin\User $user, ?string $role) => get(route('grp.product_developments.json.member_access', ['user_id' => $user->id, 'role' => $role]))->assertOk()->json('warnings');

    expect($check($outsider, ProductDevelopmentMemberRoleEnum::DESIGNER->value))->toHaveCount(1)
        ->and($check($outsider, ProductDevelopmentMemberRoleEnum::PURCHASING->value))->toHaveCount(2)
        ->and($check($this->user, ProductDevelopmentMemberRoleEnum::DESIGNER->value))->toBe([]);
});

test('an artwork built in Aiku downloads as a vector PDF at its real size, drawing every item type, and only for people who may see it', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    approveDesign($productDevelopment, 'concept', $people);
    $box = $productDevelopment->designSections()->where('name', 'Box')->firstOrFail();

    $artwork  = \App\Actions\Goods\ProductDevelopment\Design\StartProductDevelopmentArtworkInAiku::make()->action($box, $people['designer'], ['product_development_item_id' => $productDevelopment->items->first()->id]);
    $elements = [
        ['id' => 'text', 'type' => 'text', 'x' => 2, 'y' => 2, 'w' => 40, 'h' => 8, 'text' => 'Incense', 'font_size' => 12, 'rotate' => 15],
        ['id' => 'image', 'type' => 'image', 'x' => 2, 'y' => 12, 'w' => 10, 'h' => 10],
        ['id' => 'shape', 'type' => 'shape', 'shape' => 'ellipse', 'x' => 14, 'y' => 12, 'w' => 10, 'h' => 10, 'fill' => 'radial:#fde047:#16a34a', 'stroke' => '#111827', 'stroke_px' => 1],
        ['id' => 'data', 'type' => 'data', 'source' => 'product_name', 'x' => 26, 'y' => 12, 'w' => 30, 'h' => 8],
        ['id' => 'pictogram', 'type' => 'pictogram', 'source' => 'pictogram_flammable', 'x' => 2, 'y' => 24, 'w' => 10, 'h' => 10],
        ['id' => 'barcode', 'type' => 'barcode', 'source' => 'unit', 'x' => 14, 'y' => 24, 'w' => 20, 'h' => 10],
        ['id' => 'zone', 'type' => 'zone', 'zone' => 'compliance', 'x' => 36, 'y' => 24, 'w' => 20, 'h' => 10],
    ];
    $layout = [...$artwork->layout, 'width' => 60, 'length' => 50, 'height' => 40];
    $layout['faces']['front']['elements'] = $elements;
    \App\Actions\Goods\ProductDevelopment\Design\UpdateProductDevelopmentArtworkLayout::make()->action($artwork, ['layout' => $layout]);

    preg_match('/export type ElementType = ([^\n]+)/', file_get_contents(resource_path('js/Components/Goods/ProductDevelopment/Design/boxNet.ts')), $editorTypes);
    preg_match_all('/"(\w+)"/', $editorTypes[1], $editorTypeNames);
    expect(\App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignPdf::ELEMENT_TYPES)->toEqualCanonicalizing($editorTypeNames[1])
        ->and(collect($elements)->pluck('type')->all())->toEqualCanonicalizing(\App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignPdf::ELEMENT_TYPES)
        ->and(\App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignLayout::netSize($layout))->toEqual(['width' => 235, 'height' => 190]);

    $parameters = ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id];
    $response   = get(route('grp.product_developments.artwork.pdf', $parameters))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $pdf        = $response->getContent();
    preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $mediaBox);
    expect(str_starts_with($pdf, '%PDF'))->toBeTrue()
        ->and(round((float) $mediaBox[1] / 72 * 25.4))->toEqual(235.0)
        ->and(round((float) $mediaBox[2] / 72 * 25.4))->toEqual(190.0);

    actingAs(newGuestUser($this));
    get(route('grp.product_developments.artwork.pdf', $parameters))->assertForbidden();
});

test('the HELP-4211 migration turns a development built the old way into the artwork stage without errors', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items->first();
    $stages                        = \Illuminate\Support\Facades\DB::table('product_development_stages')->where('product_development_id', $productDevelopment->id);
    $artworkStage                  = (clone $stages)->where('stage', 'artwork')->first();

    $productDevelopment->designSections()->delete();
    (clone $stages)->where('stage', 'artwork')->delete();
    (clone $stages)->where('position', '>=', 5)->increment('position');
    foreach (['packaging' => ['position' => 4, 'state' => 'completed'], 'label' => ['position' => 5, 'state' => 'in_progress']] as $stage => $values) {
        \Illuminate\Support\Facades\DB::table('product_development_stages')->insert([
            'product_development_id' => $productDevelopment->id,
            'stage'                  => $stage,
            'role'                   => 'designer',
            'due_at'                 => $artworkStage->due_at,
            'started_at'             => now()->subDays(3),
            'created_at'             => now(),
            'updated_at'             => now(),
            ...$values,
        ]);
    }
    $productDevelopment->updateQuietly(['current_position' => 5, 'data' => [...($productDevelopment->data ?? []), 'skipped_rounds' => ['packaging' => ['name' => 'Someone']]]]);

    $oldRound = \Illuminate\Support\Facades\DB::table('product_development_design_rounds')->insertGetId(['product_development_id' => $productDevelopment->id, 'round' => 'label', 'product_development_item_id' => $item->id, 'version' => 1, 'status' => 'in_review', 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    $comment  = \Illuminate\Support\Facades\DB::table('product_development_comments')->insertGetId(['product_development_id' => $productDevelopment->id, 'model_type' => 'ProductDevelopmentDesignRound', 'model_id' => $oldRound, 'user_id' => $people['buyer']->id, 'body' => 'Old label pin', 'mentions' => '[]', 'data' => '{}', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
    $task     = developmentTasks($productDevelopment)->first();
    $task->update(['status' => \App\Enums\Tasks\StaffTaskStatusEnum::TODO, 'data' => [...($task->data ?? []), 'product_development_event' => 'design_label']]);

    $migration = require database_path('migrations/2026_10_22_120000_create_product_development_design_sections_and_artworks.php');
    (fn () => $this->moveExistingDevelopments())->call($migration);
    (fn () => $this->moveExistingDevelopments())->call($migration);

    $productDevelopment = ProductDevelopment::find($productDevelopment->id);
    $artwork            = $productDevelopment->stages()->where('stage', 'artwork')->first();
    expect($productDevelopment->current_position)->toBe(ProductDevelopmentStageEnum::ARTWORK->position())
        ->and($productDevelopment->stages()->whereIn('stage', ['packaging', 'label'])->count())->toBe(0)
        ->and($artwork->state)->toBe(ProductDevelopmentStageStateEnum::IN_PROGRESS)
        ->and($artwork->position)->toBe(4)
        ->and($productDevelopment->stages()->where('stage', 'final_approval')->value('position'))->toBe(5)
        ->and($productDevelopment->designSections()->pluck('name')->all())->toBe(['Box', 'Label'])
        ->and($productDevelopment->data['skipped_rounds'] ?? [])->toBe([])
        ->and(\Illuminate\Support\Facades\DB::table('product_development_design_rounds')->where('id', $oldRound)->exists())->toBeFalse()
        ->and(\Illuminate\Support\Facades\DB::table('product_development_comments')->where('id', $comment)->value('model_type'))->toBeNull()
        ->and($task->fresh()->status)->toBe(\App\Enums\Tasks\StaffTaskStatusEnum::CANCELLED);

    actingAs($people['buyer']);
    foreach (['brief', 'design', 'activity', 'tasks', 'files'] as $tab) {
        get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => $tab]))->assertOk();
    }
    get(route('grp.product_developments.board'))->assertOk();
    get(route('grp.product_developments.waiting_on_me'))->assertOk();
});

test('the brief takes PDF references for the range and a short brief with reference images per product, from the create form and on the Brief tab, HELP-4196', function () {
    $suffix = Str::upper(Str::random(5));

    post(route('grp.models.product_development.store'), [
        'name'        => 'Scented candles '.$suffix,
        'type'        => 'new',
        'brief'       => 'Soft pastel range for spring',
        'brief_files' => [UploadedFile::fake()->create('moodboard.pdf', 50, 'application/pdf'), UploadedFile::fake()->image('inspiration.jpg'), UploadedFile::fake()->create('walkthrough.mp4', 200, 'video/mp4')],
        'items'       => [
            ['code' => 'SC-'.$suffix.'-1', 'name' => 'Lavender candle', 'brief' => 'Lavender, lilac wax', 'images' => [UploadedFile::fake()->image('lavender.jpg'), UploadedFile::fake()->image('jar.jpg')]],
            ['code' => 'SC-'.$suffix.'-2', 'name' => 'Rose candle'],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $productDevelopment = ProductDevelopment::where('name', 'Scented candles '.$suffix)->firstOrFail();
    [$lavender, $rose]  = $productDevelopment->items()->orderBy('position')->get()->all();
    expect($lavender->brief)->toBe('Lavender, lilac wax')
        ->and($rose->brief)->toBeNull()
        ->and($productDevelopment->files()->where('scope', 'brief_reference')->count())->toBe(3)
        ->and($productDevelopment->files()->where('scope', 'brief_image')->where('product_development_item_id', $lavender->id)->count())->toBe(2);

    post(route('grp.models.product_development.store'), [
        'name' => 'Wrong files '.$suffix, 'type' => 'new', 'brief_files' => [UploadedFile::fake()->create('notes.docx', 10)],
    ])->assertSessionHasErrors('brief_files.0');

    patch(route('grp.models.product_development.update', ['productDevelopment' => $productDevelopment->id]), ['brief' => 'Soft pastel range for spring and summer'])->assertRedirect();
    patch(route('grp.models.product_development.item.brief', ['productDevelopmentItem' => $rose->id]), ['brief' => 'Deep red, rose petals'])->assertRedirect();
    expect($productDevelopment->fresh()->brief)->toBe('Soft pastel range for spring and summer')
        ->and($rose->fresh()->brief)->toBe('Deep red, rose petals')
        ->and(collect(GetProductDevelopmentActivity::run($productDevelopment->fresh()))->pluck('sentence'))->toContain(__('Changed the brief of :sku', ['sku' => 'SC-'.$suffix.'-2']));

    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'brief']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('brief.brief_editor.can_edit', true)
            ->has('brief.brief_editor.references', 3)
            ->where('brief.brief_editor.products.0.brief', 'Lavender, lilac wax')
            ->has('brief.brief_editor.products.0.images', 2)
            ->where('brief.brief_editor.products.1.brief', 'Deep red, rose petals'));

    $image = $productDevelopment->files()->where('scope', 'brief_image')->firstOrFail();
    \Pest\Laravel\delete(route('grp.models.product_development.brief_file.remove', ['productDevelopmentFile' => $image->id]))->assertRedirect();
    expect($productDevelopment->files()->where('scope', 'brief_image')->count())->toBe(1);

    $other = UploadProductDevelopmentFiles::make()->action($productDevelopment, $this->user, ['files' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], 'scope' => 'other'])[0];
    \Pest\Laravel\delete(route('grp.models.product_development.brief_file.remove', ['productDevelopmentFile' => $other->id]))->assertSessionHasErrors('file');
    expect($productDevelopment->files()->whereKey($other->id)->exists())->toBeTrue();
});

test('each SKU artwork has its own discussion with roles, the review comment joins it, and the general feed leaves it out, HELP-4254', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items()->firstOrFail();
    $box                           = $productDevelopment->designSections()->where('name', 'Box')->firstOrFail();
    $artwork                       = uploadArtwork($box, $people['designer'], $item->id, 'First draft');

    \App\Actions\Goods\ProductDevelopment\StoreProductDevelopmentComment::make()->action($productDevelopment, $people['designer'], ['body' => 'Barcode moved to the back', 'artwork_id' => $artwork->id]);
    \App\Actions\Goods\ProductDevelopment\StoreProductDevelopmentComment::make()->action($productDevelopment, $people['buyer'], ['body' => 'General note on the range']);
    \App\Actions\Goods\ProductDevelopment\Design\ReviewProductDevelopmentArtwork::make()->action($artwork->fresh(), $people['buyer'], ['operation' => 'request_changes', 'comment' => 'Text too small']);

    $otherDevelopment = storeDevelopmentWithNewSkus($this, 1);
    expect(fn () => \App\Actions\Goods\ProductDevelopment\StoreProductDevelopmentComment::make()->action($otherDevelopment, $this->user, ['body' => 'Wrong place', 'artwork_id' => $artwork->id]))->toThrow(ValidationException::class);

    actingAs($people['buyer']);
    $thread = get(route('grp.product_developments.json.artwork_comments', ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id]))
        ->assertOk()
        ->json('threads');
    $byBody = collect($thread)->keyBy('body');
    expect($thread)->toHaveCount(2)
        ->and($byBody['Barcode moved to the back']['role'])->toBe('Designer')
        ->and($byBody['Text too small']['role'])->toBe('Buyer')
        ->and($byBody['Text too small']['review'])->toBe('request_changes')
        ->and($byBody['Text too small']['version'])->toBe(1);

    expect(collect(\App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentComments::run($productDevelopment)['threads'])->pluck('body')->all())->toBe(['General note on the range']);

    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'design']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('design.sections.0.rows.0.artwork.comments', 2)
            ->where('design.sections.0.rows.0.artwork.update_note', 'First draft')
            ->where('design.sections.0.rows.0.artwork.comments_route.name', 'grp.product_developments.json.artwork_comments'));

    actingAs($this->user);
    get(route('grp.product_developments.json.artwork_comments', ['productDevelopment' => $otherDevelopment->slug, 'artwork' => $artwork->id]))->assertNotFound();

    actingAs(newGuestUser($this));
    get(route('grp.product_developments.json.artwork_comments', ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id]))->assertForbidden();
});

test('every artwork version sent for review is kept with its file, note and review, and an earlier PDF still downloads, HELP-4254', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items()->firstOrFail();
    $box                           = $productDevelopment->designSections()->where('name', 'Box')->firstOrFail();
    $review                        = \App\Actions\Goods\ProductDevelopment\Design\ReviewProductDevelopmentArtwork::make();

    $review->action(uploadArtwork($box, $people['designer'], $item->id, 'First draft')->fresh(), $people['buyer'], ['operation' => 'request_changes', 'comment' => 'Logo too big']);
    uploadArtwork($box, $people['designer'], $item->id, 'Smaller logo');
    $artwork = uploadArtwork($box, $people['designer'], $item->id, 'Fixed a typo');
    $review->action($artwork->fresh(), $people['buyer'], ['operation' => 'approve']);

    expect($artwork->versions()->pluck('status', 'version')->map->value->all())->toBe([3 => 'approved', 2 => 'in_review', 1 => 'changes_required']);

    actingAs($people['buyer']);
    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'design']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('design.sections.0.rows.0.artwork.versions', 3)
            ->where('design.sections.0.rows.0.artwork.versions.0.version', 3)
            ->where('design.sections.0.rows.0.artwork.versions.0.is_current', true)
            ->where('design.sections.0.rows.0.artwork.versions.0.status.value', 'approved')
            ->where('design.sections.0.rows.0.artwork.versions.2.update_note', 'First draft')
            ->where('design.sections.0.rows.0.artwork.versions.2.review_comment', 'Logo too big')
            ->where('design.sections.0.rows.0.artwork.versions.2.reviewed_by', $people['buyer']->contact_name ?: $people['buyer']->username)
            ->where('design.sections.0.rows.0.artwork.versions.2.pdf_route.parameters.version', 1));

    get(route('grp.product_developments.artwork.pdf', ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id, 'version' => 1]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$item->tradeUnit->code.'_Box_V1.pdf"');
    get(route('grp.product_developments.artwork.pdf', ['productDevelopment' => $productDevelopment->slug, 'artwork' => $artwork->id, 'version' => 9]))->assertNotFound();
});

test('a design still being made in Aiku can be cancelled so the artwork is uploaded as a PDF instead, HELP-4245', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 1);
    $item                          = $productDevelopment->items()->firstOrFail();
    $box                           = $productDevelopment->designSections()->where('name', 'Box')->firstOrFail();
    approveDesign($productDevelopment, 'concept', $people);

    $artwork = \App\Actions\Goods\ProductDevelopment\Design\StartProductDevelopmentArtworkInAiku::make()->action($box, $people['designer'], ['product_development_item_id' => $item->id]);
    expect(fn () => uploadArtwork($box, $people['designer'], $item->id))->toThrow(ValidationException::class);

    actingAs($people['designer']);
    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'design']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('design.sections.0.rows.0.artwork.can_cancel_aiku', true));
    actingAs($people['buyer']);
    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'design']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('design.sections.0.rows.0.artwork.can_cancel_aiku', false));

    \Illuminate\Support\Facades\DB::table('product_development_artworks')->where('id', $artwork->id)->update(['status' => 'in_review']);
    expect(fn () => \App\Actions\Goods\ProductDevelopment\Design\CancelProductDevelopmentArtworkInAiku::make()->action($artwork->fresh()))->toThrow(ValidationException::class);
    \Illuminate\Support\Facades\DB::table('product_development_artworks')->where('id', $artwork->id)->update(['status' => 'not_submitted']);

    actingAs($people['designer']);
    \Pest\Laravel\delete(route('grp.models.product_development.artwork.aiku.cancel', ['productDevelopmentArtwork' => $artwork->id]))->assertRedirect();
    expect($artwork->fresh()->source)->toBeNull()
        ->and($artwork->fresh()->layout)->toBe([]);

    $uploaded = uploadArtwork($box, $people['designer'], $item->id, 'Done in Illustrator');
    expect($uploaded->id)->toBe($artwork->id)
        ->and($uploaded->source)->toBe('upload')
        ->and($uploaded->version)->toBe(1)
        ->and(fn () => \App\Actions\Goods\ProductDevelopment\Design\CancelProductDevelopmentArtworkInAiku::make()->action($uploaded->fresh()))->toThrow(ValidationException::class);

    actingAs(newGuestUser($this));
    \Pest\Laravel\delete(route('grp.models.product_development.artwork.aiku.cancel', ['productDevelopmentArtwork' => $artwork->id]))->assertForbidden();
});

test('compliance marks which pack text goes on the label per SKU, and only that text reaches the Label & Packaging view and the designers, HELP-4244', function () {
    [$productDevelopment, $people] = developmentWithDesignTeam($this, 2);
    [$first, $second]              = $productDevelopment->items()->orderBy('id')->get()->all();
    \App\Actions\Goods\TradeUnit\UpdateTradeUnit::make()->action($first->tradeUnit, ['gpsr_warnings' => 'Keep away from flames', 'gpsr_manufacturer' => 'Ancient Wisdom Ltd']);

    $checklistRow = fn (ProductDevelopmentTargetFieldEnum $field) => $productDevelopment->checklistItems()->where('product_development_item_id', $first->id)->where('target_field', $field->value)->firstOrFail();
    $textKeys     = fn () => collect(\App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent::run($productDevelopment, $first->fresh()->load('tradeUnit'))['text'])->pluck('key')->all();

    expect($first->label_fields)->toBeNull()
        ->and($textKeys())->toContain('warnings', 'manufacturer');

    $onLabel = \App\Actions\Goods\ProductDevelopment\UpdateProductDevelopmentChecklistItemOnLabel::make();
    $onLabel->action($checklistRow(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_WARNINGS), ['include' => false]);
    expect($textKeys())->not->toContain('warnings')
        ->and($textKeys())->toContain('manufacturer')
        ->and(\App\Actions\Goods\ProductDevelopment\UI\GetProductDevelopmentLabelContent::run($productDevelopment, $first->fresh()->load('tradeUnit'))['values']['text']['warnings'])->toBeNull()
        ->and($second->fresh()->label_fields)->toBeNull();

    $onLabel->action($checklistRow(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_WARNINGS), ['include' => true]);
    expect($textKeys())->toContain('warnings')
        ->and(fn () => $onLabel->action($checklistRow(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_TARIFF_CODE), ['include' => true]))->toThrow(ValidationException::class);

    actingAs($people['compliance']);
    patch(route('grp.models.product_development.checklist_item.on_label', ['productDevelopmentChecklistItem' => $checklistRow(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_MANUFACTURER)->id]), ['include' => false])->assertRedirect();
    expect($first->fresh()->label_fields)->not->toContain('manufacturer');

    get(route('grp.product_developments.show', [$productDevelopment->slug, 'tab' => 'compliance']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('compliance.routes.on_label', 'grp.models.product_development.checklist_item.on_label')
            ->where('compliance.skus.0.label_content_route.name', 'grp.product_developments.json.label_content')
            ->where('compliance.skus.0.rows', fn ($rows) => collect($rows)->firstWhere('label_field', 'manufacturer')['on_label'] === false
                && collect($rows)->firstWhere('label_field', 'warnings')['on_label'] === true
                && collect($rows)->firstWhere('label', $checklistRow(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_TARIFF_CODE)->label)['label_field'] === null));

    actingAs(newGuestUser($this));
    patch(route('grp.models.product_development.checklist_item.on_label', ['productDevelopmentChecklistItem' => $checklistRow(ProductDevelopmentTargetFieldEnum::TRADE_UNIT_GPSR_WARNINGS)->id]), ['include' => false])->assertForbidden();
});
