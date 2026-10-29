<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentSales;
use App\Actions\SupplyChain\SupplierSubmission\UI\ShowSupplierSubmission;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
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
 * The parts of a supplier's page about working together: its products with what they sold at its prices, the terms
 * agreed with it that purchasing shared, the people at AW who buy from it, what it sent us and how each is going,
 * and its compliance documents with their expiry. Only the supplier's own records, at its own prices.
 */
class GetSupplierPortalDetails
{
    use AsObject;

    public const int EXPIRY_WARNING_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function handle(Supplier $supplier): array
    {
        return [
            'products'     => $this->products($supplier),
            'terms'        => $this->terms($supplier),
            'buyers'       => $this->buyers($supplier),
            'submissions'  => $this->submissions($supplier),
            'compliance'   => $this->compliance($supplier),
            'currency'     => $supplier->currency?->code,
        ];
    }

    /**
     * Each product with the packs sold and their value at the supplier's price in the last twelve whole months and
     * the twelve before. A product with nothing sold before has no percentage, it is new.
     *
     * @return array<int, array<string, mixed>>
     */
    private function products(Supplier $supplier): array
    {
        $to           = now()->subMonthNoOverflow()->format('Y-m');
        $from         = now()->subMonthsNoOverflow(12)->format('Y-m');
        $previousTo   = now()->subMonthsNoOverflow(13)->format('Y-m');
        $previousFrom = now()->subMonthsNoOverflow(24)->format('Y-m');
        $packs        = '(r.cogs_org_currency / nullif(os.sku_value, 0)) * '.ShowAgentSales::EXTERNAL_SHARE.' / '.GetAgentSalesProducts::suppliersOfSql('os.id');

        $sold = DB::table('org_stock_has_org_supplier_products as link')
            ->join('org_supplier_products as osp', 'osp.id', 'link.org_supplier_product_id')
            ->join('org_stocks as os', 'os.id', 'link.org_stock_id')
            ->join('org_stock_time_series as ts', fn ($join) => $join->on('ts.org_stock_id', 'os.id')->where('ts.frequency', TimeSeriesFrequencyEnum::MONTHLY->value))
            ->join('org_stock_time_series_records as r', 'r.org_stock_time_series_id', 'ts.id')
            ->where('link.status', true)
            ->whereIn('osp.supplier_product_id', SupplierProduct::where('supplier_id', $supplier->id)->select('id'))
            ->whereBetween('r.period', [$previousFrom, $to])
            ->groupBy('osp.supplier_product_id')
            ->selectRaw("osp.supplier_product_id,
                sum(case when r.period between ? and ? then $packs end) as packs,
                sum(case when r.period between ? and ? then $packs end) as previous_packs", [$from, $to, $previousFrom, $previousTo])
            ->get()
            ->keyBy('supplier_product_id');

        $stateLabels = SupplierProductStateEnum::labels();

        return SupplierProduct::where('supplier_id', $supplier->id)
            ->where('state', '!=', SupplierProductStateEnum::IN_PROCESS)
            ->with('image')
            ->orderBy('code')
            ->get()
            ->map(function (SupplierProduct $product) use ($sold, $stateLabels) {
                $pricePerPack = (float) $product->cost * ($product->units_per_pack ?: 1);
                $packs        = round((float) ($sold[$product->id]->packs ?? 0), 1);
                $previous     = round((float) ($sold[$product->id]->previous_packs ?? 0), 1);
                $status       = match (true) {
                    in_array($product->state, [SupplierProductStateEnum::DISCONTINUING, SupplierProductStateEnum::DISCONTINUED], true) => 'discontinued',
                    !$product->is_available => 'on_hold',
                    default => 'active',
                };

                return [
                    'id'             => $product->id,
                    'code'           => $product->code,
                    'name'           => $product->name,
                    'image'          => $product->imageSources(80, 80),
                    'status'         => $status,
                    'status_label'   => ['active' => __('Active'), 'on_hold' => __('On hold'), 'discontinued' => $stateLabels[$product->state->value] ?? __('Discontinued')][$status],
                    'price'          => (float) $product->cost,
                    'units_per_pack' => $product->units_per_pack ?: 1,
                    'packs'          => $packs,
                    'sold'           => round($packs * $pricePerPack, 2),
                    'previous'       => round($previous * $pricePerPack, 2),
                    'change'         => $previous > 0 ? round(($packs / $previous - 1) * 100, 1) : null,
                ];
            })
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
     * Who placed the latest purchase order of each organisation with the supplier, by name only: staff emails are
     * logins and the page is reachable by anyone holding the link.
     *
     * @return array<int, array{organisation: string, name: string|null}>
     */
    private function buyers(Supplier $supplier): array
    {
        return DB::table('purchase_orders as po')
            ->join('org_suppliers', fn ($join) => $join->on('org_suppliers.id', 'po.parent_id')->where('po.parent_type', 'OrgSupplier'))
            ->join('organisations', 'organisations.id', 'po.organisation_id')
            ->join('users', 'users.id', 'po.buyer_id')
            ->where('org_suppliers.supplier_id', $supplier->id)
            ->whereNull('po.deleted_at')
            ->whereNotNull('po.submitted_at')
            ->orderBy('po.organisation_id')
            ->orderByDesc('po.submitted_at')
            ->selectRaw('distinct on (po.organisation_id) organisations.name as organisation, users.contact_name as name')
            ->get()
            ->map(fn ($row) => ['organisation' => $row->organisation, 'name' => $row->name])
            ->all();
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
