<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\SupplyChain\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalSummary;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalDetails;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\User;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierPortalContact\SupplierPortalContactDesignationEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductAvailabilityStatusEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductUnavailableReasonEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\CRM\Prospect;
use App\Models\Goods\TradeUnit;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\SupplierPortalContact;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use App\Notifications\SupplierProductAvailabilityChangedNotification;

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

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    $this->withoutVite();
});

/**
 * @return array{0: \App\Models\SupplyChain\Supplier, 1: \App\Models\SupplyChain\SupplierProduct, 2: string}
 */
function portalSupplier(object $test): array
{
    [$orgSupplier, $supplierProduct] = independentOrgSupplierFixture($test);
    $supplier                        = $orgSupplier->supplier;
    $token                           = basename(StoreSupplierShareLink::make()->handle($supplier, ['days' => 7])['url']);

    return [$supplier, $supplierProduct, $token];
}

test('a supplier sends from its page only about its own products, and only while its link is live', function () {
    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    [, $otherSupplierProduct]             = portalSupplier($this);
    Auth::logout();

    $this->post(route('grp.supplier_share.submit', [$token, 'photos']), [
        'supplier_product_id' => $otherSupplierProduct->id,
        'picture_kind'        => 'product',
        'files'               => [UploadedFile::fake()->image('front.jpg')],
    ])->assertSessionHasErrors('supplier_product_id');

    $this->post(route('grp.supplier_share.submit', [$token, 'photos']), [
        'supplier_product_id' => $supplierProduct->id,
        'picture_kind'        => 'product',
        'files'               => [UploadedFile::fake()->create('virus.exe', 10)],
    ])->assertSessionHasErrors('files.0');

    $this->post(route('grp.supplier_share.submit', [$token, 'photos']), [
        'supplier_product_id' => $supplierProduct->id,
        'picture_kind'        => 'packaging',
        'files'               => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.png')],
    ])->assertSessionHasNoErrors()->assertSessionHas('submitted', 'photos');

    $this->post(route('grp.supplier_share.submit', [$token, 'referral']), ['company_name' => 'Tiny Shop'])
        ->assertSessionHasErrors(['country', 'email', 'permission_confirmed']);
    $this->post(route('grp.supplier_share.submit', [$token, 'referral']), [
        'company_name'         => 'Tiny Shop',
        'country'              => 'Portugal',
        'email'                => 'owner@tinyshop.test',
        'permission_confirmed' => 1,
    ])->assertSessionHasNoErrors();

    $this->post(route('grp.supplier_share.submit', [$token, 'nonsense']), [])->assertNotFound();

    $submissions = SupplierSubmission::where('supplier_id', $supplier->id)->get();
    expect($submissions)->toHaveCount(2)
        ->and($submissions->firstWhere('type', SupplierSubmissionTypeEnum::PHOTOS)->attachments)->toHaveCount(2)
        ->and($submissions->pluck('state')->unique()->all())->toBe([SupplierSubmissionStateEnum::RECEIVED]);

    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page
            ->has('forms.submit.referral')
            ->loadDeferredProps('details', fn ($reload) => $reload
                ->has('details.submissions', 2)
                ->where('details.products', fn ($products) => collect($products)->pluck('id')->all() === [$supplierProduct->id])));
    $this->getJson(route('grp.supplier_share.products', $token))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $supplierProduct->id);

    \App\Models\SupplyChain\SupplierShareLink::where('supplier_id', $supplier->id)->update(['revoked_at' => now()]);
    $this->post(route('grp.supplier_share.submit', [$token, 'referral']), ['company_name' => 'Late'])->assertNotFound();
});

test('a new product idea can be kept as a draft, and a submission we asked more about is completed and sent again', function () {
    [$supplier, , $token] = portalSupplier($this);
    Auth::logout();

    $this->post(route('grp.supplier_share.submit', [$token, 'product_proposal']), ['name' => 'Moon lamp', 'draft' => 1])->assertSessionHasNoErrors();
    $draft = SupplierSubmission::where('supplier_id', $supplier->id)->sole();
    expect($draft->state)->toBe(SupplierSubmissionStateEnum::DRAFT)->and($draft->submitted_at)->toBeNull();

    $update = route('grp.supplier_share.submission.update', [$token, $draft->id]);
    $this->post($update, ['name' => 'Moon lamp'])->assertSessionHasErrors(['description', 'proposed_price']);
    $this->post($update, [
        'name'           => 'Moon lamp',
        'description'    => 'Salt lamp shaped as a moon',
        'proposed_price' => 4.5,
        'currency'       => 'INR',
        'unit_basis'     => 'piece',
    ])->assertSessionHasNoErrors();
    expect($draft->fresh()->state)->toBe(SupplierSubmissionStateEnum::RECEIVED);

    actingAs($this->adminGuest->getUser());
    $this->get(route('grp.supply-chain.supplier_submissions.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('SupplyChain/SupplierSubmissions'));
    $this->get(route('grp.supply-chain.supplier_submissions.show', $draft->id))->assertOk()
        ->assertInertia(fn ($page) => $page->where('submission.data.name', 'Moon lamp')->where('decide.can', true));

    $decide = route('grp.models.supplier_submission.decide', $draft->id);
    $this->post($decide, ['state' => 'more_information_requested'])->assertSessionHasErrors('feedback');
    $this->post($decide, ['state' => 'more_information_requested', 'feedback' => 'Which sizes?'])->assertSessionHasNoErrors();
    Auth::logout();

    $this->post($update, ['name' => 'Moon lamp', 'description' => 'Salt lamp, 10 and 15 cm', 'proposed_price' => 4.5, 'currency' => 'INR', 'unit_basis' => 'piece'])->assertSessionHasNoErrors();
    expect($draft->fresh())->state->toBe(SupplierSubmissionStateEnum::RECEIVED)
        ->and($draft->fresh()->data['description'])->toBe('Salt lamp, 10 and 15 cm');

    $this->post($update, ['name' => 'Again'])->assertForbidden();
    [, , $otherToken] = portalSupplier($this);
    Auth::logout();
    $this->post(route('grp.supplier_share.submission.update', [$otherToken, $draft->id]), ['name' => 'Hijack'])->assertNotFound();
});

test('accepting a submission applies it in aiku: ticked company details, pictures, a prospect and a compliance document', function () {
    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    $tradeUnit                            = StoreTradeUnit::make()->action($this->group, TradeUnit::factory()->definition());
    SyncSupplierProductTradeUnits::run($supplierProduct, [$tradeUnit->id => ['quantity' => 1]]);
    Auth::logout();

    $this->post(route('grp.supplier_share.submit', [$token, 'company_details']), ['name' => 'Renamed Ltd', 'contact_name' => 'New Person', 'email' => 'new@supplier.test'])->assertSessionHasErrors('phone');
    $this->post(route('grp.supplier_share.submit', [$token, 'company_details']), ['name' => 'Renamed Ltd', 'contact_name' => 'New Person', 'email' => 'new@supplier.test', 'phone' => '+34 600 000 000', 'contact_website' => 'https://renamed.test'])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.submit', [$token, 'photos']), ['supplier_product_id' => $supplierProduct->id, 'picture_kind' => 'product', 'files' => [UploadedFile::fake()->image('front.jpg', 300, 300)]])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.submit', [$token, 'referral']), ['company_name' => 'Referred Ltd', 'country' => 'Spain', 'email' => 'referred-'.$supplier->id.'@shop.test', 'permission_confirmed' => 1])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.submit', [$token, 'compliance_document']), ['document_type' => 'cpsr', 'supplier_product_id' => $supplierProduct->id, 'expires_on' => now()->addDays(10)->toDateString(), 'files' => [UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')]])->assertSessionHasNoErrors();

    $submission = fn (SupplierSubmissionTypeEnum $type) => SupplierSubmission::where('supplier_id', $supplier->id)->where('type', $type)->sole();
    $before     = $supplier->fresh()->only(['name', 'contact_name', 'email', 'contact_website']);

    actingAs($this->adminGuest->getUser());
    $this->post(route('grp.models.supplier_submission.decide', $submission(SupplierSubmissionTypeEnum::COMPANY_DETAILS)->id), ['state' => 'accepted', 'fields' => ['phone']])->assertSessionHasNoErrors();
    expect($supplier->fresh()->only(['name', 'contact_name', 'email', 'contact_website']))->toBe($before)
        ->and($supplier->fresh()->phone)->toBe('+34 600 000 000')
        ->and($submission(SupplierSubmissionTypeEnum::COMPANY_DETAILS)->applied['fields'])->toBe(['phone' => '+34 600 000 000'])
        ->and($submission(SupplierSubmissionTypeEnum::COMPANY_DETAILS)->data)->not->toHaveKeys(['name', 'contact_name', 'email']);

    $this->post(route('grp.models.supplier_submission.decide', $submission(SupplierSubmissionTypeEnum::PHOTOS)->id), ['state' => 'accepted'])->assertSessionHasNoErrors();
    expect($supplierProduct->fresh()->images()->count())->toBeGreaterThanOrEqual(1)
        ->and($supplierProduct->fresh()->image_id)->not->toBeNull();

    $referral = $submission(SupplierSubmissionTypeEnum::REFERRAL);
    $this->post(route('grp.models.supplier_submission.decide', $referral->id), ['state' => 'accepted'])->assertSessionHasErrors('shop_id');
    [, , $shop] = createShop();
    $this->post(route('grp.models.supplier_submission.decide', $referral->id), ['state' => 'accepted', 'shop_id' => $shop->id, 'feedback' => 'Thank you'])->assertSessionHasNoErrors();
    $prospect = Prospect::findOrFail($referral->fresh()->applied['prospect_id']);
    expect($prospect->shop_id)->toBe($shop->id)->and($prospect->email)->toBe('referred-'.$supplier->id.'@shop.test')
        ->and($prospect->data['referred_by_supplier_id'])->toBe($supplier->id);

    $this->post(route('grp.models.supplier_submission.decide', $submission(SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT)->id), ['state' => 'accepted'])->assertSessionHasNoErrors();
    expect($tradeUnit->fresh()->attachments()->wherePivot('scope', 'cpsr')->count())->toBe(1);

    $file = $submission(SupplierSubmissionTypeEnum::PHOTOS)->attachments->first();
    $this->get(route('grp.media.download', $file->ulid))->assertOk();
    Auth::logout();

    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('details.compliance.0.document_type', 'CPSR')
            ->where('details.compliance.0.expiring', true)
            ->where('details.submissions', fn ($rows) => collect($rows)->firstWhere('type', 'referral')['feedback'] === 'Thank you')));
});

test('a supplier adds a contact from its page; once accepted the email signs in, gets the newsletter and shows with its details', function () {
    [$supplier, , $token]   = portalSupplier($this);
    [$otherSupplier]        = portalSupplier($this);
    $otherSupplier->update(['email' => 'boss-'.$otherSupplier->id.'@other.test', 'status' => true]);
    $supplier->update(['email' => 'boss-'.$supplier->id.'@supplier.test']);
    $email                  = 'designer-'.$supplier->id.'@supplier.test';
    $submit                 = route('grp.supplier_share.submit', [$token, 'contact']);
    Auth::logout();

    $this->post($submit, ['designation' => 'designer', 'name' => 'Asha'])->assertSessionHasErrors('email');
    $this->post($submit, ['designation' => 'boss', 'name' => 'Asha', 'email' => $email])->assertSessionHasErrors('designation');
    $this->post($submit, ['designation' => 'designer', 'name' => 'Asha', 'email' => $otherSupplier->email])->assertSessionHasErrors('email');
    $this->post($submit, ['designation' => 'designer', 'name' => 'Not the boss', 'email' => strtoupper((string) $supplier->email)])->assertSessionHasErrors('email');
    $this->post($submit, ['designation' => 'designer', 'name' => 'Asha', 'email' => $email, 'mobile' => '+91 98000 00000', 'whatsapp' => '+91 98000 00001', 'wechat' => 'asha_w'])->assertSessionHasNoErrors();
    expect(SupplierPortalContact::where('supplier_id', $supplier->id)->where('email', $email)->exists())->toBeFalse();

    $contactSubmission = SupplierSubmission::where('supplier_id', $supplier->id)->where('type', SupplierSubmissionTypeEnum::CONTACT)->sole();
    actingAs($this->adminGuest->getUser());
    $this->get(route('grp.supply-chain.supplier_submissions.show', $contactSubmission->id))->assertOk()
        ->assertInertia(fn ($page) => $page->where('decide.applies', true)->where('decide.designations.designer', 'Designer'));
    $this->post(route('grp.models.supplier_submission.decide', $contactSubmission->id), ['state' => 'accepted'])->assertSessionHasNoErrors();

    $contact = SupplierPortalContact::where('supplier_id', $supplier->id)->where('email', $email)->sole();
    expect($contact)->name->toBe('Asha')->designation->toBe(SupplierPortalContactDesignationEnum::DESIGNER)
        ->phone->toBe('+91 98000 00000')->whatsapp->toBe('+91 98000 00001')->wechat->toBe('asha_w')
        ->revoked_at->toBeNull()->is_subscribed_to_newsletter->toBeTrue()
        ->authorised_by_user_id->toBe($this->adminGuest->getUser()->id)
        ->and($supplier->fresh()->data['extra_emails'])->toContain($email)
        ->and($contactSubmission->fresh()->applied['supplier_portal_contact_id'])->toBe($contact->id);

    $contact->update(['revoked_at' => now()]);
    Auth::logout();
    $this->post($submit, ['designation' => 'designer', 'name' => 'Asha again', 'email' => $email])->assertSessionHasErrors('email');
    $contact->update(['revoked_at' => null]);
    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page
            ->where('forms.designations.0.value', 'sales_manager')
            ->where('company.contacts', fn ($contacts) => collect($contacts)->contains(fn ($row) => $row['email'] === $email && $row['designation'] === 'Designer' && $row['mobile'] === '+91 98000 00000')));
});

test('the production time card ranks only suppliers with enough orders that have both a production start and a clean hand over', function () {
    [$supplier] = portalSupplier($this);
    $supplier->address->update(['country_code' => 'VN']);
    $orgSupplier = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();

    $order = function (?int $days) use ($orgSupplier): void {
        $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
        $purchaseOrder->updateQuietly([
            'state'                 => 'settled',
            'production_started_at' => $days === null ? null : now()->subDays(10 + $days),
            'handed_over_at'        => now()->subDays(10),
        ]);
    };

    $order(40);
    $order(50);
    $order(null);
    expect(GetSupplierPortalSummary::make()->production($supplier, 'VN'))
        ->average->toBe(45.0)
        ->orders->toBe(2)
        ->position->toBeNull();

    $order(60);
    expect(GetSupplierPortalSummary::make()->production($supplier, 'VN'))
        ->average->toBe(50.0)
        ->orders->toBe(3)
        ->position->toBe(1)
        ->target->toBe(GetSupplierPortalSummary::CHO_TARGET_DAYS);

    actingAs($this->adminGuest->getUser());
    $this->patch(route('grp.models.supplier_portal_settings.update'), ['start' => 'drop table', 'target_days' => 0, 'minimum_orders' => 2])->assertSessionHasErrors(['start', 'target_days']);
    $this->patch(route('grp.models.supplier_portal_settings.update'), ['start' => 'confirmed_at', 'target_days' => 30, 'minimum_orders' => 5])->assertSessionHasNoErrors();
    $this->get(route('grp.supply-chain.supplier_submissions.index'))->assertInertia(fn ($page) => $page->where('portalSettings.values.start', 'confirmed_at')->where('portalSettings.values.target_days', 30));

    expect(GetSupplierPortalSummary::make()->production($supplier->fresh(), 'VN'))
        ->target->toBe(30)
        ->minimum->toBe(5)
        ->orders->toBe(0);

    $this->patch(route('grp.models.supplier_portal_settings.update'), ['start' => 'production_started_at', 'target_days' => 45, 'minimum_orders' => 3])->assertSessionHasNoErrors();
});

test('the supplier page lists the terms purchasing shared and who buys from it', function () {
    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['submitted_at' => now(), 'buyer_id' => $this->adminGuest->getUser()->id]);

    DB::table('supplier_agreed_terms')->insert([
        'group_id'     => $this->group->id,
        'subject_type' => 'Supplier',
        'subject_id'   => $supplier->id,
        'term_type'    => 'payment_terms',
        'value_type'   => 'text',
        'value_text'   => '30% deposit',
        'agreed_on'    => now()->toDateString(),
        'state'        => 'active',
        'visibility'   => 'supplier',
        'show_on_po'   => false,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    DB::table('supplier_agreed_terms')->insert([
        'group_id'     => $this->group->id,
        'subject_type' => 'Supplier',
        'subject_id'   => $supplier->id,
        'term_type'    => 'other_charge',
        'value_type'   => 'text',
        'value_text'   => 'internal only',
        'agreed_on'    => now()->toDateString(),
        'state'        => 'active',
        'visibility'   => 'internal',
        'show_on_po'   => false,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    Auth::logout();

    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->has('details.terms', 1)
            ->where('details.terms.0.text', '30% deposit')
            ->where('details.buyers.0.name', $this->adminGuest->getUser()->contact_name)
            ->where('details.products.0.code', $supplierProduct->code)));
    $this->getJson(route('grp.supplier_share.products', [$token, 'search' => $supplierProduct->code]))
        ->assertOk()
        ->assertJsonPath('data.0.code', $supplierProduct->code);

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $received = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $received->updateQuietly(['state' => PurchaseOrderStateEnum::SETTLED, 'settled_at' => now()]);
    $orders = collect($this->get(route('grp.supplier_share.show', [$token, 'purchase_orders_facets' => ['state' => 'all']]))->viewData('page')['props']['purchase_orders']['data'])->keyBy('reference');

    expect(array_keys($orders[$purchaseOrder->reference]['labels']))->toBe(['carton', 'sko', 'unit'])
        ->and($orders[$purchaseOrder->reference]['labels']['sko'])->toContain('purchase-order-labels/'.$purchaseOrder->id)->toContain('levels=sko')
        ->and($orders[$received->reference]['labels'])->toBeNull();
    $this->get($orders[$purchaseOrder->reference]['labels']['sko'])->assertStatus(404);
});

test('INI-539 the owner as relationship contact gets the Main label on his own card, never a second card, and suppliers never see his personal email', function () {
    [$supplier, , $token] = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $buyer                = $this->adminGuest->getUser();
    $purchaseOrder        = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['submitted_at' => now(), 'buyer_id' => $buyer->id]);

    $owner = User::where('group_id', $this->group->id)->where('username', GetSupplierPortalDetails::OWNER_USERNAME)->first()
        ?? StoreGuest::make()->action($this->group, array_merge(Guest::factory()->definition(), [
            'user'      => ['username' => GetSupplierPortalDetails::OWNER_USERNAME, 'password' => 'password'],
            'positions' => [],
        ]))->getUser();
    $owner->updateQuietly(['status' => true, 'email' => 'owner.personal@gmail.com']);
    Auth::logout();

    $contacts = function () use ($token): array {
        $buyers = null;
        $owner  = $this->get(route('grp.supplier_share.show', $token))
            ->assertInertia(function (AssertableInertia $page) use (&$buyers) {
                $page->loadDeferredProps('details', function (AssertableInertia $reload) use (&$buyers) {
                    $buyers = collect($reload->toArray()['props']['details']['buyers']);
                });
            })
            ->viewData('page')['props']['owner'];

        return [$owner, $buyers];
    };

    [$ownerCard, $buyers] = $contacts();
    expect($ownerCard['is_main'])->toBeFalse()
        ->and($buyers->pluck('is_main')->unique()->all())->toBe([false]);
    $buyersWithoutMain = $buyers->count();

    $supplier->updateQuietly(['relationship_contact_user_id' => $owner->id]);
    [$ownerCard, $buyers] = $contacts();
    expect($ownerCard)->toMatchArray(['name' => $owner->contact_name, 'email' => GetSupplierPortalDetails::OWNER_EMAIL, 'is_main' => true])
        ->and($buyers)->toHaveCount($buyersWithoutMain)
        ->and($buyers->where('is_main', true))->toBeEmpty()
        ->and($buyers->pluck('email'))->not->toContain('owner.personal@gmail.com');

    $purchaseOrder->updateQuietly(['buyer_id' => $owner->id]);
    [$ownerCard, $buyers] = $contacts();
    expect($ownerCard['is_main'])->toBeTrue()
        ->and($buyers->where('is_main', true))->toBeEmpty()
        ->and($buyers->firstWhere('name', $owner->contact_name)['email'])->toBe(GetSupplierPortalDetails::OWNER_EMAIL);

    $purchaseOrder->updateQuietly(['buyer_id' => $buyer->id]);
    $supplier->updateQuietly(['relationship_contact_user_id' => $buyer->id]);
    [$ownerCard, $buyers] = $contacts();
    expect($ownerCard['is_main'])->toBeFalse()
        ->and($buyers)->toHaveCount($buyersWithoutMain)
        ->and($buyers->where('is_main', true)->pluck('name')->all())->toBe([$buyer->contact_name]);
});

test('the person on a direct link keeps their own profile through a cookie, and a cookie never opens another supplier profile', function () {
    [$supplier, , $token]  = portalSupplier($this);
    [, , $otherToken]      = portalSupplier($this);
    Auth::logout();

    $this->get(route('grp.supplier_share.show', $token))->assertOk();
    $this->post(route('grp.supplier_share.profile.update', $token), ['name' => 'Mei', 'phone' => '+86 138 0000 0000', 'theme' => 2, 'photo' => UploadedFile::fake()->image('me.jpg', 200, 200)])
        ->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.profile.update', $token), ['theme' => 99])->assertSessionHasErrors('theme');

    $profile = \App\Models\SupplyChain\SupplierPortalProfile::where('supplier_id', $supplier->id)->sole();
    expect($profile->name)->toBe('Mei')->and($profile->theme)->toBe(2)->and($profile->image_id)->not->toBeNull();

    $this->withCookie(\App\Actions\SupplyChain\SupplierShareLink\ResolveSupplierPortalProfile::COOKIE, $profile->ulid)
        ->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page->where('profile.name', 'Mei')->where('profile.theme', 2));

    $this->withCookie(\App\Actions\SupplyChain\SupplierShareLink\ResolveSupplierPortalProfile::COOKIE, $profile->ulid)
        ->get(route('grp.supplier_share.show', $otherToken))
        ->assertInertia(fn ($page) => $page->where('profile.name', null));
});

test('the supplier page search answers only on a live link opened past its code, with this supplier orders and products', function () {
    [$supplier, , $token] = portalSupplier($this);
    $codeToken            = basename(StoreSupplierShareLink::make()->handle($supplier, ['days' => 7, 'email' => 'buyer@supplier.test'])['url']);
    Auth::logout();

    $this->getJson(route('grp.supplier_share.search', [$token, 'q' => 'a']))->assertOk()->assertExactJson(['products' => [], 'orders' => []]);
    $this->getJson(route('grp.supplier_share.search', [$token, 'q' => 'anything']))->assertOk()->assertJsonStructure(['products', 'orders']);
    $this->getJson(route('grp.supplier_share.search', [$codeToken, 'q' => 'anything']))->assertForbidden();
    $this->getJson(route('grp.supplier_share.search', [str_repeat('a', 48), 'q' => 'anything']))->assertNotFound();
});

test('supply chain lists how suppliers use their page, the visited ones first', function () {
    [$supplier, , $token] = portalSupplier($this);
    Auth::logout();
    $this->withHeader('CF-IPCountry', 'GB')->get(route('grp.supplier_share.show', $token))->assertOk();
    expect(\App\Models\SupplyChain\SupplierShareLinkView::latest('id')->first()->country_code)->toBe('GB');

    actingAs($this->adminGuest->getUser());
    $this->get(route('grp.supply-chain.supplier_portal_activity.index'))
        ->assertInertia(fn ($page) => $page->component('SupplyChain/SupplierPortalActivity')
            ->where('data.data', fn ($rows) => collect($rows)->firstWhere('code', $supplier->code)['number_visits'] === 1)
            ->has('data.facets.state', 2));
});

test('a proposed price change puts the product on hold and tells its last buyer, again on each edit, until we accept or decline it', function () {
    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    $buyer         = $this->adminGuest->getUser();
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    StorePurchaseOrderTransaction::make()->action($purchaseOrder, $supplierProduct->historicSupplierProduct, $this->orgStocks[0], PurchaseOrderTransaction::factory()->definition());
    $purchaseOrder->updateQuietly(['submitted_at' => now(), 'buyer_id' => $buyer->id]);
    Auth::logout();
    Notification::fake();

    $submit = route('grp.supplier_share.submit', [$token, 'price_change']);
    $this->post($submit, ['supplier_product_id' => $supplierProduct->id, 'proposed_price' => 6.2, 'reason' => 'Cotton went up'])->assertSessionHasNoErrors();
    expect($supplierProduct->fresh())
        ->is_available->toBeFalse()
        ->unavailable_reason->toBe(SupplierProductUnavailableReasonEnum::PRICE_CHANGE_PROPOSED)
        ->and((float) $supplierProduct->fresh()->availability_new_cost)->toBe(6.2);
    Notification::assertSentToTimes($buyer, SupplierProductAvailabilityChangedNotification::class, 1);
    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page
            ->where('price_changes.data.0.code', $supplierProduct->code)
            ->where('price_changes.data.0.cost', 5)
            ->where('price_changes.data.0.status', 'on_hold')
            ->where('price_changes.data.0.price_change.proposed_price', 6.2)
            ->where('price_changes.data.0.price_change.editable', true)
            ->where('price_changes.data.0.price_change.data.supplier_product_id', $supplierProduct->id)
            ->where('price_changes.facets.state.0', ['value' => 'proposed', 'label' => 'Proposed', 'count' => 1]));

    $this->post($submit, ['supplier_product_id' => $supplierProduct->id, 'proposed_price' => 7, 'reason' => 'Again'])->assertSessionHasErrors('supplier_product_id');

    $priceChange = SupplierSubmission::where('supplier_id', $supplier->id)->sole();
    $update      = route('grp.supplier_share.submission.update', [$token, $priceChange->id]);
    $this->post($update, ['supplier_product_id' => $supplierProduct->id, 'proposed_price' => 6, 'reason' => 'Cotton went up, we absorb some'])->assertSessionHasNoErrors();
    expect((float) $supplierProduct->fresh()->availability_new_cost)->toBe(6.0);
    Notification::assertSentToTimes($buyer, SupplierProductAvailabilityChangedNotification::class, 2);

    actingAs($buyer);
    $this->post(route('grp.models.supplier_submission.decide', $priceChange->id), ['state' => 'accepted'])->assertSessionHasNoErrors();
    expect($supplierProduct->fresh())
        ->is_available->toBeTrue()
        ->availability_status->toBe(SupplierProductAvailabilityStatusEnum::AVAILABLE)
        ->unavailable_reason->toBeNull()
        ->and((float) $supplierProduct->fresh()->cost)->toBe(6.0)
        ->and($priceChange->fresh()->applied)->toMatchArray(['previous_cost' => 5, 'cost' => 6]);
    Auth::logout();

    $this->post($submit, ['supplier_product_id' => $supplierProduct->id, 'proposed_price' => 9, 'reason' => 'Try again'])->assertSessionHasNoErrors();
    expect($supplierProduct->fresh()->is_available)->toBeFalse();
    actingAs($buyer);
    $this->post(route('grp.models.supplier_submission.decide', SupplierSubmission::where('supplier_id', $supplier->id)->latest('id')->first()->id), ['state' => 'not_proceeding', 'feedback' => 'Too high'])->assertSessionHasNoErrors();
    expect($supplierProduct->fresh())->is_available->toBeTrue()->availability_new_cost->toBeNull()
        ->and((float) $supplierProduct->fresh()->cost)->toBe(6.0);
});

test('the supplier labels plan names only the organisations buying from the supplier, never the rest of the group', function () {
    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    $supplierProduct->updateQuietly(['state' => \App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum::ACTIVE]);
    DB::table('stock_has_supplier_products')->insert(['stock_id' => $this->stock->id, 'supplier_product_id' => $supplierProduct->id, 'created_at' => now(), 'updated_at' => now()]);
    $otherOrganisation = \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($this->group, \App\Models\SupplyChain\Agent::factory()->definition())->organisation;
    createOrgStocks($otherOrganisation, [$this->stock]);

    $plan = $this->get(route('grp.supplier_share.labels.plan', [$token, 'supplier_products' => $supplierProduct->id, 'for_organisation' => $otherOrganisation->id]))
        ->assertOk()->json();

    expect(collect($plan['organisations'])->pluck('id')->all())->toBe([$this->organisation->id])
        ->and($plan['organisation_id'])->toBe($this->organisation->id);
});

test('a supplier link prints at most five label PDFs a minute', function () {
    [, , $token] = portalSupplier($this);
    \Illuminate\Support\Facades\RateLimiter::clear('supplier-portal-labels:'.$token);

    foreach (range(1, 5) as $ignored) {
        $this->getJson(route('grp.supplier_share.labels', $token))->assertUnprocessable();
    }
    $this->getJson(route('grp.supplier_share.labels', $token))->assertTooManyRequests();
});

/**
 * Publishes a new version of the group's Supplier Agreement through the supervisor routes, reusing a draft left by an
 * earlier test or the v1 draft the migration adds.
 */
function publishSupplierAgreement(object $test, bool $requiresReacceptance = true, ?string $changeSummary = null): \App\Models\SupplyChain\SupplierAgreementVersion
{
    actingAs($test->adminGuest->getUser());
    $draft = \App\Models\SupplyChain\SupplierAgreementVersion::where('group_id', $test->group->id)->where('state', 'draft')->first();
    if (!$draft) {
        $test->post(route('grp.models.supplier_agreement_version.store'))->assertSessionHasNoErrors();
        $draft = \App\Models\SupplyChain\SupplierAgreementVersion::where('group_id', $test->group->id)->where('state', 'draft')->firstOrFail();
    }

    $test->patch(route('grp.models.supplier_agreement_version.update', $draft->id), [
        'body'                  => "## Conditions\n\nVersion text ".Str::random(8),
        'requires_reacceptance' => $requiresReacceptance,
        'change_summary'        => $changeSummary,
    ])->assertSessionHasNoErrors();
    $test->post(route('grp.models.supplier_agreement_version.publish', $draft->id))->assertSessionHasNoErrors();

    return $draft->refresh();
}

/**
 * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
 */
function acceptSupplierAgreement(object $test, string $token, \App\Models\SupplyChain\SupplierAgreementVersion $version, array $overrides = []): \Illuminate\Testing\TestResponse
{
    return $test->withHeaders(['CF-IPCountry' => 'ID', 'User-Agent' => 'PortalTest/1.0'])->post(route('grp.supplier_share.agreement.accept', $token), [
        'supplier_agreement_version_id' => $version->id,
        'accept'                        => true,
        'supplier_legal_name'           => 'PT Kayu Indah',
        'authorised_person'             => 'Made Wira',
        'position'                      => 'Director',
        ...$overrides,
    ]);
}

test('INI-238 a supervisor publishes the Supplier Agreement and the supplier accepts it in its portal, with the proof stored and never changed', function () {
    [$supplier, , $token] = portalSupplier($this);
    $version              = publishSupplierAgreement($this);
    Auth::logout();

    expect($version->state)->toBe(\App\Enums\SupplyChain\SupplierAgreement\SupplierAgreementVersionStateEnum::PUBLISHED)
        ->and(\App\Models\SupplyChain\SupplierAgreementVersion::where('group_id', $this->group->id)->where('state', 'published')->count())->toBe(1);

    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page
            ->where('agreement.version.id', $version->id)
            ->where('agreement.status.status', 'not_accepted')
            ->where('agreement.must_accept', true));

    acceptSupplierAgreement($this, $token, $version, ['accept' => false])->assertSessionHasErrors('accept');
    acceptSupplierAgreement($this, $token, $version, ['authorised_person' => ''])->assertSessionHasErrors('authorised_person');
    acceptSupplierAgreement($this, $token, $version, ['accepted_at' => '2020-01-01'])->assertSessionHasNoErrors();

    $acceptance = \App\Models\SupplyChain\SupplierAgreementAcceptance::where('supplier_id', $supplier->id)->sole();
    expect($acceptance->supplier_agreement_version_id)->toBe($version->id)
        ->and($acceptance->supplier_share_link_id)->toBe(\App\Models\SupplyChain\SupplierShareLink::findLiveByToken($token)->id)
        ->and($acceptance->accepted_at->isToday())->toBeTrue()
        ->and($acceptance->ip)->not->toBeNull()
        ->and($acceptance->country_code)->toBe('ID')
        ->and($acceptance->user_agent)->toBe('PortalTest/1.0')
        ->and($acceptance->locale)->not->toBeNull()
        ->and($acceptance->body_hash)->toBe(hash('sha256', $version->body))
        ->and($acceptance->authorised_person)->toBe('Made Wira');

    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page
            ->where('agreement.status.status', 'accepted')
            ->where('agreement.status.label', 'Accepted v'.$version->version)
            ->where('agreement.must_accept', false));

    expect(fn () => $acceptance->update(['position' => 'Owner']))->toThrow(LogicException::class)
        ->and(fn () => $acceptance->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::table('supplier_agreement_acceptances')->where('id', $acceptance->id)->update(['position' => 'Owner']))->toThrow(\Illuminate\Database\QueryException::class)
        ->and(fn () => DB::table('supplier_agreement_acceptances')->where('id', $acceptance->id)->delete())->toThrow(\Illuminate\Database\QueryException::class)
        ->and(fn () => $version->update(['body' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => DB::table('supplier_agreement_versions')->where('id', $version->id)->update(['body' => 'changed']))->toThrow(\Illuminate\Database\QueryException::class);

    actingAs($this->adminGuest->getUser());
    $this->patch(route('grp.models.supplier_agreement_version.update', $version->id), ['body' => 'changed'])->assertSessionHasErrors('version');
    expect($version->refresh()->body)->not->toBe('changed');
});

test('INI-238 the portal preview shows the agreement form but nothing can be sent from it, and only procurement supervisors publish', function () {
    [$supplier, , $token] = portalSupplier($this);
    $version              = publishSupplierAgreement($this);

    $this->get(route('grp.supply-chain.suppliers.portal_preview', $supplier))
        ->assertInertia(fn ($page) => $page
            ->where('preview', true)
            ->where('agreement.status.status', 'not_accepted')
            ->where('agreement.accept_route', route('grp.supply-chain.suppliers.portal_preview.blocked', [$supplier, 'agreement'])));
    $this->post(route('grp.supply-chain.suppliers.portal_preview.blocked', [$supplier, 'agreement']), [
        'supplier_agreement_version_id' => $version->id,
        'accept'                        => true,
        'supplier_legal_name'           => 'X',
        'authorised_person'             => 'Y',
        'position'                      => 'Z',
    ])->assertSessionHasErrors('preview');
    expect(\App\Models\SupplyChain\SupplierAgreementAcceptance::where('supplier_id', $supplier->id)->exists())->toBeFalse();

    $buyer = userWithJobPosition($this, 'buy', 'ini238-buyer-'.Str::lower(Str::random(6)));
    actingAsUserWithOnlyPermissions($buyer, ['supply-chain.view', "procurement.{$this->organisation->id}.view", "procurement.{$this->organisation->id}.edit"]);
    $this->post(route('grp.models.supplier_agreement_version.store'))->assertForbidden();
    $this->get(route('grp.supply-chain.supplier_agreement.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('SupplyChain/SupplierAgreementVersions')->where('can_edit', false)->where('store_route', null));
});

test('INI-238 a new version asking to accept again makes the supplier outdated, one that does not keeps it accepted, and every page says the same', function () {
    [$supplier, , $token] = portalSupplier($this);
    $first                = publishSupplierAgreement($this);
    Auth::logout();
    acceptSupplierAgreement($this, $token, $first)->assertSessionHasNoErrors();

    $clarification = publishSupplierAgreement($this, false, 'Fixed a typo in clause 4');
    Auth::logout();
    acceptSupplierAgreement($this, $token, $first)->assertSessionHasErrors('supplier_agreement_version_id');
    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page
            ->where('agreement.status.status', 'accepted')
            ->where('agreement.status.label', 'Accepted v'.$first->version)
            ->where('agreement.must_accept', false)
            ->where('agreement.changes.0.change_summary', 'Fixed a typo in clause 4'));
    expect($first->refresh()->state->value)->toBe('retired')
        ->and($clarification->state->value)->toBe('published');

    $newTerms = publishSupplierAgreement($this);
    $status   = \App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus::make()->handle($supplier);
    expect($status['status'])->toBe('outdated');

    $this->get(route('grp.supply-chain.suppliers.show', $supplier))
        ->assertInertia(fn ($page) => $page->where('agreement.supplier_agreement.status', 'outdated')->has('agreement.supplier_agreement.history', 1));
    $listed = collect($this->getJson(route('grp.supply-chain.suppliers.index', ['elements[agreement]' => 'outdated', 'filter[global]' => $supplier->code]))->json('data'))->keyBy('code');
    expect($listed[$supplier->code]['agreement']['status'])->toBe('outdated');
    $notAccepted = collect($this->getJson(route('grp.supply-chain.suppliers.index', ['elements[agreement]' => 'not_accepted', 'filter[global]' => $supplier->code]))->json('data'));
    expect($notAccepted->pluck('code'))->not->toContain($supplier->code);

    Auth::logout();
    $this->get(route('grp.supplier_share.show', $token))->assertInertia(fn ($page) => $page->where('agreement.status.status', 'outdated')->where('agreement.must_accept', true));
    acceptSupplierAgreement($this, $token, $newTerms)->assertSessionHasNoErrors();
    expect(\App\Actions\SupplyChain\SupplierAgreement\GetSupplierAgreementStatus::make()->handle($supplier)['label'])->toBe('Accepted v'.$newTerms->version);

    actingAs($this->adminGuest->getUser());
    $acceptance = \App\Models\SupplyChain\SupplierAgreementAcceptance::where('supplier_id', $supplier->id)->latest('id')->first();
    $this->get(route('grp.supply-chain.suppliers.agreement_acceptance.pdf', [$supplier->slug, $acceptance->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('INI-238 a purchase order confirmed after the acceptance keeps it, one confirmed before keeps none, and an order to a supplier that has not accepted warns but is not blocked', function () {
    [$supplier, , $token] = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $version              = publishSupplierAgreement($this);

    $confirmedBefore = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $confirmedBefore->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);
    $confirmedBefore = \App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed::make()->action($confirmedBefore->refresh());
    expect($confirmedBefore->supplier_agreement_acceptance_id)->toBeNull();

    $warning = \App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement::make()->supplierAgreement($confirmedBefore);
    expect($warning['status'])->toBe('not_accepted')->and($warning['warning'])->not->toBeNull();

    Auth::logout();
    acceptSupplierAgreement($this, $token, $version)->assertSessionHasNoErrors();
    $acceptance = \App\Models\SupplyChain\SupplierAgreementAcceptance::where('supplier_id', $supplier->id)->sole();

    $this->travel(1)->minutes();
    $confirmedAfter = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $confirmedAfter->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);
    $confirmedAfter = \App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed::make()->action($confirmedAfter->refresh());

    expect($confirmedAfter->supplier_agreement_acceptance_id)->toBe($acceptance->id)
        ->and($confirmedBefore->refresh()->supplier_agreement_acceptance_id)->toBeNull();

    $status = \App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement::make()->supplierAgreement($confirmedAfter);
    expect($status['status'])->toBe('accepted')
        ->and($status['warning'])->toBeNull()
        ->and($status['order_acceptance']['version'])->toBe($version->version);
});

test('INI-238 once a version is published every staff page shows the status, agent organisations see only the status, and reverting an order clears its acceptance', function () {
    [$supplier, , $token] = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $agent                = \App\Actions\SupplyChain\Agent\StoreAgent::make()->action($this->group, \App\Models\SupplyChain\Agent::factory()->definition());
    $orgAgent             = \App\Actions\Procurement\OrgAgent\StoreOrgAgent::make()->action($this->organisation, $agent, []);
    [$agentOrgSupplier]   = createAgentOrgSupplierWithProduct($this, $orgAgent);
    $version              = publishSupplierAgreement($this);
    Auth::logout();
    acceptSupplierAgreement($this, $token, $version)->assertSessionHasNoErrors();
    actingAs($this->adminGuest->getUser());

    $this->get(route('grp.supply-chain.supplier_agreement.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('can_edit', true)->has('supplier_counts.accepted'));
    $this->get(route('grp.supply-chain.supplier_agreement.show', $version->id))->assertOk()
        ->assertInertia(fn ($page) => $page->component('SupplyChain/SupplierAgreementVersion')->where('can_edit', false)->where('version.body_hash', hash('sha256', $version->body)));

    $this->get(route('grp.org.procurement.org_suppliers.show', [$this->organisation->slug, $orgSupplier->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('agreement.supplier_agreement.status', 'accepted')->has('agreement.supplier_agreement.history', 1));
    $this->get(route('grp.supply-chain.agents.show', [$agent->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('agreement.supplier_agreement.not_accepted', 1)->where('agreement.supplier_agreement.suppliers.0.code', $agentOrgSupplier->supplier->code)->where('agreement.supplier_agreement.suppliers_route.name', 'grp.supply-chain.agents.show.suppliers.index'));
    $this->get(route('grp.org.procurement.org_agents.show', [$this->organisation->slug, $orgAgent->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('agreement.supplier_agreement.suppliers.0.status', 'not_accepted'));
    $agentSuppliers = collect($this->getJson(route('grp.supply-chain.agent_suppliers.index', ['elements[agreement]' => 'not_accepted', 'filter[global]' => $agentOrgSupplier->supplier->code]))->json('data'));
    expect($agentSuppliers->firstWhere('code', $agentOrgSupplier->supplier->code)['agreement']['status'])->toBe('not_accepted');
    expect(collect($this->getJson(route('grp.supply-chain.agent_suppliers.index', ['elements[agreement]' => 'accepted', 'filter[global]' => $agentOrgSupplier->supplier->code]))->json('data'))->pluck('code'))
        ->not->toContain($agentOrgSupplier->supplier->code);

    $forAgentOrganisation = \App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement::run($supplier, $this->organisation, $this->adminGuest->getUser(), true)['supplier_agreement'];
    expect($forAgentOrganisation['status'])->toBe('accepted')
        ->and($forAgentOrganisation)->not->toHaveKey('history')
        ->and($forAgentOrganisation)->not->toHaveKey('authorised_person');
    $agentRows = \App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement::run($agent, $this->organisation, $this->adminGuest->getUser(), true)['supplier_agreement']['suppliers'];
    expect($agentRows[0]['slug'])->toBeNull()->and($agentRows[0])->not->toHaveKey('authorised_person');

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);
    $purchaseOrder = \App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());
    expect($purchaseOrder->supplier_agreement_acceptance_id)->not->toBeNull();

    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('agreement.supplier_agreement_status.status', 'accepted')->where('agreement.supplier_agreement_status.order_acceptance.version', $version->version));

    $purchaseOrder = \App\Actions\Procurement\PurchaseOrder\RevertPurchaseOrderToSubmitted::make()->action($purchaseOrder);
    expect($purchaseOrder->refresh()->supplier_agreement_acceptance_id)->toBeNull();

    $supervisor = userWithJobPosition($this, 'buy', 'ini238-lead-'.Str::lower(Str::random(6)));
    actingAsUserWithOnlyPermissions($supervisor, ["org-supervisor.{$this->organisation->id}.procurement", "procurement.{$this->organisation->id}.view", "procurement.{$this->organisation->id}.edit"]);
    $this->get(route('grp.supply-chain.supplier_agreement.index'))->assertOk()->assertInertia(fn ($page) => $page->where('can_edit', true));
});

test('INI-238 a published version never goes back to draft, a supplier accepts a version once, and an order confirmed on an earlier date keeps the acceptance in force then', function () {
    [$supplier, , $token] = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $version              = publishSupplierAgreement($this);

    expect(fn () => $version->update(['state' => 'draft']))->toThrow(LogicException::class)
        ->and(fn () => DB::table('supplier_agreement_versions')->where('id', $version->id)->update(['state' => 'draft']))->toThrow(\Illuminate\Database\QueryException::class);

    $this->travel(-10)->days();
    Auth::logout();
    acceptSupplierAgreement($this, $token, $version)->assertSessionHasNoErrors();
    $this->travelBack();
    acceptSupplierAgreement($this, $token, $version)->assertSessionHasNoErrors();
    expect(\App\Models\SupplyChain\SupplierAgreementAcceptance::where('supplier_id', $supplier->id)->count())->toBe(1)
        ->and(fn () => \App\Actions\SupplyChain\SupplierAgreement\AcceptSupplierAgreement::make()->handle($supplier, $version, [
            'supplier_legal_name' => 'X', 'authorised_person' => 'Y', 'position' => 'Z',
        ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    $purchaseOrder = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);
    $purchaseOrder = \App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrderStateToConfirmed::make()->action($purchaseOrder->refresh());
    expect($purchaseOrder->supplier_agreement_acceptance_id)->not->toBeNull();

    $purchaseOrder->update(['confirmed_at' => now()->subDays(20)]);
    expect($purchaseOrder->refresh()->supplier_agreement_acceptance_id)->toBeNull();
});

test('HELP-4731 a supplier uploads shipping documents to its own order from its portal and sees only those, never what the buyer attached', function () {
    [$supplier, , $token] = portalSupplier($this);
    [, , $otherToken]     = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $purchaseOrder        = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now()]);

    \App\Actions\Helpers\Media\AttachAttachmentToModel::make()->action($purchaseOrder, [
        'attachments' => [UploadedFile::fake()->createWithContent('our-bank-statement.pdf', '%PDF-1.4 bank '.Str::random(32))],
        'scope'       => 'Bank statement',
    ]);
    $buyerMedia = $purchaseOrder->attachments()->sole();
    Auth::logout();

    $route = route('grp.supplier_share.purchase_order.documents.store', ['token' => $token, 'purchaseOrder' => $purchaseOrder->id]);
    $this->postJson($route, ['scope' => 'Claim', 'files' => [UploadedFile::fake()->create('bl.pdf', 20, 'application/pdf')]])->assertJsonValidationErrors('scope');
    $this->postJson(route('grp.supplier_share.purchase_order.documents.store', ['token' => $otherToken, 'purchaseOrder' => $purchaseOrder->id]), [
        'scope' => 'Bill of lading',
        'files' => [UploadedFile::fake()->create('bl.pdf', 20, 'application/pdf')],
    ])->assertNotFound();

    $this->postJson($route, ['scope' => 'Bill of lading', 'files' => [UploadedFile::fake()->createWithContent('bl.html', '%PDF-1.4 '.Str::random(32))]])->assertJsonValidationErrors('files.0');
    $this->postJson($route, ['scope' => 'Invoice', 'files' => [UploadedFile::fake()->createWithContent('copy-of-ours.pdf', file_get_contents($buyerMedia->getPath()))]])
        ->assertJsonValidationErrors('files');

    $origin    = '%PDF-1.4 origin '.Str::random(32);
    $this->postJson($route, ['scope' => 'Proforma', 'files' => [UploadedFile::fake()->createWithContent('origin.pdf', $origin)]])->assertOk();
    $documents = $this->postJson($route, [
        'scope' => 'Certificate of origin',
        'files' => [UploadedFile::fake()->createWithContent('origin.pdf', $origin)],
    ])->assertOk()->json('documents');

    expect($documents['files'])->toHaveCount(1)
        ->and($documents['open'])->toBeTrue()
        ->and($documents['files'][0]['type'])->toBe('Certificate of origin')
        ->and($documents['files'][0]['file_name'])->toBe('origin.pdf')
        ->and(collect($documents['types'])->pluck('value')->all())->toContain('Bill of lading', 'Packing list', 'Proforma')
        ->and($purchaseOrder->attachments()->wherePivot('scope', 'Certificate of origin')->wherePivot('sub_scope', 'supplier_portal')->count())->toBe(1)
        ->and($purchaseOrder->attachments()->where('media.id', $buyerMedia->id)->sole()->pivot->scope)->toBe('Bank statement');

    $this->get($documents['files'][0]['download_url'])->assertOk();
    $this->get(route('grp.supplier_share.purchase_order.document', ['token' => $token, 'purchaseOrder' => $purchaseOrder->id, 'media' => $buyerMedia->ulid]))->assertNotFound();

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::CANCELLED]);
    $this->postJson($route, ['scope' => 'Invoice', 'files' => [UploadedFile::fake()->createWithContent('late.pdf', '%PDF-1.4 '.Str::random(32))]])->assertJsonValidationErrors('files');

    actingAs($this->adminGuest->getUser());
    $this->post(route('grp.supply-chain.suppliers.portal_preview.blocked', [$supplier, 'path' => "purchase-orders/$purchaseOrder->id/documents"]))->assertSessionHasErrors('preview');

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('supplier_proformas', []));

    \App\Actions\Helpers\Media\AttachAttachmentToModel::make()->action($purchaseOrder, [
        'attachments' => [UploadedFile::fake()->createWithContent('PI-2291.pdf', '%PDF-1.4 proforma '.Str::random(32))],
        'scope'       => 'Proforma',
    ]);
    $this->get(route('grp.org.procurement.purchase_orders.show', [$this->organisation->slug, $purchaseOrder->slug]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('supplier_proformas', 1)
            ->where('supplier_proformas.0.media_ulid', $purchaseOrder->attachments()->wherePivot('scope', 'Proforma')->sole()->ulid)
            ->where('pageHead.actions', fn ($actions) => !collect($actions)->flatten(1)->contains(fn ($action) => ($action['key'] ?? null) === 'proforma_invoice')));
});

/**
 * @param  list<list<mixed>>  $rows
 */
function w4ProposalSheet(array $rows, string $name = 'products.xlsx', ?array $secondSheetRows = null): UploadedFile
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    if ($secondSheetRows) {
        $spreadsheet->createSheet()->fromArray($secondSheetRows, null, 'A1', true);
    }
    $path = tempnam(sys_get_temp_dir(), 'w4').'.xlsx';
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

function w4ProposalHeadings(): array
{
    return (new \App\Exports\SupplyChain\SupplierProductProposalTemplateExport('USD'))->headings();
}

test('W4 the product upload reads only the template: wrong headings and more than 200 rows are refused, formulas are calculated and formula starts are stripped', function () {
    [, , $token] = portalSupplier($this);
    Auth::logout();
    $route = route('grp.supplier_share.product_proposals.preview', $token);

    $this->postJson($route, ['file' => w4ProposalSheet([['Foo', 'Bar'], ['Bowl', 'Nice']])])->assertJsonValidationErrors('file');

    $tooMany = [w4ProposalHeadings()];
    for ($i = 1; $i <= \App\Actions\SupplyChain\SupplierSubmission\ImportSupplierPortalProductProposals::MAX_ROWS + 1; $i++) {
        $tooMany[] = ['Bowl '.$i, 'Hand carved bowl '.$i, 4.5, 'USD', 'piece'];
    }
    $this->postJson($route, ['file' => w4ProposalSheet($tooMany)])->assertJsonValidationErrors('file');

    $headings    = array_map(fn (string $heading) => strtoupper(str_replace(' *', '', $heading)), w4ProposalHeadings());
    $rows        = $this->postJson($route, ['file' => w4ProposalSheet(
        [$headings, ['=HYPERLINK("http://x.test","Bowl")', '@Hand carved bowl', '=2+2.5', 'usd', 'piece', '100 pieces', 45, '20 cm', '+cmd', 'Paper bag']],
        secondSheetRows: array_fill(0, 3, ['ignored'])
    )])->assertOk()->json('rows');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['row'])->toBe(2)
        ->and($rows[0]['values']['name'])->toBe('Bowl')
        ->and($rows[0]['values']['description'])->toBe('Hand carved bowl')
        ->and($rows[0]['values']['materials'])->toBe('cmd')
        ->and((float) $rows[0]['values']['proposed_price'])->toBe(4.5)
        ->and($rows[0]['values']['currency'])->toBe('USD')
        ->and($rows[0]['errors'])->toBe([]);
});

test('W4 a portal contact sets the company photo and the avatar, both taking their type from the file content', function () {
    [$supplier, , $token] = portalSupplier($this);
    Auth::logout();
    $imageBefore = $supplier->fresh()->image_id;

    $this->get(route('grp.supplier_share.show', $token))->assertOk();
    $this->post(route('grp.supplier_share.profile.update', $token), ['company_photo' => UploadedFile::fake()->image('logo.jpg', 200, 200)])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.profile.update', $token), ['company_photo' => UploadedFile::fake()->create('x.jpg', 10, 'text/plain')])->assertSessionHasErrors('company_photo');
    $this->post(route('grp.supplier_share.profile.update', $token), ['photo' => UploadedFile::fake()->image('me.png', 200, 200)])->assertSessionHasNoErrors();

    $profile = \App\Models\SupplyChain\SupplierPortalProfile::where('supplier_id', $supplier->id)->sole();
    expect($supplier->fresh()->image_id)->not->toBeNull()->not->toBe($imageBefore)
        ->and($profile->image_id)->not->toBeNull();
});

test('W4 supplier planning and deposit notifications are queued and show supplier text as plain text in the email', function () {
    expect(\App\Notifications\SupplierPlanningNoteNotification::class)->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and(\App\Notifications\SupplierDepositRequestedNotification::class)->toImplement(\Illuminate\Contracts\Queue\ShouldQueue::class);

    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    $submission                           = SupplierSubmission::create([
        'group_id'               => $supplier->group_id,
        'supplier_id'            => $supplier->id,
        'supplier_share_link_id' => \App\Models\SupplyChain\SupplierShareLink::where('supplier_id', $supplier->id)->value('id'),
        'type'                   => SupplierSubmissionTypeEnum::MATERIAL_PLANNING,
        'state'                  => SupplierSubmissionStateEnum::RECEIVED,
        'data'                   => [],
        'submitted_at'           => now(),
    ]);

    $notification = new \App\Notifications\SupplierPlanningNoteNotification($submission, 'Title', 'Click [here](https://evil.test) <b>now</b>.', 'Why');
    $mail         = $notification->toMail($this->adminGuest->getUser());

    expect($mail->introLines[0])->toBe('Click \[here\]\(https://evil.test\) \<b\>now\</b\>.')
        ->and($notification->afterCommit)->toBeTrue();
});

test('W4 a second identical deposit request from the portal sends no second email and keeps the rest of the order data', function () {
    [$supplier, , $token] = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $purchaseOrder        = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $buyer                = $this->adminGuest->getUser();
    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED, 'submitted_at' => now(), 'buyer_id' => $buyer->id, 'data' => ['keep_me' => 'yes']]);
    Auth::logout();
    if ($published = \App\Models\SupplyChain\SupplierAgreementVersion::where('group_id', $this->group->id)->where('state', 'published')->first()) {
        acceptSupplierAgreement($this, $token, $published)->assertSessionHasNoErrors();
    }
    Notification::fake();

    $route = route('grp.supplier_share.purchase_order.deposit', ['token' => $token, 'purchaseOrder' => $purchaseOrder->id]);
    $this->postJson($route, ['choice' => 'request'])->assertOk();
    $this->postJson($route, ['choice' => 'request'])->assertOk();

    Notification::assertSentToTimes($buyer, \App\Notifications\SupplierDepositRequestedNotification::class, 1);
    expect($purchaseOrder->fresh()->data)->toHaveKeys(['keep_me', 'supplier_deposit_request']);

    $this->postJson($route, ['choice' => 'not_needed'])->assertJsonValidationErrors('deposit');
});

test('W4 the supplier records production only from the day the order was agreed, never in the future, and clears only a date it set itself', function () {
    [$supplier, , $token] = portalSupplier($this);
    $orgSupplier          = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $purchaseOrder        = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $purchaseOrder->updateQuietly([
        'state'                 => PurchaseOrderStateEnum::CONFIRMED,
        'submitted_at'          => now()->subDays(12),
        'confirmed_at'          => now()->subDays(10),
        'deposit_amount'        => 0,
        'production_started_at' => now()->subDays(3),
    ]);
    Auth::logout();

    $route = route('grp.supplier_share.purchase_order.production', ['token' => $token, 'purchaseOrder' => $purchaseOrder->id]);
    $send  = fn (?string $startedAt) => $this->postJson($route, ['production_started_at' => $startedAt, 'estimated_production_date' => null]);

    $send(null)->assertJsonValidationErrors('production_started_at');
    $send(now()->subDays(2)->toDateString())->assertJsonValidationErrors('production_started_at');
    expect($purchaseOrder->fresh()->production_started_at->toDateString())->toBe(now()->subDays(3)->toDateString());

    $purchaseOrder->updateQuietly(['production_started_at' => null]);
    $send(now()->subDays(13)->toDateString())->assertJsonValidationErrors('production_started_at');
    $send(now()->addDays(3)->toDateString())->assertJsonValidationErrors('production_started_at');

    $send(now()->subDays(4)->toDateString())->assertOk();
    expect($purchaseOrder->fresh()->production_started_at->toDateString())->toBe(now()->subDays(4)->toDateString());

    $send(null)->assertOk();
    expect($purchaseOrder->fresh()->production_started_at)->toBeNull();
});

test('W4 the product upload preview has its own rate limit, apart from the submit one', function () {
    $request = \Illuminate\Http\Request::create('/x');
    $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('POST', '/x/{token}', []))->bind($request));

    $preview = \Illuminate\Support\Facades\RateLimiter::limiter('supplier-portal-preview')($request);
    $submit  = \Illuminate\Support\Facades\RateLimiter::limiter('supplier-portal-submit')($request);

    expect($preview[0]->maxAttempts)->toBe(60)
        ->and($preview[0]->key)->not->toBe($submit[0]->key);
});

test('W4 products sent from the upload are saved with their pictures attached once the products are stored', function () {
    [$supplier, , $token] = portalSupplier($this);
    Auth::logout();

    $this->postJson(route('grp.supplier_share.product_proposals.import', $token), ['products' => [[
        'row'            => 2,
        'name'           => 'Mango wood bowl',
        'description'    => 'Hand carved bowl',
        'proposed_price' => 4.5,
        'currency'       => 'USD',
        'unit_basis'     => 'piece',
        'files'          => [UploadedFile::fake()->image('bowl.jpg', 100, 100)],
    ]]])->assertOk()->assertJson(['sent' => [2]]);

    $submission = SupplierSubmission::where('supplier_id', $supplier->id)->where('type', SupplierSubmissionTypeEnum::PRODUCT_PROPOSAL)->sole();
    expect($submission->attachments()->count())->toBe(1);
});

test('INI-523 a direct supplier marking its order complete from its portal tells the buyer once', function () {
    [$supplier, $supplierProduct, $token] = portalSupplier($this);
    $orgSupplier   = $supplier->orgSuppliers()->where('organisation_id', $this->organisation->id)->firstOrFail();
    $orgSupplierProduct = $orgSupplier->orgSupplierProducts()->where('supplier_product_id', $supplierProduct->id)->firstOrFail();
    $purchaseOrder = createConfirmedAgentOrder($this, $orgSupplier, $orgSupplierProduct, 0, 10);
    $buyer         = $this->adminGuest->getUser();
    $purchaseOrder->updateQuietly(['buyer_id' => $buyer->id]);
    Auth::logout();
    if ($published = \App\Models\SupplyChain\SupplierAgreementVersion::where('group_id', $this->group->id)->where('state', 'published')->first()) {
        acceptSupplierAgreement($this, $token, $published)->assertSessionHasNoErrors();
    }

    $this->postJson(route('grp.supplier_share.purchase_order.documents.store', ['token' => $token, 'purchaseOrder' => $purchaseOrder->id]), [
        'scope' => 'Packing list',
        'files' => [UploadedFile::fake()->createWithContent('pl.pdf', '%PDF-1.4 pl '.Str::random(32))],
    ])->assertOk();

    Notification::fake();
    $this->postJson(route('grp.supplier_share.purchase_order.complete', ['token' => $token, 'purchaseOrder' => $purchaseOrder->id]))->assertOk();

    Notification::assertSentToTimes($buyer, \App\Notifications\SupplierDepositRequestedNotification::class, 1);
    Notification::assertSentTo($buyer, \App\Notifications\SupplierDepositRequestedNotification::class, fn ($notification) => $notification->type === 'supplier_shipped_order');
});
