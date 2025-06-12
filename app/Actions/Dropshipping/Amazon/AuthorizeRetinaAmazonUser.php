<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Amazon;

use App\Actions\Dropshipping\Amazon\Traits\WithAmazonApiRequest;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthorizeRetinaAmazonUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithAmazonApiRequest;

    public $commandSignature = 'retina:ds:authorize-amazon {customer} {name} {url}';

    public function handle(): string
    {
        dd($this->getAmazonAuthUrl());
        return $this->getAmazonOAuthUrl();
    }

    public function jsonResponse(string $url): string
    {
        return $url;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        return redirect()->away($this->handle());
    }

    public function asCommand(Command $command): void
    {
        $modelData = [];

        $customer = Customer::find($command->argument('customer'))->first();

        $this->handle($customer, $modelData);
    }
}
