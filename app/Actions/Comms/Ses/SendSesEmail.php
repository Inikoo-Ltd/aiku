<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Ses;

use App\Actions\Comms\DispatchedEmail\UpdateDispatchedEmail;
use App\Actions\Comms\EmailAddress\Traits\AwsClient;
use App\Actions\Comms\EmailCopy\StoreEmailCopy;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateDispatchedEmails;
use App\Actions\CRM\Prospect\UpdateProspectEmailSent;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\DispatchedEmail;
use Aws\Exception\AwsException;
use Aws\Result;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Sentry;

class SendSesEmail
{
    use AsAction;
    use AwsClient;

    public mixed $message;

    public function handle(string $subject, string $emailHtmlBody, DispatchedEmail $dispatchedEmail, string $sender, ?string $unsubscribeUrl = null, ?string $senderName = null, bool $isTest = false): DispatchedEmail
    {
        if ($dispatchedEmail->state != DispatchedEmailStateEnum::READY) {
            return $dispatchedEmail;
        }

        $emailTo = $dispatchedEmail->emailAddress->email;

        $actuallySend = false;
        if (app()->isProduction()) {
            $actuallySend = true;
        } elseif (config('app.send_email_in_non_production_env') || $isTest) {
            $actuallySend = true;

            $emailTo = config('app.test_email_to_address');
            if (!$emailTo) {
                $actuallySend = false;
            }
        }


        if (!$actuallySend) {
            UpdateDispatchedEmail::run(
                $dispatchedEmail,
                [
                    'state'                => DispatchedEmailStateEnum::SENT,
                    'sent_at'              => now(),
                ]
            );

            if ($dispatchedEmail->recipient_type == 'Prospect') {
                UpdateProspectEmailSent::run($dispatchedEmail->recipient);
            }


            UpdateDispatchedEmail::run(
                $dispatchedEmail,
                [
                    'state' => DispatchedEmailStateEnum::DELIVERED,
                ]
            );


            return $dispatchedEmail;
        }


        $emailData = $this->getEmailData(
            $subject,
            $sender,
            $emailTo,
            $emailHtmlBody,
            $unsubscribeUrl,
            $senderName
        );


        $numberAttempts = 12;
        $attempt        = 0;

        do {
            try {
                $result = $this->sendEmail($emailData);

                $dispatchedEmail = UpdateDispatchedEmail::run(
                    $dispatchedEmail,
                    [
                        'state'                => DispatchedEmailStateEnum::SENT,
                        'sent_at'              => now(),
                    ]
                );

                if (!$isTest && Arr::get($result, 'MessageId')) {
                    DB::table('ses_dispatched_emails')->insert([
                        'dispatched_email_id' => $dispatchedEmail->id,
                        'ses_id'              => Arr::get($result, 'MessageId'),
                        'send_at'             => now()
                    ]);
                }


                if (!$isTest && $dispatchedEmail->outbox
                    && in_array($dispatchedEmail->outbox->code, [
                        OutboxCodeEnum::DELIVERY_CONFIRMATION,
                        OutboxCodeEnum::ORDER_CONFIRMATION,
                        OutboxCodeEnum::CREDIT_BALANCE_NOTIFICATION_FOR_CUSTOMER,
                        OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER,
                        OutboxCodeEnum::RENTAL_AGREEMENT,
                    ])) {
                    StoreEmailCopy::make()->action($dispatchedEmail, [
                        'subject' => $subject,
                        'body'    => $emailHtmlBody
                    ]);
                }


                if ($dispatchedEmail->recipient && $dispatchedEmail->recipient_type == 'Prospect') {
                    UpdateProspectEmailSent::dispatch($dispatchedEmail->recipient);
                }
            } catch (AwsException $e) {
                Sentry::captureException($e);
                if ($e->getAwsErrorCode() == 'Throttling' && $attempt < $numberAttempts - 1) {
                    $attempt++;
                    usleep(rand(200, 300) + pow(2, $attempt));
                    continue;
                } else {
                    UpdateDispatchedEmail::run(
                        $dispatchedEmail,
                        [
                            'state'       => DispatchedEmailStateEnum::ERROR,
                            'data->error' =>
                                [
                                    'code'    => $e->getAwsErrorCode(),
                                    'msg'     => $e->getAwsErrorMessage(),
                                    'attempt' => $attempt

                                ],
                        ]
                    );

                    break;
                }
            } catch (Exception $e) {
                UpdateDispatchedEmail::run(
                    $dispatchedEmail,
                    [
                        'state'       => DispatchedEmailStateEnum::ERROR,
                        'is_error'    => true,
                        'date'        => now(),
                        'data->error' =>
                            [
                                'msg' => $e->getMessage(),
                            ],
                    ]
                );

                break;
            }

            break;
        } while ($attempt < $numberAttempts);


        OutboxHydrateDispatchedEmails::dispatch($dispatchedEmail->outbox_id)->delay(900);


        return $dispatchedEmail;
    }

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function sendEmail($emailData): Result
    {
        return $this->getSesClient()->sendRawEmail($this->getRawEmail($emailData));
    }

    public function getEmailData($subject, $sender, $to, $emailHtmlBody, $unsubscribeUrl = null, ?string $senderName = null): array
    {
        $message = [
            'Message' => [
                'Subject' => [
                    'Data' => $subject,
                ]
            ]
        ];

        $message['Message']['Body']['Html'] = [
            'Data' => $emailHtmlBody
        ];


        $headers = [];
        if ($unsubscribeUrl) {
            data_set($headers, 'List-Unsubscribe', '<'.$unsubscribeUrl.'>');
            data_set($headers, 'List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }


        return [
            'Source'      => $sender,
            'SourceName'  => $senderName,
            'Destination' => [
                'ToAddresses' => [
                    $to
                ]
            ],
            'Message'     => $message['Message'],
            'Headers'     => $headers
        ];
    }

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function getRawEmail(array $emailData): array
    {
        $mail = new PHPMailer();

        $mail->addAddress($emailData['Destination']['ToAddresses'][0]);
        $mail->setFrom($emailData['Source'], $emailData['SourceName'] ?? '');

        foreach (Arr::get($emailData, 'Headers', []) as $key => $header) {
            $mail->addCustomHeader($key, $header);
        }
        $mail->isHTML();
        $mail->Subject = $emailData['Message']['Subject']['Data'];
        $mail->CharSet = 'UTF-8';

        $mail->Body    = $emailData['Message']['Body']['Html']['Data'];
        $mail->XMailer = null;


        $mail->preSend();

        $rawData = [
            'Source'       => $emailData['Source'],
            'Destinations' => $emailData['Destination']['ToAddresses'],
            'RawMessage'   => [
                'Data' => $mail->getSentMIMEMessage(),
            ]
        ];

        if (config('services.ses.configuration_set')) {
            data_set($rawData, 'ConfigurationSetName', config('services.ses.configuration_set'));
        }


        return $rawData;
    }


}
