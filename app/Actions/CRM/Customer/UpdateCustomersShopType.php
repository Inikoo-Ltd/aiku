<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMEditAuthorisation;
use App\Enums\CRM\Customer\CustomerShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCustomersShopType extends OrgAction
{
    use WithCRMEditAuthorisation;

    /**
     * @param array{customers: array<int, int>, shop_type: ?string} $modelData
     */
    public function handle(Shop $shop, array $modelData): void
    {
        Customer::where('shop_id', $shop->id)
            ->whereIn('id', $modelData['customers'])
            ->get()
            ->each(fn (Customer $customer) => UpdateCustomer::make()->action($customer, ['shop_type' => $modelData['shop_type']]));
    }

    public function rules(): array
    {
        return [
            'customers'   => ['required', 'array', 'min:1', 'max:200'],
            'customers.*' => ['required', 'integer', Rule::exists('customers', 'id')->where('shop_id', $this->shop->id)],
            'shop_type'   => ['present', 'nullable', Rule::enum(CustomerShopTypeEnum::class)],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
