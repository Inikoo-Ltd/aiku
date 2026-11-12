<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Procurement\PurchaseOrder\UI\GetPurchaseOrderCompliance;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Notifications\ComplianceDocumentsReminderNotification;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daily: the buyer of an order still missing required compliance documents is reminded when a delivery of it
 * leaves the supplier, and again 21 and 7 days before the documents are due.
 */
class SendComplianceDocumentsReminders
{
    use AsAction;

    public const array REMINDER_DAYS = [21, 7];

    public string $commandSignature = 'procurement:send_compliance_documents_reminders';

    public function handle(): int
    {
        $sent = 0;

        PurchaseOrder::whereIn('state', PurchaseOrderStateEnum::open())
            ->where('parent_type', '!=', 'OrgPartner')
            ->whereNotNull('buyer_id')
            ->with('buyer')
            ->chunkById(200, function ($purchaseOrders) use (&$sent) {
                foreach ($purchaseOrders as $purchaseOrder) {
                    $sent += (int) $this->remind($purchaseOrder);
                }
            });

        return $sent;
    }

    public function remind(PurchaseOrder $purchaseOrder): bool
    {
        if (!$purchaseOrder->buyer?->status) {
            return false;
        }

        $compliance = GetPurchaseOrderCompliance::run($purchaseOrder);
        if (!$compliance['missing'] || !$compliance['deadline']) {
            return false;
        }

        $occasion = match (true) {
            $purchaseOrder->stockDeliveries()->where('dispatched_at', '>=', now()->subDay())->exists() => __('The goods have left the supplier.'),
            in_array($compliance['days_to_deadline'], self::REMINDER_DAYS, true) => __(':days days before the compliance documents are due.', ['days' => $compliance['days_to_deadline']]),
            default => null,
        };

        if (!$occasion) {
            return false;
        }

        $missing = collect($compliance['products'])
            ->flatMap(fn (array $product) => array_map(fn (string $document) => __(':document for :code', ['document' => $document, 'code' => $product['code']]), $product['missing']))
            ->all();

        NotifyUserOnChosenChannels::run(
            $purchaseOrder->buyer,
            UserNotificationEnum::PURCHASE_ORDER_COMPLIANCE_DUE,
            new ComplianceDocumentsReminderNotification($purchaseOrder, $missing, $compliance['deadline'], $occasion),
            __(':reference: compliance documents due :date', ['reference' => $purchaseOrder->reference, 'date' => $compliance['deadline']]),
            $occasion.' '.__('Still missing: :documents.', ['documents' => implode(', ', $missing)]),
            route('grp.org.procurement.purchase_orders.show', [$purchaseOrder->organisation->slug, $purchaseOrder->slug]).'?tab=compliance'
        );

        return true;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info($this->handle().' reminders sent');

        return 0;
    }
}
