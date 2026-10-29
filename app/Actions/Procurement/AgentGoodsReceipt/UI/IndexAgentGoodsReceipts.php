<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 15:50
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\AgentWarehouseStock\UI\IndexAgentProductsInProduction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithAgentWarehouseAuthorisation;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Every delivery the agent received from its suppliers, newest first, with whether the signed delivery note was
 * photographed.
 */
class IndexAgentGoodsReceipts extends OrgAction
{
    use WithAgentOrganisation;
    use WithAgentWarehouseAuthorisation;
    use WithAgentGoodsReceiptBreadcrumbs;

    private Agent $agent;

    private const string PHOTOS_SQL = "(select count(*) from model_has_attachments where model_has_attachments.model_type = 'AgentGoodsReceipt' and model_has_attachments.model_id = agent_goods_receipts.id)";

    private function baseQuery(Agent $agent): Builder
    {
        return AgentGoodsReceipt::query()->where('agent_goods_receipts.agent_id', $agent->id);
    }

    public function handle(Agent $agent): LengthAwarePaginator
    {
        $query = $this->baseQuery($agent)
            ->leftJoin('suppliers', 'suppliers.id', 'agent_goods_receipts.supplier_id')
            ->leftJoin('users', 'users.id', 'agent_goods_receipts.user_id')
            ->select([
                'agent_goods_receipts.id',
                'agent_goods_receipts.reference',
                'agent_goods_receipts.slug',
                'agent_goods_receipts.state',
                'agent_goods_receipts.received_at',
                'agent_goods_receipts.supplier_delivery_note',
                'agent_goods_receipts.number_items',
                'agent_goods_receipts.quantity',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                DB::raw("coalesce(nullif(users.contact_name, ''), users.username) as received_by"),
                DB::raw(self::PHOTOS_SQL.' as number_photos'),
            ]);

        return QueryBuilder::for($query)
            ->defaultSort('-received_at')
            ->allowedSorts([
                AllowedSort::field('reference', 'agent_goods_receipts.number'),
                AllowedSort::field('received_at', 'agent_goods_receipts.received_at'),
                AllowedSort::field('supplier_code', 'suppliers.code'),
                AllowedSort::field('number_items', 'agent_goods_receipts.number_items'),
                AllowedSort::field('quantity', 'agent_goods_receipts.quantity'),
            ])
            ->allowedFilters([
                AllowedFilter::callback('global', function ($query, $value) {
                    $like = '%'.addcslashes((string) $value, '%_\\').'%';
                    $query->where(fn ($query) => $query
                        ->where('agent_goods_receipts.reference', 'ilike', $like)
                        ->orWhere('agent_goods_receipts.supplier_delivery_note', 'ilike', $like)
                        ->orWhere('suppliers.code', 'ilike', $like)
                        ->orWhere('suppliers.name', 'ilike', $like));
                }),
                AllowedFilter::callback('state', fn ($query, $value) => $query->where('agent_goods_receipts.state', $value)),
                AllowedFilter::callback('photo', fn ($query, $value) => $query->whereRaw(self::PHOTOS_SQL.($value === 'missing' ? ' = 0' : ' > 0'))),
            ])
            ->withPaginator(null, tableName: request()->route()?->getName())
            ->withQueryString()
            ->through(fn (AgentGoodsReceipt $receipt) => [
                'id'                     => $receipt->id,
                'reference'              => $receipt->reference,
                'slug'                   => $receipt->slug,
                'state'                  => $receipt->state->value,
                'state_icon'             => $receipt->state->icon(),
                'received_at'            => $receipt->received_at->toDateString(),
                'supplier_code'          => $receipt->supplier_code,
                'supplier_name'          => $receipt->supplier_name,
                'supplier_delivery_note' => $receipt->supplier_delivery_note,
                'number_items'           => $receipt->number_items,
                'quantity'               => (float) $receipt->quantity,
                'number_photos'          => (int) $receipt->number_photos,
                'received_by'            => $receipt->received_by,
            ]);
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Goods receipt'), __('Goods receipts')])
                ->withEmptyState(['title' => __('No goods received from the suppliers yet'), 'description' => __('Use Receive goods when a supplier delivers')])
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], canBeHidden: false, type: 'icon')
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true)
                ->column(key: 'received_at', label: __('Received'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_delivery_note', label: __('Delivery note'), canBeHidden: false)
                ->column(key: 'number_photos', label: __('Photo'), canBeHidden: false)
                ->column(key: 'number_items', label: __('Lines'), sortable: true, align: 'right')
                ->column(key: 'quantity', label: __('Units'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'received_by', label: __('Received by'))
                ->defaultSort('-received_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        $agent = $this->getOrganisationAgent($organisation);
        abort_unless($agent, 404);
        $this->agent = $agent;

        return $this->handle($agent);
    }

    public function htmlResponse(LengthAwarePaginator $receipts, ActionRequest $request): Response
    {
        $counts = $this->baseQuery($this->agent)
            ->selectRaw('state, '.self::PHOTOS_SQL.' > 0 as has_photo, count(*) as total')
            ->groupBy('state', 'has_photo')
            ->toBase()
            ->get();

        $received = $counts->where('state', AgentGoodsReceiptStateEnum::RECEIVED->value);

        return Inertia::render(
            'Org/Procurement/AgentGoodsReceipts',
            [
                'title'         => __('Goods receipts'),
                'breadcrumbs'   => $this->goodsReceiptBreadcrumbs($request),
                'pageHead'      => [
                    'model' => __('Received'),
                    'icon'  => ['title' => __('Goods receipts'), 'icon' => 'fal fa-clipboard-check'],
                    'title' => __('Goods receipts'),
                ],
                'data'          => $receipts,
                'filterOptions' => [
                    'state' => collect(AgentGoodsReceiptStateEnum::cases())->map(fn (AgentGoodsReceiptStateEnum $state) => [
                        'label' => $state->label(),
                        'value' => $state->value,
                        'count' => (int) $counts->where('state', $state->value)->sum('total'),
                    ])->all(),
                    'photo' => [
                        ['label' => __('Photographed'), 'value' => 'with', 'count' => (int) $received->where('has_photo', true)->sum('total')],
                        ['label' => __('Missing'), 'value' => 'missing', 'count' => (int) $received->where('has_photo', false)->sum('total'), 'tooltip' => __('The signed delivery note is not uploaded yet')],
                    ],
                ],
                'receive'       => $this->canEdit ? IndexAgentProductsInProduction::receiveScopes($this->agent) : null,
            ]
        )->table($this->tableStructure());
    }
}
