<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentInvoice\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\AspoDeposit\UI\ShowAgentAccountingDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The agent's commission on the products of every purchase order shipped in a month, grouped by supplier.
 * A purchase order ships on its handed over date, or else on the first dispatch of a container it is in.
 */
class ShowAgentCommissionInvoice extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementAuthorisation;

    /**
     * Agent organisations that charge a commission, by slug.
     *
     * @var array<string, array{rate: float, excluded_supplier_codes: list<string>}>
     */
    public const array COMMISSIONS = [
        'salina' => ['rate' => 0.02, 'excluded_supplier_codes' => ['ESH']],
    ];

    private CarbonImmutable $month;

    /**
     * @param  list<string>  $excludedSupplierCodes
     * @return array{rate: float, suppliers: list<array<string, mixed>>, totals: list<array{currency_code: string, products: float, commission: float}>}
     */
    public function handle(Agent $agent, CarbonImmutable $month, float $rate, array $excludedSupplierCodes): array
    {
        $shippedAt = 'coalesce(purchase_orders.handed_over_at, (select min(stock_deliveries.dispatched_at) from purchase_order_stock_delivery join stock_deliveries on stock_deliveries.id = purchase_order_stock_delivery.stock_delivery_id where purchase_order_stock_delivery.purchase_order_id = purchase_orders.id and stock_deliveries.deleted_at is null and stock_deliveries.state != \''.StockDeliveryStateEnum::CANCELLED->value.'\'))';

        $purchaseOrders = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->join('currencies', 'currencies.id', '=', 'purchase_orders.currency_id')
            ->join('organisations', 'organisations.id', '=', 'purchase_orders.organisation_id')
            ->where('purchase_orders.agent_id', $agent->id)
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.state', '!=', PurchaseOrderStateEnum::CANCELLED->value)
            ->whereNotIn('suppliers.code', $excludedSupplierCodes)
            ->whereRaw("$shippedAt >= ? and $shippedAt < ?", [$month->startOfMonth(), $month->startOfMonth()->addMonth()])
            ->select('purchase_orders.reference', 'suppliers.code as supplier_code', 'suppliers.name as supplier_name', 'currencies.code as currency_code', 'organisations.name as organisation')
            ->selectRaw("$shippedAt as shipped_at")
            ->selectRaw('(select coalesce(sum(quantity_ordered * unit_cost), 0) from purchase_order_transactions where purchase_order_transactions.purchase_order_id = purchase_orders.id and purchase_order_transactions.deleted_at is null) as products')
            ->orderBy('suppliers.code')
            ->orderByRaw($shippedAt)
            ->orderBy('purchase_orders.reference')
            ->get();

        $suppliers = $purchaseOrders->groupBy(fn ($row) => $row->supplier_code.'|'.$row->currency_code)->values()->map(function ($rows) use ($rate) {
            $products = round($rows->sum(fn ($row) => (float) $row->products), 2);

            return [
                'code'            => $rows[0]->supplier_code,
                'name'            => $rows[0]->supplier_name,
                'currency_code'   => $rows[0]->currency_code,
                'purchase_orders' => $rows->map(fn ($row) => [
                    'reference'    => $row->reference,
                    'organisation' => $row->organisation,
                    'shipped_at'   => CarbonImmutable::parse($row->shipped_at)->toDateString(),
                    'products'     => round((float) $row->products, 2),
                ])->all(),
                'products'        => $products,
                'commission'      => round($products * $rate, 2),
            ];
        });

        return [
            'rate'      => $rate,
            'suppliers' => $suppliers->all(),
            'totals'    => $suppliers->groupBy('currency_code')->map(fn ($groups, $currencyCode) => [
                'currency_code' => $currencyCode,
                'products'      => round($groups->sum('products'), 2),
                'commission'    => round($groups->sum('commission'), 2),
            ])->values()->all(),
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->commissionFor($organisation, $request);
    }

    public function pdf(Organisation $organisation, ActionRequest $request): HttpResponse
    {
        $this->initialisation($organisation, $request);
        $commission = $this->commissionFor($organisation, $request);
        $organisation->loadMissing('address');

        $pdf = PDF::loadView('supplyChain.templates.pdf.agent-commission-invoice', [
            'organisation' => $organisation,
            'month'        => $this->month,
            'commission'   => $commission,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="commission-'.$organisation->slug.'-'.$this->month->format('Y-m').'.pdf"');
    }

    private function commissionFor(Organisation $organisation, ActionRequest $request): array
    {
        $agent  = $this->getOrganisationAgent($organisation);
        $config = self::COMMISSIONS[$organisation->slug] ?? null;
        abort_unless($agent && $config, 404);

        $month       = $request->string('month')->value();
        $this->month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? CarbonImmutable::createFromFormat('!Y-m', $month)
            : CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        return $this->handle($agent, $this->month, $config['rate'], $config['excluded_supplier_codes']);
    }

    public function htmlResponse(array|HttpResponse $commission, ActionRequest $request): Response|HttpResponse
    {
        if ($commission instanceof HttpResponse) {
            return $commission;
        }

        $title = __('Commission');

        return Inertia::render(
            'Org/Procurement/AgentCommission',
            [
                'title'       => $title,
                'breadcrumbs' => array_merge(
                    ShowAgentAccountingDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => $title,
                                'route' => [
                                    'name'       => 'grp.org.agent.accounting.commission.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'    => [
                    'icon'  => [
                        'title' => $title,
                        'icon'  => 'fal fa-percent',
                    ],
                    'title' => $title,
                ],
                'month'       => $this->month->format('Y-m'),
                'pdfRoute'    => [
                    'name'       => 'grp.org.agent.accounting.commission.pdf',
                    'parameters' => ['organisation' => $this->organisation->slug, 'month' => $this->month->format('Y-m')],
                ],
                'commission'  => $commission,
            ]
        );
    }
}
