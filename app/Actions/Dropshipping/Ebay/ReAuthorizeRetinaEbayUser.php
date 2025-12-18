<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\RetinaAction;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class ReAuthorizeRetinaEbayUser extends RetinaAction
{
    use WithEbayApiRequest;

    public $commandSignature = 'retina:ds:authorize-ebay {customer} {name} {url}';

    /**
     * @throws \Exception
     */
    public function handle(EbayUser $ebayUser): string
    {
        $ebayUser->update(['step' => EbayUserStepEnum::MARKETPLACE]);

        return $this->getEbayAuthUrl();
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }

    public function action(EbayUser $ebayUser, ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($ebayUser);
    }
}
