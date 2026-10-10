<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Actions\Procurement\PartnerShoppingListItem\EnsurePartnerOrderPackedInMatches;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\Customer\UpdateCustomer;
use App\Actions\OrgAction;
use App\Actions\Production\PartnerShippingList\UI\IndexPrePickList;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\SalesChannel\StoreSalesChannel;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Actions\Procurement\OrgPartner\GetPartnerIntercompanyCustomer;
use App\Actions\Procurement\OrgPartner\GetPartnerSellingProduct;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CherryPickPartnerShoppingListItems extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(Organisation $seller, array $lines): array
    {
        $ids   = collect($lines)->pluck('id');
        $items = PartnerShoppingListItem::query()
            ->whereIn('id', $ids)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->where('partner_organisation_id', $seller->id)
            ->get()
            ->keyBy('id');

        $orders       = [];
        $skipped      = [];
        $picked       = 0;
        $touchedOrgPartners = [];
        $sellerSideByBuyer  = [];

        foreach ($lines as $line) {
            /** @var PartnerShoppingListItem|null $item */
            $item = $items->get($line['id']);
            if (!$item) {
                $skipped[] = ['id' => $line['id'], 'reason' => 'not found, not open, or not addressed to this organisation'];
                continue;
            }

            $product = GetPartnerSellingProduct::run($item->orgPartner, $item->stock_id);
            if (!$product) {
                $skipped[] = ['id' => $item->id, 'reason' => 'no product for this stock in the shops the partner sells from'];
                continue;
            }

            if ($packedInMismatches = EnsurePartnerOrderPackedInMatches::make()->mismatches($item->orgPartner, $product->orgStocks()->pluck('stock_id')->push($item->stock_id)->unique()->all())) {
                $skipped[] = ['id' => $item->id, 'reason' => $packedInMismatches[0]];
                continue;
            }

            $customer = $this->resolveIntercompanyCustomer($item->orgPartner, $product->shop);
            if (!$customer) {
                $skipped[] = ['id' => $item->id, 'reason' => 'buying organisation has no address, cannot create intercompany customer'];
                continue;
            }

            $sellerSide = $sellerSideByBuyer[$item->organisation_id] ??= OrgPartner::where('organisation_id', $seller->id)
                ->where('partner_id', $item->organisation_id)
                ->first();
            $splits     = $sellerSide && ($sellerSide->split_cosmetics || $sellerSide->split_gb_origin);
            $split      = $splits ? $sellerSide->splitFor((bool) $item->stock->is_cosmetic, $sellerSide->isGbPallet($item->stock_id)) : null;
            $orderKey   = $customer->id.($splits ? ':'.$split : '');

            $order = $orders[$orderKey] ?? $this->resolveOrder($customer, $splits, $split);

            $orders[$orderKey] = $order;

            $quantityRequested = (float) ($line['quantity'] ?? $item->quantity);
            $quantityPicked    = min($quantityRequested, (float) $item->quantity);
            $remainder         = (float) $item->quantity - $quantityPicked;

            $skosPerProductUnit = (float) ($product->pivot->quantity ?? 1);
            if ($skosPerProductUnit <= 0) {
                $skosPerProductUnit = 1;
            }
            $productUnits = round($quantityPicked / $skosPerProductUnit, 6);
            $amount       = round($productUnits * (float) $product->price, 2);

            $transaction = StoreTransaction::make()->action(
                $order,
                $product->historicAsset,
                [
                    'quantity_ordered' => $productUnits,
                    'gross_amount'     => $amount,
                    'net_amount'       => $amount,
                ]
            );

            $openSibling = $remainder > 0 && !$item->pre_picked_at
                ? PartnerShoppingListItem::openPartnerLineFor($item->org_partner_id, $item->org_stock_id)->where('id', '!=', $item->id)->first()
                : null;

            if ($openSibling) {
                $openSibling->increment('quantity', $remainder);
            } elseif ($remainder > 0) {
                PartnerShoppingListItem::create([
                    ...$item->only([
                        'group_id',
                        'organisation_id',
                        'org_partner_id',
                        'partner_organisation_id',
                        'stock_id',
                        'org_stock_id',
                        'priority',
                        'needed_by',
                        'notes',
                        'transaction_id',
                        'added_by_user_id',
                        'pre_picked_at',
                    ]),
                    'job_order_id'   => in_array($item->jobOrder?->state, [JobOrderStateEnum::IN_PROCESS, JobOrderStateEnum::SUBMITTED, JobOrderStateEnum::CONFIRMED], true) ? $item->job_order_id : null,
                    'parent_id'      => $item->id,
                    'quantity' => $remainder,
                    'state'          => ShoppingListItemStateEnum::OPEN,
                    'created_at'     => $item->created_at,
                ]);
            }

            if ($seller->is_manufacturing_hub) {
                $transaction->update([
                    'discretionary_offer'       => GetPartnerBuyingPriceFactor::hubPartnerDiscount($seller),
                    'discretionary_offer_label' => __('Intercompany partner discount'),
                ]);
            }

            $item->update([
                'quantity' => $quantityPicked,
                'state'          => ShoppingListItemStateEnum::ORDERED,
                'transaction_id' => $transaction->id,
            ]);

            $touchedOrgPartners[$item->org_partner_id] = $item->orgPartner;
            $picked++;
        }

        foreach ($orders as $order) {
            if ($seller->is_manufacturing_hub) {
                OrderHydrateDiscretionaryOffersData::run($order);
                CalculateOrderDiscounts::run($order->refresh());
            }
            if (!$order->at_gate_at) {
                $order->update(['at_gate_at' => now()]);
            }
        }

        foreach ($touchedOrgPartners as $touchedOrgPartner) {
            OrgPartnerHydrateShoppingListItems::dispatch($touchedOrgPartner);
        }

        return [
            'orders'  => array_values($orders),
            'picked'  => $picked,
            'skipped' => $skipped,
        ];
    }

    public function resolveIntercompanyCustomer(OrgPartner $orgPartner, Shop $shop): ?Customer
    {
        $buyer        = $orgPartner->organisation;
        $buyerAddress = $buyer->address?->only([
            'address_line_1',
            'address_line_2',
            'sorting_code',
            'postal_code',
            'locality',
            'dependent_locality',
            'administrative_area',
            'country_id',
        ]);

        $customer = GetPartnerIntercompanyCustomer::run($orgPartner, $shop->id);
        if ($customer) {
            if (!$customer->address_id && $buyerAddress) {
                $customer = UpdateCustomer::make()->action($customer, ['contact_address' => $buyerAddress], strict: false);
                if (!$customer->delivery_address_id) {
                    $customer->updateQuietly(['delivery_address_id' => $customer->address_id]);
                }
            }

            return $customer;
        }

        if (!$buyerAddress) {
            return null;
        }

        $customer = StoreCustomer::make()->action($shop, [
            'company_name'    => $buyer->name,
            'contact_name'    => $buyer->name,
            'contact_address' => $buyerAddress,
        ]);

        $orgPartner->update([
            'data' => array_replace_recursive($orgPartner->data, [
                'intercompany_customers' => [$shop->id => $customer->id],
            ]),
        ]);

        return $customer;
    }

    /**
     * A partner splitting off cosmetics or GB-origin goods gets one order per pallet, told apart by
     * data.partner_cosmetic and data.partner_gb, so each pallet has its own delivery note and invoice.
     */
    private function resolveOrder(Customer $customer, bool $splits, ?string $split): Order
    {
        $channel = $this->intercompanySalesChannel($customer->group_id);

        $order = $customer->orders()
            ->where('state', OrderStateEnum::CREATING)
            ->where('sales_channel_id', $channel->id)
            ->when($splits, fn ($query) => $query
                ->whereRaw("coalesce((data->>'partner_cosmetic')::boolean, false) = ?", [$split === 'cosmetic'])
                ->whereRaw("coalesce((data->>'partner_gb')::boolean, false) = ?", [$split === 'gb']))
            ->first();

        if ($order) {
            return $order;
        }

        $order = StoreOrder::make()->action($customer, [
            'sales_channel_id' => $channel->id,
        ]);

        if ($splits) {
            $order->update(['data' => array_replace($order->data ?? [], ['partner_cosmetic' => $split === 'cosmetic', 'partner_gb' => $split === 'gb'])]);
        }

        return $order;
    }

    public function intercompanySalesChannel(int $groupId): SalesChannel
    {
        $channel = SalesChannel::where('group_id', $groupId)
            ->where('code', 'intercompany')
            ->first();

        if ($channel) {
            return $channel;
        }

        return StoreSalesChannel::make()->action(
            Group::find($groupId),
            [
                'code' => 'intercompany',
                'name' => 'Intercompany',
                'type' => SalesChannelTypeEnum::OTHER,
            ]
        );
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request->input('lines', []));
    }

    public function everything(Organisation $organisation, Production $production, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        $index = IndexPrePickList::make();
        $index->initialisationFromProduction($production, $request);
        $lines = $index->eligibleLines($organisation);

        return $this->handle($organisation, $lines);
    }

    public function action(Organisation $seller, array $lines): array
    {
        $this->asAction = true;
        $this->initialisation($seller, []);

        return $this->handle($seller, $lines);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
