<?php

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\SupplyChain\SupplierShareLink\StoreSupplierShareLink;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalSummary;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\CRM\Prospect;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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

    Config::set('inertia.testing.page_paths', [resource_path('js/Pages/Grp')]);
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
    Auth::logout();

    $this->post(route('grp.supplier_share.submit', [$token, 'company_details']), ['name' => 'Renamed Ltd', 'contact_name' => 'New Person', 'email' => 'new@supplier.test', 'phone' => '+34 600 000 000'])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.submit', [$token, 'photos']), ['supplier_product_id' => $supplierProduct->id, 'picture_kind' => 'product', 'files' => [UploadedFile::fake()->image('front.jpg', 300, 300)]])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.submit', [$token, 'referral']), ['company_name' => 'Referred Ltd', 'country' => 'Spain', 'email' => 'referred-'.$supplier->id.'@shop.test', 'permission_confirmed' => 1])->assertSessionHasNoErrors();
    $this->post(route('grp.supplier_share.submit', [$token, 'compliance_document']), ['document_type' => 'Certificate', 'expires_on' => now()->addDays(10)->toDateString(), 'files' => [UploadedFile::fake()->create('cert.pdf', 20, 'application/pdf')]])->assertSessionHasNoErrors();

    $submission = fn (SupplierSubmissionTypeEnum $type) => SupplierSubmission::where('supplier_id', $supplier->id)->where('type', $type)->sole();
    $oldEmail   = $supplier->email;

    actingAs($this->adminGuest->getUser());
    $this->post(route('grp.models.supplier_submission.decide', $submission(SupplierSubmissionTypeEnum::COMPANY_DETAILS)->id), ['state' => 'accepted', 'fields' => ['contact_name', 'name']])->assertSessionHasNoErrors();
    expect($supplier->fresh())->contact_name->toBe('New Person')->name->toBe('Renamed Ltd')->email->toBe($oldEmail)
        ->and($submission(SupplierSubmissionTypeEnum::COMPANY_DETAILS)->applied['fields'])->toBe(['name' => 'Renamed Ltd', 'contact_name' => 'New Person']);

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
    expect($supplier->fresh()->attachments()->wherePivot('scope', 'Certificate')->count())->toBe(1);

    $file = $submission(SupplierSubmissionTypeEnum::PHOTOS)->attachments->first();
    $this->get(route('grp.media.download', $file->ulid))->assertOk();
    Auth::logout();

    $this->get(route('grp.supplier_share.show', $token))
        ->assertInertia(fn ($page) => $page->loadDeferredProps('details', fn ($reload) => $reload
            ->where('details.compliance.0.document_type', 'Certificate')
            ->where('details.compliance.0.expiring', true)
            ->where('details.submissions', fn ($rows) => collect($rows)->firstWhere('type', 'referral')['feedback'] === 'Thank you')));
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

    $purchaseOrder->updateQuietly(['state' => PurchaseOrderStateEnum::SUBMITTED]);
    $received = StorePurchaseOrder::make()->action($orgSupplier, PurchaseOrder::factory()->definition());
    $received->updateQuietly(['state' => PurchaseOrderStateEnum::SETTLED, 'settled_at' => now()]);
    $orders = collect($this->get(route('grp.supplier_share.show', $token))->viewData('page')['props']['purchase_orders'])->keyBy('reference');

    expect(array_keys($orders[$purchaseOrder->reference]['labels']))->toBe(['carton', 'sko', 'unit'])
        ->and($orders[$purchaseOrder->reference]['labels']['sko'])->toContain('purchase-order-labels/'.$purchaseOrder->id)->toContain('levels=sko')
        ->and($orders[$received->reference]['labels'])->toBeNull();
    $this->get($orders[$purchaseOrder->reference]['labels']['sko'])->assertStatus(404);
});
