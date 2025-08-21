<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:34 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AwsEmail;

use App\Actions\Comms\EmailAddress\Traits\AwsClient;
use App\Actions\Comms\SenderEmail\StoreSenderEmail;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\SenderEmail\SenderEmailStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\SenderEmail;
use Aws\Ses\Exception\SesException;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

class SendIdentityEmailVerification extends OrgAction
{
    use WithActionUpdate;
    use AwsClient;

    /**
     * @var \App\Models\Comms\SenderEmail|null
     */
    private ?SenderEmail $senderEmail;

    public function handle(Shop $shop, array $modelData): SenderEmail
    {
        $senderEmailAddress = Arr::pull($modelData, 'sender_email');

        if ($senderEmailAddress !== $shop->senderEmail?->email_address || !$shop->senderEmail) {
            $senderEmail = StoreSenderEmail::make()->action([
                'email_address' => $senderEmailAddress
            ]);

            $this->update($shop, ['sender_email_id' => $senderEmail->id]);
        } else {
            $senderEmail = $shop->senderEmail;
        }

        $email = $senderEmail->email_address;

        $state = GetEmailSesVerificationState::run($email);

        if (in_array($state, [SenderEmailStateEnum::VERIFIED, SenderEmailStateEnum::PENDING])) {

            if ($state == SenderEmailStateEnum::VERIFIED && $senderEmail->verified_at === null) {
                data_set($modelData, 'verified_at', now());
            }

            data_set($modelData, 'state', $state);

            return $this->update($senderEmail, $modelData);
        }

        try {
            $result = $this->getSesClient()->verifyEmailIdentity([
                'EmailAddress' => $email,
            ]);

            if ($result['@metadata']['statusCode'] != 200) {
                $state = SenderEmailStateEnum::VERIFICATION_SUBMISSION_ERROR;
            } else {
                $state = SenderEmailStateEnum::PENDING;
                data_set($modelData, 'last_verification_submitted_at', now());
            }
        } catch (SesException|Throwable) {
            $state = SenderEmailStateEnum::ERROR;
        }


        data_set($modelData, 'state', $state);

        return $this->update($senderEmail, $modelData);
    }

    public function jsonResponse(SenderEmail $senderEmail): array
    {
        return [
            'state' => $senderEmail->state->value,
            'message' => $senderEmail->state->message()[$senderEmail->state->value]
        ];
    }

    public function rules(): array
    {
        return [
            'sender_email' => ['sometimes', 'email',
                Rule::unique('sender_emails', 'email_address')
            ->ignore($this->senderEmail->id)],
        ];
    }

    public function inShop(Shop $shop, ActionRequest $request): SenderEmail
    {
        $this->senderEmail = $shop->senderEmail;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
