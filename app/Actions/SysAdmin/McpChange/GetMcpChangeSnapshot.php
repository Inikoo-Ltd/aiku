<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\McpChange;

use App\Actions\Chat\ChatSession\WaitForCustomerReply;
use App\Actions\Inventory\OrgStock\DiscontinueOrgStocks;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryServiceInvoiceTypeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Models\Chat\ChatSession;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Order;
use App\Models\Ordering\UpcomingTransaction;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The state a change type touches, read the same way before a change, after it and before a
 * revert: a revert only goes ahead while the current snapshot still equals the stored after.
 */
class GetMcpChangeSnapshot
{
    use AsObject;

    public const array PRODUCTION_TABLES = [
        'artefact'         => 'artefacts',
        'raw_material'     => 'raw_materials',
        'manufacture_task' => 'manufacture_tasks',
    ];

    public const array PRODUCTION_RECORD_FIELDS = [
        'artefact'         => ['id', 'code', 'name', 'state', 'recommended_batch_size', 'shelf_life_days', 'artefact_family_id', 'artefact_department_id', 'org_stock_id', 'trade_unit_id'],
        'raw_material'     => ['id', 'code', 'description', 'type', 'state', 'unit', 'unit_cost', 'org_stock_id', 'trade_unit_id'],
        'manufacture_task' => ['id', 'code', 'name', 'description', 'status', 'is_piece_rate', 'is_recorded_in_kg', 'is_quantity_only'],
    ];

    public const array TRADE_UNIT_FIELDS = [
        'name', 'description', 'gross_weight', 'net_weight', 'marketing_weight', 'marketing_ingredients',
        'origin_country_id', 'tariff_code', 'duty_rate', 'hts_us', 'cpnp_number', 'ufi_number', 'scpn_number',
        'un_number', 'un_class', 'packing_group', 'proper_shipping_name',
    ];

    public function handle(McpChangeTypeEnum $type, array $target): array
    {
        return match ($type) {
            McpChangeTypeEnum::RELATED_PRODUCTS => $this->relatedProducts($target),
            McpChangeTypeEnum::ORG_STOCK_STATE  => $this->orgStockStates($target),
            McpChangeTypeEnum::SUPPLIER_PRODUCT_AVAILABILITY => $this->supplierProductAvailability($target),
            McpChangeTypeEnum::PARTNER_SHOPPING_LIST => $this->partnerShoppingList($target),
            McpChangeTypeEnum::PRODUCTION_RECORD => $this->productionRecord($target),
            McpChangeTypeEnum::PRODUCTION_RECIPE => $this->productionRecipes($target),
            McpChangeTypeEnum::PLACED_ORDER => $this->placedOrder($target),
            McpChangeTypeEnum::MASTER_PRICES => $this->masterPrices($target),
            McpChangeTypeEnum::STOCK_DELIVERY_COSTS => $this->stockDeliveryCosts($target),
            McpChangeTypeEnum::CUSTOMER_SERVICE => $this->customerService($target),
            McpChangeTypeEnum::CHAT_SPAM => $this->chatSpam($target),
            McpChangeTypeEnum::CHAT_CLOSED => ['status' => ChatSession::find($target['chat_session_id'])?->status?->value],
            McpChangeTypeEnum::TRADE_UNIT_PROPERTIES => $this->tradeUnitProperties($target),
            McpChangeTypeEnum::WEBSITE_REDIRECTS => $this->websiteRedirects($target),
            McpChangeTypeEnum::STOCK_LOCATIONS => $this->stockLocations($target),
            McpChangeTypeEnum::PRODUCTION_SETUP => $this->productionSetup($target),
        };
    }

    public function describe(McpChangeTypeEnum $type, array $target, array $snapshot): string
    {
        if ($type === McpChangeTypeEnum::STOCK_LOCATIONS) {
            return collect($target['pairs'])
                ->map(fn (array $pair) => $pair['sko'].' @ '.$pair['location'].': '.(isset($snapshot['pairs'][$pair['org_stock_id'].'-'.$pair['location_id']]) ? 'on location' : 'not on location'))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::PRODUCTION_SETUP) {
            $departments = DB::table('artefact_departments')->where('production_id', $target['production_id'])->pluck('code', 'id');
            $artisans    = DB::table('employees')->whereIn('id', collect($snapshot['areas'])->pluck('artisan_ids')->flatten())->pluck('contact_name', 'id');

            return 'factory location: '.($snapshot['factory_location'] ?? '-').'; '.(collect($snapshot['areas'])
                ->map(fn (array $area) => $area['name'].' (departments: '.(collect($area['department_ids'])->map(fn ($id) => $departments[$id] ?? '#'.$id)->implode(', ') ?: '-')
                    .'; artisans: '.(collect($area['artisan_ids'])->map(fn ($id) => $artisans[$id] ?? '#'.$id)->implode(', ') ?: '-').')')
                ->implode('; ') ?: 'no work areas');
        }

        if ($type === McpChangeTypeEnum::ORG_STOCK_STATE) {
            return collect($snapshot['org_stocks'])
                ->map(fn (array $orgStock) => $orgStock['code'].': '.$orgStock['state'].($orgStock['scheduled'] ? ' (scheduled '.$orgStock['scheduled']['to_state'].')' : ''))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::SUPPLIER_PRODUCT_AVAILABILITY) {
            return collect($snapshot['org_supplier_products'])
                ->map(fn (array $row) => $row['code'].' ('.$row['organisation'].'): '.($row['is_available'] ? 'available' : 'unavailable'))
                ->merge(collect($snapshot['supplier_products'])->map(fn (array $row) => $row['code'].' (supplier): '.($row['is_available'] ? 'available' : 'unavailable')))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::PARTNER_SHOPPING_LIST) {
            $codes = DB::table('stocks')->whereIn('id', $target['stock_ids'])->pluck('code', 'id');

            return collect($target['stock_ids'])
                ->map(fn ($stockId) => ($codes[$stockId] ?? '#'.$stockId).': '.($snapshot['lines'][$stockId]['quantity'] ?? 'not on list'))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::PLACED_ORDER) {
            $purchaseOrders = collect($snapshot['purchase_orders'])->map(fn (array $purchaseOrder) => $purchaseOrder['reference'].': '.$purchaseOrder['state'].' '.$purchaseOrder['lines'].' lines');
            if (isset($target['org_supplier_id'])) {
                return $purchaseOrders->implode(', ') ?: 'no open purchase order';
            }

            return trans_choice(':count line in the basket|:count lines in the basket', $snapshot['basket_lines'])
                .($snapshot['sent_lines'] ? ', sent: '.collect($snapshot['sent_lines'])->implode(', ') : '')
                .$purchaseOrders->map(fn (string $purchaseOrder) => ', '.$purchaseOrder)->implode('');
        }

        if ($type === McpChangeTypeEnum::MASTER_PRICES) {
            $codes = DB::table('master_assets')->whereIn('id', $target['master_asset_ids'])->pluck('code', 'id');

            return collect($snapshot['prices'])
                ->map(fn (array $prices, $masterAssetId) => ($codes[$masterAssetId] ?? '#'.$masterAssetId).': '.collect(Arr::only($prices, ['GBP', 'EUR']) ?: $prices)
                    ->map(fn (array $price, string $currencyCode) => $currencyCode.' '.$price['value'])
                    ->implode(', '))
                ->implode('; ');
        }

        if ($type === McpChangeTypeEnum::STOCK_DELIVERY_COSTS) {
            $deliveryCurrency = DB::table('stock_deliveries')
                ->join('currencies', 'currencies.id', '=', 'stock_deliveries.currency_id')
                ->where('stock_deliveries.id', $target['stock_delivery_id'])
                ->value('currencies.code');
            $currencyCodes    = DB::table('currencies')->pluck('code', 'id');

            return collect($snapshot['costs'])
                ->map(function (array $cost) use ($deliveryCurrency, $currencyCodes) {
                    $name = $cost['type'] === 'extra' && $cost['category']
                        ? (StockDeliveryServiceInvoiceTypeEnum::labels()[$cost['category']] ?? $cost['category'])
                        : (StockDeliveryCostTypeEnum::labels()[$cost['type']] ?? $cost['type']);

                    $value = match (true) {
                        $cost['is_na']             => 'n/a',
                        $cost['amount'] !== null   => number_format((float) $cost['amount'], 2, '.', '').' '.($currencyCodes[$cost['currency_id']] ?? $deliveryCurrency).($cost['is_estimate'] ? ' (estimate)' : ''),
                        $cost['is_waiting_for_invoice'] => 'waiting for the invoice',
                        default                    => 'not set',
                    };

                    return $name.' '.$value;
                })
                ->implode('; ') ?: 'no costs';
        }

        if ($type === McpChangeTypeEnum::CUSTOMER_SERVICE) {
            return collect([
                'chat '.$snapshot['chat']['status'].($snapshot['chat']['held_by'] ? ', held by '.$snapshot['chat']['held_by'] : ', held by nobody'),
                $snapshot['chat']['agent_messages'].' replies',
                $snapshot['chat']['waiting_until'] ? 'waiting for the customer until '.$snapshot['chat']['waiting_until'] : null,
                $snapshot['customer'] ? $snapshot['customer']['name'].' balance '.$snapshot['customer']['balance'] : 'no customer linked',
                $snapshot['order'] ? $snapshot['order']['reference'].': '.collect($snapshot['order']['lines'])->map(fn ($quantity, $code) => $code.' x'.$quantity)->implode(', ')
                    .', to '.$snapshot['order']['delivery_address']
                    .', '.count($snapshot['order']['refunds']).' refunds, '.count($snapshot['order']['replacements']).' replacements, '.$snapshot['order']['follow_ons'].' in next order'
                    .collect($snapshot['order']['notes'])->filter()->map(fn ($note, $field) => ', '.$field.': '.$note)->implode('') : null,
            ])->filter()->implode('; ');
        }

        if ($type === McpChangeTypeEnum::CHAT_CLOSED) {
            return 'chat '.$snapshot['status'];
        }

        if ($type === McpChangeTypeEnum::CHAT_SPAM) {
            return ($snapshot['is_spam'] ? 'in spam' : 'in the inbox').($snapshot['sender_blocked'] ? ', sender blocked' : '');
        }

        if ($type === McpChangeTypeEnum::TRADE_UNIT_PROPERTIES) {
            return $target['code'].': '.collect($snapshot)->except('barcode_id')->map(fn ($value, $field) => $field.'='.($value ?? '-'))->implode(', ');
        }

        if ($type === McpChangeTypeEnum::WEBSITE_REDIRECTS) {
            $fromPaths = DB::table('redirects')->whereIn('id', array_keys($snapshot['redirects']))->pluck('from_path', 'id');
            $toPaths   = DB::table('webpages')->whereIn('id', array_values($snapshot['redirects']))->pluck('url', 'id');

            return collect($snapshot['redirects'])
                ->map(fn ($toWebpageId, $redirectId) => '/'.($fromPaths[$redirectId] ?? '#'.$redirectId).' -> /'.($toPaths[$toWebpageId] ?? '#'.$toWebpageId))
                ->implode(', ');
        }

        if ($type === McpChangeTypeEnum::PRODUCTION_RECORD) {
            return collect($snapshot)->map(fn ($value, $field) => $field.'='.($value ?? '-'))->implode(', ') ?: 'not there';
        }

        if ($type === McpChangeTypeEnum::PRODUCTION_RECIPE) {
            $steps         = collect($snapshot['artefacts'])->flatten(1);
            $taskCodes     = DB::table('manufacture_tasks')->whereIn('id', $steps->pluck('manufacture_task_id'))->pluck('code', 'id');
            $materialCodes = DB::table('raw_materials')->whereIn('id', $steps->pluck('raw_materials')->flatten(1)->pluck('raw_material_id'))->pluck('code', 'id');
            $artefactCodes = DB::table('artefacts')->whereIn('id', $target['artefact_ids'])->pluck('code', 'id');

            return collect($snapshot['artefacts'])
                ->map(fn (array $recipe, $artefactId) => ($artefactCodes[$artefactId] ?? '#'.$artefactId).': '.(collect($recipe)
                    ->map(fn (array $step) => $step['position'].' '.($taskCodes[$step['manufacture_task_id']] ?? '#'.$step['manufacture_task_id'])
                        .' x'.(float) $step['units_per_artefact']
                        .($step['standard_rate'] !== null ? ' @'.(float) $step['standard_rate'].'/h' : '')
                        .collect($step['raw_materials'])->map(fn (array $rawMaterial) => ' +'.($materialCodes[$rawMaterial['raw_material_id']] ?? '#'.$rawMaterial['raw_material_id']).' '.(float) $rawMaterial['quantity_per_unit'])->implode(''))
                    ->implode('; ') ?: 'no steps'))
                ->implode(' | ');
        }

        $codes = DB::table($target['level'] === 'master' ? 'master_assets' : 'products')
            ->whereIn('id', $snapshot['ids'])
            ->pluck('code', 'id');

        return collect($snapshot['ids'])->map(fn ($id) => $codes[$id] ?? '#'.$id)->implode(', ') ?: '-';
    }

    private function websiteRedirects(array $target): array
    {
        return [
            'redirects' => DB::table('redirects')
                ->whereIn('id', $target['redirect_ids'])
                ->orderBy('id')
                ->pluck('to_webpage_id', 'id')
                ->map(fn ($toWebpageId) => (int) $toWebpageId)
                ->all(),
        ];
    }

    /**
     * @return array{pairs: array<string, float>}
     */
    private function stockLocations(array $target): array
    {
        $pairs = [];
        foreach ($target['pairs'] as $pair) {
            $quantity = DB::table('location_org_stocks')->where('org_stock_id', $pair['org_stock_id'])->where('location_id', $pair['location_id'])->value('quantity');
            if ($quantity !== null) {
                $pairs[$pair['org_stock_id'].'-'.$pair['location_id']] = (float) $quantity;
            }
        }

        return ['pairs' => $pairs];
    }

    private function productionSetup(array $target): array
    {
        $production = DB::table('productions')->where('id', $target['production_id'])->first(['factory_location_id']);

        return [
            'factory_location_id' => $production?->factory_location_id,
            'factory_location'    => $production?->factory_location_id ? DB::table('locations')->where('id', $production->factory_location_id)->value('code') : null,
            'areas'               => DB::table('work_areas')->where('production_id', $target['production_id'])->orderBy('id')->get(['id', 'name'])
                ->mapWithKeys(fn ($area) => [$area->id => [
                    'name'           => $area->name,
                    'department_ids' => DB::table('artefact_departments')->where('work_area_id', $area->id)->whereNull('deleted_at')->orderBy('id')->pluck('id')->all(),
                    'artisan_ids'    => DB::table('artisan_assignments')->where('artisanable_type', 'WorkArea')->where('artisanable_id', $area->id)->orderBy('employee_id')->pluck('employee_id')->all(),
                ]])
                ->all(),
        ];
    }

    private function tradeUnitProperties(array $target): array
    {
        return (array) DB::table('trade_units')
            ->where('id', $target['trade_unit_id'])
            ->first([...self::TRADE_UNIT_FIELDS, 'barcode_id', 'barcode']);
    }

    /**
     * Every currency of each master price with its hand-set mark, so a revert puts back exactly what was there.
     */
    private function masterPrices(array $target): array
    {
        return [
            'prices' => DB::table('master_assets')
                ->whereIn('id', $target['master_asset_ids'])
                ->orderBy('id')
                ->pluck('master_prices', 'id')
                ->map(fn ($masterPrices) => collect(json_decode($masterPrices ?? '{}', true))
                    ->map(fn ($price) => ['value' => (string) (float) ($price['value'] ?? 0), 'independent' => (bool) ($price['independent'] ?? false)])
                    ->sortKeys()
                    ->all())
                ->all(),
        ];
    }

    /**
     * Every live cost row of the delivery, so a revert puts back exactly the rows and fields that were there.
     */
    private function stockDeliveryCosts(array $target): array
    {
        return [
            'costs' => DB::table('stock_delivery_costs')
                ->where('stock_delivery_id', $target['stock_delivery_id'])
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'type', 'category', 'label', 'amount', 'currency_id', 'exchange', 'is_na', 'received_at', 'is_waiting_for_invoice', 'invoice_expected_at', 'is_estimate'])
                ->mapWithKeys(fn (object $cost) => [$cost->id => [
                    'id'                     => $cost->id,
                    'type'                   => $cost->type,
                    'category'               => $cost->category,
                    'label'                  => $cost->label,
                    'amount'                 => $cost->amount !== null ? (string) (float) $cost->amount : null,
                    'currency_id'            => $cost->currency_id,
                    'exchange'               => $cost->exchange !== null ? (string) (float) $cost->exchange : null,
                    'is_na'                  => (bool) $cost->is_na,
                    'received_at'            => $cost->received_at,
                    'is_waiting_for_invoice' => (bool) $cost->is_waiting_for_invoice,
                    'invoice_expected_at'    => $cost->invoice_expected_at,
                    'is_estimate'            => (bool) $cost->is_estimate,
                ]])
                ->all(),
        ];
    }

    private function relatedProducts(array $target): array
    {
        $query = $target['level'] === 'master'
            ? DB::table('master_product_category_has_related_assets')->where('master_product_category_id', $target['id'])->select('master_asset_id as id')
            : DB::table('product_category_has_related_products')->where('product_category_id', $target['id'])->select('product_id as id');

        return ['ids' => $query->orderBy('position')->pluck('id')->map(fn ($id) => (int) $id)->all()];
    }

    /**
     * The basket drafts and the purchase orders still being prepared or just sent, so the log shows
     * what an order the assistant placed took from the basket and where it went. A supplier order
     * has no basket, only its purchase orders.
     */
    private function placedOrder(array $target): array
    {
        if (isset($target['org_supplier_id'])) {
            return ['purchase_orders' => $this->openPurchaseOrders('OrgSupplier', $target['org_supplier_id'])];
        }

        return [
            'basket_lines'    => PartnerShoppingListItem::where('org_partner_id', $target['org_partner_id'])
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->count(),
            'sent_lines'      => PartnerShoppingListItem::where('org_partner_id', $target['org_partner_id'])
                ->whereIn('stock_id', $target['stock_ids'] ?? [])
                ->where('state', ShoppingListItemStateEnum::OPEN)
                ->orderBy('id')
                ->pluck('quantity', 'id')
                ->map(fn ($quantity) => (float) $quantity)
                ->all(),
            'purchase_orders' => $this->openPurchaseOrders('OrgPartner', $target['org_partner_id']),
        ];
    }

    private function openPurchaseOrders(string $parentType, int $parentId): array
    {
        return DB::table('purchase_orders')
                ->where('parent_type', $parentType)
                ->where('parent_id', $parentId)
                ->whereIn('state', [PurchaseOrderStateEnum::IN_PROCESS->value, PurchaseOrderStateEnum::SUBMITTED->value])
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->limit(5)
                ->select(['id', 'reference', 'state'])
                ->selectRaw('(select count(*) from purchase_order_transactions where purchase_order_id = purchase_orders.id) as number_lines')
                ->get()
                ->mapWithKeys(fn ($purchaseOrder) => [$purchaseOrder->id => [
                    'reference' => $purchaseOrder->reference,
                    'state'     => $purchaseOrder->state,
                    'lines'     => (int) $purchaseOrder->number_lines,
                ]])
                ->all();
    }

    private function partnerShoppingList(array $target): array
    {
        return [
            'lines' => PartnerShoppingListItem::where('org_partner_id', $target['org_partner_id'])
                ->whereIn('stock_id', $target['stock_ids'])
                ->where('state', ShoppingListItemStateEnum::DRAFT)
                ->whereNull('job_order_id')
                ->whereNull('pre_picked_at')
                ->orderBy('stock_id')
                ->get()
                ->mapWithKeys(fn (PartnerShoppingListItem $item) => [
                    $item->stock_id => [
                        'id'       => $item->id,
                        'quantity' => (float) $item->quantity,
                        'notes'    => $item->notes,
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * A record is found by id once it exists, and by code while it is being created.
     */
    private function productionRecord(array $target): array
    {
        $record = DB::table(self::PRODUCTION_TABLES[$target['kind']])
            ->where('organisation_id', $target['organisation_id'])
            ->whereNull('deleted_at')
            ->when(
                $target['id'] ?? null,
                fn ($query, $id) => $query->where('id', $id),
                fn ($query) => $query->whereRaw('lower(code) = ?', [strtolower($target['code'])])
            )
            ->first(self::PRODUCTION_RECORD_FIELDS[$target['kind']]);

        return $record ? (array) $record : [];
    }

    private function productionRecipes(array $target): array
    {
        $steps = DB::table('artefacts_manufacture_tasks')
            ->whereIn('artefact_id', $target['artefact_ids'])
            ->orderBy('position')
            ->orderBy('manufacture_task_id')
            ->get(['id', 'artefact_id', 'manufacture_task_id', 'position', 'units_per_artefact', 'standard_rate']);

        $rawMaterials = DB::table('recipe_step_raw_materials')
            ->whereIn('artefact_manufacture_task_id', $steps->pluck('id'))
            ->orderBy('raw_material_id')
            ->get(['artefact_manufacture_task_id', 'raw_material_id', 'quantity_per_unit'])
            ->groupBy('artefact_manufacture_task_id');

        return [
            'artefacts' => collect($target['artefact_ids'])
                ->mapWithKeys(fn ($artefactId) => [
                    $artefactId => $steps->where('artefact_id', $artefactId)->map(fn ($step) => [
                        'manufacture_task_id' => $step->manufacture_task_id,
                        'position'            => $step->position,
                        'units_per_artefact'  => $step->units_per_artefact,
                        'standard_rate'       => $step->standard_rate,
                        'raw_materials'       => collect($rawMaterials->get($step->id, []))->map(fn ($rawMaterial) => [
                            'raw_material_id'   => $rawMaterial->raw_material_id,
                            'quantity_per_unit' => $rawMaterial->quantity_per_unit,
                        ])->values()->all(),
                    ])->values()->all(),
                ])
                ->all(),
        ];
    }

    private function supplierProductAvailability(array $target): array
    {
        return [
            'org_supplier_products' => DB::table('org_supplier_products')
                ->join('supplier_products', 'supplier_products.id', '=', 'org_supplier_products.supplier_product_id')
                ->join('organisations', 'organisations.id', '=', 'org_supplier_products.organisation_id')
                ->whereIn('org_supplier_products.id', $target['org_supplier_product_ids'])
                ->orderBy('org_supplier_products.id')
                ->get(['org_supplier_products.id', 'supplier_products.code', 'organisations.code as organisation', 'org_supplier_products.is_available'])
                ->mapWithKeys(fn ($row) => [$row->id => ['code' => $row->code, 'organisation' => $row->organisation, 'is_available' => (bool) $row->is_available]])
                ->all(),
            'supplier_products'     => DB::table('supplier_products')
                ->whereIn('id', $target['supplier_product_ids'])
                ->orderBy('id')
                ->get(['id', 'code', 'is_available'])
                ->mapWithKeys(fn ($row) => [$row->id => ['code' => $row->code, 'is_available' => (bool) $row->is_available]])
                ->all(),
        ];
    }

    private function orgStockStates(array $target): array
    {
        return [
            'org_stocks' => OrgStock::whereIn('id', $target['org_stock_ids'])
                ->with('organisation:id,code')
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (OrgStock $orgStock) => [
                    $orgStock->id => [
                        'code'      => $orgStock->code.' ('.$orgStock->organisation->code.')',
                        'state'     => $orgStock->state->value,
                        'scheduled' => Arr::get($orgStock->data, DiscontinueOrgStocks::SCHEDULED_KEY),
                    ],
                ])
                ->all(),
        ];
    }

    private function chatSpam(array $target): array
    {
        $chatSession = ChatSession::find($target['chat_session_id']);
        $email       = strtolower((string) data_get($chatSession?->metadata, 'email'));

        return [
            'is_spam'             => (bool) $chatSession?->is_spam,
            'spammed_by_agent_id' => $chatSession?->spammed_by_agent_id,
            'sender_blocked'      => $email !== '' && in_array($email, data_get($chatSession?->shop?->settings, 'gmail.blocked_senders') ?? [], true),
        ];
    }

    /**
     * What a customer service tool can move: the conversation, the customer's balance and, when the
     * change is on an order, its lines, address, notes and what was already given for a claim.
     */
    private function customerService(array $target): array
    {
        $chatSession = ChatSession::with('webUser.customer')->find($target['chat_session_id']);
        $customer    = $chatSession?->webUser?->customer;
        $order       = isset($target['order_id']) ? Order::find($target['order_id']) : null;

        return [
            'chat'     => [
                'status'         => $chatSession?->status?->value,
                'held_by'        => $chatSession?->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->latest('id')->first()?->chatAgent?->user?->username,
                'agent_messages' => $chatSession ? $chatSession->messages()->where('sender_type', ChatSenderTypeEnum::AGENT->value)->count() : 0,
                'waiting_until'  => $chatSession ? WaitForCustomerReply::until($chatSession) : null,
            ],
            'customer' => $customer ? [
                'id'      => $customer->id,
                'name'    => $customer->name,
                'balance' => (float) $customer->balance,
            ] : null,
            'order'    => $order ? [
                'reference'        => $order->reference,
                'lines'            => $order->transactions()->where('model_type', 'Product')->with('asset:id,code')->orderBy('id')->get()
                    ->mapWithKeys(fn ($transaction) => [$transaction->asset?->code ?? '#'.$transaction->id => (float) $transaction->quantity_ordered])
                    ->all(),
                'delivery_address' => $order->deliveryAddress
                    ? collect($order->deliveryAddress->only(['address_line_1', 'address_line_2', 'locality', 'administrative_area', 'postal_code', 'country_code']))->filter()->implode(', ')
                    : null,
                'notes'            => $order->only(['customer_notes', 'public_notes', 'internal_notes', 'shipping_notes', 'private_warehouse_note']),
                'refunds'          => $order->invoices()->where('type', InvoiceTypeEnum::REFUND)->orderBy('id')->pluck('reference')->all(),
                'replacements'     => $order->deliveryNotes()->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->orderBy('delivery_notes.id')->pluck('reference')->all(),
                'follow_ons'       => UpcomingTransaction::whereIn('source_transaction_id', $order->transactions()->pluck('id'))->count(),
            ] : null,
        ];
    }
}
