<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryServiceInvoice\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryServiceInvoice;
use App\Models\Helpers\Media;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Every bill paid locally for the organisation's stock deliveries: freight, customs, import VAT and the rest.
 */
class IndexStockDeliveryServiceInvoices extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        abort_if($this->organisation->type === OrganisationTypeEnum::AGENT, 404);

        return $request->user()->authTo(["procurement.{$this->organisation->id}.view", "accounting.{$this->organisation->id}.view"]);
    }

    /**
     * @param array{search?: string|null, type?: string|null, paid?: string|null, from?: string|null, to?: string|null} $filters
     */
    public function handle(Organisation $organisation, array $filters): LengthAwarePaginator
    {
        $type   = StockDeliveryServiceInvoiceTypeEnum::tryFrom((string) Arr::get($filters, 'type'));
        $paid   = Arr::get($filters, 'paid');
        $search = Arr::get($filters, 'search');

        return StockDeliveryServiceInvoice::query()
            ->where('organisation_id', $organisation->id)
            ->with(['currency:id,code', 'stockDeliveries:id,slug,reference', 'attachments'])
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->when($paid === 'paid', fn (Builder $query) => $query->whereNotNull('paid_at'))
            ->when($paid === 'unpaid', fn (Builder $query) => $query->whereNull('paid_at'))
            ->when(Arr::get($filters, 'from'), fn (Builder $query, string $from) => $query->where('date', '>=', $from))
            ->when(Arr::get($filters, 'to'), fn (Builder $query, string $to) => $query->where('date', '<=', $to))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereAnyWordStartWith('issuer', $search)
                ->orWhereStartWith('reference', $search)
                ->orWhereHas('stockDeliveries', fn (Builder $query) => $query->whereStartWith('stock_deliveries.reference', $search))))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (StockDeliveryServiceInvoice $serviceInvoice) => self::row($serviceInvoice, $organisation));
    }

    public static function row(StockDeliveryServiceInvoice $serviceInvoice, Organisation $organisation): array
    {
        return [
            'id'               => $serviceInvoice->id,
            'type'             => $serviceInvoice->type->value,
            'type_label'       => StockDeliveryServiceInvoiceTypeEnum::labels()[$serviceInvoice->type->value],
            'issuer'           => $serviceInvoice->issuer,
            'reference'        => $serviceInvoice->reference,
            'date'             => $serviceInvoice->date->toDateString(),
            'currency_id'      => $serviceInvoice->currency_id,
            'currency_code'    => $serviceInvoice->currency->code,
            'exchange'         => (float) $serviceInvoice->exchange,
            'total_amount'     => (float) $serviceInvoice->total_amount,
            'org_total_amount' => (float) $serviceInvoice->org_total_amount,
            'paid_at'          => $serviceInvoice->paid_at?->toDateString(),
            'notes'            => $serviceInvoice->notes,
            'attachments'      => $serviceInvoice->attachments->map(fn (Media $media) => [
                'name'  => $media->file_name,
                'ulid'  => $media->ulid,
            ])->values()->all(),
            'allocations'      => $serviceInvoice->stockDeliveries->map(fn (StockDelivery $stockDelivery) => [
                'stock_delivery_id' => $stockDelivery->id,
                'reference'         => $stockDelivery->reference,
                'amount'            => (float) $stockDelivery->pivot->amount,
                'route'             => [
                    'name'       => 'grp.org.procurement.stock_deliveries.show',
                    'parameters' => [$organisation->slug, $stockDelivery->slug],
                ],
            ])->values()->all(),
            'update_route'     => ['name' => 'grp.models.stock_delivery_service_invoice.update', 'parameters' => ['serviceInvoice' => $serviceInvoice->id], 'method' => 'patch'],
            'paid_route'       => ['name' => 'grp.models.stock_delivery_service_invoice.paid', 'parameters' => ['serviceInvoice' => $serviceInvoice->id], 'method' => 'patch'],
            'delete_route'     => ['name' => 'grp.models.stock_delivery_service_invoice.delete', 'parameters' => ['serviceInvoice' => $serviceInvoice->id], 'method' => 'delete'],
        ];
    }

    public static function typeOptions(): array
    {
        return collect(StockDeliveryServiceInvoiceTypeEnum::labels())
            ->map(fn (string $label, string $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->filters($request));
    }

    private function filters(ActionRequest $request): array
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? $request->query($key) : null;

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'type'   => StockDeliveryServiceInvoiceTypeEnum::tryFrom($request->string('type')->value())?->value,
            'paid'   => in_array($request->query('paid'), ['paid', 'unpaid'], true) ? $request->query('paid') : null,
            'from'   => $date('from'),
            'to'     => $date('to'),
        ];
    }

    public function htmlResponse(LengthAwarePaginator $serviceInvoices, ActionRequest $request): Response
    {
        $canEdit = $request->user()->authTo([
            "procurement.{$this->organisation->id}.edit",
            "accounting.{$this->organisation->id}.edit",
            "org-supervisor.{$this->organisation->id}.accounting",
        ]);

        return Inertia::render(
            'Org/Procurement/StockDeliveryServiceInvoices',
            [
                'title'        => __('Service invoices'),
                'breadcrumbs'  => array_merge(
                    ShowProcurementDashboard::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'   => 'simple',
                            'simple' => [
                                'label' => __('Service invoices'),
                                'route' => [
                                    'name'       => 'grp.org.procurement.service_invoices.index',
                                    'parameters' => [$this->organisation->slug],
                                ],
                            ],
                        ],
                    ]
                ),
                'pageHead'     => [
                    'icon'  => ['title' => __('Service invoices'), 'icon' => 'fal fa-file-invoice-dollar'],
                    'title' => __('Service invoices'),
                ],
                'filters'      => $this->filters($request),
                'types'        => self::typeOptions(),
                'org_currency' => $this->organisation->currency->code,
                'can_edit'     => $canEdit,
                'data'         => $serviceInvoices,
            ]
        );
    }
}
