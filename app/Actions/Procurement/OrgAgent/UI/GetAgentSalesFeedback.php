<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Enums\Procurement\ProcurementNote\ProcurementNoteStateEnum;
use App\Actions\Reviews\TranslateReviewMessage;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\Models\Helpers\Language;
use App\Models\Reviews\Review;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What customers, purchasing and the warehouse say about an agent's products, or one supplier's. Customers are only
 * quoted when a review is public, the rest of their side (refunds, replacements) is numbers. Purchasing shows only
 * the notes marked as visible to the agent, or to the supplier when a supplier is read on its own. The warehouse is
 * numbers from goods-in. Given a language, as on the supplier portal, the quoted reviews are in that language,
 * translated the first time they are shown.
 */
class GetAgentSalesFeedback
{
    use AsObject;

    private const int RECENT_REVIEWS = 5;

    private const int WORST_PRODUCTS = 5;

    private const int NOTES = 20;

    /**
     * @return array<string, mixed>
     */
    public function handle(Agent|Supplier $agent, ?Supplier $supplier = null, ?Language $language = null): array
    {
        if ($agent instanceof Supplier) {
            $supplier = $agent;
        }

        $products = GetAgentSalesProducts::make()->products($agent, null, false)
            ->when($supplier, fn (Builder $query) => $query->where('supplier_id', $supplier->id))
            ->get();

        $reviews  = (int) $products->sum('reviews');
        $invoices = (float) $products->sum('invoices');
        $lines    = (int) $products->sum('delivery_lines');

        return [
            'customers'  => [
                'rating'         => $reviews ? round($products->sum('rating_sum') / $reviews, 2) : null,
                'reviews'        => $reviews,
                'refund_percent' => $invoices ? round(100 * $products->sum('refunds') / $invoices, 2) : null,
                'recent'         => $this->recentReviews($agent, $supplier, $language),
            ],
            'purchasing' => $this->notes($agent, $supplier),
            'warehouse'  => [
                'short_percent' => $lines ? round(100 * $products->sum('short_lines') / $lines, 2) : null,
                'short_lines'   => (int) $products->sum('short_lines'),
                'lines'         => $lines,
                'worst'         => $products->where('short_lines', '>', 0)->sortByDesc('short_lines')->take(self::WORST_PRODUCTS)
                    ->map(fn ($product) => ['code' => $product->code, 'name' => $product->name, 'short_lines' => (int) $product->short_lines, 'lines' => (int) $product->delivery_lines])
                    ->values()->all(),
            ],
        ];
    }

    private function agentProducts(Agent|Supplier $agent, ?Supplier $supplier): Builder
    {
        return DB::table('org_supplier_products as feedback_osp')
            ->when($agent instanceof Agent, fn (Builder $query) => $query->join('org_agents as feedback_oa', fn ($join) => $join->on('feedback_oa.id', 'feedback_osp.org_agent_id')->where('feedback_oa.agent_id', $agent->id)))
            ->join('supplier_products as feedback_sp', 'feedback_sp.id', 'feedback_osp.supplier_product_id')
            ->whereNull('feedback_sp.deleted_at')
            ->when($supplier, fn (Builder $query) => $query->where('feedback_sp.supplier_id', $supplier->id));
    }

    /**
     * @return array<int, array{code: string, rating: int, message: string, date: string}>
     */
    private function recentReviews(Agent|Supplier $agent, ?Supplier $supplier, ?Language $language): array
    {
        $reviews = GetAgentSalesProducts::make()->publicReviews()
            ->join('org_stock_has_org_supplier_products as link', fn ($join) => $join->on('link.org_stock_id', 'phos.org_stock_id')->where('link.status', true))
            ->join('org_supplier_products as feedback_osp', 'feedback_osp.id', 'link.org_supplier_product_id')
            ->when($agent instanceof Agent, fn (Builder $query) => $query->join('org_agents as feedback_oa', fn ($join) => $join->on('feedback_oa.id', 'feedback_osp.org_agent_id')->where('feedback_oa.agent_id', $agent->id)))
            ->join('supplier_products as feedback_sp', 'feedback_sp.id', 'feedback_osp.supplier_product_id')
            ->when($supplier, fn (Builder $query) => $query->where('feedback_sp.supplier_id', $supplier->id))
            ->whereRaw("trim(coalesce(review.message, '')) <> ''")
            ->orderByDesc('review.created_at')
            ->orderBy('review.id')
            ->limit(self::RECENT_REVIEWS * 3)
            ->selectRaw('review.id, feedback_sp.code, review.rating_main as rating, review.message, review.created_at as date')
            ->get()
            ->unique('id')
            ->take(self::RECENT_REVIEWS)
            ->values();

        if ($language) {
            $models  = Review::with(['language', 'shop.language'])->findMany($reviews->pluck('id'))->keyBy('id');
            $english = Language::where('code', 'en')->first();
            $reviews = $reviews->map(function ($review) use ($models, $language, $english) {
                $model           = $models->get($review->id);
                $review->message = ($model ? TranslateReviewMessage::run($model, $language) : null)
                    ?? ($model && $english ? $model->messageTranslations()[$english->id] ?? null : null)
                    ?? $review->message;

                return $review;
            });
        }

        return $reviews
            ->map(fn ($review) => ['code' => $review->code, 'rating' => (int) $review->rating, 'message' => $review->message, 'date' => substr((string) $review->date, 0, 10)])
            ->all();
    }

    /**
     * Only notes the agent was meant to read, on the supplier, its products or its purchase orders.
     *
     * @return array<int, array{title: string, body: string|null, severity: string, category: string, date: string}>
     */
    private function notes(Agent|Supplier $agent, ?Supplier $supplier): array
    {
        $suppliers = DB::table('suppliers')->when($agent instanceof Agent, fn (Builder $query) => $query->where('agent_id', $agent->id))->when($supplier, fn (Builder $query) => $query->where('id', $supplier->id))->select('id');
        $products  = $this->agentProducts($agent, $supplier);

        return DB::table('procurement_notes as note')
            ->where('note.visibility', $agent instanceof Agent ? ProcurementNoteVisibilityEnum::AGENT->value : ProcurementNoteVisibilityEnum::SUPPLIER->value)
            ->where('note.state', ProcurementNoteStateEnum::ACTIVE->value)
            ->where(function (Builder $query) use ($suppliers, $products) {
                $query
                    ->where(fn (Builder $subject) => $subject->where('note.subject_type', 'Supplier')->whereIn('note.subject_id', (clone $suppliers)))
                    ->orWhere(fn (Builder $subject) => $subject->where('note.subject_type', 'SupplierProduct')->whereIn('note.subject_id', (clone $products)->select('feedback_sp.id')))
                    ->orWhere(fn (Builder $subject) => $subject->where('note.subject_type', 'OrgSupplierProduct')->whereIn('note.subject_id', (clone $products)->select('feedback_osp.id')))
                    ->orWhere(fn (Builder $subject) => $subject->where('note.subject_type', 'OrgSupplier')->whereIn('note.subject_id', DB::table('org_suppliers')->whereIn('supplier_id', (clone $suppliers))->select('id')))
                    ->orWhere(fn (Builder $subject) => $subject->where('note.subject_type', 'PurchaseOrder')->whereIn('note.subject_id', DB::table('purchase_orders')->where('parent_type', 'OrgSupplier')->whereIn('parent_id', DB::table('org_suppliers')->whereIn('supplier_id', (clone $suppliers))->select('id'))->select('id')));
            })
            ->orderByRaw("case note.severity when 'critical' then 1 when 'warning' then 2 else 3 end")
            ->orderByDesc('note.created_at')
            ->limit(self::NOTES)
            ->get(['note.title', 'note.body', 'note.severity', 'note.category', 'note.created_at'])
            ->map(fn ($note) => ['title' => $note->title, 'body' => $note->body, 'severity' => $note->severity, 'category' => $note->category, 'date' => substr((string) $note->created_at, 0, 10)])
            ->all();
    }
}
