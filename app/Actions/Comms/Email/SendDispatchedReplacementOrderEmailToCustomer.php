<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-18h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Http\Resources\Dispatching\RetinaShipmentsResource;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;

class SendDispatchedReplacementOrderEmailToCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;


    private Email $email;

    public function handle(DeliveryNote $deliveryNote): ?DispatchedEmail
    {
        $order = $deliveryNote->orders->first();

        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody($order->customer, OutboxCodeEnum::DELIVERY_CONFIRMATION);
        if (!$emailHtmlBody) {
            return null;
        }
        $outbox = $dispatchedEmail->outbox;
        $orderUrl   = $this->getOrderLink($order);
        $deliveryNote = $order->deliveryNotes->first();
        $shipments    = $deliveryNote?->shipments ? RetinaShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->resolve() : null;

        // Create an email-client compatible HTML block with order information
        $orderHtmlBlock = '
        <table width="100%" cellpadding="0" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; border-radius: 3px; background-color: #fff; margin: 0 0 20px; border: 1px solid #e9e9e9;" bgcolor="#fff">
            <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                <td class="content-wrap" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 20px;" valign="top">
                    <table width="100%" cellpadding="0" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                        <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                            <td class="content-block" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
                                <h2 style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 18px; color: #000; line-height: 1.2em; font-weight: 400; margin: 0 0 15px;">
                                    <!--[if mso]><span style="font-family: Arial, sans-serif" class="fallback-text">Order Details</span><![endif]-->
                                    <!--[if !mso]><!--><span class="fallback-text">'.__('Order Details').'</span><!--<![endif]-->
                                </h2>
                            </td>
                        </tr>
                        <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                            <td class="content-block" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; margin: 0; padding: 0 0 20px;" valign="top">
                                <table width="100%" cellpadding="0" cellspacing="0" style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0; border-collapse: collapse;">
                                    <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">Order Number:</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            <a href="'.$orderUrl.'" target="_blank" style="color: #3498DB; text-decoration: underline;"><span class="fallback-text">'.$order->reference.'</span></a>
                                        </td>
                                    </tr>'.
            ($order->customer_client_id ? '
                                    <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">Client:</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            <span class="fallback-text">'.$order->customerClient->name.'</span>
                                        </td>
                                    </tr>' : '').'
                                    <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">Shipping to:</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            <span class="fallback-text">'.($order->deliveryAddress ? $order->deliveryAddress->getHtml() : 'N/A').'</span>
                                        </td>
                                    </tr>
                                    <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">'.__('Dispatched Date').':</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            <span class="fallback-text">'.($order->dispatched_at ? $order->dispatched_at->format('F j, Y h:i A P') : 'N/A').'</span>
                                        </td>
                                    </tr>';

        $orderHtmlBlock .= ' <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">Tracking Information:</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            '.($shipments ? $this->generateTrackingHtml($shipments) : '<span class="fallback-text">No tracking information available</span>').'
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        return $this->sendEmailWithMergeTags(
            $dispatchedEmail,
            $outbox->emailOngoingRun->sender(),
            $outbox->emailOngoingRun?->email?->subject,
            $emailHtmlBody,
            '',
            additionalData: [
                'order'           => $orderHtmlBlock,
                'customer_name'   => $order->customer->name,
                'order_reference' => $order->reference,
                'date'            => $order->created_at->format('F jS, Y'),
                'order_link'      => $orderUrl
            ]
        );
    }


    public string $commandSignature = 'test:send-replacement-email';


    public function asCommand(): void
    {
        $deliveryNote = DeliveryNote::where('slug', 'awd153845-r1')->first();

        $this->handle($deliveryNote);
    }

    /**
     * Generate HTML for tracking information
     *
     * @param  array  $shipments  Array of shipment data
     *
     * @return string HTML for tracking information
     */
    private function generateTrackingHtml(array $shipments): string
    {
        $html = '';

        foreach ($shipments as $shipment) {
            $shipperName = $shipment['name'] ?? 'Unknown';

            if (!empty($shipment['formatted_tracking_urls'])) {
                foreach ($shipment['formatted_tracking_urls'] as $trackingData) {
                    $trackingNumber = $trackingData['tracking'] ?? '';
                    $trackingUrl    = $trackingData['url'] ?? '';

                    if ($trackingNumber) {
                        if ($trackingUrl && $trackingUrl !== __('tracking')) {
                            $html .= '<div style="margin-bottom: 4px;"><span class="fallback-text">'.$shipperName.': </span>';
                            $html .= '<a href="'.$trackingUrl.'" target="_blank" style="color: #3498DB; text-decoration: underline;"><span class="fallback-text">'.$trackingNumber.'</span></a></div>';
                        } else {
                            $html .= '<div style="margin-bottom: 4px;"><span class="fallback-text">'.$shipperName.': '.$trackingNumber.'</span></div>';
                        }
                    }
                }
            } elseif (!empty($shipment['tracking'])) {
                $html .= '<div style="margin-bottom: 4px;"><span class="fallback-text">'.$shipperName.': '.$shipment['tracking'].'</span></div>';
            }
        }


        return $html ?: '<span class="fallback-text">No tracking information available</span>';
    }


    private function generateInvoicePdfHtml(Order $order): ?string
    {

        $invoices     = $order->invoices ?? [];

        $pdfLinks = [];

        foreach ($invoices as $invoice) {
            $url = $this->getPdfInvoiceLink($invoice);
            if ($url != '') {
                $pdfLinks[] = '<a href="'.$url.'" target="_blank" style="color: #3498DB; text-decoration: underline;"><span class="fallback-text">'.$invoice->reference.'</span></a></div>';
            }
        }

        if (empty($pdfLinks)) {
            return null;
        }

        return implode(', ', $pdfLinks);
    }
}
