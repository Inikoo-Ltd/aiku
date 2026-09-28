<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\Comms\EmailAddress\StoreEmailAddress;
use App\Actions\Comms\Ses\SendSesEmail;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Enums\Procurement\SupplierEmail\SupplierEmailDirectionEnum;
use App\Enums\Procurement\SupplierEmail\SupplierEmailRoutedByEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\ModelHasDispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierEmail;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SendPurchaseOrderToSupplier
{
    use AsAction;

    /**
     * Sent through SES rather than Gmail so delivery, opens and clicks come back as tracking
     * events on the dispatched email. Replies go to the procurement mailbox, where the mirror
     * picks them up and files them under the same supplier.
     */
    public function handle(PurchaseOrder $purchaseOrder): ?DispatchedEmail
    {
        $recipient = self::recipientEmail($purchaseOrder);
        $outbox    = self::outbox($purchaseOrder);

        if (! $recipient || ! $outbox) {
            return null;
        }

        /** @var OrgSupplier $orgSupplier */
        $orgSupplier  = $purchaseOrder->parent;
        $organisation = $purchaseOrder->organisation;
        $mailbox      = Arr::get($organisation->settings, 'procurement.gmail.email');

        /** @var DispatchedEmail $dispatchedEmail */
        $dispatchedEmail = $outbox->emailOngoingRun->dispatchedEmails()->create([
            'outbox_id'        => $outbox->id,
            'email_address_id' => StoreEmailAddress::run($organisation->group, $recipient)->id,
        ]);

        ModelHasDispatchedEmail::create([
            'model_type'          => 'PurchaseOrder',
            'model_id'            => $purchaseOrder->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'outbox_id'           => $outbox->id,
        ]);

        $subject = __('Purchase order :reference from :organisation', [
            'reference'    => $purchaseOrder->reference,
            'organisation' => $organisation->name,
        ]);

        $html = view('emails.procurement.purchase-order', [
            'purchaseOrder'    => $purchaseOrder,
            'supplierName'     => $orgSupplier->supplier->contact_name ?: $orgSupplier->supplier->name,
            'organisationName' => $organisation->name,
            'numberItems'      => $purchaseOrder->purchaseOrderTransactions()->count(),
        ])->render();

        $sender = app()->isProduction()
            ? ($mailbox ?: $organisation->email)
            : config('app.email_address_in_non_production_env');

        $dispatchedEmail = SendSesEmail::run(
            subject: $subject,
            emailHtmlBody: $html,
            dispatchedEmail: $dispatchedEmail,
            sender: $sender,
            senderName: $organisation->name,
            attachments: [[
                'content'  => PdfPurchaseOrder::make()->handle($purchaseOrder),
                'filename' => PdfPurchaseOrder::make()->filename($purchaseOrder),
            ]],
            replyTo: $mailbox,
        );

        SupplierEmail::create([
            'group_id'            => $organisation->group_id,
            'organisation_id'     => $organisation->id,
            'supplier_id'         => $orgSupplier->supplier_id,
            'org_supplier_id'     => $orgSupplier->id,
            'purchase_order_id'   => $purchaseOrder->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'direction'           => SupplierEmailDirectionEnum::OUTBOUND,
            'routed_by'           => SupplierEmailRoutedByEnum::PURCHASE_ORDER,
            'from_address'        => $sender,
            'from_name'           => $organisation->name,
            'to'                  => [['name' => $orgSupplier->supplier->name, 'address' => $recipient]],
            'subject'             => $subject,
            'snippet'             => __('Purchase order :reference sent with the PDF attached.', ['reference' => $purchaseOrder->reference]),
            'body_html'           => $html,
            'body_text'           => trim(preg_replace('/\s+\n/', "\n", strip_tags($html))),
            'sent_at'             => now(),
        ]);

        return $dispatchedEmail;
    }

    public static function recipientEmail(PurchaseOrder $purchaseOrder): ?string
    {
        $email = $purchaseOrder->parent instanceof OrgSupplier ? trim((string) $purchaseOrder->parent->supplier?->email) : '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public static function outbox(PurchaseOrder $purchaseOrder): ?Outbox
    {
        return $purchaseOrder->organisation->outboxes()
            ->where('code', OutboxCodeEnum::SEND_PURCHASE_ORDER_TO_SUPPLIER)
            ->whereNull('shop_id')
            ->where('state', OutboxStateEnum::ACTIVE)
            ->whereHas('emailOngoingRun')
            ->first();
    }
}
