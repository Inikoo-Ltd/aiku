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
use App\Enums\Procurement\SupplierMessage\SupplierMessageDirectionEnum;
use App\Enums\Procurement\SupplierMessage\SupplierMessageRoutedByEnum;
use App\Actions\Procurement\SupplierMessage\RouteSupplierMessage;
use App\Actions\Procurement\SupplierMessage\Whatsapp\SendSupplierWhatsappMessage;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\ModelHasDispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class SendPurchaseOrderToSupplier
{
    use AsAction;

    /**
     * Sent through SES rather than Gmail so delivery, opens and clicks come back as tracking
     * events on the dispatched email. Replies go to the procurement mailbox, where the mirror
     * picks them up and files them under the same supplier.
     */
    public function handle(PurchaseOrder $purchaseOrder, string $channel = 'email'): DispatchedEmail|SupplierMessage|null
    {
        return $this->send(collect([$purchaseOrder]), $purchaseOrder->reference, $channel);
    }

    /**
     * The supplier orders of one agent order go to the agent together: one message, one PDF with
     * a section per supplier order.
     *
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    public function sendAgentOrder(Collection $purchaseOrders, string $agentOrderReference, string $channel = 'email'): DispatchedEmail|SupplierMessage|null
    {
        return $this->send($purchaseOrders, $agentOrderReference, $channel);
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    private function send(Collection $purchaseOrders, string $reference, string $channel): DispatchedEmail|SupplierMessage|null
    {
        $purchaseOrder = $purchaseOrders->first();
        $document      = $purchaseOrders->count() === 1
            ? ['content' => PdfPurchaseOrder::make()->handle($purchaseOrder), 'filename' => PdfPurchaseOrder::make()->filename($purchaseOrder)]
            : ['content' => PdfPurchaseOrder::make()->handleMany($purchaseOrders), 'filename' => PdfPurchaseOrder::make()->filenameFor($reference)];

        if ($channel === 'whatsapp') {
            return $this->sendByWhatsapp($purchaseOrder, $reference, $document);
        }

        $recipient = self::recipientEmail($purchaseOrder);
        $outbox    = self::outbox($purchaseOrder);

        if (! $recipient || ! $outbox) {
            return null;
        }

        /** @var OrgSupplier|OrgAgent|OrgPartner $counterpart */
        $counterpart  = self::counterpart($purchaseOrder);
        $organisation = $purchaseOrder->organisation;
        $mailbox      = Arr::get($organisation->settings, 'procurement.gmail.email');

        /** @var DispatchedEmail $dispatchedEmail */
        $dispatchedEmail = $outbox->emailOngoingRun->dispatchedEmails()->create([
            'outbox_id'        => $outbox->id,
            'email_address_id' => StoreEmailAddress::run($organisation->group, $recipient)->id,
        ]);

        foreach ($purchaseOrders as $order) {
            ModelHasDispatchedEmail::create([
                'model_type'          => 'PurchaseOrder',
                'model_id'            => $order->id,
                'dispatched_email_id' => $dispatchedEmail->id,
                'outbox_id'           => $outbox->id,
            ]);
        }

        $subject = __('Purchase order :reference from :organisation', [
            'reference'    => $reference,
            'organisation' => $organisation->name,
        ]);

        $html = view('emails.procurement.purchase-order', [
            'reference'        => $reference,
            'date'             => $purchaseOrder->submitted_at ?? now(),
            'supplierName'     => self::counterpartName($counterpart, contact: true),
            'organisationName' => $organisation->name,
            'numberItems'      => $purchaseOrders->sum(fn (PurchaseOrder $order) => $order->purchaseOrderTransactions()->count()),
            'supplierOrders'   => $purchaseOrders->count() > 1 ? $purchaseOrders->map(fn (PurchaseOrder $order) => ['reference' => $order->reference, 'supplier' => $order->parent_name])->all() : [],
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
            attachments: [$document],
            replyTo: $mailbox,
        );

        SupplierMessage::create([
            ...SupplierMessage::counterpartAttributes($counterpart),
            'group_id'            => $organisation->group_id,
            'organisation_id'     => $organisation->id,
            'purchase_order_id'   => $purchaseOrder->id,
            'dispatched_email_id' => $dispatchedEmail->id,
            'direction'           => SupplierMessageDirectionEnum::OUTBOUND,
            'routed_by'           => SupplierMessageRoutedByEnum::PURCHASE_ORDER,
            'from_address'        => $sender,
            'from_name'           => $organisation->name,
            'to'                  => [['name' => self::counterpartName($counterpart), 'address' => $recipient]],
            'subject'             => $subject,
            'snippet'             => __('Purchase order :reference sent with the PDF attached.', ['reference' => $reference]),
            'body_html'           => $html,
            'body_text'           => trim(preg_replace('/\s+\n/', "\n", strip_tags($html))),
            'sent_at'             => now(),
        ]);

        return $dispatchedEmail;
    }

    /**
     * The template carries the order reference and the PDF as its document; the supplier answers
     * in the chat, which lands in the procurement inbox like any other WhatsApp.
     *
     * @param  array{content: string, filename: string}  $document
     */
    private function sendByWhatsapp(PurchaseOrder $purchaseOrder, string $reference, array $document): ?SupplierMessage
    {
        $phone = self::recipientPhone($purchaseOrder);

        if (! $phone || ! SendSupplierWhatsappMessage::isConnected($purchaseOrder->organisation)) {
            return null;
        }

        return SendSupplierWhatsappMessage::make()->handle(
            organisation: $purchaseOrder->organisation,
            user: null,
            phone: $phone,
            text: __('Purchase order :reference from :organisation. Please confirm the order, prices and the expected dispatch date.', [
                'reference'    => $reference,
                'organisation' => $purchaseOrder->organisation->name,
            ]),
            counterpart: self::counterpart($purchaseOrder),
            document: $document,
            purchaseOrder: $purchaseOrder,
        );
    }

    /**
     * @return array<int, array{channel: string, to: string}>
     */
    public static function channels(PurchaseOrder $purchaseOrder): array
    {
        return array_values(array_filter([
            self::outbox($purchaseOrder) && ($email = self::recipientEmail($purchaseOrder)) ? ['channel' => 'email', 'to' => $email] : null,
            SendSupplierWhatsappMessage::isConnected($purchaseOrder->organisation) && ($phone = self::recipientPhone($purchaseOrder)) ? ['channel' => 'whatsapp', 'to' => '+'.$phone] : null,
        ]));
    }

    /**
     * Only a number written with its country code can be dialled from abroad; one starting with a
     * trunk zero would reach nobody, so it is not offered.
     */
    public static function recipientPhone(PurchaseOrder $purchaseOrder): ?string
    {
        $parent = self::counterpart($purchaseOrder);

        $phone = trim((string) match (true) {
            $parent instanceof OrgSupplier => $parent->supplier?->phone,
            $parent instanceof OrgAgent    => $parent->agent?->organisation?->phone,
            $parent instanceof OrgPartner  => $parent->partner?->phone,
            default                        => null,
        });

        $digits = RouteSupplierMessage::phoneDigits($phone);

        return strlen($digits) >= 10 && (str_starts_with($phone, '+') || str_starts_with($phone, '00')) ? $digits : null;
    }

    public static function recipientEmail(PurchaseOrder $purchaseOrder): ?string
    {
        $parent = self::counterpart($purchaseOrder);

        $email = trim((string) match (true) {
            $parent instanceof OrgSupplier => $parent->supplier?->email,
            $parent instanceof OrgAgent    => $parent->agent?->organisation?->email,
            $parent instanceof OrgPartner  => $parent->partner?->email,
            default                        => null,
        });

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * An order to a supplier behind an agent goes to the agent, who places it with the supplier.
     */
    public static function counterpart(PurchaseOrder $purchaseOrder): OrgSupplier|OrgAgent|OrgPartner|null
    {
        return $purchaseOrder->isAgentOrder() ? $purchaseOrder->orgAgentOfOrder() : $purchaseOrder->parent;
    }

    private static function counterpartName(OrgSupplier|OrgAgent|OrgPartner $counterpart, bool $contact = false): string
    {
        $model = match (true) {
            $counterpart instanceof OrgSupplier => $counterpart->supplier,
            $counterpart instanceof OrgAgent    => $counterpart->agent->organisation,
            $counterpart instanceof OrgPartner  => $counterpart->partner,
        };

        return ($contact ? $model->contact_name : null) ?: $model->name;
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
