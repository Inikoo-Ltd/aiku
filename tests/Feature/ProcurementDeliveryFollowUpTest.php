<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\GoodsIn\StockDelivery\DispatchStockDelivery;
use App\Actions\GoodsIn\StockDelivery\StoreStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UpdateStockDeliveryStateToReceived;
use App\Actions\GoodsIn\StockDeliveryClaim\UpdateStockDeliveryClaim;
use App\Actions\GoodsIn\StockDeliveryFollowUp\CreateSupplierInstructionFromFeedback;
use App\Actions\GoodsIn\StockDeliveryFollowUp\StoreStockDeliveryFeedback;
use App\Actions\GoodsIn\StockDeliveryFollowUp\UI\IndexBuyerDeliveryDiscrepancies;
use App\Actions\GoodsIn\StockDeliveryItem\ResolveStockDeliveryItemDiscrepancy;
use App\Actions\GoodsIn\StockDeliveryItem\SetStockDeliveryItemCheckedQuantity;
use App\Actions\GoodsIn\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Procurement\OrgAgent\StoreOrgAgent;
use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToSubmitted;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement;
use App\Actions\SupplyChain\Agent\StoreAgent;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteCategoryEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryClaim;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Notifications\StockDeliveryFeedbackNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../Support/ProcurementHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();
    $this->group        = group();
    $this->adminGuest   = createAdminGuest($this->group);
    $this->stocks       = createStocks($this->group);
    $this->stock        = $this->stocks[0];
    $this->orgStocks    = createOrgStocks($this->organisation, $this->stocks);

    followUpSettings($this, ['delivery_buyer_id' => null]);

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    $this->withoutVite();
    actingAs($this->adminGuest->getUser());
});

function followUpSettings(object $test, array $settings): void
{
    $organisationSettings = $test->organisation->refresh()->settings ?? [];
    data_set($organisationSettings, 'procurement.delivery_tolerance_percentage', 0);
    data_set($organisationSettings, 'procurement.delivery_tolerance_amount', 0);
    foreach ($settings as $key => $value) {
        data_set($organisationSettings, "procurement.$key", $value);
    }
    $test->organisation->update(['settings' => $organisationSettings]);
}

function followUpBuyer(object $test, string $name): User
{
    return userWithJobPosition($test, 'buy', $name.'-'.Str::lower(Str::random(6)));
}

/**
 * @return array{0: OrgSupplier, 1: SupplierProduct}
 */
function followUpSupplier(object $test, Group|Agent $parent): array
{
    $supplier    = StoreSupplier::make()->action($parent, Supplier::factory()->definition());
    $orgSupplier = OrgSupplier::where('supplier_id', $supplier->id)->where('organisation_id', $test->organisation->id)->firstOrFail();

    $supplierProduct = StoreSupplierProduct::make()->action($supplier, [
        'code'             => 'FUP-'.Str::upper(Str::random(8)),
        'name'             => 'Follow up product',
        'cost'             => 10,
        'stock_id'         => $test->stocks[0]->id,
        'units_per_pack'   => 10,
        'units_per_carton' => 100,
    ]);
    StoreOrgSupplierProduct::make()->action($orgSupplier, $supplierProduct);

    return [$orgSupplier->refresh(), $supplierProduct];
}

function followUpAgent(object $test): Agent
{
    $agent = StoreAgent::make()->action($test->group, Agent::factory()->definition());
    StoreOrgAgent::make()->action($test->organisation, $agent, []);

    return $agent->refresh();
}

/**
 * @param array<int, array{0: SupplierProduct, 1: int, 2: int}> $lines  supplier product, org stock index, units expected
 */
function followUpDelivery(object $test, OrgSupplier $orgSupplier, array $lines, ?Agent $agent = null): StockDelivery
{
    $stockDelivery = StoreStockDelivery::make()->action($orgSupplier, ['reference' => 'FUP-'.Str::upper(Str::random(8)), 'date' => date('Y-m-d')]);
    foreach ($lines as [$supplierProduct, $orgStockIndex, $units]) {
        StoreStockDeliveryItem::make()->action($stockDelivery, $supplierProduct->historicSupplierProduct, $test->orgStocks[$orgStockIndex], ['unit_quantity' => $units]);
    }
    if ($agent) {
        $stockDelivery->update(['agent_id' => $agent->id]);
    }

    return $stockDelivery->refresh();
}

/**
 * @param array<int, int> $received  units counted, in item order
 */
function followUpCheck(StockDelivery $stockDelivery, array $received): StockDelivery
{
    $stockDelivery = DispatchStockDelivery::make()->action($stockDelivery->refresh());
    $stockDelivery = UpdateStockDeliveryStateToReceived::make()->action($stockDelivery);
    foreach ($stockDelivery->items()->orderBy('id')->get()->values() as $index => $item) {
        SetStockDeliveryItemCheckedQuantity::make()->action($item, ['unit_quantity_checked' => $received[$index]]);
    }

    return $stockDelivery->refresh();
}

function followUpOrder(object $test, OrgSupplier $orgSupplier, SupplierProduct $supplierProduct, int $orgStockIndex, User $buyer, StockDelivery $stockDelivery): PurchaseOrder
{
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action(
        $purchaseOrder,
        $supplierProduct->historicSupplierProduct,
        $test->orgStocks[$orgStockIndex],
        array_merge(PurchaseOrderTransaction::factory()->definition(), ['quantity_ordered' => 10])
    );
    $purchaseOrder = UpdatePurchaseOrderStateToSubmitted::make()->action($purchaseOrder->refresh());
    $purchaseOrder->update(['buyer_id' => $buyer->id]);
    $stockDelivery->purchaseOrders()->syncWithoutDetaching([$purchaseOrder->id]);

    return $purchaseOrder->refresh();
}

function followUpFeedback(StockDelivery $stockDelivery, User $author, array $modelData): StockDeliveryFeedback
{
    return StoreStockDeliveryFeedback::make()->action($stockDelivery->refresh(), $author, $modelData);
}

function portalToken(Supplier $supplier): string
{
    return basename(StoreSupplierShareLink::make()->handle($supplier, ['days' => 7])['url']);
}

function discrepancyRows(object $test, User $user, string $query = ''): \Illuminate\Support\Collection
{
    actingAs($user);

    return collect($test->get(route('grp.org.procurement.delivery_discrepancies.index', [$test->organisation->slug]).$query)->assertOk()->viewData('page')['props']['data']['data']);
}

test('a five-star feedback is stored and reaches the buyer notification and the supplier portal', function () {
    Notification::fake();
    $buyer = followUpBuyer($this, 'fup-five');
    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);
    $stockDelivery = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 10]]);
    followUpOrder($this, $orgSupplier, $supplierProduct, 0, $buyer, $stockDelivery);
    $author = $this->adminGuest->getUser();

    expect(fn () => followUpFeedback($stockDelivery, $author, ['rating' => 5]))->toThrow(ValidationException::class);

    $stockDelivery = followUpCheck($stockDelivery, [10]);
    expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::CHECKED)
        ->and(fn () => followUpFeedback($stockDelivery, $author, []))->toThrow(ValidationException::class)
        ->and(fn () => followUpFeedback($stockDelivery, $author, ['rating' => 4]))->toThrow(ValidationException::class)
        ->and(fn () => followUpFeedback($stockDelivery, $author, ['rating' => 5, 'topics' => ['made_up']]))->toThrow(ValidationException::class);

    $thanks   = 'Thank you. Goods received in perfect condition, quantities correct and packaging excellent.';
    $feedback = followUpFeedback($stockDelivery, $author, ['rating' => 5, 'comment' => $thanks]);

    expect($feedback->supplier_id)->toBe($supplierProduct->supplier_id)
        ->and($feedback->buyer_id)->toBe($buyer->id)
        ->and($feedback->author_id)->toBe($author->id)
        ->and($feedback->rating)->toBe(5);
    Notification::assertSentToTimes($buyer, StockDeliveryFeedbackNotification::class, 1);

    Auth::logout();
    $this->get(route('grp.supplier_share.show', portalToken($supplierProduct->supplier)))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('delivery_feedback', fn ($reload) => $reload
            ->where('delivery_feedback.0.id', $feedback->id)
            ->where('delivery_feedback.0.comment', $thanks)
            ->where('delivery_feedback.0.photos', [])
            ->etc()));
});

test('another supplier on the same consolidated delivery does not see the feedback', function () {
    $agent = followUpAgent($this);
    $author = $this->adminGuest->getUser();
    [$orgSupplierA, $productA] = followUpSupplier($this, $agent);
    [, $productB]              = followUpSupplier($this, $agent);

    $stockDelivery = followUpCheck(followUpDelivery($this, $orgSupplierA, [[$productA, 0, 10], [$productB, 1, 10]], $agent), [10, 10]);

    expect(fn () => followUpFeedback($stockDelivery, $author, ['rating' => 5, 'comment' => 'ok']))->toThrow(ValidationException::class);
    expect(collect(\App\Actions\GoodsIn\StockDeliveryFollowUp\UI\GetStockDeliveryFeedbackForm::run($stockDelivery->refresh())['suppliers'])->pluck('id')->sort()->values()->all())
        ->toBe(collect([$productA->supplier_id, $productB->supplier_id])->sort()->values()->all());

    $feedbackA = followUpFeedback($stockDelivery, $author, ['supplier_id' => $productA->supplier_id, 'rating' => 5, 'comment' => 'Perfect']);
    $feedbackB = followUpFeedback($stockDelivery, $author, ['supplier_id' => $productB->supplier_id, 'rating' => 2, 'comment' => 'Broken cartons', 'topics' => ['packing']]);

    expect($feedbackA->agent_id)->toBe($agent->id)
        ->and(collect(\App\Actions\GoodsIn\StockDeliveryFollowUp\UI\GetStockDeliveryFeedbackForm::run($stockDelivery->refresh())['suppliers']))->toBeEmpty();

    Auth::logout();
    foreach ([[$productA, $feedbackA], [$productB, $feedbackB]] as [$product, $feedback]) {
        $this->get(route('grp.supplier_share.show', portalToken($product->supplier)))
            ->assertInertia(fn ($page) => $page->loadDeferredProps('delivery_feedback', fn ($reload) => $reload
                ->where('delivery_feedback', fn ($cards) => collect($cards)->pluck('id')->all() === [$feedback->id])
                ->etc()));
    }
});

test('a repeated submit makes no duplicate feedback and no second notification, a separate partial delivery gets its own', function () {
    Notification::fake();
    $buyer = followUpBuyer($this, 'fup-twice');
    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);
    $first = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 10]]);
    followUpOrder($this, $orgSupplier, $supplierProduct, 0, $buyer, $first);
    $first  = followUpCheck($first, [8]);
    $second = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 10]]);
    followUpOrder($this, $orgSupplier, $supplierProduct, 0, $buyer, $second);
    $second = followUpCheck($second, [10]);

    $data     = ['rating' => 3, 'comment' => 'Two short', 'topics' => ['quantities']];
    $feedback = followUpFeedback($first, $this->adminGuest->getUser(), $data);
    $again    = followUpFeedback($first, $this->adminGuest->getUser(), [...$data, 'rating' => 1, 'comment' => 'Clicked twice']);
    $this->post(route('grp.models.stock-delivery.feedback.store', $first->id), $data)->assertSessionHasNoErrors();
    $partial  = followUpFeedback($second, $this->adminGuest->getUser(), ['rating' => 5, 'comment' => 'Rest arrived fine']);

    expect($again->id)->toBe($feedback->id)
        ->and($again->rating)->toBe(3)
        ->and($partial->id)->not->toBe($feedback->id)
        ->and(StockDeliveryFeedback::where('stock_delivery_id', $first->id)->count())->toBe(1);
    Notification::assertSentToTimes($buyer, StockDeliveryFeedbackNotification::class, 2);
});

test('feedback leaves stock, claims and discrepancy outcomes unchanged, and only its author edits it', function () {
    $buyer = followUpBuyer($this, 'fup-neutral');
    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);
    $stockDelivery = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 40], [$supplierProduct, 1, 40]]);
    followUpOrder($this, $orgSupplier, $supplierProduct, 0, $buyer, $stockDelivery);
    $stockDelivery = followUpCheck($stockDelivery, [30, 40]);
    ResolveStockDeliveryItemDiscrepancy::make()->action($stockDelivery->items()->orderBy('id')->first(), $buyer, ['outcome' => 'supplier_claim']);

    $snapshot = fn () => [
        'items'     => $stockDelivery->items()->orderBy('id')->get(['unit_quantity', 'unit_quantity_checked', 'unit_quantity_placed', 'net_amount', 'cost_items', 'discrepancy_outcome', 'discrepancy_resolved_at'])->toArray(),
        'claims'    => StockDeliveryClaim::where('stock_delivery_id', $stockDelivery->id)->get(['state', 'quantity', 'amount', 'credit_note_amount'])->toArray(),
        'delivery'  => $stockDelivery->refresh()->only(['state', 'cost_items', 'cost_total', 'is_costed', 'number_stock_delivery_items_under_delivered']),
        'movements' => OrgStockMovement::whereIn('org_stock_id', [$this->orgStocks[0]->id, $this->orgStocks[1]->id])->count(),
    ];
    $before = $snapshot();

    $author   = $this->adminGuest->getUser();
    $feedback = followUpFeedback($stockDelivery, $author, ['rating' => 1, 'comment' => 'Short and damaged', 'topics' => ['quantities', 'product_quality']]);

    actingAs($buyer);
    $this->patch(route('grp.models.stock-delivery-feedback.update', $feedback->id), ['rating' => 2, 'comment' => 'Not mine'])->assertForbidden();
    actingAs($author);
    $this->patch(route('grp.models.stock-delivery-feedback.update', $feedback->id), ['rating' => 2, 'comment' => 'Short'])->assertSessionHasNoErrors();
    CreateSupplierInstructionFromFeedback::make()->action($feedback->refresh(), $author, ['body' => 'Count before packing']);

    expect($snapshot())->toEqual($before)
        ->and($feedback->refresh()->rating)->toBe(2);

    $this->withoutExceptionHandling();
    $props = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $stockDelivery->slug]).'?tab=feedback')->viewData('page')['props'];
    $card  = $props['feedback']['cards'][0];
    expect($card['id'])->toBe($feedback->id)
        ->and($card['open_discrepancies'])->toHaveCount(1)
        ->and($card['open_discrepancies'][0]['claim_state'])->toBe('open')
        ->and($card['update_route']['name'])->toBe('grp.models.stock-delivery-feedback.update')
        ->and($card['make_instruction_route'])->toBeNull()
        ->and($card['instruction']['body'])->toBe('Count before packing')
        ->and($props['give_feedback']['suppliers'])->toBe([])
        ->and($props['give_feedback']['default_comments'][5])->toContain('perfect condition')
        ->and(collect($props['pageHead']['actions'])->pluck('key'))->toContain('give_feedback');
});

test('booking in works without any feedback', function () {
    $stockDelivery = placedStockDeliveryWithTwoLines($this, 'FUP-PLACED-'.Str::random(6));

    expect($stockDelivery->state)->toBe(StockDeliveryStateEnum::PLACED)
        ->and($stockDelivery->feedbacks()->count())->toBe(0);

    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);
    $notChecked = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 10]]);

    $props = $this->get(route('grp.org.procurement.stock_deliveries.show', [$this->organisation->slug, $notChecked->slug]))->viewData('page')['props'];
    expect($props['give_feedback'])->toBeNull()
        ->and(array_keys($props['tabs']['navigation']))->not->toContain('feedback');
});

test('an instruction made from a four-star carton-label feedback shows on the next purchase order', function () {
    $buyer  = followUpBuyer($this, 'fup-label');
    $clerk  = userWithJobPosition($this, 'acc-c', 'fup-noproc-'.Str::lower(Str::random(6)));
    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);
    $stockDelivery = followUpCheck(followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 10]]), [10]);

    $feedback = followUpFeedback($stockDelivery, $this->adminGuest->getUser(), [
        'rating'  => 4,
        'topics'  => ['carton_labels'],
        'comment' => 'Thank you. Goods received in good condition. Please put carton labels on the side of the carton, rather than underneath.',
    ]);

    $instruction = 'Carton labels must be placed on the side of each carton, not underneath.';
    actingAs($clerk);
    $this->post(route('grp.models.stock-delivery-feedback.instruction.store', $feedback->id), ['body' => $instruction])->assertForbidden();
    actingAs($buyer);
    $this->post(route('grp.models.stock-delivery-feedback.instruction.store', $feedback->id), ['body' => $instruction])->assertSessionHasNoErrors();

    $note = $feedback->refresh()->procurementNote;
    expect($note->is_pinned)->toBeTrue()
        ->and($note->body)->toBe($instruction)
        ->and($note->category)->toBe(ProcurementNoteCategoryEnum::PACKAGING)
        ->and($note->visibility)->toBe(ProcurementNoteVisibilityEnum::SUPPLIER)
        ->and($note->author_user_id)->toBe($buyer->id)
        ->and($note->subject_id)->toBe($supplierProduct->supplier_id);

    $nextPurchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    expect(collect(GetPurchaseOrderAgreement::run($nextPurchaseOrder)['notes'])->pluck('id'))->toContain($note->id);

    $agent = followUpAgent($this);
    [$agentOrgSupplier, $agentProduct] = followUpSupplier($this, $agent);
    $agentFeedback = followUpFeedback(followUpCheck(followUpDelivery($this, $agentOrgSupplier, [[$agentProduct, 0, 10]], $agent), [10]), $buyer, ['rating' => 3, 'comment' => 'Invoice missing', 'topics' => ['documents']]);
    $agentNote     = CreateSupplierInstructionFromFeedback::make()->action($agentFeedback, $buyer, ['body' => 'Send the invoice with the goods'])->procurementNote;
    expect($agentNote->visibility)->toBe(ProcurementNoteVisibilityEnum::AGENT)
        ->and($agentNote->category)->toBe(ProcurementNoteCategoryEnum::DELIVERY);
});

test('discrepancies list on the buyer page without any feedback, routed by purchase order buyer, else the organisation buyer, else unassigned', function () {
    $poBuyer  = followUpBuyer($this, 'fup-po');
    $orgBuyer = followUpBuyer($this, 'fup-org');
    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);

    $stockDelivery = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 100], [$supplierProduct, 1, 100], [$supplierProduct, 2, 100]]);
    $purchaseOrder = followUpOrder($this, $orgSupplier, $supplierProduct, 0, $poBuyer, $stockDelivery);
    $stockDelivery = followUpCheck($stockDelivery, [97, 90, 100]);
    [$poLine, $orphanLine, $complete] = $stockDelivery->items()->orderBy('id')->get()->all();

    expect($stockDelivery->feedbacks()->count())->toBe(0);

    $unassigned = discrepancyRows($this, $poBuyer, '?owner=unassigned')->pluck('id');
    expect($unassigned)->toContain($orphanLine->id)->not->toContain($poLine->id);

    $mine = discrepancyRows($this, $poBuyer);
    $row  = $mine->firstWhere('id', $poLine->id);
    expect($mine->pluck('id')->all())->toBe([$poLine->id])
        ->and($row['buyer_id'])->toBe($poBuyer->id)
        ->and($row['purchase_order_references'])->toBe($purchaseOrder->reference)
        ->and($row['expected_quantity'])->toEqual(100)
        ->and($row['received_quantity'])->toEqual(97)
        ->and($row['difference'])->toEqual(-3)
        ->and($row['status'])->toBe('needs_decision')
        ->and($row['stock_delivery_route']['parameters']['tab'])->toBe('under_over_delivered')
        ->and(array_keys($row['stock_delivery_route']['parameters']))->toBe(['organisation', 'stockDelivery', 'tab']);

    followUpSettings($this, ['delivery_buyer_id' => $orgBuyer->id]);
    expect(discrepancyRows($this, $orgBuyer)->pluck('id')->all())->toBe([$orphanLine->id])
        ->and(discrepancyRows($this, $orgBuyer, '?owner=all')->pluck('id'))->toContain($poLine->id)->toContain($orphanLine->id)->not->toContain($complete->id);

    followUpSettings($this, ['delivery_buyer_id' => $orgBuyer->id, 'delivery_tolerance_percentage' => 5]);
    expect(discrepancyRows($this, $poBuyer)->pluck('id')->all())->toBe([]);

    $summary = IndexBuyerDeliveryDiscrepancies::summary($this->organisation->refresh(), 'mine', $orgBuyer);
    expect($summary['counts']['needs_decision'])->toBe(1);

    actingAs($this->adminGuest->getUser());
    $this->patch(route('grp.org.procurement.settings.update', [$this->organisation->slug]), ['delivery_buyer_id' => $poBuyer->id])->assertSessionHasNoErrors();
    expect(data_get($this->organisation->refresh()->settings, 'procurement.delivery_buyer_id'))->toBe($poBuyer->id);
    $blueprint = collect($this->get(route('grp.org.procurement.settings.edit', [$this->organisation->slug]))->assertOk()->viewData('page')['props']['formData']['blueprint'])
        ->flatMap(fn (array $section) => $section['fields']);
    expect($blueprint['delivery_buyer_id']['value'])->toBe($poBuyer->id)
        ->and(collect($blueprint['delivery_buyer_id']['options'])->pluck('value'))->toContain($orgBuyer->id);
});

test('a claim still open keeps the line listed after the stock is corrected, until its credit arrives', function () {
    $buyer = followUpBuyer($this, 'fup-claim');
    [$orgSupplier, $supplierProduct] = followUpSupplier($this, $this->group);
    $stockDelivery = followUpDelivery($this, $orgSupplier, [[$supplierProduct, 0, 30]]);
    followUpOrder($this, $orgSupplier, $supplierProduct, 0, $buyer, $stockDelivery);
    $item = followUpCheck($stockDelivery, [21])->items()->sole();

    $item  = ResolveStockDeliveryItemDiscrepancy::make()->action($item, $buyer, ['outcome' => 'supplier_claim']);
    $claim = UpdateStockDeliveryClaim::make()->action($item->claim, ['state' => 'sent']);

    SetStockDeliveryItemCheckedQuantity::make()->action($item->refresh(), ['unit_quantity_checked' => 22]);

    $row = discrepancyRows($this, $buyer)->firstWhere('id', $item->id);
    expect($row['status'])->toBe('claim_open')
        ->and($row['received_quantity'])->toEqual(22)
        ->and($row['claim_state'])->toBe('sent')
        ->and($claim->refresh()->state)->toBe(StockDeliveryClaimStateEnum::SENT);

    $summary = IndexBuyerDeliveryDiscrepancies::summary($this->organisation, 'mine', $buyer);
    expect($summary['counts']['claim_open'])->toBe(1)
        ->and($summary['open_claims'][0]['currency_code'])->toBe($stockDelivery->currency->code)
        ->and($summary['open_claims'][0]['amount'])->toEqual((float) $claim->amount);

    UpdateStockDeliveryClaim::make()->action($claim, ['state' => 'credit_received', 'credit_note_reference' => 'CN-FUP-1', 'credit_note_amount' => 9]);
    expect(discrepancyRows($this, $buyer)->pluck('id'))->not->toContain($item->id)
        ->and(discrepancyRows($this, $buyer, '?elements[status]=closed')->firstWhere('id', $item->id)['status'])->toBe('closed');
});
