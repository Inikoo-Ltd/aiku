<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use App\Notifications\PurchaseOrderMinimumPokeDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Requests to add to a draft reach email and Slack grouped, one message per buyer every 15 minutes.
 */
class SendPurchaseOrderMinimumPokeDigests
{
    use AsAction;

    public string $commandSignature = 'procurement:send-minimum-poke-digests';

    public function handle(): int
    {
        $pokes = DB::table('purchase_order_minimum_pokes as poke')
            ->join('supplier_products as sp', 'sp.id', 'poke.supplier_product_id')
            ->join('organisations as o', 'o.id', 'poke.organisation_id')
            ->join('users as sender', 'sender.id', 'poke.sender_id')
            ->whereNull('poke.digested_at')
            ->orderBy('poke.id')
            ->get(['poke.id', 'poke.recipient_id', 'poke.purchase_order_id', 'poke.cartons_short', 'poke.minimum_carton_order', 'sp.code', 'sp.name', 'o.code as organisation_code', 'sender.contact_name', 'sender.username']);

        $purchaseOrders = PurchaseOrder::with('organisation')->whereIn('id', $pokes->pluck('purchase_order_id')->unique())->get()->keyBy('id');

        foreach ($pokes->groupBy('recipient_id') as $recipientId => $recipientPokes) {
            $recipient = User::find($recipientId);
            $channels  = $recipient ? UserNotificationEnum::PURCHASE_ORDER_MINIMUM_POKE->deliverTo($recipient) : [];

            $requests = $recipientPokes->map(function ($poke) use ($purchaseOrders) {
                $purchaseOrder = $purchaseOrders->get($poke->purchase_order_id);

                return [
                    'line' => __(':sender (:organisation) needs :short more cartons of :code :name to reach the supplier minimum of :moq. Your draft: :reference.', [
                        'sender'       => $poke->contact_name ?: $poke->username,
                        'organisation' => $poke->organisation_code,
                        'short'        => trimDecimalZeros($poke->cartons_short),
                        'code'         => $poke->code,
                        'name'         => $poke->name,
                        'moq'          => $poke->minimum_carton_order,
                        'reference'    => $purchaseOrder?->reference,
                    ]),
                    'url'  => $purchaseOrder ? route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]) : '',
                ];
            })->values()->all();

            if (in_array('email', $channels, true) && $recipient->notificationEmail()) {
                $recipient->notify(new PurchaseOrderMinimumPokeDigestNotification($requests));
            }

            if (in_array('slack', $channels, true) && $recipient->slack_user_id) {
                SendTicketSlackDirectMessage::dispatch($recipient, '*'.__('Requests to add to your drafts to reach supplier minimums')."*\n"
                    .collect($requests)->map(fn (array $request) => '• '.$request['line'].' <'.$request['url'].'|'.__('Open in aiku').'>')->implode("\n"));
            }

            DB::table('purchase_order_minimum_pokes')->whereIn('id', $recipientPokes->pluck('id'))->update(['digested_at' => now()]);
        }

        return $pokes->count();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $command->info($this->handle().' requests sent');

        return 0;
    }
}
