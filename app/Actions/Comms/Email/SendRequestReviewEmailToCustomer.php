<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 14 Apr 2026 11:20:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Email;

use App\Actions\Comms\Traits\WithOrderingCustomerNotification;
use App\Actions\Comms\Traits\WithSendBulkEmails;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Email;
use App\Models\Ordering\Order;

class SendReviewEmailToCustomer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithSendBulkEmails;
    use WithOrderingCustomerNotification;

    private Email $email;

    public function handle(Order $order): ?DispatchedEmail
    {
        if ($order->shop->type === ShopTypeEnum::EXTERNAL) {
            return null;
        }

        list($emailHtmlBody, $dispatchedEmail) = $this->getEmailBody($order->customer, OutboxCodeEnum::REQUEST_REVIEW);
        if (!$emailHtmlBody) {
            return null;
        }
        $outbox = $dispatchedEmail->outbox;

        $order->dispatchedEmails()->attach($dispatchedEmail, ['outbox_id' => $outbox->id]);

        $orderUrl = $this->getOrderLink($order);

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
                                    <!--[if !mso]><!--><span class="fallback-text">' . __('Order Details') . '</span><!--<![endif]-->
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
                                            <a href="' . $orderUrl . '" target="_blank" style="color: #3498DB; text-decoration: underline;"><span class="fallback-text">' . $order->reference . '</span></a>
                                        </td>
                                    </tr>
                                    <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">' . __('Order Date') . ':</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            <span class="fallback-text">' . ($order->created_at ? $order->created_at->format('F j, Y') : 'N/A') . '</span>
                                        </td>
                                    </tr>
                                    <tr style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; margin: 0;">
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top">
                                            <strong><span class="fallback-text">' . __('Total Amount') . ':</span></strong>
                                        </td>
                                        <td style="font-family: \'Helvetica Neue\',Helvetica,Arial,sans-serif; box-sizing: border-box; font-size: 14px; vertical-align: top; text-align: right; border-top: 1px solid #eee; margin: 0; padding: 8px 0;" valign="top" align="right">
                                            <span class="fallback-text">' . $order->currency->code . ' ' . number_format($order->total_amount, 2) . '</span>
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
                'order' => $orderHtmlBlock,
                'customer_name' => $order->customer->name,
                'order_reference' => $order->reference,
                'date' => $order->created_at->format('F jS, Y'),
                'order_link' => $orderUrl,
            ],
            senderName: $outbox->emailOngoingRun->senderName()
        );
    }

    public string $commandSignature = 'test:send-review-email';

    public function asCommand(): void
    {
        $order = Order::where('slug', 'awd151487')->first();

        $this->handle($order);
    }
}
