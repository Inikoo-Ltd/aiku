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
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class AuthorizeRetinaEbayUser extends RetinaAction
{
    use WithEbayApiRequest;

    public $commandSignature = 'retina:ds:authorize-ebay {customer} {name} {url}';

    public function handle(): string
    {
        return $this->getEbayAuthUrl();
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }


    public function asController(ActionRequest $request): string
    {
        $this->initialisation($request);
        return $this->handle();
    }

    public function asCommand(Command $command): void
    {

        $this->customer = Customer::find($command->argument('customer'))->first();

        $this->handle();
    }
}
