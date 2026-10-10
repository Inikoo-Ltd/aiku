<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList;

use App\Models\Procurement\OrgPartner;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RaiseScheduledPartnerOrders
{
    use AsAction;

    public string $commandSignature = 'production:raise_scheduled_partner_orders';
    public string $commandDescription = 'The day before a partner shipment, turn what sits in its bay into an order for the warehouse';

    /**
     * Runs the cut-off the day before the shipment so the warehouse has a day to pack, then moves
     * the next shipment on by the partner's rhythm. An empty bay still moves the date on: the
     * truck leaves anyway, and whatever is late goes on the next one.
     *
     * @return array{orders: int, skipped: array<int, string>}|null null when no shipment is due
     *
     * @throws \Throwable
     */
    public function handle(OrgPartner $orgPartner, Carbon $today): ?array
    {
        if (!$orgPartner->next_shipment_on || $orgPartner->next_shipment_on->gt($today->copy()->addDay())) {
            return null;
        }

        $cutOff = StorePartnerOrderFromBay::make();
        try {
            $orders = count($cutOff->action($orgPartner));
        } catch (ValidationException $e) {
            $orders                 = 0;
            $cutOff->skippedReasons = $e->validator->errors()->all();
        }

        $next = $orgPartner->shipment_every_days ? $orgPartner->next_shipment_on->copy() : null;
        while ($next && $next->lte($today->copy()->addDay())) {
            $next->addDays($orgPartner->shipment_every_days);
        }
        $orgPartner->update(['next_shipment_on' => $next]);

        return ['orders' => $orders, 'skipped' => $cutOff->skippedReasons];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        OrgPartner::whereNotNull('next_shipment_on')->whereNotNull('goods_out_location_id')->with(['organisation', 'partner'])->get()
            ->each(function (OrgPartner $orgPartner) use ($command) {
                $result = $this->handle($orgPartner, now($orgPartner->organisation->timezone?->name ?? 'UTC')->startOfDay());
                if ($result) {
                    $command->info("{$orgPartner->organisation->code} → {$orgPartner->partner->code}: {$result['orders']} orders".($result['skipped'] ? ', left on the list: '.implode('; ', $result['skipped']) : ''));
                }
            });

        return 0;
    }
}
