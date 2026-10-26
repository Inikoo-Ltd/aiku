<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesFeedback;
use App\Actions\Procurement\OrgAgent\UI\GetAgentSalesProducts;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentSales;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a supplier sees through its private link: how its products sell, valued at its own prices and in its own
 * currency, where that puts it in its country, the notes purchasing shared with it, and its purchase orders. Built from the link alone, with no account and no
 * staff layout, so nothing else in Aiku can be reached from it.
 */
class ShowSupplierShare
{
    use AsAction;

    private const int RECEIVED_DAYS = 60;

    /**
     * Orders sent to the supplier and not yet closed, plus the ones received lately. Drafts were never sent, so they
     * stay out.
     *
     * @return array<int, array<string, mixed>>
     */
    public function purchaseOrders(Supplier $supplier): array
    {
        $stateLabels    = PurchaseOrderStateEnum::labels();
        $deliveryLabels = PurchaseOrderDeliveryStateEnum::labels();

        return DB::table('purchase_orders as po')
            ->join('organisations', 'organisations.id', 'po.organisation_id')
            ->leftJoin('currencies', 'currencies.id', 'po.currency_id')
            ->where('po.parent_type', 'OrgSupplier')
            ->whereIn('po.parent_id', DB::table('org_suppliers')->where('supplier_id', $supplier->id)->select('id'))
            ->whereNull('po.deleted_at')
            ->where(fn ($query) => $query
                ->whereIn('po.state', [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value])
                ->orWhere(fn ($received) => $received->where('po.state', PurchaseOrderStateEnum::SETTLED->value)->where('po.settled_at', '>=', now()->subDays(self::RECEIVED_DAYS))))
            ->orderByRaw("case po.state when 'submitted' then 1 when 'confirmed' then 2 else 3 end")
            ->orderByDesc('po.submitted_at')
            ->selectRaw('po.reference, organisations.name as organisation, po.state, po.delivery_state, po.submitted_at, po.confirmed_at,
                po.estimated_received_at, po.settled_at, po.cost_items as amount, currencies.code as currency,
                (select count(*) from purchase_order_transactions t where t.purchase_order_id = po.id and t.deleted_at is null) as lines')
            ->get()
            ->map(fn ($order) => [
                'reference'      => $order->reference,
                'organisation'   => $order->organisation,
                'state'          => $order->state,
                'state_label'    => $stateLabels[$order->state] ?? $order->state,
                'delivery_label' => $deliveryLabels[$order->delivery_state] ?? $order->delivery_state,
                'submitted_at'   => $order->submitted_at,
                'confirmed_at'   => $order->confirmed_at,
                'expected_at'    => $order->estimated_received_at,
                'received_at'    => $order->state === PurchaseOrderStateEnum::SETTLED->value ? $order->settled_at : null,
                'amount'         => (float) $order->amount,
                'currency'       => $order->currency,
                'lines'          => (int) $order->lines,
            ])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function company(Supplier $supplier): array
    {
        $supplier->loadMissing(['address', 'currency']);

        return [
            'name'         => $supplier->name,
            'contact_name' => $supplier->contact_name,
            'email'        => $supplier->email,
            'phone'        => $supplier->phone,
            'location'     => collect([$supplier->address?->locality, $supplier->address?->country?->name])->filter()->implode(', ') ?: null,
            'currency'     => $supplier->currency?->code,
        ];
    }

    /**
     * The agent that follows the supplier for us, with the person to talk to there.
     *
     * @return array<string, string|null>|null
     */
    private function agent(Supplier $supplier): ?array
    {
        $organisation = $supplier->agent?->organisation;
        if (!$organisation) {
            return null;
        }

        return [
            'name'         => $supplier->agent->name,
            'contact_name' => $organisation->contact_name,
            'email'        => $organisation->email,
            'phone'        => $organisation->phone,
        ];
    }

    public function asController(string $token, Request $request): Response
    {
        $link     = SupplierShareLink::findLiveByToken($token);
        $supplier = $link->supplier;
        $page     = [
            'title'    => $supplier->name,
            'supplier' => ['code' => $supplier->code, 'name' => $supplier->name],
            'group'    => $link->group->name,
            'routes'   => [
                'send_code' => route('grp.supplier_share.send_code', $token),
                'verify'    => route('grp.supplier_share.verify', $token),
            ],
        ];

        if ($link->needsCode()) {
            $response = Inertia::render('SupplyChain/SupplierShare', [
                ...$page,
                'needs_code' => true,
                'email'      => $link->maskedEmail(),
                'code_sent'  => (bool) session('code_sent'),
            ]);
        } else {
            if (!$request->header('X-Inertia-Partial-Data')) {
                $link->views()->create(['ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000), 'created_at' => now()]);
                $link->update(['last_viewed_at' => now(), 'number_views' => $link->number_views + 1]);
            }

            $valuation = GetAgentSalesProducts::make()->valuedBy(null, null, [])->atSupplierPrices($supplier);
            $response  = Inertia::render('SupplyChain/SupplierShare', [
                ...$page,
                'needs_code'      => false,
                'company'         => $this->company($supplier),
                'agent'           => $this->agent($supplier),
                'summary'         => Inertia::defer(fn () => GetSupplierPortalSummary::make()->handle($supplier)),
                'valuation'       => $valuation->valuation($supplier),
                'purchase_orders' => $this->purchaseOrders($supplier),
                'sales'           => Inertia::defer(fn () => ShowAgentSales::make()->handle($supplier, null, $valuation)),
                'feedback'        => Inertia::defer(fn () => GetAgentSalesFeedback::run($supplier), 'feedback'),
            ]);
        }

        return $response->toResponse($request)->withHeaders([
            'Cache-Control'   => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag'    => 'noindex, nofollow',
        ]);
    }
}
