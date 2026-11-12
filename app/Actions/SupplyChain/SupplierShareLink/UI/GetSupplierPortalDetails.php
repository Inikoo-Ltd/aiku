<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\SupplyChain\SupplierSubmission\UI\ShowSupplierSubmission;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermStateEnum;
use App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermTypeEnum;
use App\Enums\Procurement\SupplierAgreedTerm\SupplierAgreedTermValueTypeEnum;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\Procurement\SupplierAgreedTerm;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The parts of a supplier's page about working together: the terms agreed with it that purchasing shared, the people
 * at AW who buy from it, what it sent us and how each is going, and its compliance documents with their expiry. Only the
 * supplier's own records, at its own prices. Its products table comes a page at a time from IndexSupplierPortalProducts.
 */
class GetSupplierPortalDetails
{
    use AsObject;

    public const int EXPIRY_WARNING_DAYS = 30;

    public const string OWNER_USERNAME = 'david';

    /**
     * The purchasing staff each organisation puts in front of its suppliers, by organisation slug.
     *
     * @var array<string, list<string>>
     */
    public const array PURCHASING_CONTACT_USERNAMES = [
        'aw' => ['kristy'],
        'es' => ['toni', 'quentin'],
        'sk' => ['tomas', 'juraj'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(Supplier $supplier): array
    {
        return [
            'products'     => $this->productOptions($supplier),
            'terms'        => $this->terms($supplier),
            'buyers'       => $this->buyers($supplier),
            'submissions'  => $this->submissions($supplier),
            'compliance'   => $this->compliance($supplier),
            'currency'     => $supplier->currency?->code,
        ];
    }

    /**
     * The supplier's products to pick from in its forms, without pictures or sales: the table pages through them.
     *
     * @return list<array{id: int, code: string, name: string|null, price: float}>
     */
    private function productOptions(Supplier $supplier): array
    {
        return SupplierProduct::where('supplier_id', $supplier->id)
            ->where('state', '!=', SupplierProductStateEnum::IN_PROCESS)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'cost'])
            ->map(fn (SupplierProduct $product) => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name, 'price' => (float) $product->cost])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function terms(Supplier $supplier): array
    {
        $types      = SupplierAgreedTermTypeEnum::labels();
        $valueTypes = SupplierAgreedTermValueTypeEnum::labels();

        return SupplierAgreedTerm::query()
            ->where('visibility', ProcurementNoteVisibilityEnum::SUPPLIER)
            ->where('state', SupplierAgreedTermStateEnum::ACTIVE)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('subject_type', 'Supplier')->where('subject_id', $supplier->id))
                ->orWhere(fn ($q) => $q->where('subject_type', 'SupplierProduct')->whereIn('subject_id', SupplierProduct::where('supplier_id', $supplier->id)->select('id'))))
            ->with(['currency', 'subject'])
            ->orderBy('term_type')
            ->get()
            ->map(fn (SupplierAgreedTerm $term) => [
                'term'       => $types[$term->term_type->value] ?? $term->term_type->value,
                'product'    => $term->subject instanceof SupplierProduct ? $term->subject->code : null,
                'value_type' => $valueTypes[$term->value_type->value] ?? $term->value_type->value,
                'amount'     => $term->amount !== null ? (float) $term->amount : null,
                'currency'   => $term->currency?->code,
                'text'       => $term->value_text,
                'agreed_on'  => $term->agreed_on?->toDateString(),
                'valid_to'   => $term->valid_to?->toDateString(),
            ])
            ->all();
    }

    /**
     * The owner of AW, so the supplier can always write to the top.
     *
     * @return array{name: string|null, email: string|null}|null
     */
    public function owner(Supplier $supplier): ?array
    {
        $owner = DB::table('users')
            ->where('group_id', $supplier->group_id)
            ->where('username', self::OWNER_USERNAME)
            ->selectRaw('users.contact_name as name, '.$this->emailSql().' as email')
            ->first();

        return $owner ? ['name' => $owner->name, 'email' => $owner->email] : null;
    }

    /**
     * The purchasing contacts of each organisation buying from the supplier, with the email to write to them: the staff
     * the organisation names in PURCHASING_CONTACT_USERNAMES, otherwise who placed its latest purchase order. A contact
     * for several organisations is listed once, with all of them.
     *
     * @return list<array{organisations: list<string>, country_code: string|null, name: string|null, email: string|null}>
     */
    private function buyers(Supplier $supplier): array
    {
        $latestBuyerPerOrganisation = DB::table('purchase_orders as po')
            ->join('org_suppliers', fn ($join) => $join->on('org_suppliers.id', 'po.parent_id')->where('po.parent_type', 'OrgSupplier'))
            ->join('organisations', 'organisations.id', 'po.organisation_id')
            ->leftJoin('countries', 'countries.id', 'organisations.country_id')
            ->join('users', 'users.id', 'po.buyer_id')
            ->where('org_suppliers.supplier_id', $supplier->id)
            ->whereNull('po.deleted_at')
            ->whereNotNull('po.submitted_at')
            ->orderBy('po.organisation_id')
            ->orderByDesc('po.submitted_at')
            ->selectRaw("distinct on (po.organisation_id) users.id as user_id, organisations.slug as organisation_slug, countries.code as country_code, organisations.name || coalesce(' (' || countries.name || ')', '') as organisation, users.contact_name as name, ".$this->emailSql().' as email')
            ->get();

        $namedContacts = DB::table('users')
            ->where('group_id', $supplier->group_id)
            ->where('status', true)
            ->whereIn('username', collect(self::PURCHASING_CONTACT_USERNAMES)->flatten()->unique()->all())
            ->selectRaw('users.id as user_id, users.username, users.contact_name as name, '.$this->emailSql().' as email')
            ->get()
            ->keyBy('username');

        return $latestBuyerPerOrganisation
            ->flatMap(function ($latestBuyer) use ($namedContacts) {
                $contacts = collect(self::PURCHASING_CONTACT_USERNAMES[$latestBuyer->organisation_slug] ?? [])
                    ->map(fn (string $username) => $namedContacts->get($username))
                    ->filter();

                if ($contacts->isEmpty()) {
                    return [$latestBuyer];
                }

                return $contacts->map(fn ($contact) => (object) [...(array) $contact, 'organisation' => $latestBuyer->organisation, 'country_code' => $latestBuyer->country_code]);
            })
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'organisations' => $rows->pluck('organisation')->all(),
                'country_code'  => $rows->first()->country_code,
                'name'          => $rows->first()->name,
                'email'         => $rows->first()->email,
            ])
            ->values()
            ->all();
    }

    /**
     * A staff member's work email when HR has one, otherwise the email of their user.
     */
    private function emailSql(): string
    {
        return "coalesce((select nullif(employees.work_email, '') from user_has_models join employees on employees.id = user_has_models.model_id where user_has_models.user_id = users.id and user_has_models.model_type = 'Employee' and user_has_models.status order by employees.work_email is null limit 1), nullif(users.email, ''))";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function submissions(Supplier $supplier): array
    {
        $types  = SupplierSubmissionTypeEnum::labels();
        $states = SupplierSubmissionStateEnum::labels();

        return SupplierSubmission::where('supplier_id', $supplier->id)
            ->with('supplierProduct:id,code')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (SupplierSubmission $submission) => [
                'id'          => $submission->id,
                'type'        => $submission->type->value,
                'type_label'  => $types[$submission->type->value],
                'state'       => $submission->state->value,
                'state_label' => $states[$submission->state->value],
                'summary'     => ShowSupplierSubmission::summary($submission, $submission->supplierProduct?->code),
                'feedback'    => $submission->feedback,
                'sent_at'     => $submission->submitted_at,
                'created_at'  => $submission->created_at,
                'editable'    => $submission->state->isEditableBySupplier($submission->type),
                'data'        => $submission->state->isEditableBySupplier($submission->type) ? $submission->data : null,
            ])
            ->all();
    }

    /**
     * The compliance documents we accepted, the ones about to expire first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function compliance(Supplier $supplier): array
    {
        return SupplierSubmission::where('supplier_id', $supplier->id)
            ->where('type', SupplierSubmissionTypeEnum::COMPLIANCE_DOCUMENT)
            ->where('state', SupplierSubmissionStateEnum::ACCEPTED)
            ->with('supplierProduct:id,code')
            ->orderByRaw('expires_on nulls last')
            ->get()
            ->map(fn (SupplierSubmission $submission) => [
                'document_type' => $submission->data['document_type'] ?? null,
                'product'       => $submission->supplierProduct?->code,
                'expires_on'    => $submission->expires_on?->toDateString(),
                'days_left'     => $submission->expires_on ? (int) now()->startOfDay()->diffInDays($submission->expires_on, false) : null,
                'expiring'      => $submission->expires_on && $submission->expires_on->lte(now()->addDays(self::EXPIRY_WARNING_DAYS)),
            ])
            ->all();
    }
}
