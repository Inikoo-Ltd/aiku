<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\Ordering\Order\UpdateOrderFixedAddress;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
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
 */
class RepairBlankAddressesCommand extends Command
{
    protected $signature = 'repair:blank_addresses
                           {--shop= : Only this shop slug}
                           {--months= : Only customers who ordered within this many months, default all}
                           {--fix-open-orders : Repoint open orders whose customer now has a real address}';

    protected $description = 'List customers whose address is blank or "0", and repoint their open orders';

    public function handle(): int
    {
        Nightwatch::dontSample();

        $blankAddressIds = Address::whereIn(DB::raw("coalesce(address_line_1,'')"), ['', '0'])
            ->whereIn(DB::raw("coalesce(locality,'')"), ['', '0'])
            ->pluck('id');

        $customers = Customer::with(['shop'])
            ->whereIn('address_id', $blankAddressIds)
            ->when($this->option('shop'), fn ($query, $shop) => $query->whereRelation('shop', 'slug', $shop))
            ->whereHas('orders', fn ($query) => $query->when($this->option('months'), fn ($query, $months) => $query->where('orders.created_at', '>', now()->subMonths((int)$months))))
            ->get();

        $this->table(
            ['Shop', 'Customer', 'Name', 'Email', 'Phone'],
            $customers->map(fn (Customer $customer) => [
                $customer->shop->slug,
                $customer->reference,
                $customer->name,
                $customer->email,
                $customer->phone,
            ])
        );
        $this->info($customers->count().' customers have no address and have ordered');

        if (!$this->option('fix-open-orders')) {
            return 0;
        }

        $openOrders = Order::with(['customer.address'])
            ->whereIn('state', [OrderStateEnum::CREATING, OrderStateEnum::SUBMITTED])
            ->where(fn ($query) => $query->whereIn('billing_address_id', $blankAddressIds)->orWhereIn('delivery_address_id', $blankAddressIds))
            ->when($this->option('shop'), fn ($query, $shop) => $query->whereRelation('shop', 'slug', $shop))
            ->get();

        $repointed = 0;
        foreach ($openOrders as $order) {
            $address = $order->customer->address;
            if (!$address || in_array($address->address_line_1, [null, '', '0'], true)) {
                continue;
            }

            foreach (['billing', 'delivery'] as $type) {
                if (!$blankAddressIds->contains($type == 'billing' ? $order->billing_address_id : $order->delivery_address_id)) {
                    continue;
                }
                UpdateOrderFixedAddress::make()->action($order, ['address' => $address, 'type' => $type], audit: false);
                $repointed++;
            }
        }

        $this->info($repointed.' open order addresses repointed to the customer address');

        return 0;
    }
}
