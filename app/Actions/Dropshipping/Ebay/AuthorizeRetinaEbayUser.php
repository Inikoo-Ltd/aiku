<?php
/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthorizeRetinaEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public $commandSignature = 'retina:ds:authorize-ebay {customer} {name} {url}';

    public function handle(Customer $customer, $modelData): string
    {
        data_set($modelData, 'store_url', Arr::pull($modelData, 'url'));
        $ebayUser = StoreEbayUser::run($customer, $modelData);

        // $endpoint = '/wc-auth/v1/authorize'; //todo
        $params = [
            'app_name' => config('app.name'),
            'scope' => 'read_write',
            'user_id' => $ebayUser->id,
            'return_url' => route('retina.dropshipping.customer_sales_channels.index'),
            'callback_url' => route('webhooks.ebay.callback')
        ];

        return Arr::get($ebayUser, 'settings.credentials.store_url').$endpoint.'?'.http_build_query($params);
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

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('woo_commerce_users', 'name')],
            'url' => ['required', 'string']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('name', $request->input('name'));
    }

    public function asController(ActionRequest $request): string
    {
        $customer = $request->user()->customer;
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer, $this->validatedData);
    }

    public function asCommand(Command $command): void
    {
        $modelData = [
            'name' => $command->argument('name'),
            'url' => $command->argument('url'),
        ];

        $customer = Customer::find($command->argument('customer'))->first();

        $this->handle($customer, $modelData);
    }
}
