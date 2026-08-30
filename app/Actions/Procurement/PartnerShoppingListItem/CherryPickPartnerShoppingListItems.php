<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\OrgAction;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\SalesChannel\StoreSalesChannel;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\SalesChannel\SalesChannelTypeEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Inventory\OrgStock;
use App\Models\Ordering\Order;
use App\Models\Ordering\SalesChannel;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
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

        foreach ($lines as $line) {
            /** @var PartnerShoppingListItem|null $item */
            $item = $items->get($line['id']);
            if (!$item) {
                $skipped[] = ['id' => $line['id'], 'reason' => 'not found, not open, or not addressed to this organisation'];
                continue;
            }

            $product = $this->resolveSellerProduct($seller, $item);
            if (!$product) {
                $skipped[] = ['id' => $item->id, 'reason' => 'no active product for this stock in the partner organisation'];
                continue;
            }

            $customer = $this->resolveIntercompanyCustomer($item->orgPartner, $product->shop);
            if (!$customer) {
                $skipped[] = ['id' => $item->id, 'reason' => 'buying organisation has no address, cannot create intercompany customer'];
                continue;
            }

            $order = $orders[$customer->id] ?? $this->resolveOrder($customer);

            $orders[$customer->id] = $order;

            $quantityRequested = (float) ($line['quantity'] ?? $item->quantity);
            $quantityPicked    = min($quantityRequested, (float) $item->quantity);
            $remainder         = (float) $item->quantity - $quantityPicked;

            $skosPerProductUnit = (float) ($product->pivot->quantity ?? 1);
            if ($skosPerProductUnit <= 0) {
                $skosPerProductUnit = 1;
            }
            $productUnits = round($quantityPicked / $skosPerProductUnit, 3);
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

            if ($remainder > 0) {
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
                        'added_by_user_id',
                    ]),
                    'parent_id'      => $item->id,
                    'quantity' => $remainder,
                    'state'          => ShoppingListItemStateEnum::OPEN,
                    'created_at'     => $item->created_at,
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

    private function resolveSellerProduct(Organisation $seller, PartnerShoppingListItem $item): ?Product
    {
        $sellerOrgStock = OrgStock::where('organisation_id', $seller->id)
            ->where('stock_id', $item->stock_id)
            ->first();

        return $sellerOrgStock
            ?->products()
            ->where('products.state', ProductStateEnum::ACTIVE)
            ->first();
    }

    private function resolveIntercompanyCustomer(OrgPartner $orgPartner, Shop $shop): ?Customer
    {
        $customerId = data_get($orgPartner->data, "intercompany_customers.{$shop->id}");
        if ($customerId) {
            $customer = Customer::find($customerId);
            if ($customer) {
                return $customer;
            }
        }

        $buyer        = $orgPartner->organisation;
        $buyerAddress = $buyer->address;
        if (!$buyerAddress) {
            return null;
        }

        $customer = StoreCustomer::make()->action($shop, [
            'company_name'    => $buyer->name,
            'contact_name'    => $buyer->name,
            'contact_address' => $buyerAddress->only([
                'address_line_1',
                'address_line_2',
                'sorting_code',
                'postal_code',
                'locality',
                'dependent_locality',
                'administrative_area',
                'country_id',
            ]),
        ]);

        $orgPartner->update([
            'data' => array_replace_recursive($orgPartner->data, [
                'intercompany_customers' => [$shop->id => $customer->id],
            ]),
        ]);

        return $customer;
    }

    private function resolveOrder(Customer $customer): Order
    {
        $channel = $this->intercompanySalesChannel($customer->group_id);

        $order = $customer->orders()
            ->where('state', OrderStateEnum::CREATING)
            ->where('sales_channel_id', $channel->id)
            ->first();

        if ($order) {
            return $order;
        }

        return StoreOrder::make()->action($customer, [
            'sales_channel_id' => $channel->id,
        ]);
    }

    private function intercompanySalesChannel(int $groupId): SalesChannel
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

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request->input('lines', []));
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
