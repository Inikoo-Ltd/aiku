<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\Ordering\Order\UpdateOrderFixedAddress;
use App\Actions\Ordering\Order\UpdateState\SendOrderToWarehouse;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;

/**
 * Registration only ever validated the country, so a customer could sign up with an empty address.
 * An empty address hashes to the same checksum as the Aurora "0 / 0 / 0" placeholder, so those orders
 * and their invoices print "0" for the street, town and postcode (HELP-3102).
 *
 * Dispatched orders and their invoices are history and are never rewritten; only open orders whose
 * customer has since given a real address are repointed. The rest is a chase list for CS.
 * "Blank" means every line empty, the same test the warehouse hold uses (HELP-3110).
 */
class RepairBlankAddressesCommand extends Command
{
    protected $signature = 'repair:blank_addresses
                           {--shop= : Only this shop slug}
                           {--months= : Only customers who ordered within this many months, default all}
                           {--fix-open-orders : Repoint open orders whose customer now has a real address}
                           {--clean-held-notes : Remove the held warning from orders, and their open delivery notes, that are no longer held}';

    protected $description = 'List customers whose address is blank or "0", and repoint their open orders';

    public function handle(): int
    {
        Nightwatch::dontSample();

        $customers = Customer::with(['shop'])
            ->withMax('orders', 'created_at')
            ->whereIn('address_id', $this->blankAddresses())
            ->when($this->option('shop'), fn ($query, $shop) => $query->whereRelation('shop', 'slug', $shop))
            ->whereHas('orders', fn ($query) => $query->when($this->option('months'), fn ($query, $months) => $query->where('orders.created_at', '>', now()->subMonths((int)$months))))
            ->orderByDesc('orders_max_created_at')
            ->get();

        $this->table(
            ['Shop', 'Customer', 'Name', 'Email', 'Phone', 'Last order'],
            $customers->map(fn (Customer $customer) => [
                $customer->shop->slug,
                $customer->reference,
                $customer->name,
                $customer->email,
                $customer->phone,
                $customer->orders_max_created_at,
            ])
        );
        $this->info($customers->count().' customers have no address and have ordered');

        if ($this->option('clean-held-notes')) {
            $this->cleanHeldNotes();
        }

        if (!$this->option('fix-open-orders')) {
            return 0;
        }

        $openOrders = Order::with(['customer.address', 'billingAddress', 'deliveryAddress'])
            ->whereIn('state', [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])
            ->where(fn ($query) => $query->whereIn('billing_address_id', $this->blankAddresses())->orWhereIn('delivery_address_id', $this->blankAddresses()))
            ->when($this->option('shop'), fn ($query, $shop) => $query->whereRelation('shop', 'slug', $shop))
            ->get();

        $repointed = 0;
        foreach ($openOrders as $order) {
            $address = $order->customer->address;
            if (!$address?->hasAnyLine()) {
                continue;
            }

            foreach (['billing' => $order->billingAddress, 'delivery' => $order->deliveryAddress] as $type => $orderAddress) {
                if ($orderAddress?->hasAnyLine()) {
                    continue;
                }
                UpdateOrderFixedAddress::make()->action($order, ['address' => $address, 'type' => $type], audit: false);
                $repointed++;
            }
        }

        $this->info($repointed.' open order addresses repointed to the customer address');

        return 0;
    }

    /** The blank addresses are tens of thousands of rows, too many to bind as a list of ids */
    private function blankAddresses(): Closure
    {
        return fn ($query) => $query->select('id')->from('addresses')
            ->whereIn(DB::raw("trim(coalesce(address_line_1,''))"), ['', '0'])
            ->whereIn(DB::raw("trim(coalesce(address_line_2,''))"), ['', '0'])
            ->whereIn(DB::raw("trim(coalesce(locality,''))"), ['', '0'])
            ->whereIn(DB::raw("trim(coalesce(postal_code,''))"), ['', '0'])
            ->whereIn(DB::raw("trim(coalesce(administrative_area,''))"), ['', '0']);
    }

    /** A held warning left on an order that has moved on, or on its delivery note, tells a picker to stop for nothing */
    private function cleanHeldNotes(): void
    {
        $cleaned = 0;

        Order::with(['deliveryNotes', 'billingAddress', 'deliveryAddress'])
            ->where('private_warehouse_note', 'like', '%'.SendOrderToWarehouse::HELD_MARKER.'%')
            ->when($this->option('shop'), fn ($query, $shop) => $query->whereRelation('shop', 'slug', $shop))
            ->each(function (Order $order) use (&$cleaned) {
                $stillHeld = $order->state == OrderStateEnum::SUBMITTED && $order->deliveryNotes->isEmpty() && $order->isMissingARequiredAddress();
                if ($stillHeld) {
                    return;
                }

                $order->update(['private_warehouse_note' => SendOrderToWarehouse::withoutHeldNote($order->private_warehouse_note)]);

                foreach ($order->deliveryNotes as $deliveryNote) {
                    if (str_contains((string)$deliveryNote->private_warehouse_note, SendOrderToWarehouse::HELD_MARKER)
                        && !in_array($deliveryNote->state, [DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED])) {
                        $deliveryNote->update(['private_warehouse_note' => SendOrderToWarehouse::withoutHeldNote($deliveryNote->private_warehouse_note)]);
                    }
                }

                $cleaned++;
            });

        $this->info($cleaned.' orders no longer held had the held warning removed');
    }
}
